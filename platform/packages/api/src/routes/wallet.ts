import type { FastifyInstance } from 'fastify';
import { desc, eq } from 'drizzle-orm';
import { type Db, deposits, securityAuditEvents, user2fa, withdrawalRequests } from '@paws/db';
import { openSecret, verifyPassword, verifyTotp } from '@paws/auth';
import {
  PaymentsError,
  createCharge,
  createDeposit,
  handleWebhookEvent,
  markWithdrawalPaid,
  requestWithdrawal,
  reviewWithdrawal,
  verifyWebhookSignature,
} from '@paws/payments';
import { LedgerError } from '@paws/ledger';
import { z } from 'zod';
import { requireAuthSecret } from '../util.js';

const MIN_DEPOSIT_CENTS = 100n; // $1
const MAX_DEPOSIT_CENTS = 1_000_000n; // $10,000

const amountString = z.string().regex(/^\d{1,15}$/, 'integer minor units');

const depositBody = z.object({
  amountMinor: amountString,
  currency: z.literal('USD'),
});

const withdrawBody = z.object({
  amountMinor: amountString,
  currency: z.literal('USD'),
  destination: z.string().min(5).max(200),
  currentPassword: z.string().min(1).max(512),
  totp: z.string().max(16).optional(),
}).strict();

const reviewBody = z.object({
  approve: z.boolean(),
  note: z.string().max(500).optional(),
});

function paymentsErrorReply(err: unknown):
  | { status: number; error: string }
  | null {
  if (err instanceof LedgerError && err.code === 'INSUFFICIENT_FUNDS') {
    return { status: 402, error: 'insufficient_funds' };
  }
  if (!(err instanceof PaymentsError)) return null;
  switch (err.code) {
    case 'INVALID_AMOUNT':
    case 'INVALID_DESTINATION':
      return { status: 400, error: err.code.toLowerCase() };
    case 'NOT_FOUND':
      return { status: 404, error: 'not_found' };
    case 'ALREADY_REVIEWED':
      return { status: 409, error: 'already_reviewed' };
    case 'PROVIDER_ERROR':
      return { status: 502, error: 'provider_error' };
  }
}

export interface WalletDeps {
  /** Injected for tests; production uses global fetch. */
  providerFetch?: typeof fetch;
}

export function registerWalletRoutes(app: FastifyInstance, db: Db, deps: WalletDeps = {}): void {
  app.post('/wallet/deposit', async (request, reply) => {
    const apiKey = process.env['COINBASE_API_KEY'];
    if (!apiKey) {
      // Fail closed: without provider credentials there is no deposit path.
      return reply.code(503).send({ error: 'deposits_unavailable' });
    }
    const parsed = depositBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({ error: 'invalid_request' });
    }
    const amountMinor = BigInt(parsed.data.amountMinor);
    if (amountMinor < MIN_DEPOSIT_CENTS || amountMinor > MAX_DEPOSIT_CENTS) {
      return reply.code(400).send({ error: 'amount_out_of_range' });
    }
    const user = request.user!;
    try {
      const chargeInput: Parameters<typeof createCharge>[0] = {
        apiKey,
        name: 'paws.money deposit',
        description: `Deposit for ${user.username}`,
        amountMinor,
        currency: 'USD',
        metadata: { userId: user.id },
      };
      if (deps.providerFetch) chargeInput.fetchImpl = deps.providerFetch;
      const charge = await createCharge(chargeInput);
      await createDeposit(db, {
        userId: user.id,
        amountMinor,
        currency: 'USD',
        chargeId: charge.chargeId,
      });
      return reply.code(201).send({ chargeId: charge.chargeId, hostedUrl: charge.hostedUrl });
    } catch (err) {
      const mapped = paymentsErrorReply(err);
      if (mapped) return reply.code(mapped.status).send({ error: mapped.error });
      throw err;
    }
  });

  app.get('/wallet/deposits', async (request, reply) => {
    const rows = await db
      .select()
      .from(deposits)
      .where(eq(deposits.userId, request.user!.id))
      .orderBy(desc(deposits.createdAt));
    return reply.send({
      deposits: rows.map((d) => ({
        id: d.id,
        amountMinor: d.amountMinor.toString(),
        currency: d.currency,
        status: d.status,
        createdAt: d.createdAt,
      })),
    });
  });

  app.post('/wallet/withdrawals', async (request, reply) => {
    const parsed = withdrawBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({ error: 'invalid_request' });
    }
    const passwordOk = await verifyPassword(request.user!.passwordHash, parsed.data.currentPassword);
    if (!passwordOk) {
      await db.insert(securityAuditEvents).values({
        userId: request.user!.id,
        eventType: 'wallet.withdrawal_reauth_failed',
      });
      return reply.code(401).send({ error: 'reauthentication_failed' });
    }
    const twoFactor = (await db.select().from(user2fa).where(eq(user2fa.userId, request.user!.id)))[0];
    if (twoFactor?.enabled) {
      const secret = openSecret(twoFactor.totpSecret, requireAuthSecret());
      if (!parsed.data.totp || !verifyTotp(secret, parsed.data.totp)) {
        return reply.code(401).send({ error: 'totp_required' });
      }
    }
    try {
      const id = await requestWithdrawal(db, {
        userId: request.user!.id,
        amountMinor: BigInt(parsed.data.amountMinor),
        currency: parsed.data.currency,
        destination: parsed.data.destination,
      });
      await db.insert(securityAuditEvents).values({
        userId: request.user!.id,
        eventType: 'wallet.withdrawal_requested',
        metadata: { withdrawalId: id, currency: parsed.data.currency },
      });
      return reply.code(201).send({ id, status: 'pending' });
    } catch (err) {
      const mapped = paymentsErrorReply(err);
      if (mapped) return reply.code(mapped.status).send({ error: mapped.error });
      throw err;
    }
  });

  app.get('/wallet/withdrawals', async (request, reply) => {
    const rows = await db
      .select()
      .from(withdrawalRequests)
      .where(eq(withdrawalRequests.userId, request.user!.id))
      .orderBy(desc(withdrawalRequests.createdAt));
    return reply.send({
      withdrawals: rows.map((w) => ({
        id: w.id,
        amountMinor: w.amountMinor.toString(),
        currency: w.currency,
        destination: w.destination,
        status: w.status,
        createdAt: w.createdAt,
      })),
    });
  });

  // === Admin review queue ===

  function requireAdmin(request: { user: { role: string } | null }): boolean {
    return request.user?.role === 'admin';
  }

  app.get('/admin/withdrawals', async (request, reply) => {
    if (!requireAdmin(request)) return reply.code(403).send({ error: 'forbidden' });
    const rows = await db
      .select()
      .from(withdrawalRequests)
      .where(eq(withdrawalRequests.status, 'pending'))
      .orderBy(desc(withdrawalRequests.createdAt));
    return reply.send({
      withdrawals: rows.map((w) => ({
        id: w.id,
        userId: w.userId,
        amountMinor: w.amountMinor.toString(),
        currency: w.currency,
        destination: w.destination,
        createdAt: w.createdAt,
      })),
    });
  });

  app.post('/admin/withdrawals/:id/review', async (request, reply) => {
    if (!requireAdmin(request)) return reply.code(403).send({ error: 'forbidden' });
    const id = z.string().uuid().safeParse((request.params as { id: string }).id);
    const parsed = reviewBody.safeParse(request.body);
    if (!id.success || !parsed.success) {
      return reply.code(400).send({ error: 'invalid_request' });
    }
    try {
      const input: Parameters<typeof reviewWithdrawal>[1] = {
        withdrawalId: id.data,
        reviewerId: request.user!.id,
        approve: parsed.data.approve,
      };
      if (parsed.data.note !== undefined) input.note = parsed.data.note;
      const verdict = await reviewWithdrawal(db, input);
      return reply.send({ id: id.data, status: verdict });
    } catch (err) {
      const mapped = paymentsErrorReply(err);
      if (mapped) return reply.code(mapped.status).send({ error: mapped.error });
      throw err;
    }
  });

  app.post('/admin/withdrawals/:id/paid', async (request, reply) => {
    if (!requireAdmin(request)) return reply.code(403).send({ error: 'forbidden' });
    const id = z.string().uuid().safeParse((request.params as { id: string }).id);
    if (!id.success) return reply.code(400).send({ error: 'invalid_request' });
    try {
      await markWithdrawalPaid(db, id.data);
      return reply.send({ id: id.data, status: 'paid' });
    } catch (err) {
      const mapped = paymentsErrorReply(err);
      if (mapped) return reply.code(mapped.status).send({ error: mapped.error });
      throw err;
    }
  });

  // === Coinbase webhook: raw-body scope so the HMAC covers exact bytes ===

  app.register(async (scope) => {
    scope.removeAllContentTypeParsers();
    scope.addContentTypeParser('*', { parseAs: 'buffer' }, (_req, body, done) => {
      done(null, body);
    });
    scope.post('/webhooks/coinbase', { config: { public: true } }, async (request, reply) => {
      const secret = process.env['COINBASE_WEBHOOK_SECRET'];
      if (!secret) {
        // Fail closed: unverifiable events must never touch the ledger.
        return reply.code(503).send({ error: 'webhooks_unavailable' });
      }
      const raw = request.body as Buffer;
      const signature = request.headers['x-cc-webhook-signature'];
      if (!verifyWebhookSignature(raw, signature, secret)) {
        return reply.code(401).send({ error: 'invalid_signature' });
      }
      let event: unknown;
      try {
        event = JSON.parse(raw.toString('utf8'));
      } catch {
        return reply.code(400).send({ error: 'invalid_payload' });
      }
      const result = await handleWebhookEvent(db, event as { type: string });
      return reply.send({ received: true, result });
    });
  });
}
