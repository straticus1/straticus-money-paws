import type { FastifyInstance } from 'fastify';
import { randomUUID } from 'node:crypto';
import { eq, sql } from 'drizzle-orm';
import { type Db, storeItems, inventory } from '@paws/db';
import {
  ensureUserAccount,
  ensureSystemAccount,
  transfer,
  LedgerError,
} from '@paws/ledger';
import { z } from 'zod';

const purchaseBody = z.object({
  itemId: z.string().uuid(),
  quantity: z.number().int().min(1).max(100),
});

function publicItem(item: typeof storeItems.$inferSelect) {
  return {
    id: item.id,
    name: item.name,
    description: item.description,
    category: item.category,
    priceMinor: item.priceMinor.toString(),
    currency: item.currency,
    effect: item.effect,
  };
}

export function registerStoreRoutes(app: FastifyInstance, db: Db): void {
  app.get('/store/items', { config: { public: true } }, async (_request, reply) => {
    const rows = await db.select().from(storeItems).where(eq(storeItems.active, true));
    return reply.send({ items: rows.map(publicItem) });
  });

  app.post('/store/purchase', async (request, reply) => {
    const parsed = purchaseBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({ error: 'invalid_request' });
    }
    const { itemId, quantity } = parsed.data;
    const userId = request.user!.id;

    const itemRows = await db.select().from(storeItems).where(eq(storeItems.id, itemId));
    const item = itemRows[0];
    if (!item || !item.active) {
      return reply.code(404).send({ error: 'not_found' });
    }

    const amountMinor = item.priceMinor * BigInt(quantity);

    // Prefer a client-supplied key so retries are idempotent; otherwise mint a
    // unique one (CSPRNG — never Math.random).
    const headerKey = request.headers['idempotency-key'];
    let idempotencyKey: string;
    if (typeof headerKey === 'string' && headerKey.length > 0) {
      if (headerKey.length > 150) {
        return reply.code(400).send({ error: 'invalid_idempotency_key' });
      }
      // Namespace client keys per user so one user cannot replay another's
      // key to probe transaction ids or suppress their own posting.
      idempotencyKey = `purchase:${userId}:${headerKey}`;
    } else {
      idempotencyKey = `purchase:${userId}:${randomUUID()}`;
    }

    try {
      const outcome = await db.transaction(async (tx) => {
        // The ledger helpers expect a Db; a drizzle tx is runtime-compatible
        // (nested writes become savepoints) so the transfer and the inventory
        // upsert commit atomically together.
        const txDb = tx as unknown as Db;
        const fromAccount = await ensureUserAccount(txDb, userId, item.currency);
        const toAccount = await ensureSystemAccount(txDb, 'revenue', item.currency);

        const posted = await transfer(txDb, {
          idempotencyKey,
          kind: 'store_purchase',
          fromAccountId: fromAccount,
          toAccountId: toAccount,
          amountMinor,
          metadata: { itemId, quantity, userId },
        });

        // Only grant inventory on the first posting; a replayed idempotency key
        // must not add the items twice.
        if (!posted.alreadyPosted) {
          await tx
            .insert(inventory)
            .values({ userId, itemId, quantity })
            .onConflictDoUpdate({
              target: [inventory.userId, inventory.itemId],
              set: { quantity: sql`${inventory.quantity} + ${quantity}` },
            });
        }

        return posted;
      });

      return reply.send({
        ok: true,
        transactionId: outcome.transactionId,
        alreadyPosted: outcome.alreadyPosted,
      });
    } catch (err) {
      if (err instanceof LedgerError && err.code === 'INSUFFICIENT_FUNDS') {
        return reply.code(402).send({ error: 'insufficient_funds' });
      }
      throw err;
    }
  });
}
