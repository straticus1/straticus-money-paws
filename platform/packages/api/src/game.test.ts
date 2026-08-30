import { randomBytes, randomUUID } from 'node:crypto';
import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { sql } from 'drizzle-orm';
import type { FastifyInstance } from 'fastify';
import { createDb, dailyGameRewards, gameSessions, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import { buildApp } from './app.js';

const TEST_URL =
  process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_game';

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
    TRUNCATE ledger_entries, ledger_transactions, ledger_accounts,
             inventory, deposits, withdrawal_requests, sessions, user_2fa,
             pets, store_items, users CASCADE
  `);
});

async function register(email: string, username: string): Promise<{ token: string; userId: string }> {
  const response = await app.inject({
    method: 'POST',
    url: '/api/v1/auth/register',
    payload: { email, username, password: 'correct horse battery' },
  });
  expect(response.statusCode).toBe(201);
  return { token: response.json().token, userId: response.json().user.id };
}

function auth(token: string): Record<string, string> {
  return { authorization: `Bearer ${token}` };
}

async function start(token: string) {
  return app.inject({
    method: 'POST',
    url: '/api/v1/games/paw-match',
    headers: auth(token),
  });
}

async function flip(
  token: string,
  gameId: string,
  position: number,
  actionId: string = randomUUID(),
) {
  return app.inject({
    method: 'POST',
    url: `/api/v1/games/paw-match/${gameId}/flip`,
    headers: auth(token),
    payload: { position, actionId },
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

describe('Paw Match security boundary', () => {
  it('requires authentication and never sends the hidden board to the browser', async () => {
    const anonymous = await app.inject({ method: 'POST', url: '/api/v1/games/paw-match' });
    expect(anonymous.statusCode).toBe(401);

    const user = await register('player@paws.money', 'player');
    const response = await start(user.token);
    expect(response.statusCode).toBe(201);
    expect(response.json().game.cards).toHaveLength(12);
    expect(response.json().game.cards.every((card: { symbol?: string }) => card.symbol === undefined))
      .toBe(true);
  });

  it('rejects browser-supplied outcomes, rewards, and currencies', async () => {
    const user = await register('forger@paws.money', 'forger');
    const started = await start(user.token);
    const gameId = started.json().game.id as string;

    const response = await app.inject({
      method: 'POST',
      url: `/api/v1/games/paw-match/${gameId}/flip`,
      headers: auth(user.token),
      payload: {
        position: 0,
        actionId: randomUUID(),
        won: true,
        rewardMinor: '999999999',
        currency: 'USD',
      },
    });

    expect(response.statusCode).toBe(400);
    expect(await pawsBalance(user.token)).toBe(0n);
  });

  it('enforces ownership and makes repeated actions idempotent', async () => {
    const alice = await register('alice-game@paws.money', 'alicegame');
    const bob = await register('bob-game@paws.money', 'bobgame');
    const started = await start(alice.token);
    const gameId = started.json().game.id as string;

    const stolen = await flip(bob.token, gameId, 0);
    expect(stolen.statusCode).toBe(404);

    const actionId = randomUUID();
    const first = await flip(alice.token, gameId, 0, actionId);
    const replay = await flip(alice.token, gameId, 0, actionId);
    expect(first.statusCode).toBe(200);
    expect(replay.statusCode).toBe(200);
    expect(replay.json()).toEqual(first.json());

    const duplicateCard = await flip(alice.token, gameId, 0);
    expect(duplicateCard.statusCode).toBe(409);
  });

  it('settles a completed board exactly once through the PAWS ledger', async () => {
    const user = await register('winner@paws.money', 'winner');
    const started = await start(user.token);
    const gameId = started.json().game.id as string;

    // Test code may inspect the DB; the HTTP response above must not expose it.
    const boardRows = await handle.db.execute(sql`
      SELECT board FROM game_sessions WHERE id = ${gameId}::uuid
    `);
    const board = boardRows.rows[0]!.board as string[];
    const positions = new Map<string, number[]>();
    board.forEach((symbol, position) => {
      positions.set(symbol, [...(positions.get(symbol) ?? []), position]);
    });

    let finalActionId = '';
    let finalResponse;
    for (const pair of positions.values()) {
      await flip(user.token, gameId, pair[0]!);
      finalActionId = randomUUID();
      finalResponse = await flip(user.token, gameId, pair[1]!, finalActionId);
    }

    expect(finalResponse!.statusCode).toBe(200);
    expect(finalResponse!.json().game).toMatchObject({
      status: 'completed',
      moves: 6,
      rewardMinor: '25',
    });
    expect(await pawsBalance(user.token)).toBe(25n);

    const firstPair = [...positions.values()][0]!;
    const replay = await flip(user.token, gameId, firstPair[1]!, finalActionId);
    expect(replay.json()).toEqual(finalResponse!.json());
    expect(await pawsBalance(user.token)).toBe(25n);

    const postings = await handle.db.execute(sql`
      SELECT count(*)::int AS count
      FROM ledger_transactions
      WHERE kind = 'paw_match_reward'
    `);
    expect(postings.rows[0]!.count).toBe(1);
  });

  it('allows play but awards nothing after the daily reward cap', async () => {
    const user = await register('capped@paws.money', 'capped');
    const completedAt = new Date();
    const completedBoard = ['🐕', '🐕', '🐈', '🐈', '🐦', '🐦', '🐰', '🐰', '🐴', '🐴', '🐾', '🐾'];
    for (let i = 0; i < 5; i += 1) {
      await handle.db.insert(gameSessions).values({
        userId: user.userId,
        board: completedBoard,
        matchedPositions: [...Array(12).keys()],
        status: 'completed',
        moves: 6,
        rewardMinor: 25n,
        completedAt,
      });
    }
    await handle.db.insert(dailyGameRewards).values({
      userId: user.userId,
      rewardDate: completedAt.toISOString().slice(0, 10),
      awardedMinor: 125n,
      rewardedCompletions: 5,
    });

    const started = await start(user.token);
    const gameId = started.json().game.id as string;
    const boardRows = await handle.db.execute(sql`
      SELECT board FROM game_sessions WHERE id = ${gameId}::uuid
    `);
    const board = boardRows.rows[0]!.board as string[];
    const positions = new Map<string, number[]>();
    board.forEach((symbol, position) => {
      positions.set(symbol, [...(positions.get(symbol) ?? []), position]);
    });

    let finalResponse;
    for (const pair of positions.values()) {
      await flip(user.token, gameId, pair[0]!);
      finalResponse = await flip(user.token, gameId, pair[1]!);
    }

    expect(finalResponse!.json().game).toMatchObject({
      status: 'completed',
      rewardMinor: '0',
    });
    expect(finalResponse!.json().dailyRewardsRemaining).toBe(0);
    expect(await pawsBalance(user.token)).toBe(0n);
  });
});
