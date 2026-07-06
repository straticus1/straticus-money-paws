import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { sql } from 'drizzle-orm';
import { createDb, users, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import {
  LedgerError,
  ensureSystemAccount,
  ensureUserAccount,
  getBalance,
  postTransaction,
  transfer,
} from './index.js';

const TEST_URL = process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test';

let handle: DbHandle;
let userId: string;
let userAccount: string;
let treasury: string;

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
             inventory, deposits, withdrawal_requests, sessions, user_2fa,
             pets, users CASCADE
  `);
  const inserted = await db
    .insert(users)
    .values({
      email: 'test@paws.money',
      username: 'tester',
      passwordHash: 'x',
    })
    .returning({ id: users.id });
  userId = inserted[0]!.id;
  userAccount = await ensureUserAccount(db, userId, 'PAWS');
  treasury = await ensureSystemAccount(db, 'treasury', 'PAWS');
});

/** Drizzle wraps DB errors; the trigger message lands in error.cause. */
async function expectDbError(p: Promise<unknown>, pattern: RegExp) {
  try {
    await p;
    expect.unreachable('expected query to be rejected');
  } catch (err) {
    const cause = (err as { cause?: Error }).cause;
    expect(`${(err as Error).message} ${cause?.message ?? ''}`).toMatch(pattern);
  }
}

async function fund(amount: bigint, key = `fund-${amount}`) {
  return transfer(handle.db, {
    idempotencyKey: key,
    kind: 'deposit',
    fromAccountId: treasury,
    toAccountId: userAccount,
    amountMinor: amount,
  });
}

describe('postTransaction', () => {
  it('credits a user account from treasury and derives the balance', async () => {
    await fund(500n);
    expect(await getBalance(handle.db, userAccount)).toBe(500n);
    expect(await getBalance(handle.db, treasury)).toBe(-500n);
  });

  it('is idempotent: replaying a key posts nothing new', async () => {
    const first = await fund(500n, 'dup-key');
    const replay = await fund(500n, 'dup-key');
    expect(first.alreadyPosted).toBe(false);
    expect(replay.alreadyPosted).toBe(true);
    expect(replay.transactionId).toBe(first.transactionId);
    expect(await getBalance(handle.db, userAccount)).toBe(500n);
  });

  // Negative: unbalanced input fails closed in code.
  it('rejects unbalanced entries', async () => {
    await expect(
      postTransaction(handle.db, {
        idempotencyKey: 'unbalanced',
        kind: 'bad',
        entries: [
          { accountId: treasury, amountMinor: -100n },
          { accountId: userAccount, amountMinor: 99n },
        ],
      }),
    ).rejects.toMatchObject({ code: 'UNBALANCED' });
    expect(await getBalance(handle.db, userAccount)).toBe(0n);
  });

  // Negative: the DB trigger rejects unbalanced writes even if code is bypassed.
  it('database trigger rejects unbalanced entries written directly', async () => {
    await expectDbError(
      handle.db.execute(sql`
        WITH t AS (
          INSERT INTO ledger_transactions (idempotency_key, kind)
          VALUES ('bypass', 'bad') RETURNING id
        )
        INSERT INTO ledger_entries (transaction_id, account_id, amount_minor)
        SELECT t.id, ${userAccount}::uuid, 100 FROM t
      `),
      /unbalanced/,
    );
  });

  // Negative: overdraft fails closed and leaves no partial state.
  it('rejects spends beyond the user balance', async () => {
    await fund(100n);
    await expect(
      transfer(handle.db, {
        idempotencyKey: 'overdraft',
        kind: 'purchase',
        fromAccountId: userAccount,
        toAccountId: treasury,
        amountMinor: 101n,
      }),
    ).rejects.toMatchObject({ code: 'INSUFFICIENT_FUNDS' });
    expect(await getBalance(handle.db, userAccount)).toBe(100n);
  });

  it('allows spending the exact balance to zero', async () => {
    await fund(100n);
    await transfer(handle.db, {
      idempotencyKey: 'spend-all',
      kind: 'purchase',
      fromAccountId: userAccount,
      toAccountId: treasury,
      amountMinor: 100n,
    });
    expect(await getBalance(handle.db, userAccount)).toBe(0n);
  });

  // Negative: concurrent double-spend — exactly one wins.
  it('prevents concurrent double-spends', async () => {
    await fund(100n);
    const spend = (key: string) =>
      transfer(handle.db, {
        idempotencyKey: key,
        kind: 'purchase',
        fromAccountId: userAccount,
        toAccountId: treasury,
        amountMinor: 100n,
      });
    const results = await Promise.allSettled([spend('race-a'), spend('race-b')]);
    const ok = results.filter((r) => r.status === 'fulfilled');
    const failed = results.filter(
      (r) => r.status === 'rejected' && (r.reason as LedgerError).code === 'INSUFFICIENT_FUNDS',
    );
    expect(ok).toHaveLength(1);
    expect(failed).toHaveLength(1);
    expect(await getBalance(handle.db, userAccount)).toBe(0n);
  });

  // Negative: ledger history cannot be rewritten.
  it('rejects updates and deletes on posted entries', async () => {
    await fund(100n);
    await expectDbError(
      handle.db.execute(sql`UPDATE ledger_entries SET amount_minor = 1`),
      /append-only/,
    );
    await expectDbError(handle.db.execute(sql`DELETE FROM ledger_entries`), /append-only/);
  });

  // Negative: malformed inputs fail closed.
  it('rejects invalid entry sets and amounts', async () => {
    await expect(
      postTransaction(handle.db, {
        idempotencyKey: 'one-entry',
        kind: 'bad',
        entries: [{ accountId: userAccount, amountMinor: 100n }],
      }),
    ).rejects.toMatchObject({ code: 'INVALID_ENTRIES' });
    await expect(
      transfer(handle.db, {
        idempotencyKey: 'zero',
        kind: 'bad',
        fromAccountId: treasury,
        toAccountId: userAccount,
        amountMinor: 0n,
      }),
    ).rejects.toMatchObject({ code: 'INVALID_ENTRIES' });
    await expect(
      transfer(handle.db, {
        idempotencyKey: 'negative',
        kind: 'bad',
        fromAccountId: treasury,
        toAccountId: userAccount,
        amountMinor: -5n,
      }),
    ).rejects.toMatchObject({ code: 'INVALID_ENTRIES' });
  });

  it('a failed post releases its idempotency key for retry', async () => {
    await fund(100n);
    const attempt = () =>
      transfer(handle.db, {
        idempotencyKey: 'retry-key',
        kind: 'purchase',
        fromAccountId: userAccount,
        toAccountId: treasury,
        amountMinor: 101n,
      });
    await expect(attempt()).rejects.toMatchObject({ code: 'INSUFFICIENT_FUNDS' });
    await fund(1n, 'topup');
    const second = await attempt();
    expect(second.alreadyPosted).toBe(false);
    expect(await getBalance(handle.db, userAccount)).toBe(0n);
  });
});
