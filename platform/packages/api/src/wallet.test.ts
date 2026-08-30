import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { createHmac, randomBytes } from 'node:crypto';
import { sql } from 'drizzle-orm';
import type { FastifyInstance } from 'fastify';
import { createDb, securityAuditEvents, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import { buildApp } from './app.js';

const TEST_URL = process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_api';
const WEBHOOK_SECRET = 'whsec_wallet_test';

let handle: DbHandle;
let app: FastifyInstance;
let userToken: string;
let adminToken: string;

const BTC_DESTINATION = 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh';
const WITHDRAWAL_AUTH = {
  currentPassword: 'a-strong-password',
  requestId: '11111111-1111-4111-8111-111111111111',
};
const ADMIN_AUTH = { currentPassword: 'a-strong-password' };

const providerFetch = (async () =>
  new Response(
    JSON.stringify({ data: { id: `ch_${randomBytes(8).toString('hex')}`, hosted_url: 'https://commerce.coinbase.com/charges/test' } }),
    { status: 201 },
  )) as typeof fetch;

function sign(body: string): string {
  return createHmac('sha256', WEBHOOK_SECRET).update(body).digest('hex');
}

beforeAll(async () => {
  process.env['AUTH_SECRET'] = randomBytes(32).toString('base64');
  process.env['COINBASE_API_KEY'] = 'test-key';
  process.env['COINBASE_WEBHOOK_SECRET'] = WEBHOOK_SECRET;
  await runMigrations(TEST_URL);
  handle = createDb(TEST_URL);
  app = buildApp({ db: handle.db, providerFetch });
  await app.ready();
});

afterAll(async () => {
  await app.close();
  await handle.pool.end();
});

async function register(email: string, username: string): Promise<string> {
  const res = await app.inject({
    method: 'POST',
    url: '/api/v1/auth/register',
    payload: { email, username, password: 'a-strong-password' },
  });
  expect(res.statusCode).toBe(201);
  return (res.json() as { token: string }).token;
}

beforeEach(async () => {
  await handle.db.execute(sql`
    TRUNCATE security_audit_events, ledger_entries, ledger_transactions, ledger_accounts,
             deposits, withdrawal_requests, inventory, sessions, user_2fa,
             pets, users CASCADE
  `);
  userToken = await register('wallet@paws.money', 'walletuser');
  adminToken = await register('admin@paws.money', 'adminuser');
  await handle.db.execute(sql`UPDATE users SET role = 'admin' WHERE username = 'adminuser'`);
});

async function depositAndConfirm(amountMinor: string): Promise<void> {
  const dep = await app.inject({
    method: 'POST',
    url: '/api/v1/wallet/deposit',
    headers: { authorization: `Bearer ${userToken}` },
    payload: { amountMinor, currency: 'USD' },
  });
  expect(dep.statusCode).toBe(201);
  const { chargeId } = dep.json() as { chargeId: string };
  const body = JSON.stringify({ type: 'charge:confirmed', data: { id: chargeId } });
  const hook = await app.inject({
    method: 'POST',
    url: '/api/v1/webhooks/coinbase',
    headers: { 'content-type': 'application/json', 'x-cc-webhook-signature': sign(body) },
    payload: body,
  });
  expect(hook.statusCode).toBe(200);
}

async function usdBalance(): Promise<string> {
  const res = await app.inject({
    method: 'GET',
    url: '/api/v1/me/balances',
    headers: { authorization: `Bearer ${userToken}` },
  });
  const { balances } = res.json() as { balances: { currency: string; amountMinor: string }[] };
  return balances.find((b) => b.currency === 'USD')!.amountMinor;
}

describe('deposits', () => {
  it('creates a charge and credits the balance on a signed webhook', async () => {
    await depositAndConfirm('2500');
    expect(await usdBalance()).toBe('2500');
  });

  // Negative: unsigned/forged webhooks are rejected and credit nothing.
  it('rejects webhooks with missing or forged signatures', async () => {
    const dep = await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/deposit',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '2500', currency: 'USD' },
    });
    const { chargeId } = dep.json() as { chargeId: string };
    const body = JSON.stringify({ type: 'charge:confirmed', data: { id: chargeId } });

    const noSig = await app.inject({
      method: 'POST',
      url: '/api/v1/webhooks/coinbase',
      headers: { 'content-type': 'application/json' },
      payload: body,
    });
    expect(noSig.statusCode).toBe(401);

    const forged = await app.inject({
      method: 'POST',
      url: '/api/v1/webhooks/coinbase',
      headers: {
        'content-type': 'application/json',
        'x-cc-webhook-signature': createHmac('sha256', 'wrong-secret').update(body).digest('hex'),
      },
      payload: body,
    });
    expect(forged.statusCode).toBe(401);
    expect(await usdBalance()).toBe('0');
  });

  it('replayed webhooks credit exactly once', async () => {
    const dep = await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/deposit',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '2500', currency: 'USD' },
    });
    const { chargeId } = dep.json() as { chargeId: string };
    const body = JSON.stringify({ type: 'charge:confirmed', data: { id: chargeId } });
    for (let i = 0; i < 3; i++) {
      await app.inject({
        method: 'POST',
        url: '/api/v1/webhooks/coinbase',
        headers: { 'content-type': 'application/json', 'x-cc-webhook-signature': sign(body) },
        payload: body,
      });
    }
    expect(await usdBalance()).toBe('2500');
  });

  // Negative: bounds and auth are enforced.
  it('rejects out-of-range amounts and unauthenticated deposits', async () => {
    const tooSmall = await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/deposit',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '99', currency: 'USD' },
    });
    expect(tooSmall.statusCode).toBe(400);
    const noAuth = await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/deposit',
      payload: { amountMinor: '2500', currency: 'USD' },
    });
    expect(noAuth.statusCode).toBe(401);
  });
});

describe('withdrawals', () => {
  it('requires password reauthentication before placing a hold', async () => {
    await depositAndConfirm('1000');
    const request = await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '500', currency: 'USD', network: 'bitcoin', destination: BTC_DESTINATION, ...WITHDRAWAL_AUTH, currentPassword: 'wrong-password' },
    });
    expect(request.statusCode).toBe(401);
    expect(request.json()).toEqual({ error: 'reauthentication_failed' });
    expect(await usdBalance()).toBe('1000');
  });

  it('request holds funds; admin denial refunds', async () => {
    await depositAndConfirm('5000');
    const req = await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '3000', currency: 'USD', network: 'bitcoin', destination: BTC_DESTINATION, ...WITHDRAWAL_AUTH },
    });
    expect(req.statusCode).toBe(201);
    expect(await usdBalance()).toBe('2000');

    const { id } = req.json() as { id: string };
    const review = await app.inject({
      method: 'POST',
      url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: { approve: false, note: 'test denial', ...ADMIN_AUTH },
    });
    expect(review.statusCode).toBe(200);
    expect(await usdBalance()).toBe('5000');
  });

  it('approve then paid empties the hold; double review is 409', async () => {
    await depositAndConfirm('5000');
    const req = await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '3000', currency: 'USD', network: 'bitcoin', destination: BTC_DESTINATION, ...WITHDRAWAL_AUTH },
    });
    const { id } = req.json() as { id: string };
    const approve = await app.inject({
      method: 'POST',
      url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: { approve: true, ...ADMIN_AUTH },
    });
    expect(approve.statusCode).toBe(200);
    const again = await app.inject({
      method: 'POST',
      url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: { approve: false, ...ADMIN_AUTH },
    });
    expect(again.statusCode).toBe(409);
    const paid = await app.inject({
      method: 'POST',
      url: `/api/v1/admin/withdrawals/${id}/paid`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: ADMIN_AUTH,
    });
    expect(paid.statusCode).toBe(200);
    expect(await usdBalance()).toBe('2000');
  });

  // Negative: overdraw and non-admin review are rejected.
  it('rejects overdraw requests and non-admin review', async () => {
    await depositAndConfirm('1000');
    const over = await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '1001', currency: 'USD', network: 'bitcoin', destination: BTC_DESTINATION, ...WITHDRAWAL_AUTH },
    });
    expect(over.statusCode).toBe(402);
    expect(await usdBalance()).toBe('1000');

    const req = await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '500', currency: 'USD', network: 'bitcoin', destination: BTC_DESTINATION, ...WITHDRAWAL_AUTH },
    });
    const { id } = req.json() as { id: string };
    const asUser = await app.inject({
      method: 'POST',
      url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${userToken}` },
      payload: { approve: true, ...ADMIN_AUTH },
    });
    expect(asUser.statusCode).toBe(403);
  });

  it('admin queue lists pending withdrawals', async () => {
    await depositAndConfirm('1000');
    await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '500', currency: 'USD', network: 'bitcoin', destination: BTC_DESTINATION, ...WITHDRAWAL_AUTH },
    });
    const list = await app.inject({
      method: 'GET',
      url: '/api/v1/admin/withdrawals',
      headers: { authorization: `Bearer ${adminToken}` },
    });
    expect(list.statusCode).toBe(200);
    const { withdrawals } = list.json() as { withdrawals: { amountMinor: string }[] };
    expect(withdrawals).toHaveLength(1);
    expect(withdrawals[0]!.amountMinor).toBe('500');
  });

  it('rejects malformed or network-mismatched wallet destinations', async () => {
    await depositAndConfirm('1000');
    for (const payload of [
      { network: 'bitcoin', destination: '0x1111111111111111111111111111111111111111' },
      { network: 'ethereum', destination: BTC_DESTINATION },
      { network: 'bitcoin', destination: 'https://lookalike.example/wallet' },
    ]) {
      const response = await app.inject({
        method: 'POST', url: '/api/v1/wallet/withdrawals',
        headers: { authorization: `Bearer ${userToken}` },
        payload: { amountMinor: '500', currency: 'USD', ...payload, ...WITHDRAWAL_AUTH },
      });
      expect(response.statusCode).toBe(400);
      expect(response.json()).toEqual({ error: 'invalid_destination' });
    }
    expect(await usdBalance()).toBe('1000');
  });

  it('requires admin step-up authentication and records privileged actions', async () => {
    await depositAndConfirm('1000');
    const request = await app.inject({
      method: 'POST', url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '500', currency: 'USD', network: 'bitcoin', destination: BTC_DESTINATION, ...WITHDRAWAL_AUTH },
    });
    const id = request.json().id as string;

    const missing = await app.inject({
      method: 'POST', url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${adminToken}` }, payload: { approve: true },
    });
    expect(missing.statusCode).toBe(400);

    const wrong = await app.inject({
      method: 'POST', url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: { approve: true, currentPassword: 'wrong-password' },
    });
    expect(wrong.statusCode).toBe(401);

    const approved = await app.inject({
      method: 'POST', url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: { approve: true, ...ADMIN_AUTH },
    });
    expect(approved.statusCode).toBe(200);

    const events = await handle.db.select().from(securityAuditEvents);
    expect(events.map((event) => event.eventType)).toEqual(expect.arrayContaining([
      'wallet.admin_reauth_failed',
      'wallet.withdrawal_approved',
    ]));
  });

  it('keeps security audit evidence append-only', async () => {
    await handle.db.insert(securityAuditEvents).values({ eventType: 'test.security_event' });
    await expect(
      handle.db.execute(sql`UPDATE security_audit_events SET event_type = 'tampered'`),
    ).rejects.toThrow();
    await expect(
      handle.db.execute(sql`DELETE FROM security_audit_events`),
    ).rejects.toThrow();
    const events = await handle.db.select().from(securityAuditEvents);
    expect(events.map((event) => event.eventType)).toContain('test.security_event');
  });

  it('prevents an admin from reviewing their own withdrawal', async () => {
    await handle.db.execute(sql`UPDATE users SET role = 'admin' WHERE username = 'walletuser'`);
    await depositAndConfirm('1000');
    const request = await app.inject({
      method: 'POST', url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '500', currency: 'USD', network: 'bitcoin', destination: BTC_DESTINATION, ...WITHDRAWAL_AUTH },
    });
    const review = await app.inject({
      method: 'POST', url: `/api/v1/admin/withdrawals/${request.json().id}/review`,
      headers: { authorization: `Bearer ${userToken}` },
      payload: { approve: true, ...ADMIN_AUTH },
    });
    expect(review.statusCode).toBe(403);
    expect(review.json()).toEqual({ error: 'separation_of_duties' });

    const approved = await app.inject({
      method: 'POST', url: `/api/v1/admin/withdrawals/${request.json().id}/review`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: { approve: true, ...ADMIN_AUTH },
    });
    expect(approved.statusCode).toBe(200);
    const selfPaid = await app.inject({
      method: 'POST', url: `/api/v1/admin/withdrawals/${request.json().id}/paid`,
      headers: { authorization: `Bearer ${userToken}` }, payload: ADMIN_AUTH,
    });
    expect(selfPaid.statusCode).toBe(403);
    expect(selfPaid.json()).toEqual({ error: 'separation_of_duties' });
  });

  it('makes repeated withdrawal requests exactly idempotent', async () => {
    await depositAndConfirm('1000');
    const payload = {
      amountMinor: '500', currency: 'USD', network: 'bitcoin',
      destination: BTC_DESTINATION, ...WITHDRAWAL_AUTH,
    };
    const first = await app.inject({
      method: 'POST', url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` }, payload,
    });
    const replay = await app.inject({
      method: 'POST', url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` }, payload,
    });
    expect(first.statusCode).toBe(201);
    expect(replay.statusCode).toBe(200);
    expect(replay.json()).toMatchObject({ id: first.json().id, alreadyRequested: true });
    const conflictingReplay = await app.inject({
      method: 'POST', url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { ...payload, amountMinor: '400' },
    });
    expect(conflictingReplay.statusCode).toBe(409);
    expect(conflictingReplay.json()).toEqual({ error: 'idempotency_conflict' });
    expect(await usdBalance()).toBe('500');
  });
});
