import type { FastifyInstance } from 'fastify';
import { eq } from 'drizzle-orm';
import { type Db, inventory, storeItems } from '@paws/db';
import { ensureUserAccount, getBalance } from '@paws/ledger';
import { toPublicUser } from '../types.js';

const CURRENCIES = ['PAWS', 'USD'] as const;

export function registerMeRoutes(app: FastifyInstance, db: Db): void {
  app.get('/me', async (request, reply) => {
    return reply.send({ user: toPublicUser(request.user!) });
  });

  app.get('/me/balances', async (request, reply) => {
    const userId = request.user!.id;
    const balances = [];
    for (const currency of CURRENCIES) {
      const accountId = await ensureUserAccount(db, userId, currency);
      const amount = await getBalance(db, accountId);
      balances.push({ currency, amountMinor: amount.toString() });
    }
    return reply.send({ balances });
  });

  app.get('/me/inventory', async (request, reply) => {
    const userId = request.user!.id;
    const rows = await db
      .select({
        itemId: storeItems.id,
        name: storeItems.name,
        category: storeItems.category,
        quantity: inventory.quantity,
        effect: storeItems.effect,
      })
      .from(inventory)
      .innerJoin(storeItems, eq(storeItems.id, inventory.itemId))
      .where(eq(inventory.userId, userId));
    return reply.send({ items: rows });
  });
}
