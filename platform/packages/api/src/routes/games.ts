/**
 * Server-authoritative Paw Match.
 *
 * Threats: protects the hidden board, game outcome, PAWS reward, and ledger
 * settlement from forged browser state, cross-user access, concurrent moves,
 * and replayed requests. It does NOT prevent general browser automation; a
 * strict daily reward cap bounds that exposure. There is no USD/crypto wager.
 */
import { randomInt } from 'node:crypto';
import type { FastifyInstance } from 'fastify';
import { and, eq } from 'drizzle-orm';
import {
  type Db,
  gameActions,
  gameSessions,
  users,
  type GameSession,
} from '@paws/db';
import { ensureSystemAccount, ensureUserAccount, transfer } from '@paws/ledger';
import { z } from 'zod';
import { reserveGameReward } from '../game-rewards.js';

const SYMBOLS = ['🐕', '🐈', '🐦', '🐰', '🐴', '🐾'] as const;
const BOARD_SIZE = SYMBOLS.length * 2;
const MAX_REWARD = 25n;
const MIN_REWARD = 5n;

const gameParams = z.object({ id: z.string().uuid() }).strict();
const flipBody = z
  .object({
    position: z.number().int().min(0).max(BOARD_SIZE - 1),
    actionId: z.string().uuid(),
  })
  .strict();

type GameRow = GameSession;

interface PublicCard {
  position: number;
  matched: boolean;
  symbol?: string;
}

interface PublicGame {
  id: string;
  status: GameRow['status'];
  moves: number;
  matchedPairs: number;
  firstPosition: number | null;
  rewardMinor: string;
  cards: PublicCard[];
  createdAt: string;
  completedAt: string | null;
}

interface GameResponse {
  game: PublicGame;
  outcome?: 'first_pick' | 'match' | 'miss' | 'completed';
  dailyRewardsRemaining?: number;
  dailyRewardMinorRemaining?: string;
}

function shuffledBoard(): string[] {
  const board = [...SYMBOLS, ...SYMBOLS];
  for (let i = board.length - 1; i > 0; i -= 1) {
    const j = randomInt(i + 1);
    [board[i], board[j]] = [board[j]!, board[i]!];
  }
  return board;
}

function validatedBoard(value: unknown): string[] {
  if (
    !Array.isArray(value) ||
    value.length !== BOARD_SIZE ||
    value.some((symbol) => typeof symbol !== 'string' || !SYMBOLS.includes(symbol as typeof SYMBOLS[number]))
  ) {
    throw new Error('invalid persisted Paw Match board');
  }
  return value as string[];
}

function validatedPositions(value: unknown): number[] {
  if (
    !Array.isArray(value) ||
    value.some(
      (position) =>
        !Number.isInteger(position) || position < 0 || position >= BOARD_SIZE,
    )
  ) {
    throw new Error('invalid persisted Paw Match positions');
  }
  return [...new Set(value as number[])].sort((a, b) => a - b);
}

function publicGame(row: GameRow, additionallyRevealed: number[] = []): PublicGame {
  const board = validatedBoard(row.board);
  const matched = new Set(validatedPositions(row.matchedPositions));
  const visible = new Set([...matched, ...additionallyRevealed]);
  if (row.firstPosition !== null) visible.add(row.firstPosition);

  return {
    id: row.id,
    status: row.status,
    moves: row.moves,
    matchedPairs: matched.size / 2,
    firstPosition: row.firstPosition,
    rewardMinor: row.rewardMinor.toString(),
    cards: board.map((symbol, position) => {
      const card: PublicCard = { position, matched: matched.has(position) };
      if (visible.has(position)) card.symbol = symbol;
      return card;
    }),
    createdAt: row.createdAt.toISOString(),
    completedAt: row.completedAt?.toISOString() ?? null,
  };
}

function rewardForMoves(moves: number): bigint {
  const penalty = BigInt(Math.max(0, moves - SYMBOLS.length) * 2);
  const reward = MAX_REWARD - penalty;
  return reward < MIN_REWARD ? MIN_REWARD : reward;
}

export function registerGameRoutes(app: FastifyInstance, db: Db): void {
  app.post(
    '/games/paw-match',
    { config: { rateLimit: { max: 10, timeWindow: '1 minute' } } },
    async (request, reply) => {
      const userId = request.user!.id;
      const result = await db.transaction(async (tx) => {
        // Serialize start/completion operations for this user. This also makes
        // the daily reward cap race-safe.
        await tx
          .select({ id: users.id })
          .from(users)
          .where(eq(users.id, userId))
          .for('update');

        const active = await tx
          .select()
          .from(gameSessions)
          .where(and(eq(gameSessions.userId, userId), eq(gameSessions.status, 'active')));
        if (active[0]) return { created: false, row: active[0] };

        const inserted = await tx
          .insert(gameSessions)
          .values({ userId, board: shuffledBoard() })
          .returning();
        return { created: true, row: inserted[0]! };
      });

      return reply.code(result.created ? 201 : 200).send({ game: publicGame(result.row) });
    },
  );

  app.get('/games/paw-match/:id', async (request, reply) => {
    const parsed = gameParams.safeParse(request.params);
    if (!parsed.success) return reply.code(400).send({ error: 'invalid_request' });

    const rows = await db
      .select()
      .from(gameSessions)
      .where(
        and(
          eq(gameSessions.id, parsed.data.id),
          eq(gameSessions.userId, request.user!.id),
        ),
      );
    if (!rows[0]) return reply.code(404).send({ error: 'not_found' });
    return reply.send({ game: publicGame(rows[0]) });
  });

  app.post(
    '/games/paw-match/:id/flip',
    { config: { rateLimit: { max: 120, timeWindow: '1 minute' } } },
    async (request, reply) => {
      const params = gameParams.safeParse(request.params);
      const body = flipBody.safeParse(request.body);
      if (!params.success || !body.success) {
        return reply.code(400).send({ error: 'invalid_request' });
      }

      const userId = request.user!.id;
      const result = await db.transaction(async (tx) => {
        // Keep lock ordering consistent with game creation and settlement.
        await tx
          .select({ id: users.id })
          .from(users)
          .where(eq(users.id, userId))
          .for('update');

        const sessions = await tx
          .select()
          .from(gameSessions)
          .where(
            and(eq(gameSessions.id, params.data.id), eq(gameSessions.userId, userId)),
          )
          .for('update');
        const session = sessions[0];
        if (!session) return { status: 404 as const, body: { error: 'not_found' } };

        const priorActions = await tx
          .select()
          .from(gameActions)
          .where(
            and(
              eq(gameActions.userId, userId),
              eq(gameActions.idempotencyKey, body.data.actionId),
            ),
          );
        const prior = priorActions[0];
        if (prior) {
          if (prior.sessionId !== session.id) {
            return { status: 409 as const, body: { error: 'idempotency_conflict' } };
          }
          return { status: 200 as const, body: prior.response };
        }

        if (session.status !== 'active') {
          return { status: 409 as const, body: { error: 'game_completed' } };
        }

        const board = validatedBoard(session.board);
        const matched = validatedPositions(session.matchedPositions);
        const position = body.data.position;
        if (matched.includes(position) || session.firstPosition === position) {
          return { status: 409 as const, body: { error: 'invalid_move' } };
        }

        let updated: GameRow;
        let response: GameResponse;

        if (session.firstPosition === null) {
          const rows = await tx
            .update(gameSessions)
            .set({ firstPosition: position, updatedAt: new Date() })
            .where(eq(gameSessions.id, session.id))
            .returning();
          updated = rows[0]!;
          response = { game: publicGame(updated, [position]), outcome: 'first_pick' };
        } else {
          const first = session.firstPosition;
          const isMatch = board[first] === board[position];
          const nextMatched = isMatch ? [...matched, first, position].sort((a, b) => a - b) : matched;
          const moves = session.moves + 1;
          const completed = nextMatched.length === BOARD_SIZE;
          let rewardMinor = 0n;
          let dailyRewardsRemaining: number | undefined;
          let dailyRewardMinorRemaining: bigint | undefined;

          if (completed) {
            const reserved = await reserveGameReward(
              tx as unknown as Db,
              userId,
              rewardForMoves(moves),
            );
            rewardMinor = reserved.awardedMinor;
            dailyRewardsRemaining = Number(reserved.remainingMinor / MAX_REWARD);
            dailyRewardMinorRemaining = reserved.remainingMinor;
          }

          const now = new Date();
          const rows = await tx
            .update(gameSessions)
            .set({
              firstPosition: null,
              matchedPositions: nextMatched,
              moves,
              status: completed ? 'completed' : 'active',
              rewardMinor,
              updatedAt: now,
              completedAt: completed ? now : null,
            })
            .where(eq(gameSessions.id, session.id))
            .returning();
          updated = rows[0]!;

          if (rewardMinor > 0n) {
            const txDb = tx as unknown as Db;
            const treasury = await ensureSystemAccount(txDb, 'game_rewards', 'PAWS');
            const player = await ensureUserAccount(txDb, userId, 'PAWS');
            await transfer(txDb, {
              idempotencyKey: `paw_match_reward:${session.id}`,
              kind: 'paw_match_reward',
              fromAccountId: treasury,
              toAccountId: player,
              amountMinor: rewardMinor,
              metadata: { gameSessionId: session.id, moves },
            });
          }

          response = {
            game: publicGame(updated, [first, position]),
            outcome: completed ? 'completed' : isMatch ? 'match' : 'miss',
          };
          if (dailyRewardsRemaining !== undefined) {
            response.dailyRewardsRemaining = dailyRewardsRemaining;
          }
          if (dailyRewardMinorRemaining !== undefined) {
            response.dailyRewardMinorRemaining = dailyRewardMinorRemaining.toString();
          }
        }

        await tx.insert(gameActions).values({
          sessionId: session.id,
          userId,
          idempotencyKey: body.data.actionId,
          response: response as unknown as Record<string, unknown>,
        });
        return { status: 200 as const, body: response };
      });

      return reply.code(result.status).send(result.body);
    },
  );
}
