import { randomBytes } from 'node:crypto';
import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { sql } from 'drizzle-orm';
import { createDb, storeItems, inventory, pets, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import {
  ensureUserAccount,
  ensureSystemAccount,
  postTransaction,
} from '@paws/ledger';
import { currentTotp } from '@paws/auth';
import type { FastifyInstance } from 'fastify';
import { buildApp } from './app.js';

// Sealing key for TOTP secrets — a throwaway 32-byte base64 value.
process.env['AUTH_SECRET'] = randomBytes(32).toString('base64');

const TEST_URL =
  process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_api';

let handle: DbHandle;
let app: FastifyInstance;

beforeAll(async () => {
  await runMigrations(TEST_URL);
  handle = createDb(TEST_URL);
  app = buildApp({ db: handle.db });
  await app.ready();
});

afterAll(async () => {
  await app.close();
  await handle.pool.end();
});

beforeEach(async () => {
  await handle.db.execute(sql`
    TRUNCATE ledger_entries, ledger_transactions, ledger_accounts,
             inventory, deposits, withdrawal_requests, sessions, user_2fa,
             pets, store_items, users CASCADE
  `);
});

// --- helpers -------------------------------------------------------------

async function register(
  overrides: Partial<{ email: string; username: string; password: string }> = {},
): Promise<{ token: string; userId: string }> {
  const suffix = randomBytes(4).toString('hex');
  const res = await app.inject({
    method: 'POST',
    url: '/api/v1/auth/register',
    payload: {
      email: overrides.email ?? `user_${suffix}@paws.money`,
      username: overrides.username ?? `user_${suffix}`,
      password: overrides.password ?? 'correct horse battery',
    },
  });
  expect(res.statusCode).toBe(201);
  const body = res.json();
  return { token: body.token, userId: body.user.id };
}

function auth(token: string): Record<string, string> {
  return { authorization: `Bearer ${token}` };
}

async function seedItem(values: {
  name: string;
  category: string;
  priceMinor: bigint;
  currency?: 'PAWS' | 'USD';
  effect?: Record<string, unknown>;
  active?: boolean;
}): Promise<string> {
  const inserted = await handle.db
    .insert(storeItems)
    .values({
      name: values.name,
      category: values.category,
      priceMinor: values.priceMinor,
      currency: values.currency ?? 'PAWS',
      effect: values.effect ?? {},
      active: values.active ?? true,
    })
    .returning({ id: storeItems.id });
  return inserted[0]!.id;
}

/** Credit a user account from the treasury so it can afford a purchase. */
async function fund(userId: string, currency: 'PAWS' | 'USD', amount: bigint): Promise<void> {
  const userAccount = await ensureUserAccount(handle.db, userId, currency);
  const treasury = await ensureSystemAccount(handle.db, 'treasury', currency);
  await postTransaction(handle.db, {
    idempotencyKey: `fund:${userId}:${currency}:${randomBytes(6).toString('hex')}`,
    kind: 'test_fund',
    entries: [
      { accountId: treasury, amountMinor: -amount },
      { accountId: userAccount, amountMinor: amount },
    ],
  });
}

async function pawsBalance(token: string): Promise<bigint> {
  const res = await app.inject({ method: 'GET', url: '/api/v1/me/balances', headers: auth(token) });
  expect(res.statusCode).toBe(200);
  const entry = res.json().balances.find((b: { currency: string }) => b.currency === 'PAWS');
  return BigInt(entry.amountMinor);
}

// --- tests ---------------------------------------------------------------

describe('auth', () => {
  it('register + login + me happy path', async () => {
    const { token } = await register({ email: 'happy@paws.money', username: 'happy' });

    const login = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/login',
      payload: { email: 'happy@paws.money', password: 'correct horse battery' },
    });
    expect(login.statusCode).toBe(200);
    const loginToken = login.json().token as string;
    expect(loginToken).toBeTruthy();

    const me = await app.inject({ method: 'GET', url: '/api/v1/me', headers: auth(loginToken) });
    expect(me.statusCode).toBe(200);
    expect(me.json().user).toMatchObject({ email: 'happy@paws.money', username: 'happy', role: 'user' });
    expect(me.json().user).not.toHaveProperty('passwordHash');

    // The registration token is equally valid.
    const meAgain = await app.inject({ method: 'GET', url: '/api/v1/me', headers: auth(token) });
    expect(meAgain.statusCode).toBe(200);
  });

  it('login with wrong password -> 401 invalid_credentials', async () => {
    await register({ email: 'wp@paws.money', username: 'wrongpw' });
    const res = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/login',
      payload: { email: 'wp@paws.money', password: 'not the password' },
    });
    expect(res.statusCode).toBe(401);
    expect(res.json()).toEqual({ error: 'invalid_credentials' });
  });

  it('login for unknown email -> 401 invalid_credentials (no user enumeration)', async () => {
    const res = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/login',
      payload: { email: 'ghost@paws.money', password: 'whatever password' },
    });
    expect(res.statusCode).toBe(401);
    expect(res.json()).toEqual({ error: 'invalid_credentials' });
  });

  it('duplicate email -> 409', async () => {
    await register({ email: 'dupe@paws.money', username: 'dupe1' });
    const res = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/register',
      payload: { email: 'dupe@paws.money', username: 'dupe2', password: 'correct horse battery' },
    });
    expect(res.statusCode).toBe(409);
  });

  it('missing token -> 401 unauthorized', async () => {
    const res = await app.inject({ method: 'GET', url: '/api/v1/me' });
    expect(res.statusCode).toBe(401);
    expect(res.json()).toEqual({ error: 'unauthorized' });
  });

  it('tampered token -> 401 unauthorized', async () => {
    const { token } = await register();
    const tampered = `${token.slice(0, -3)}xyz`;
    const res = await app.inject({ method: 'GET', url: '/api/v1/me', headers: auth(tampered) });
    expect(res.statusCode).toBe(401);
    expect(res.json()).toEqual({ error: 'unauthorized' });
  });
});

describe('validation', () => {
  it('bad email -> 400', async () => {
    const res = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/register',
      payload: { email: 'not-an-email', username: 'validuser', password: 'correct horse battery' },
    });
    expect(res.statusCode).toBe(400);
  });

  it('oversized pet name -> 400', async () => {
    const { token } = await register();
    const res = await app.inject({
      method: 'POST',
      url: '/api/v1/pets',
      headers: auth(token),
      payload: { name: 'x'.repeat(51), species: 'dog' },
    });
    expect(res.statusCode).toBe(400);
  });

  it('negative purchase quantity -> 400', async () => {
    const { token } = await register();
    const itemId = await seedItem({ name: 'kibble', category: 'food', priceMinor: 10n });
    const res = await app.inject({
      method: 'POST',
      url: '/api/v1/store/purchase',
      headers: auth(token),
      payload: { itemId, quantity: -1 },
    });
    expect(res.statusCode).toBe(400);
  });
});

describe('pets', () => {
  it('cross-user pet access -> 404', async () => {
    const alice = await register();
    const bob = await register();

    const created = await app.inject({
      method: 'POST',
      url: '/api/v1/pets',
      headers: auth(alice.token),
      payload: { name: 'Rex', species: 'dog' },
    });
    expect(created.statusCode).toBe(201);
    const petId = created.json().pet.id as string;

    const ownerView = await app.inject({
      method: 'GET',
      url: `/api/v1/pets/${petId}`,
      headers: auth(alice.token),
    });
    expect(ownerView.statusCode).toBe(200);

    const strangerView = await app.inject({
      method: 'GET',
      url: `/api/v1/pets/${petId}`,
      headers: auth(bob.token),
    });
    expect(strangerView.statusCode).toBe(404);
  });

  it('feed consumes an item and clamps stats at 100', async () => {
    const { token, userId } = await register();
    const itemId = await seedItem({
      name: 'super treat',
      category: 'treat',
      priceMinor: 1n,
      effect: { hungerRestore: 80, happinessBoost: 80 },
    });
    // Give the user two of the item and a hungry pet.
    await handle.db.insert(inventory).values({ userId, itemId, quantity: 2 });
    const petRow = await handle.db
      .insert(pets)
      .values({ userId, name: 'Peckish', species: 'cat', hunger: 40, happiness: 40 })
      .returning({ id: pets.id });
    const petId = petRow[0]!.id;

    const res = await app.inject({
      method: 'POST',
      url: `/api/v1/pets/${petId}/feed`,
      headers: auth(token),
      payload: { itemId },
    });
    expect(res.statusCode).toBe(200);
    // 40 + 80 clamps to 100, not 120.
    expect(res.json().pet).toMatchObject({ hunger: 100, happiness: 100 });

    // Inventory decremented by exactly one.
    const inv = await app.inject({ method: 'GET', url: '/api/v1/me/inventory', headers: auth(token) });
    const item = inv.json().items.find((i: { itemId: string }) => i.itemId === itemId);
    expect(item.quantity).toBe(1);
  });

  it('feeding an item the user does not own -> 400', async () => {
    const { token, userId } = await register();
    const itemId = await seedItem({
      name: 'lonely kibble',
      category: 'food',
      priceMinor: 1n,
      effect: { hungerRestore: 10 },
    });
    const petRow = await handle.db
      .insert(pets)
      .values({ userId, name: 'Hungry', species: 'dog', hunger: 10 })
      .returning({ id: pets.id });
    const res = await app.inject({
      method: 'POST',
      url: `/api/v1/pets/${petRow[0]!.id}/feed`,
      headers: auth(token),
      payload: { itemId },
    });
    expect(res.statusCode).toBe(400);
  });
});

describe('store / purchase', () => {
  it('public store lists only active items', async () => {
    await seedItem({ name: 'active-item', category: 'food', priceMinor: 5n, active: true });
    await seedItem({ name: 'hidden-item', category: 'food', priceMinor: 5n, active: false });
    const res = await app.inject({ method: 'GET', url: '/api/v1/store/items' });
    expect(res.statusCode).toBe(200);
    const names = res.json().items.map((i: { name: string }) => i.name);
    expect(names).toContain('active-item');
    expect(names).not.toContain('hidden-item');
  });

  it('purchase debits balance and grants inventory', async () => {
    const { token, userId } = await register();
    const itemId = await seedItem({ name: 'ball', category: 'toy', priceMinor: 100n });
    await fund(userId, 'PAWS', 1000n);

    const res = await app.inject({
      method: 'POST',
      url: '/api/v1/store/purchase',
      headers: auth(token),
      payload: { itemId, quantity: 3 },
    });
    expect(res.statusCode).toBe(200);
    expect(await pawsBalance(token)).toBe(700n);

    const inv = await app.inject({ method: 'GET', url: '/api/v1/me/inventory', headers: auth(token) });
    const item = inv.json().items.find((i: { itemId: string }) => i.itemId === itemId);
    expect(item.quantity).toBe(3);
  });

  it('insufficient funds -> 402 and balance unchanged', async () => {
    const { token, userId } = await register();
    const itemId = await seedItem({ name: 'yacht', category: 'toy', priceMinor: 10_000n });
    await fund(userId, 'PAWS', 50n);

    const res = await app.inject({
      method: 'POST',
      url: '/api/v1/store/purchase',
      headers: auth(token),
      payload: { itemId, quantity: 1 },
    });
    expect(res.statusCode).toBe(402);
    expect(res.json()).toEqual({ error: 'insufficient_funds' });
    expect(await pawsBalance(token)).toBe(50n);

    const inv = await app.inject({ method: 'GET', url: '/api/v1/me/inventory', headers: auth(token) });
    expect(inv.json().items).toHaveLength(0);
  });

  it('idempotent purchase: same Idempotency-Key grants inventory once', async () => {
    const { token, userId } = await register();
    const itemId = await seedItem({ name: 'collar', category: 'gear', priceMinor: 100n });
    await fund(userId, 'PAWS', 1000n);

    const payload = { itemId, quantity: 2 };
    const key = `test-key-${randomBytes(6).toString('hex')}`;

    const first = await app.inject({
      method: 'POST',
      url: '/api/v1/store/purchase',
      headers: { ...auth(token), 'idempotency-key': key },
      payload,
    });
    const second = await app.inject({
      method: 'POST',
      url: '/api/v1/store/purchase',
      headers: { ...auth(token), 'idempotency-key': key },
      payload,
    });
    expect(first.statusCode).toBe(200);
    expect(second.statusCode).toBe(200);
    expect(second.json().alreadyPosted).toBe(true);

    // Debited once (200), granted once (2).
    expect(await pawsBalance(token)).toBe(800n);
    const inv = await app.inject({ method: 'GET', url: '/api/v1/me/inventory', headers: auth(token) });
    const item = inv.json().items.find((i: { itemId: string }) => i.itemId === itemId);
    expect(item.quantity).toBe(2);
  });

  it('purchasing a missing item -> 404', async () => {
    const { token } = await register();
    const res = await app.inject({
      method: 'POST',
      url: '/api/v1/store/purchase',
      headers: auth(token),
      payload: { itemId: '00000000-0000-0000-0000-000000000000', quantity: 1 },
    });
    expect(res.statusCode).toBe(404);
  });
});

describe('2fa', () => {
  it('setup then enable then login requires TOTP', async () => {
    const { token } = await register({ email: '2fa@paws.money', username: 'twofa' });

    const setup = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/2fa/setup',
      headers: auth(token),
      payload: { currentPassword: 'correct horse battery' },
    });
    expect(setup.statusCode).toBe(200);
    const secret = setup.json().secret as string;
    expect(setup.json().otpauthUrl).toContain('otpauth://totp/paws.money:');

    const enable = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/2fa/enable',
      headers: auth(token),
      payload: { totp: currentTotp(secret) },
    });
    expect(enable.statusCode).toBe(200);

    // Login without TOTP now fails.
    const noTotp = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/login',
      payload: { email: '2fa@paws.money', password: 'correct horse battery' },
    });
    expect(noTotp.statusCode).toBe(401);
    expect(noTotp.json()).toEqual({ error: 'totp_required' });

    // Login with a valid TOTP succeeds.
    const withTotp = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/login',
      payload: {
        email: '2fa@paws.money',
        password: 'correct horse battery',
        totp: currentTotp(secret),
      },
    });
    expect(withTotp.statusCode).toBe(200);
    expect(withTotp.json().token).toBeTruthy();
  });
});
