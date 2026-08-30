import { randomBytes, randomUUID } from 'node:crypto';
import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { sql } from 'drizzle-orm';
import type { FastifyInstance } from 'fastify';
import { createDb, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import { buildApp } from './app.js';
import { dailyPantryPuzzle, solvePantryPuzzle } from './pantry-engine.js';

const TEST_URL =
  process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_pantry';

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
    TRUNCATE pantry_actions, pantry_sessions, pantry_puzzles, trail_actions,
             trail_sessions, pet_game_progress, daily_game_rewards, game_actions,
             game_sessions, ledger_entries, ledger_transactions, ledger_accounts,
             inventory, deposits, withdrawal_requests, sessions, user_2fa, pets,
             store_items, users CASCADE
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

async function createPet(token: string, name = 'Nori') {
  const response = await app.inject({
    method: 'POST',
    url: '/api/v1/pets',
    headers: auth(token),
    payload: { name, species: 'cat' },
  });
  expect(response.statusCode).toBe(201);
  return response.json().pet as { id: string };
}

async function start(token: string, petId: string, mode: 'daily' | 'practice' = 'daily') {
  return app.inject({
    method: 'POST',
    url: '/api/v1/games/midnight-pantry',
    headers: auth(token),
    payload: { petId, mode, actionId: randomUUID() },
  });
}

async function act(token: string, sessionId: string, action: Record<string, unknown>, actionId = randomUUID()) {
  return app.inject({
    method: 'POST',
    url: `/api/v1/games/midnight-pantry/${sessionId}/actions`,
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
  const balance = response.json().balances.find((entry: { currency: string }) => entry.currency === 'PAWS');
  return BigInt(balance.amountMinor);
}

async function solveSession(token: string, sessionId: string, solution: Record<string, string[]>) {
  let finalResponse;
  for (const [guestId, ingredients] of Object.entries(solution)) {
    for (const [slot, ingredientId] of ingredients.entries()) {
      const placed = await act(token, sessionId, { action: 'place', guestId, slot, ingredientId });
      expect(placed.statusCode).toBe(200);
    }
    finalResponse = await act(token, sessionId, { action: 'serve', guestId });
    expect(finalResponse.statusCode).toBe(200);
  }
  return finalResponse!;
}

describe('Midnight Pantry', () => {
  it('publishes a puzzle with exactly one exhaustively verified solution', () => {
    const puzzle = dailyPantryPuzzle('2026-08-30');
    const solutions = solvePantryPuzzle(puzzle.publicDefinition);
    expect(solutions).toHaveLength(1);
    expect(solutions[0]).toEqual(puzzle.privateSolution);
  });

  it('requires authentication and never exposes the private solution', async () => {
    const anonymous = await app.inject({ method: 'POST', url: '/api/v1/games/midnight-pantry' });
    expect(anonymous.statusCode).toBe(401);

    const user = await register('pantry@paws.money', 'pantryplayer');
    const pet = await createPet(user.token);
    const response = await start(user.token, pet.id);

    expect(response.statusCode).toBe(201);
    expect(response.json().game.guests).toHaveLength(3);
    expect(response.json().game.ingredients).toHaveLength(6);
    expect(JSON.stringify(response.json())).not.toContain('privateSolution');
    expect(JSON.stringify(response.json())).not.toContain('moonberry,star-biscuit');
  });

  it('rejects another user pet and all browser-supplied result or economic fields', async () => {
    const alice = await register('pantry-alice@paws.money', 'pantryalice');
    const bob = await register('pantry-bob@paws.money', 'pantrybob');
    const pet = await createPet(alice.token);
    expect((await start(bob.token, pet.id)).statusCode).toBe(404);

    const started = await start(alice.token, pet.id);
    const response = await app.inject({
      method: 'POST',
      url: `/api/v1/games/midnight-pantry/${started.json().game.id}/actions`,
      headers: auth(alice.token),
      payload: {
        action: 'serve',
        guestId: 'moth',
        actionId: randomUUID(),
        correct: true,
        completed: true,
        stars: 3,
        rewardMinor: '999999',
        currency: 'USD',
      },
    });
    expect(response.statusCode).toBe(400);
    expect(await pawsBalance(alice.token)).toBe(0n);
  });

  it('makes actions exactly idempotent and scopes sessions to their owner', async () => {
    const alice = await register('pantry-owner@paws.money', 'pantryowner');
    const bob = await register('pantry-thief@paws.money', 'pantrythief');
    const pet = await createPet(alice.token);
    const started = await start(alice.token, pet.id);
    const sessionId = started.json().game.id as string;
    const actionId = randomUUID();

    const first = await act(alice.token, sessionId, {
      action: 'place', guestId: 'moth', slot: 0, ingredientId: 'moonberry',
    }, actionId);
    const replay = await act(alice.token, sessionId, {
      action: 'place', guestId: 'moth', slot: 0, ingredientId: 'moonberry',
    }, actionId);
    expect(first.statusCode).toBe(200);
    expect(replay.json()).toEqual(first.json());
    expect(first.json().game.placements.moth).toEqual(['moonberry', null]);

    const stolen = await act(bob.token, sessionId, { action: 'remove', guestId: 'moth', slot: 0 });
    expect(stolen.statusCode).toBe(404);
  });

  it('derives three stars, journal credit, bond XP, and one PAWS posting from server state', async () => {
    const user = await register('pantry-winner@paws.money', 'pantrywinner');
    const pet = await createPet(user.token, 'Saffron');
    const started = await start(user.token, pet.id);
    const sessionId = started.json().game.id as string;
    const privateRows = await handle.db.execute(sql`
      SELECT pp.private_solution
      FROM pantry_sessions ps JOIN pantry_puzzles pp ON pp.id = ps.puzzle_id
      WHERE ps.id = ${sessionId}::uuid
    `);
    const solution = privateRows.rows[0]!.private_solution as Record<string, string[]>;
    const completed = await solveSession(user.token, sessionId, solution);

    expect(completed.json().game).toMatchObject({
      status: 'completed', stars: 3, rewardMinor: '10', mistakes: 0, bellRings: 3,
    });
    expect(completed.json().journalCredited).toBe(true);
    expect(await pawsBalance(user.token)).toBe(10n);

    const progress = await handle.db.execute(sql`
      SELECT bond_xp FROM pet_game_progress
      WHERE pet_id = ${pet.id}::uuid AND game_type = 'midnight_pantry'
    `);
    expect(progress.rows[0]!.bond_xp).toBe(20);
    const postings = await handle.db.execute(sql`
      SELECT count(*)::int AS count FROM ledger_transactions WHERE kind = 'midnight_pantry_reward'
    `);
    expect(postings.rows[0]!.count).toBe(1);
  });

  it('charges a bell ring for a wrong bowl but does not leak the answer', async () => {
    const user = await register('pantry-mistake@paws.money', 'pantrymistake');
    const pet = await createPet(user.token);
    const started = await start(user.token, pet.id);
    const sessionId = started.json().game.id as string;
    await act(user.token, sessionId, { action: 'place', guestId: 'moth', slot: 0, ingredientId: 'moonberry' });
    await act(user.token, sessionId, { action: 'place', guestId: 'moth', slot: 1, ingredientId: 'cloud-oats' });
    const wrong = await act(user.token, sessionId, { action: 'serve', guestId: 'moth' });
    expect(wrong.statusCode).toBe(200);
    expect(wrong.json()).toMatchObject({ outcome: 'incorrect', game: { mistakes: 1, bellRings: 1 } });
    expect(wrong.json().message).not.toContain('Star Biscuit');
    expect(wrong.json()).not.toHaveProperty('correctIngredients');
    expect(wrong.json()).not.toHaveProperty('solution');
  });

  it('completes for fun but awards nothing after the shared PAWS cap', async () => {
    const user = await register('pantry-capped@paws.money', 'pantrycapped');
    const pet = await createPet(user.token);
    await handle.db.execute(sql`
      INSERT INTO daily_game_rewards (user_id, reward_date, awarded_minor, rewarded_completions)
      VALUES (${user.userId}::uuid, (now() AT TIME ZONE 'UTC')::date, 125, 8)
    `);
    const started = await start(user.token, pet.id);
    const sessionId = started.json().game.id as string;
    const privateRows = await handle.db.execute(sql`
      SELECT pp.private_solution
      FROM pantry_sessions ps JOIN pantry_puzzles pp ON pp.id = ps.puzzle_id
      WHERE ps.id = ${sessionId}::uuid
    `);
    const completed = await solveSession(
      user.token,
      sessionId,
      privateRows.rows[0]!.private_solution as Record<string, string[]>,
    );
    expect(completed.json().game).toMatchObject({ status: 'completed', stars: 3, rewardMinor: '0' });
    expect(completed.json().dailyRewardMinorRemaining).toBe('0');
    expect(await pawsBalance(user.token)).toBe(0n);
  });
});
