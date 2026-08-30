import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { createHmac, randomBytes } from 'node:crypto';
import { sql } from 'drizzle-orm';
import type { FastifyInstance } from 'fastify';
import { createDb, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import { buildApp } from './app.js';

const TEST_URL = process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_api';
const WEBHOOK_SECRET = 'whsec_wallet_test';

let handle: DbHandle;
let app: FastifyInstance;
let userToken: string;
let adminToken: string;

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
    TRUNCATE ledger_entries, ledger_transactions, ledger_accounts,
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
      payload: { amountMinor: '500', currency: 'USD', destination: 'bc1q-somewhere', currentPassword: 'wrong-password' },
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
      payload: { amountMinor: '3000', currency: 'USD', destination: 'bc1q-somewhere', currentPassword: 'a-strong-password' },
    });
    expect(req.statusCode).toBe(201);
    expect(await usdBalance()).toBe('2000');

    const { id } = req.json() as { id: string };
    const review = await app.inject({
      method: 'POST',
      url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: { approve: false, note: 'test denial' },
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
      payload: { amountMinor: '3000', currency: 'USD', destination: 'bc1q-somewhere', currentPassword: 'a-strong-password' },
    });
    const { id } = req.json() as { id: string };
    const approve = await app.inject({
      method: 'POST',
      url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: { approve: true },
    });
    expect(approve.statusCode).toBe(200);
    const again = await app.inject({
      method: 'POST',
      url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: { approve: false },
    });
    expect(again.statusCode).toBe(409);
    const paid = await app.inject({
      method: 'POST',
      url: `/api/v1/admin/withdrawals/${id}/paid`,
      headers: { authorization: `Bearer ${adminToken}` },
      payload: {},
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
      payload: { amountMinor: '1001', currency: 'USD', destination: 'bc1q-somewhere', currentPassword: 'a-strong-password' },
    });
    expect(over.statusCode).toBe(402);
    expect(await usdBalance()).toBe('1000');

    const req = await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '500', currency: 'USD', destination: 'bc1q-somewhere', currentPassword: 'a-strong-password' },
    });
    const { id } = req.json() as { id: string };
    const asUser = await app.inject({
      method: 'POST',
      url: `/api/v1/admin/withdrawals/${id}/review`,
      headers: { authorization: `Bearer ${userToken}` },
      payload: { approve: true },
    });
    expect(asUser.statusCode).toBe(403);
  });

  it('admin queue lists pending withdrawals', async () => {
    await depositAndConfirm('1000');
    await app.inject({
      method: 'POST',
      url: '/api/v1/wallet/withdrawals',
      headers: { authorization: `Bearer ${userToken}` },
      payload: { amountMinor: '500', currency: 'USD', destination: 'bc1q-somewhere', currentPassword: 'a-strong-password' },
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
});
