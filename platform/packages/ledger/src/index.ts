/**
 * Double-entry ledger core.
 *
 * Threats: Protects against balance forgery (balances are derived, never
 * stored), double-spends under concurrency (row locks on involved accounts),
 * replayed money operations (idempotency keys), and unbalanced writes
 * (validated here AND by a deferred DB constraint trigger). Does NOT handle
 * authorization (callers must verify the acting user owns the source
 * account) or currency conversion.
 *
 * All amounts are bigint minor units. User accounts can never go negative;
 * system accounts (treasury, revenue) may.
 */
import { and, eq, inArray, sql } from 'drizzle-orm';
import {
  type Db,
  ledgerAccounts,
  ledgerEntries,
  ledgerTransactions,
} from '@paws/db';

export type Currency = 'PAWS' | 'USD';

export interface EntryInput {
  accountId: string;
  amountMinor: bigint;
}

export interface PostTransactionInput {
  idempotencyKey: string;
  kind: string;
  metadata?: Record<string, unknown>;
  entries: EntryInput[];
}

export interface PostedTransaction {
  transactionId: string;
  alreadyPosted: boolean;
}

export class LedgerError extends Error {
  constructor(
    message: string,
    readonly code:
      | 'UNBALANCED'
      | 'INSUFFICIENT_FUNDS'
      | 'INVALID_ENTRIES'
      | 'ACCOUNT_NOT_FOUND',
  ) {
    super(message);
    this.name = 'LedgerError';
  }
}

export async function ensureUserAccount(
  db: Db,
  userId: string,
  currency: Currency,
): Promise<string> {
  const existing = await db
    .select({ id: ledgerAccounts.id })
    .from(ledgerAccounts)
    .where(and(eq(ledgerAccounts.ownerUserId, userId), eq(ledgerAccounts.currency, currency)));
  if (existing[0]) return existing[0].id;
  const inserted = await db
    .insert(ledgerAccounts)
    .values({ ownerUserId: userId, kind: 'user', currency })
    .onConflictDoNothing()
    .returning({ id: ledgerAccounts.id });
  if (inserted[0]) return inserted[0].id;
  // Lost a creation race; the winner's row must exist now.
  return ensureUserAccount(db, userId, currency);
}

export async function ensureSystemAccount(
  db: Db,
  systemName: string,
  currency: Currency,
): Promise<string> {
  const existing = await db
    .select({ id: ledgerAccounts.id })
    .from(ledgerAccounts)
    .where(and(eq(ledgerAccounts.systemName, systemName), eq(ledgerAccounts.currency, currency)));
  if (existing[0]) return existing[0].id;
  const inserted = await db
    .insert(ledgerAccounts)
    .values({ kind: 'system', systemName, currency })
    .onConflictDoNothing()
    .returning({ id: ledgerAccounts.id });
  if (inserted[0]) return inserted[0].id;
  return ensureSystemAccount(db, systemName, currency);
}

export async function getBalance(db: Db, accountId: string): Promise<bigint> {
  const rows = await db
    .select({ total: sql<string>`coalesce(sum(${ledgerEntries.amountMinor}), 0)` })
    .from(ledgerEntries)
    .where(eq(ledgerEntries.accountId, accountId));
  return BigInt(rows[0]?.total ?? '0');
}

/**
 * Post a balanced transaction atomically. Idempotent on idempotencyKey:
 * replaying returns the original transaction and writes nothing.
 */
export async function postTransaction(
  db: Db,
  input: PostTransactionInput,
): Promise<PostedTransaction> {
  const { idempotencyKey, kind, metadata = {}, entries } = input;

  if (entries.length < 2) {
    throw new LedgerError('a transaction needs at least two entries', 'INVALID_ENTRIES');
  }
  if (entries.some((e) => e.amountMinor === 0n)) {
    throw new LedgerError('zero-amount entries are not allowed', 'INVALID_ENTRIES');
  }
  if (!idempotencyKey || idempotencyKey.length > 200) {
    throw new LedgerError('invalid idempotency key', 'INVALID_ENTRIES');
  }

  return db.transaction(async (tx) => {
    // Idempotency: claim the key; if it exists, return the prior posting.
    const claimed = await tx
      .insert(ledgerTransactions)
      .values({ idempotencyKey, kind, metadata })
      .onConflictDoNothing()
      .returning({ id: ledgerTransactions.id });
    if (!claimed[0]) {
      const prior = await tx
        .select({ id: ledgerTransactions.id })
        .from(ledgerTransactions)
        .where(eq(ledgerTransactions.idempotencyKey, idempotencyKey));
      if (!prior[0]) throw new LedgerError('idempotency race lost twice', 'INVALID_ENTRIES');
      return { transactionId: prior[0].id, alreadyPosted: true };
    }
    const transactionId = claimed[0].id;

    // Lock involved accounts in deterministic order (prevents deadlock and
    // makes the overdraft check race-safe).
    const accountIds = [...new Set(entries.map((e) => e.accountId))].sort();
    const locked = await tx
      .select({
        id: ledgerAccounts.id,
        kind: ledgerAccounts.kind,
        currency: ledgerAccounts.currency,
      })
      .from(ledgerAccounts)
      .where(inArray(ledgerAccounts.id, accountIds))
      .orderBy(ledgerAccounts.id)
      .for('update');
    const accounts = new Map(locked.map((r) => [r.id, r]));
    for (const id of accountIds) {
      if (!accounts.has(id)) {
        throw new LedgerError(`account ${id} not found`, 'ACCOUNT_NOT_FOUND');
      }
    }

    // Balanced per currency (the DB trigger re-checks at commit).
    const perCurrency = new Map<string, bigint>();
    for (const e of entries) {
      const ccy = accounts.get(e.accountId)!.currency;
      perCurrency.set(ccy, (perCurrency.get(ccy) ?? 0n) + e.amountMinor);
    }
    for (const [ccy, total] of perCurrency) {
      if (total !== 0n) {
        throw new LedgerError(`entries sum to ${total} ${ccy}, expected 0`, 'UNBALANCED');
      }
    }

    await tx.insert(ledgerEntries).values(
      entries.map((e) => ({
        transactionId,
        accountId: e.accountId,
        amountMinor: e.amountMinor,
      })),
    );

    // Overdraft check: user accounts must not go negative. Accounts are
    // locked above, so this read is stable until commit.
    const deltaByAccount = new Map<string, bigint>();
    for (const e of entries) {
      deltaByAccount.set(e.accountId, (deltaByAccount.get(e.accountId) ?? 0n) + e.amountMinor);
    }
    for (const [accountId, delta] of deltaByAccount) {
      if (delta >= 0n) continue;
      const account = accounts.get(accountId)!;
      if (account.kind !== 'user') continue;
      const balance = await getBalanceTx(tx, accountId);
      if (balance < 0n) {
        throw new LedgerError(
          `insufficient funds in account ${accountId}`,
          'INSUFFICIENT_FUNDS',
        );
      }
    }

    return { transactionId, alreadyPosted: false };
  });
}

async function getBalanceTx(
  tx: Pick<Db, 'select'>,
  accountId: string,
): Promise<bigint> {
  const rows = await tx
    .select({ total: sql<string>`coalesce(sum(${ledgerEntries.amountMinor}), 0)` })
    .from(ledgerEntries)
    .where(eq(ledgerEntries.accountId, accountId));
  return BigInt(rows[0]?.total ?? '0');
}

/** Single-currency transfer between two accounts. */
export async function transfer(
  db: Db,
  args: {
    idempotencyKey: string;
    kind: string;
    fromAccountId: string;
    toAccountId: string;
    amountMinor: bigint;
    metadata?: Record<string, unknown>;
  },
): Promise<PostedTransaction> {
  if (args.amountMinor <= 0n) {
    throw new LedgerError('transfer amount must be positive', 'INVALID_ENTRIES');
  }
  const post: PostTransactionInput = {
    idempotencyKey: args.idempotencyKey,
    kind: args.kind,
    entries: [
      { accountId: args.fromAccountId, amountMinor: -args.amountMinor },
      { accountId: args.toAccountId, amountMinor: args.amountMinor },
    ],
  };
  if (args.metadata !== undefined) post.metadata = args.metadata;
  return postTransaction(db, post);
}
