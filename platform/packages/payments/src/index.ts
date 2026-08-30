/**
 * Payments: Coinbase Commerce deposits and manually-reviewed withdrawals.
 *
 * Threats: Protects against forged deposit webhooks (HMAC-SHA256 verified in
 * constant time against the raw body), replayed webhooks and double-credits
 * (status-gated UPDATE plus ledger idempotency key per charge), withdrawal
 * double-spends (funds move to a withholding account at request time, so a
 * second request cannot spend the same balance), and float corruption
 * (bigint minor units end to end). Does NOT handle authorization (API layer
 * verifies the acting user / admin role) or actual crypto payouts (manual,
 * outside the system).
 */
import { createHmac, timingSafeEqual } from 'node:crypto';
import { and, eq } from 'drizzle-orm';
import { type Db, deposits, withdrawalRequests } from '@paws/db';
import {
  ensureSystemAccount,
  ensureUserAccount,
  transfer,
  type Currency,
} from '@paws/ledger';

export class PaymentsError extends Error {
  constructor(
    message: string,
    readonly code:
      | 'INVALID_AMOUNT'
      | 'INVALID_DESTINATION'
      | 'NOT_FOUND'
      | 'ALREADY_REVIEWED'
      | 'PROVIDER_ERROR',
  ) {
    super(message);
    this.name = 'PaymentsError';
  }
}

// === Coinbase Commerce charge creation ===

const COINBASE_API = 'https://api.commerce.coinbase.com/charges';
const PROVIDER_TIMEOUT_MS = 10_000;
const MAX_PROVIDER_RESPONSE_BYTES = 1_000_000;

export interface CreateChargeInput {
  apiKey: string;
  name: string;
  description: string;
  amountMinor: bigint;
  currency: 'USD';
  metadata: Record<string, string>;
  fetchImpl?: typeof fetch;
}

/** Render bigint cents as a decimal string without touching floats. */
export function minorToDecimal(amountMinor: bigint): string {
  if (amountMinor < 0n) throw new PaymentsError('negative amount', 'INVALID_AMOUNT');
  const whole = amountMinor / 100n;
  const frac = (amountMinor % 100n).toString().padStart(2, '0');
  return `${whole}.${frac}`;
}

export async function createCharge(
  input: CreateChargeInput,
): Promise<{ chargeId: string; hostedUrl: string }> {
  if (input.amountMinor <= 0n) {
    throw new PaymentsError('deposit amount must be positive', 'INVALID_AMOUNT');
  }
  const doFetch = input.fetchImpl ?? fetch;
  let res: Response;
  try {
    res = await doFetch(COINBASE_API, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CC-Api-Key': input.apiKey,
        'X-CC-Version': '2018-03-22',
      },
      body: JSON.stringify({
        name: input.name,
        description: input.description,
        pricing_type: 'fixed_price',
        local_price: { amount: minorToDecimal(input.amountMinor), currency: input.currency },
        metadata: input.metadata,
      }),
      signal: AbortSignal.timeout(PROVIDER_TIMEOUT_MS),
    });
  } catch {
    throw new PaymentsError('coinbase request failed', 'PROVIDER_ERROR');
  }
  const text = await res.text();
  if (!res.ok || text.length > MAX_PROVIDER_RESPONSE_BYTES) {
    throw new PaymentsError(`coinbase returned ${res.status}`, 'PROVIDER_ERROR');
  }
  let parsed: unknown;
  try {
    parsed = JSON.parse(text);
  } catch {
    throw new PaymentsError('coinbase returned non-JSON', 'PROVIDER_ERROR');
  }
  const data = (parsed as { data?: { id?: unknown; hosted_url?: unknown } }).data;
  if (typeof data?.id !== 'string' || typeof data.hosted_url !== 'string') {
    throw new PaymentsError('coinbase response missing charge fields', 'PROVIDER_ERROR');
  }
  if (!/^[A-Za-z0-9_-]{3,128}$/.test(data.id)) {
    throw new PaymentsError('coinbase returned invalid charge id', 'PROVIDER_ERROR');
  }
  let hosted: URL;
  try {
    hosted = new URL(data.hosted_url);
  } catch {
    throw new PaymentsError('coinbase returned invalid hosted URL', 'PROVIDER_ERROR');
  }
  if (hosted.protocol !== 'https:' || hosted.hostname !== 'commerce.coinbase.com') {
    throw new PaymentsError('coinbase returned an untrusted hosted URL', 'PROVIDER_ERROR');
  }
  return { chargeId: data.id, hostedUrl: data.hosted_url };
}

// === Webhook verification (X-CC-Webhook-Signature: hex HMAC-SHA256 of raw body) ===

export function verifyWebhookSignature(
  rawBody: string | Buffer,
  signatureHex: unknown,
  sharedSecret: string,
): boolean {
  if (typeof signatureHex !== 'string' || !/^[0-9a-f]{64}$/i.test(signatureHex)) return false;
  if (!sharedSecret) return false;
  const expected = createHmac('sha256', sharedSecret).update(rawBody).digest();
  const provided = Buffer.from(signatureHex, 'hex');
  if (provided.length !== expected.length) return false;
  return timingSafeEqual(expected, provided);
}

// === Deposits ===

export async function createDeposit(
  db: Db,
  input: { userId: string; amountMinor: bigint; currency: Currency; chargeId: string },
): Promise<string> {
  if (input.amountMinor <= 0n) {
    throw new PaymentsError('deposit amount must be positive', 'INVALID_AMOUNT');
  }
  const inserted = await db
    .insert(deposits)
    .values({
      userId: input.userId,
      providerChargeId: input.chargeId,
      amountMinor: input.amountMinor,
      currency: input.currency,
    })
    .returning({ id: deposits.id });
  return inserted[0]!.id;
}

export type DepositConfirmResult = 'credited' | 'already_processed' | 'not_found';

/**
 * Idempotent: the status-gated UPDATE claims the deposit exactly once, and
 * the ledger key `deposit:<chargeId>` is a second, independent guard.
 */
export async function confirmDeposit(db: Db, chargeId: string): Promise<DepositConfirmResult> {
  return db.transaction(async (tx) => {
    // Status-gated claim: only a pending deposit can be confirmed. Replays
    // and post-failure events find no row to claim.
    const claimed = await tx
      .update(deposits)
      .set({ status: 'confirmed', confirmedAt: new Date() })
      .where(and(eq(deposits.providerChargeId, chargeId), eq(deposits.status, 'pending')))
      .returning({
        id: deposits.id,
        userId: deposits.userId,
        amountMinor: deposits.amountMinor,
        currency: deposits.currency,
      });
    const row = claimed[0];
    if (!row) {
      const existing = await tx
        .select({ status: deposits.status })
        .from(deposits)
        .where(eq(deposits.providerChargeId, chargeId));
      return existing[0] ? 'already_processed' : 'not_found';
    }
    const userAccount = await ensureUserAccount(tx as unknown as Db, row.userId, row.currency);
    const treasury = await ensureSystemAccount(tx as unknown as Db, 'treasury', row.currency);
    const posted = await transfer(tx as unknown as Db, {
      idempotencyKey: `deposit:${chargeId}`,
      kind: 'deposit',
      fromAccountId: treasury,
      toAccountId: userAccount,
      amountMinor: row.amountMinor,
      metadata: { depositId: row.id, chargeId },
    });
    return posted.alreadyPosted ? 'already_processed' : 'credited';
  });
}

export async function failDeposit(db: Db, chargeId: string, kind: 'failed' | 'expired') {
  await db
    .update(deposits)
    .set({ status: kind })
    .where(eq(deposits.providerChargeId, chargeId));
}

export interface CoinbaseEvent {
  type: string;
  data?: { id?: string };
}

export async function handleWebhookEvent(db: Db, event: CoinbaseEvent): Promise<string> {
  const chargeId = event.data?.id;
  if (typeof chargeId !== 'string' || chargeId.length === 0 || chargeId.length > 200) {
    return 'ignored';
  }
  switch (event.type) {
    case 'charge:confirmed':
      return confirmDeposit(db, chargeId);
    case 'charge:failed':
      await failDeposit(db, chargeId, 'failed');
      return 'marked_failed';
    default:
      return 'ignored';
  }
}

// === Withdrawals: hold funds at request time, manual review ===

const MAX_DESTINATION_LENGTH = 200;

export async function requestWithdrawal(
  db: Db,
  input: { userId: string; amountMinor: bigint; currency: Currency; destination: string },
): Promise<string> {
  if (input.amountMinor <= 0n) {
    throw new PaymentsError('withdrawal amount must be positive', 'INVALID_AMOUNT');
  }
  const destination = input.destination.trim();
  if (destination.length < 5 || destination.length > MAX_DESTINATION_LENGTH) {
    throw new PaymentsError('invalid destination', 'INVALID_DESTINATION');
  }
  return db.transaction(async (tx) => {
    const inserted = await tx
      .insert(withdrawalRequests)
      .values({
        userId: input.userId,
        amountMinor: input.amountMinor,
        currency: input.currency,
        destination,
      })
      .returning({ id: withdrawalRequests.id });
    const id = inserted[0]!.id;
    const userAccount = await ensureUserAccount(tx as unknown as Db, input.userId, input.currency);
    const withholding = await ensureSystemAccount(
      tx as unknown as Db,
      'withholding',
      input.currency,
    );
    // Throws INSUFFICIENT_FUNDS and rolls the request back if the balance
    // cannot cover the hold.
    await transfer(tx as unknown as Db, {
      idempotencyKey: `withdrawal:${id}:hold`,
      kind: 'withdrawal_hold',
      fromAccountId: userAccount,
      toAccountId: withholding,
      amountMinor: input.amountMinor,
      metadata: { withdrawalId: id },
    });
    return id;
  });
}

export async function reviewWithdrawal(
  db: Db,
  input: { withdrawalId: string; reviewerId: string; approve: boolean; note?: string },
): Promise<'approved' | 'denied'> {
  return db.transaction(async (tx) => {
    const rows = await tx
      .select()
      .from(withdrawalRequests)
      .where(eq(withdrawalRequests.id, input.withdrawalId))
      .for('update');
    const row = rows[0];
    if (!row) throw new PaymentsError('withdrawal not found', 'NOT_FOUND');
    if (row.status !== 'pending') {
      throw new PaymentsError(`withdrawal is ${row.status}`, 'ALREADY_REVIEWED');
    }
    await tx
      .update(withdrawalRequests)
      .set({
        status: input.approve ? 'approved' : 'denied',
        reviewedBy: input.reviewerId,
        reviewNote: input.note ?? null,
        reviewedAt: new Date(),
      })
      .where(eq(withdrawalRequests.id, input.withdrawalId));
    if (!input.approve) {
      const userAccount = await ensureUserAccount(tx as unknown as Db, row.userId, row.currency);
      const withholding = await ensureSystemAccount(
        tx as unknown as Db,
        'withholding',
        row.currency,
      );
      await transfer(tx as unknown as Db, {
        idempotencyKey: `withdrawal:${row.id}:refund`,
        kind: 'withdrawal_refund',
        fromAccountId: withholding,
        toAccountId: userAccount,
        amountMinor: row.amountMinor,
        metadata: { withdrawalId: row.id },
      });
    }
    return input.approve ? 'approved' : 'denied';
  });
}

/** After the operator has actually sent funds: withholding -> treasury. */
export async function markWithdrawalPaid(db: Db, withdrawalId: string): Promise<void> {
  await db.transaction(async (tx) => {
    const rows = await tx
      .select()
      .from(withdrawalRequests)
      .where(eq(withdrawalRequests.id, withdrawalId))
      .for('update');
    const row = rows[0];
    if (!row) throw new PaymentsError('withdrawal not found', 'NOT_FOUND');
    if (row.status !== 'approved') {
      throw new PaymentsError(`withdrawal is ${row.status}, expected approved`, 'ALREADY_REVIEWED');
    }
    await tx
      .update(withdrawalRequests)
      .set({ status: 'paid' })
      .where(eq(withdrawalRequests.id, withdrawalId));
    const withholding = await ensureSystemAccount(
      tx as unknown as Db,
      'withholding',
      row.currency,
    );
    const treasury = await ensureSystemAccount(tx as unknown as Db, 'treasury', row.currency);
    await transfer(tx as unknown as Db, {
      idempotencyKey: `withdrawal:${row.id}:payout`,
      kind: 'withdrawal_payout',
      fromAccountId: withholding,
      toAccountId: treasury,
      amountMinor: row.amountMinor,
      metadata: { withdrawalId: row.id },
    });
  });
}
