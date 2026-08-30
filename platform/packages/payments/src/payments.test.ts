import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { createHmac, randomUUID } from 'node:crypto';
import { sql } from 'drizzle-orm';
import { createDb, users, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import { ensureSystemAccount, ensureUserAccount, getBalance, transfer } from '@paws/ledger';
import {
  confirmDeposit,
  createCharge,
  createDeposit,
  handleWebhookEvent,
  markWithdrawalPaid,
  minorToDecimal,
  requestWithdrawal,
  reviewWithdrawal,
  verifyWebhookSignature,
} from './index.js';

const TEST_URL = process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_pay';

let handle: DbHandle;
let userId: string;
let adminId: string;
let userUsd: string;

beforeAll(async () => {
  await runMigrations(TEST_URL);
  handle = createDb(TEST_URL);
});

afterAll(async () => {
  await handle.pool.end();
});

beforeEach(async () => {
  const { db } = handle;
  await db.execute(sql`
    TRUNCATE ledger_entries, ledger_transactions, ledger_accounts,
             deposits, withdrawal_requests, users CASCADE
  `);
  const inserted = await db
    .insert(users)
    .values([
      { email: 'payer@paws.money', username: 'payer', passwordHash: 'x' },
      { email: 'admin@paws.money', username: 'admin', passwordHash: 'x', role: 'admin' },
    ])
    .returning({ id: users.id });
  userId = inserted[0]!.id;
  adminId = inserted[1]!.id;
  userUsd = await ensureUserAccount(db, userId, 'USD');
});

async function fundUser(amount: bigint) {
  const treasury = await ensureSystemAccount(handle.db, 'treasury', 'USD');
  await transfer(handle.db, {
    idempotencyKey: `fund-${randomUUID()}`,
    kind: 'deposit',
    fromAccountId: treasury,
    toAccountId: userUsd,
    amountMinor: amount,
  });
}

describe('minorToDecimal', () => {
  it('formats bigint cents without floats', () => {
    expect(minorToDecimal(0n)).toBe('0.00');
    expect(minorToDecimal(5n)).toBe('0.05');
    expect(minorToDecimal(1234n)).toBe('12.34');
    expect(minorToDecimal(100000000000000000001n)).toBe('1000000000000000000.01');
    expect(() => minorToDecimal(-1n)).toThrow();
  });
});

describe('webhook signature', () => {
  const secret = 'whsec_test_secret';
  const body = JSON.stringify({ type: 'charge:confirmed', data: { id: 'ch_1' } });
  const sign = (b: string, s: string) => createHmac('sha256', s).update(b).digest('hex');

  it('accepts a valid signature', () => {
    expect(verifyWebhookSignature(body, sign(body, secret), secret)).toBe(true);
  });

  // Negative: tampered body, wrong secret, malformed/empty signatures.
  it('rejects forged and malformed signatures', () => {
    expect(verifyWebhookSignature(body + ' ', sign(body, secret), secret)).toBe(false);
    expect(verifyWebhookSignature(body, sign(body, 'other-secret'), secret)).toBe(false);
    expect(verifyWebhookSignature(body, '', secret)).toBe(false);
    expect(verifyWebhookSignature(body, 'zz'.repeat(32), secret)).toBe(false);
    expect(verifyWebhookSignature(body, sign(body, secret).slice(0, 10), secret)).toBe(false);
    expect(verifyWebhookSignature(body, null, secret)).toBe(false);
    expect(verifyWebhookSignature(body, sign(body, secret), '')).toBe(false);
  });
});

describe('deposits', () => {
  it('credits exactly once across webhook replays', async () => {
    await createDeposit(handle.db, {
      userId,
      amountMinor: 2500n,
      currency: 'USD',
      chargeId: 'ch_replay',
    });
    const event = { type: 'charge:confirmed', data: { id: 'ch_replay' } };
    expect(await handleWebhookEvent(handle.db, event)).toBe('credited');
    expect(await handleWebhookEvent(handle.db, event)).toBe('already_processed');
    expect(await handleWebhookEvent(handle.db, event)).toBe('already_processed');
    expect(await getBalance(handle.db, userUsd)).toBe(2500n);
  });

  // Negative: unknown charge ids and malformed events credit nothing.
  it('ignores unknown charges and malformed events', async () => {
    expect(await confirmDeposit(handle.db, 'ch_ghost')).toBe('not_found');
    expect(await handleWebhookEvent(handle.db, { type: 'charge:confirmed' })).toBe('ignored');
    expect(
      await handleWebhookEvent(handle.db, { type: 'weird:event', data: { id: 'ch_x' } }),
    ).toBe('ignored');
    expect(await getBalance(handle.db, userUsd)).toBe(0n);
  });

  // Negative: a failed charge can never be confirmed afterwards.
  it('does not credit a charge that already failed', async () => {
    await createDeposit(handle.db, {
      userId,
      amountMinor: 2500n,
      currency: 'USD',
      chargeId: 'ch_fail',
    });
    await handleWebhookEvent(handle.db, { type: 'charge:failed', data: { id: 'ch_fail' } });
    expect(
      await handleWebhookEvent(handle.db, { type: 'charge:confirmed', data: { id: 'ch_fail' } }),
    ).toBe('already_processed');
    expect(await getBalance(handle.db, userUsd)).toBe(0n);
  });
});

describe('withdrawals', () => {
  it('holds funds at request time', async () => {
    await fundUser(1000n);
    await requestWithdrawal(handle.db, {
      userId,
      amountMinor: 600n,
      currency: 'USD',
      destination: 'bc1q-test-destination',
    });
    expect(await getBalance(handle.db, userUsd)).toBe(400n);
  });

  // Negative: cannot request more than the balance; nothing is left behind.
  it('rejects overdraw requests atomically', async () => {
    await fundUser(1000n);
    await expect(
      requestWithdrawal(handle.db, {
        userId,
        amountMinor: 1001n,
        currency: 'USD',
        destination: 'bc1q-test-destination',
      }),
    ).rejects.toMatchObject({ code: 'INSUFFICIENT_FUNDS' });
    expect(await getBalance(handle.db, userUsd)).toBe(1000n);
    const rows = await handle.db.execute(sql`SELECT count(*)::int AS n FROM withdrawal_requests`);
    expect((rows.rows[0] as { n: number }).n).toBe(0);
  });

  it('denial refunds the hold', async () => {
    await fundUser(1000n);
    const id = await requestWithdrawal(handle.db, {
      userId,
      amountMinor: 600n,
      currency: 'USD',
      destination: 'bc1q-test-destination',
    });
    const verdict = await reviewWithdrawal(handle.db, {
      withdrawalId: id,
      reviewerId: adminId,
      approve: false,
      note: 'suspicious',
    });
    expect(verdict).toBe('denied');
    expect(await getBalance(handle.db, userUsd)).toBe(1000n);
  });

  // Negative: a withdrawal cannot be reviewed twice.
  it('rejects double review', async () => {
    await fundUser(1000n);
    const id = await requestWithdrawal(handle.db, {
      userId,
      amountMinor: 600n,
      currency: 'USD',
      destination: 'bc1q-test-destination',
    });
    await reviewWithdrawal(handle.db, { withdrawalId: id, reviewerId: adminId, approve: true });
    await expect(
      reviewWithdrawal(handle.db, { withdrawalId: id, reviewerId: adminId, approve: false }),
    ).rejects.toMatchObject({ code: 'ALREADY_REVIEWED' });
    expect(await getBalance(handle.db, userUsd)).toBe(400n);
  });

  it('paid flow moves the hold to treasury and is not repeatable', async () => {
    await fundUser(1000n);
    const id = await requestWithdrawal(handle.db, {
      userId,
      amountMinor: 600n,
      currency: 'USD',
      destination: 'bc1q-test-destination',
    });
    await reviewWithdrawal(handle.db, { withdrawalId: id, reviewerId: adminId, approve: true });
    await markWithdrawalPaid(handle.db, id);
    const withholding = await ensureSystemAccount(handle.db, 'withholding', 'USD');
    expect(await getBalance(handle.db, withholding)).toBe(0n);
    await expect(markWithdrawalPaid(handle.db, id)).rejects.toMatchObject({
      code: 'ALREADY_REVIEWED',
    });
  });

  // Negative: garbage inputs fail closed.
  it('rejects invalid amounts and destinations', async () => {
    await expect(
      requestWithdrawal(handle.db, {
        userId,
        amountMinor: 0n,
        currency: 'USD',
        destination: 'bc1q-test-destination',
      }),
    ).rejects.toMatchObject({ code: 'INVALID_AMOUNT' });
    await expect(
      requestWithdrawal(handle.db, {
        userId,
        amountMinor: 100n,
        currency: 'USD',
        destination: 'ab',
      }),
    ).rejects.toMatchObject({ code: 'INVALID_DESTINATION' });
    await expect(
      requestWithdrawal(handle.db, {
        userId,
        amountMinor: 100n,
        currency: 'USD',
        destination: 'x'.repeat(300),
      }),
    ).rejects.toMatchObject({ code: 'INVALID_DESTINATION' });
  });
});

describe('createCharge', () => {
  it('creates a charge via the provider API', async () => {
    const calls: { url: string; init: RequestInit }[] = [];
    const fetchImpl = (async (url: string | URL | Request, init?: RequestInit) => {
      calls.push({ url: String(url), init: init! });
      return new Response(
        JSON.stringify({ data: { id: 'ch_new', hosted_url: 'https://commerce.coinbase.com/charges/ch_new' } }),
        { status: 201 },
      );
    }) as typeof fetch;
    const result = await createCharge({
      apiKey: 'k',
      name: 'Deposit',
      description: 'paws.money deposit',
      amountMinor: 1234n,
      currency: 'USD',
      metadata: { userId: 'u1' },
      fetchImpl,
    });
    expect(result).toEqual({ chargeId: 'ch_new', hostedUrl: 'https://commerce.coinbase.com/charges/ch_new' });
    const sent = JSON.parse(String(calls[0]!.init.body));
    expect(sent.local_price).toEqual({ amount: '12.34', currency: 'USD' });
  });

  // Negative: provider errors and malformed responses fail closed.
  it('fails closed on provider errors', async () => {
    const err500 = (async () => new Response('oops', { status: 500 })) as typeof fetch;
    const badJson = (async () => new Response('not-json', { status: 201 })) as typeof fetch;
    const missing = (async () => new Response(JSON.stringify({ data: {} }), { status: 201 })) as typeof fetch;
    const wateringHole = (async () => new Response(JSON.stringify({
      data: { id: 'ch_evil', hosted_url: 'https://lookalike.example/steal-wallet' },
    }), { status: 201 })) as typeof fetch;
    const base = {
      apiKey: 'k',
      name: 'D',
      description: 'd',
      amountMinor: 100n,
      currency: 'USD' as const,
      metadata: {},
    };
    await expect(createCharge({ ...base, fetchImpl: err500 })).rejects.toMatchObject({
      code: 'PROVIDER_ERROR',
    });
    await expect(createCharge({ ...base, fetchImpl: badJson })).rejects.toMatchObject({
      code: 'PROVIDER_ERROR',
    });
    await expect(createCharge({ ...base, fetchImpl: missing })).rejects.toMatchObject({
      code: 'PROVIDER_ERROR',
    });
    await expect(createCharge({ ...base, fetchImpl: wateringHole })).rejects.toMatchObject({
      code: 'PROVIDER_ERROR',
    });
    await expect(createCharge({ ...base, amountMinor: 0n })).rejects.toMatchObject({
      code: 'INVALID_AMOUNT',
    });
  });
});
