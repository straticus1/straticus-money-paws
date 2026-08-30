import { randomBytes, randomUUID } from 'node:crypto';
import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { sql } from 'drizzle-orm';
import type { FastifyInstance } from 'fastify';
import { createDb, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import { buildApp } from './app.js';
import { dailyLanternPuzzle, solveLanternPuzzle } from './lantern-engine.js';
import { dailyPostPuzzle, solvePostPuzzle } from './post-engine.js';
import { dailyParadePuzzle, solveParadePuzzle } from './parade-engine.js';

const TEST_URL = process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_new_games';
let handle: DbHandle;
let app: FastifyInstance;

beforeAll(async () => {
  process.env['AUTH_SECRET'] = randomBytes(32).toString('base64');
  await runMigrations(TEST_URL);
  handle = createDb(TEST_URL);
  app = buildApp({ db: handle.db });
  await app.ready();
});

afterAll(async () => { await app.close(); await handle.pool.end(); });

beforeEach(async () => {
  await handle.db.execute(sql`
    TRUNCATE parade_actions, parade_sessions, parade_puzzles,
      lantern_actions, lantern_sessions, lantern_puzzles,
      post_actions, post_sessions, post_puzzles, pantry_actions, pantry_sessions,
      pantry_puzzles, trail_actions, trail_sessions, pet_game_progress,
      daily_game_rewards, game_actions, game_sessions, ledger_entries,
      ledger_transactions, ledger_accounts, inventory, deposits,
      withdrawal_requests, sessions, user_2fa, pets, store_items, users CASCADE
  `);
});

function auth(token: string) { return { authorization: `Bearer ${token}` }; }

async function player(suffix: string, species = 'dog') {
  const registered = await app.inject({
    method: 'POST', url: '/api/v1/auth/register',
    payload: { email: `${suffix}@paws.money`, username: suffix.replaceAll('-', ''), password: 'correct horse battery' },
  });
  const token = registered.json().token as string;
  const pet = await app.inject({
    method: 'POST', url: '/api/v1/pets', headers: auth(token), payload: { name: 'Pip', species },
  });
  return { token, userId: registered.json().user.id as string, petId: pet.json().pet.id as string };
}

type NewGame = 'lantern-lines' | 'pocket-post' | 'parade-practice';

async function start(game: NewGame, token: string, petId: string, mode: 'daily' | 'practice' = 'daily') {
  return app.inject({ method: 'POST', url: `/api/v1/games/${game}`, headers: auth(token), payload: { petId, mode, actionId: randomUUID() } });
}

async function act(game: NewGame, token: string, id: string, action: Record<string, unknown>, actionId = randomUUID()) {
  return app.inject({ method: 'POST', url: `/api/v1/games/${game}/${id}/actions`, headers: auth(token), payload: { ...action, actionId } });
}

async function solveLantern(token: string, id: string) {
  const puzzle = dailyLanternPuzzle('test');
  const solution = solveLanternPuzzle(puzzle).solution;
  let current = [...puzzle.initialOrientations];
  for (const position of puzzle.rotatablePositions) {
    while (current[position] !== solution[position]) {
      const clockwise = (solution[position]! - current[position]! + 4) % 4;
      const direction = clockwise <= 2 ? 'clockwise' : 'counterclockwise';
      const response = await act('lantern-lines', token, id, { action: 'rotate', position, direction });
      expect(response.statusCode).toBe(200);
      current = response.json().game.orientations;
    }
  }
  return act('lantern-lines', token, id, { action: 'submit' });
}

async function solvePost(token: string, id: string) {
  const proof = solvePostPuzzle(dailyPostPuzzle('test'));
  let response;
  for (const direction of proof.moves) {
    response = await act('pocket-post', token, id, { action: 'move', direction });
    expect(response.statusCode).toBe(200);
  }
  return response!;
}

describe('Lantern Lines and Pocket Post API', () => {
  it('requires auth, validates owned pets, and rejects forged economic state', async () => {
    expect((await app.inject({ method: 'POST', url: '/api/v1/games/lantern-lines' })).statusCode).toBe(401);
    const alice = await player('new-alice');
    const bob = await player('new-bob');
    expect((await start('pocket-post', bob.token, alice.petId)).statusCode).toBe(404);

    const lantern = await start('lantern-lines', alice.token, alice.petId);
    const forged = await app.inject({
      method: 'POST', url: `/api/v1/games/lantern-lines/${lantern.json().game.id}/actions`, headers: auth(alice.token),
      payload: { action: 'submit', actionId: randomUUID(), stars: 3, rewardMinor: '999999', currency: 'USD', completed: true },
    });
    expect(forged.statusCode).toBe(400);
  });

  it('keeps rotations idempotent and scopes sessions to their owner', async () => {
    const alice = await player('lantern-owner');
    const bob = await player('lantern-other');
    const started = await start('lantern-lines', alice.token, alice.petId);
    const id = started.json().game.id as string;
    const actionId = randomUUID();
    const first = await act('lantern-lines', alice.token, id, { action: 'rotate', position: 2, direction: 'clockwise' }, actionId);
    const replay = await act('lantern-lines', alice.token, id, { action: 'rotate', position: 2, direction: 'clockwise' }, actionId);
    expect(replay.json()).toEqual(first.json());
    expect(first.json().game.rotations).toBe(1);
    expect((await act('lantern-lines', bob.token, id, { action: 'reset' })).statusCode).toBe(404);
  });

  it('completes Lantern Lines from server state and settles one reward', async () => {
    const user = await player('lantern-winner');
    const started = await start('lantern-lines', user.token, user.petId);
    const completed = await solveLantern(user.token, started.json().game.id);
    expect(completed.json().game).toMatchObject({ status: 'completed', stars: 3, rewardMinor: '10', journalCredited: true });
    const postings = await handle.db.execute(sql`SELECT count(*)::int count FROM ledger_transactions WHERE kind = 'lantern_lines_reward'`);
    expect(postings.rows[0]!.count).toBe(1);
  });

  it('does not count blocked mailroom moves and replays legal moves exactly', async () => {
    const user = await player('post-owner');
    const started = await start('pocket-post', user.token, user.petId, 'practice');
    const id = started.json().game.id as string;
    const first = await act('pocket-post', user.token, id, { action: 'move', direction: 'north' });
    const actionId = randomUUID();
    const blocked = await act('pocket-post', user.token, id, { action: 'move', direction: 'north' }, actionId);
    const replay = await act('pocket-post', user.token, id, { action: 'move', direction: 'north' }, actionId);
    expect(first.json().game.moves).toBe(1);
    expect(blocked.json().outcome).toBe('blocked');
    expect(blocked.json().game.moves).toBe(1);
    expect(replay.json()).toEqual(blocked.json());
  });

  it('completes Pocket Post at the verified push count and obeys the shared cap', async () => {
    const user = await player('post-winner');
    await handle.db.execute(sql`
      INSERT INTO daily_game_rewards (user_id, reward_date, awarded_minor, rewarded_completions)
      VALUES (${user.userId}::uuid, (now() AT TIME ZONE 'UTC')::date, 125, 9)
    `);
    const started = await start('pocket-post', user.token, user.petId);
    const completed = await solvePost(user.token, started.json().game.id);
    expect(completed.json().game).toMatchObject({ status: 'completed', pushes: 6, stars: 3, rewardMinor: '0', journalCredited: true });
  });

  it('bounds and idempotently simulates Parade Practice programs on the server', async () => {
    const user = await player('parade-leader', 'hedgehog');
    const started = await start('parade-practice', user.token, user.petId);
    const id = started.json().game.id as string;
    const commands = solveParadePuzzle(dailyParadePuzzle('test')).commands;
    const actionId = randomUUID();
    const completed = await act('parade-practice', user.token, id, { action: 'run', commands }, actionId);
    const replay = await act('parade-practice', user.token, id, { action: 'run', commands }, actionId);
    expect(completed.json().game).toMatchObject({ status: 'completed', stars: 3, rewardMinor: '10', runs: 1 });
    expect(replay.json()).toEqual(completed.json());

    const second = await player('parade-forger');
    const secondStart = await start('parade-practice', second.token, second.petId);
    const forged = await app.inject({
      method: 'POST', url: `/api/v1/games/parade-practice/${secondStart.json().game.id}/actions`, headers: auth(second.token),
      payload: { action: 'run', commands, actionId: randomUUID(), stars: 3, rewardMinor: '999999', currency: 'USD' },
    });
    expect(forged.statusCode).toBe(400);
  });
});
