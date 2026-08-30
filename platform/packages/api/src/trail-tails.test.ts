import { randomBytes, randomUUID } from 'node:crypto';
import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { sql } from 'drizzle-orm';
import type { FastifyInstance } from 'fastify';
import { createDb, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import { buildApp } from './app.js';
import { generateTrail, trailSupportsThreeStars } from './routes/trail-tails.js';

const TEST_URL =
  process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_trail';

let handle: DbHandle;
let app: FastifyInstance;

beforeAll(async () => {
  process.env['AUTH_SECRET'] = randomBytes(32).toString('base64');
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
    TRUNCATE trail_actions, trail_sessions, pet_game_progress, daily_game_rewards,
             game_actions, game_sessions, ledger_entries, ledger_transactions,
             ledger_accounts, inventory, deposits, withdrawal_requests, sessions,
             user_2fa, pets, store_items, users CASCADE
  `);
});

async function register(email: string, username: string) {
  const response = await app.inject({
    method: 'POST',
    url: '/api/v1/auth/register',
    payload: { email, username, password: 'correct horse battery' },
  });
  expect(response.statusCode).toBe(201);
  return { token: response.json().token as string, userId: response.json().user.id as string };
}

function auth(token: string): Record<string, string> {
  return { authorization: `Bearer ${token}` };
}

async function createPet(token: string, name = 'Miso') {
  const response = await app.inject({
    method: 'POST',
    url: '/api/v1/pets',
    headers: auth(token),
    payload: { name, species: 'cat' },
  });
  expect(response.statusCode).toBe(201);
  return response.json().pet as { id: string };
}

async function start(token: string, petId: string, actionId = randomUUID()) {
  return app.inject({
    method: 'POST',
    url: '/api/v1/games/trail-tails',
    headers: auth(token),
    payload: { petId, actionId },
  });
}

async function act(
  token: string,
  sessionId: string,
  action: Record<string, unknown>,
  actionId = randomUUID(),
) {
  return app.inject({
    method: 'POST',
    url: `/api/v1/games/trail-tails/${sessionId}/actions`,
    headers: auth(token),
    payload: { ...action, actionId },
  });
}

async function pawsBalance(token: string): Promise<bigint> {
  const response = await app.inject({
    method: 'GET',
    url: '/api/v1/me/balances',
    headers: auth(token),
  });
  const balance = response
    .json()
    .balances.find((entry: { currency: string }) => entry.currency === 'PAWS');
  return BigInt(balance.amountMinor);
}

describe('Trail Tails security boundary', () => {
  it('generates trails with a server-proven three-star route', () => {
    for (let index = 0; index < 100; index += 1) {
      const trail = generateTrail();
      expect(
        trailSupportsThreeStars(
          trail.privateMap,
          trail.keepsakePosition,
          trail.rescuePosition,
        ),
      ).toBe(true);
    }
  });

  it('requires authentication and exposes only discovered trail tiles', async () => {
    const anonymous = await app.inject({ method: 'POST', url: '/api/v1/games/trail-tails' });
    expect(anonymous.statusCode).toBe(401);

    const user = await register('trail@paws.money', 'trailplayer');
    const pet = await createPet(user.token);
    const response = await start(user.token, pet.id);

    expect(response.statusCode).toBe(201);
    expect(response.json().game.tiles).toHaveLength(25);
    expect(
      response.json().game.tiles.some(
        (tile: { discovered: boolean; terrain?: string }) => !tile.discovered && tile.terrain !== undefined,
      ),
    ).toBe(false);
    expect(JSON.stringify(response.json())).not.toContain('keepsakePosition');
    expect(JSON.stringify(response.json())).not.toContain('rescuePosition');
  });

  it('rejects another user pet and browser-supplied economic or outcome state', async () => {
    const alice = await register('trail-alice@paws.money', 'trailalice');
    const bob = await register('trail-bob@paws.money', 'trailbob');
    const pet = await createPet(alice.token);

    expect((await start(bob.token, pet.id)).statusCode).toBe(404);

    const started = await start(alice.token, pet.id);
    const response = await app.inject({
      method: 'POST',
      url: `/api/v1/games/trail-tails/${started.json().game.id}/actions`,
      headers: auth(alice.token),
      payload: {
        action: 'move',
        direction: 'north',
        actionId: randomUUID(),
        stars: 3,
        completed: true,
        rewardMinor: '999999999',
        currency: 'USD',
        energy: 99,
      },
    });

    expect(response.statusCode).toBe(400);
    expect(await pawsBalance(alice.token)).toBe(0n);
  });

  it('applies a repeated action exactly once and scopes sessions to their owner', async () => {
    const alice = await register('trail-owner@paws.money', 'trailowner');
    const bob = await register('trail-thief@paws.money', 'trailthief');
    const pet = await createPet(alice.token);
    const started = await start(alice.token, pet.id);
    const sessionId = started.json().game.id as string;
    const actionId = randomUUID();

    const first = await act(alice.token, sessionId, { action: 'sniff' }, actionId);
    const replay = await act(alice.token, sessionId, { action: 'sniff' }, actionId);
    expect(first.statusCode).toBe(200);
    expect(replay.json()).toEqual(first.json());
    expect(first.json().game.abilities.sniff).toBe(1);

    const stolen = await act(bob.token, sessionId, { action: 'sniff' });
    expect(stolen.statusCode).toBe(404);
  });

  it('derives three stars, bond XP, and one ledger reward from server state', async () => {
    const user = await register('trail-winner@paws.money', 'trailwinner');
    const pet = await createPet(user.token, 'Juniper');
    const started = await start(user.token, pet.id);
    const sessionId = started.json().game.id as string;

    // A deterministic all-meadow route makes the economic assertions independent
    // of random generation. Only tests inspect or change private persisted state.
    const map = Array.from({ length: 25 }, (_, position) => ({
      position,
      terrain: 'meadow',
    }));
    await handle.db.execute(sql`
      UPDATE trail_sessions
      SET private_map = ${JSON.stringify(map)}::jsonb,
          position = 20,
          start_position = 20,
          home_position = 24,
          keepsake_position = 12,
          rescue_position = 14,
          discovered_positions = '[20,21,22,23,24]'::jsonb,
          energy = 12
      WHERE id = ${sessionId}::uuid
    `);

    for (const direction of ['north', 'north', 'east', 'east', 'east', 'east', 'south', 'south']) {
      const response = await act(user.token, sessionId, { action: 'move', direction });
      expect(response.statusCode).toBe(200);
    }

    const completed = await app.inject({
      method: 'GET',
      url: `/api/v1/games/trail-tails/${sessionId}`,
      headers: auth(user.token),
    });
    expect(completed.json().game).toMatchObject({
      status: 'completed',
      keepsakeFound: true,
      rescueFound: true,
      stars: 3,
      rewardMinor: '10',
    });
    expect(await pawsBalance(user.token)).toBe(10n);

    const progress = await handle.db.execute(sql`
      SELECT bond_xp FROM pet_game_progress WHERE pet_id = ${pet.id}::uuid
    `);
    expect(progress.rows[0]!.bond_xp).toBe(20);

    const postings = await handle.db.execute(sql`
      SELECT count(*)::int AS count FROM ledger_transactions
      WHERE kind = 'trail_tails_reward'
    `);
    expect(postings.rows[0]!.count).toBe(1);
  });

  it('remains playable but awards no PAWS after the shared daily cap', async () => {
    const user = await register('trail-capped@paws.money', 'trailcapped');
    const pet = await createPet(user.token);
    await handle.db.execute(sql`
      INSERT INTO daily_game_rewards (user_id, reward_date, awarded_minor, rewarded_completions)
      VALUES (${user.userId}::uuid, (now() AT TIME ZONE 'UTC')::date, 125, 5)
    `);
    const started = await start(user.token, pet.id);
    const sessionId = started.json().game.id as string;
    const map = Array.from({ length: 25 }, (_, position) => ({ position, terrain: 'meadow' }));
    await handle.db.execute(sql`
      UPDATE trail_sessions
      SET private_map = ${JSON.stringify(map)}::jsonb,
          position = 23,
          home_position = 24,
          keepsake_found = true,
          rescue_found = true,
          energy = 4
      WHERE id = ${sessionId}::uuid
    `);

    const response = await act(user.token, sessionId, { action: 'move', direction: 'east' });
    expect(response.statusCode).toBe(200);
    expect(response.json().game).toMatchObject({ status: 'completed', stars: 3, rewardMinor: '0' });
    expect(response.json().dailyRewardMinorRemaining).toBe('0');
    expect(await pawsBalance(user.token)).toBe(0n);
  });
});
