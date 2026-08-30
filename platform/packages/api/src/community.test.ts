import { randomBytes } from 'node:crypto';
import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { sql } from 'drizzle-orm';
import { createDb, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import type { FastifyInstance } from 'fastify';
import { buildApp } from './app.js';

process.env['AUTH_SECRET'] = randomBytes(32).toString('base64');
const TEST_URL = process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_api';

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
    TRUNCATE user_trophies, user_settings, ledger_entries, ledger_transactions,
      ledger_accounts, sessions, user_2fa, pets, users CASCADE
  `);
});

async function register(username: string, email = `${username}@paws.money`) {
  const response = await app.inject({
    method: 'POST',
    url: '/api/v1/auth/register',
    payload: { email, username, password: 'correct horse battery' },
  });
  expect(response.statusCode).toBe(201);
  return response.json() as { token: string; user: { id: string } };
}

const auth = (token: string) => ({ authorization: `Bearer ${token}` });

describe('browser session security', () => {
  it('can issue an HttpOnly strict cookie without exposing its token to browser JavaScript', async () => {
    const response = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/register',
      headers: { 'x-paws-session-mode': 'cookie' },
      payload: { email: 'cookie@paws.money', username: 'cookie_user', password: 'correct horse battery' },
    });
    expect(response.statusCode).toBe(201);
    expect(response.json()).not.toHaveProperty('token');
    const cookie = response.headers['set-cookie'];
    expect(cookie).toContain('paws_session=');
    expect(cookie).toContain('HttpOnly');
    expect(cookie).toContain('SameSite=Strict');
  });

  it('accepts cookie auth for reads and rejects cross-site mutations', async () => {
    const login = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/register',
      headers: { 'x-paws-session-mode': 'cookie' },
      payload: { email: 'csrf@paws.money', username: 'csrf_user', password: 'correct horse battery' },
    });
    const cookie = (login.headers['set-cookie'] as string).split(';')[0]!;
    const me = await app.inject({ method: 'GET', url: '/api/v1/me', headers: { cookie } });
    expect(me.statusCode).toBe(200);

    const attack = await app.inject({
      method: 'PUT',
      url: '/api/v1/me/settings',
      headers: { cookie, origin: 'https://watering-hole.example' },
      payload: { leaderboardOptIn: true, trophyShowcase: true },
    });
    expect(attack.statusCode).toBe(403);
    expect(attack.json()).toEqual({ error: 'cross_site_request' });
  });
});

describe('account settings', () => {
  it('defaults leaderboard participation to private and updates only allowed booleans', async () => {
    const user = await register('settings_user');
    const initial = await app.inject({ method: 'GET', url: '/api/v1/me/settings', headers: auth(user.token) });
    expect(initial.statusCode).toBe(200);
    expect(initial.json().settings).toMatchObject({ leaderboardOptIn: false, trophyShowcase: true });

    const invalid = await app.inject({
      method: 'PUT', url: '/api/v1/me/settings', headers: auth(user.token),
      payload: { leaderboardOptIn: true, trophyShowcase: true, html: '<script>alert(1)</script>' },
    });
    expect(invalid.statusCode).toBe(400);

    const updated = await app.inject({
      method: 'PUT', url: '/api/v1/me/settings', headers: auth(user.token),
      payload: { leaderboardOptIn: true, trophyShowcase: false },
    });
    expect(updated.statusCode).toBe(200);
    expect(updated.json().settings).toMatchObject({ leaderboardOptIn: true, trophyShowcase: false });
  });

  it('requires the current password to change it and revokes the old session', async () => {
    const user = await register('password_user');
    const rejected = await app.inject({
      method: 'POST', url: '/api/v1/me/password', headers: auth(user.token),
      payload: { currentPassword: 'wrong password', newPassword: 'a newer correct horse battery' },
    });
    expect(rejected.statusCode).toBe(401);

    const changed = await app.inject({
      method: 'POST', url: '/api/v1/me/password', headers: auth(user.token),
      payload: { currentPassword: 'correct horse battery', newPassword: 'a newer correct horse battery' },
    });
    expect(changed.statusCode).toBe(200);
    const oldSession = await app.inject({ method: 'GET', url: '/api/v1/me', headers: auth(user.token) });
    expect(oldSession.statusCode).toBe(401);
  });
});

describe('trophies and leaderboard', () => {
  it('awards trophies from server-owned facts, not client claims', async () => {
    const user = await register('trophy_user');
    const empty = await app.inject({ method: 'GET', url: '/api/v1/me/trophies', headers: auth(user.token) });
    expect(empty.statusCode).toBe(200);
    expect(empty.json().trophies.some((t: { key: string; earned: boolean }) => t.key === 'welcome_home' && t.earned)).toBe(true);

    const forge = await app.inject({
      method: 'POST', url: '/api/v1/me/trophies', headers: auth(user.token),
      payload: { key: 'six_worlds' },
    });
    expect(forge.statusCode).toBe(404);
  });

  it('shows only opted-in usernames and never financial or identity fields', async () => {
    const privateUser = await register('private_player');
    const publicUser = await register('public_player');
    await app.inject({
      method: 'PUT', url: '/api/v1/me/settings', headers: auth(publicUser.token),
      payload: { leaderboardOptIn: true, trophyShowcase: true },
    });

    const board = await app.inject({ method: 'GET', url: '/api/v1/leaderboard' });
    expect(board.statusCode).toBe(200);
    const serialized = JSON.stringify(board.json());
    expect(serialized).toContain('public_player');
    expect(serialized).not.toContain('private_player');
    expect(serialized).not.toContain(privateUser.user.id);
    expect(serialized).not.toContain('@paws.money');
    expect(serialized).not.toMatch(/balance|amountMinor|rewardMinor/i);
  });
});

describe('response hardening', () => {
  it('sets browser security headers on API responses', async () => {
    const response = await app.inject({ method: 'GET', url: '/api/v1/leaderboard' });
    expect(response.headers['content-security-policy']).toContain("default-src 'none'");
    expect(response.headers['x-content-type-options']).toBe('nosniff');
    expect(response.headers['x-frame-options']).toBe('DENY');
    expect(response.headers['referrer-policy']).toBe('no-referrer');
  });
});
