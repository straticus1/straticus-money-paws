import type { FastifyInstance } from 'fastify';
import { and, eq, ne } from 'drizzle-orm';
import {
  type Db, type Pet, type PostHistoryEntry, type PostPuzzle, type PostSession,
  pets, postActions, postPuzzles, postSessions, users,
} from '@paws/db';
import { z } from 'zod';
import { settleDailyGame } from '../game-rewards.js';
import { applyPostMove, dailyPostPuzzle, solvePostPuzzle, type PostPuzzleDefinition } from '../post-engine.js';

const startBody = z.object({ petId: z.string().uuid(), mode: z.enum(['daily', 'practice']), actionId: z.string().uuid() }).strict();
const paramsSchema = z.object({ id: z.string().uuid() }).strict();
const actionBody = z.discriminatedUnion('action', [
  z.object({ action: z.literal('move'), direction: z.enum(['north', 'east', 'south', 'west']), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('undo'), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('reset'), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('abandon'), actionId: z.string().uuid() }).strict(),
]);

function utcDate(): string { return new Date().toISOString().slice(0, 10); }
function definitionFrom(value: unknown): PostPuzzleDefinition {
  const puzzle = value as PostPuzzleDefinition;
  if (!puzzle || puzzle.version !== 1 || puzzle.size !== 7 || !Array.isArray(puzzle.walls) ||
      !Array.isArray(puzzle.goals) || !Array.isArray(puzzle.initialBoxPositions) || puzzle.goals.length !== puzzle.initialBoxPositions.length) {
    throw new Error('invalid post puzzle definition');
  }
  return puzzle;
}
function positionsFrom(value: unknown): number[] {
  if (!Array.isArray(value) || value.some((position) => !Number.isInteger(position) || position < 0 || position >= 49)) throw new Error('invalid post positions');
  return value as number[];
}
function historyFrom(value: unknown): PostHistoryEntry[] {
  if (!Array.isArray(value) || value.length > 200) throw new Error('invalid post history');
  return value as PostHistoryEntry[];
}

async function ensurePuzzle(db: Db, mode: 'daily' | 'practice'): Promise<PostPuzzle> {
  const date = utcDate(); const key = mode === 'daily' ? `daily:${date}:v1` : 'practice:v1';
  const definition = dailyPostPuzzle(date); const proof = solvePostPuzzle(definition);
  if (!proof.solvable || proof.minimumPushes !== definition.minimumPushes) throw new Error('post puzzle verification failed');
  await db.insert(postPuzzles).values({
    puzzleKey: key, mode, definition: definition as unknown as Record<string, unknown>,
    minimumPushes: proof.minimumPushes, minimumMoves: proof.moves.length,
    verification: { solvable: true, minimumPushes: proof.minimumPushes, minimumMoves: proof.moves.length, verifiedAt: new Date().toISOString() },
  }).onConflictDoNothing({ target: postPuzzles.puzzleKey });
  const puzzle = (await db.select().from(postPuzzles).where(and(eq(postPuzzles.puzzleKey, key), eq(postPuzzles.active, true))))[0];
  if (!puzzle) throw new Error('verified post puzzle unavailable');
  definitionFrom(puzzle.definition);
  return puzzle;
}

function publicGame(session: PostSession, puzzleRow: PostPuzzle, pet: Pet) {
  const puzzle = definitionFrom(puzzleRow.definition); const boxes = positionsFrom(session.boxPositions);
  return {
    id: session.id, mode: session.mode, status: session.status, puzzleKey: puzzleRow.puzzleKey,
    pet: { id: pet.id, name: pet.name, species: pet.species }, size: puzzle.size,
    walls: puzzle.walls, goals: puzzle.goals, playerPosition: session.playerPosition, boxPositions: boxes,
    delivered: puzzle.goals.filter((goal) => boxes.includes(goal)).length, totalParcels: puzzle.goals.length,
    moves: session.moves, pushes: session.pushes, undos: session.undos, resets: session.resets,
    minimumPushes: puzzleRow.minimumPushes, canUndo: historyFrom(session.history).length > 0,
    stars: session.stars, rewardMinor: session.rewardMinor.toString(), journalCredited: session.journalCredited,
    createdAt: session.createdAt.toISOString(), completedAt: session.completedAt?.toISOString() ?? null,
  };
}

async function ownedPet(db: Db, userId: string, petId: string) {
  return (await db.select().from(pets).where(and(eq(pets.id, petId), eq(pets.userId, userId))))[0];
}

export function registerPocketPostRoutes(app: FastifyInstance, db: Db): void {
  app.post('/games/pocket-post', { config: { rateLimit: { max: 10, timeWindow: '1 minute' } } }, async (request, reply) => {
    const parsed = startBody.safeParse(request.body); if (!parsed.success) return reply.code(400).send({ error: 'invalid_request' });
    const userId = request.user!.id;
    const result = await db.transaction(async (tx) => {
      await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
      const prior = (await tx.select().from(postActions).where(and(eq(postActions.userId, userId), eq(postActions.idempotencyKey, parsed.data.actionId))))[0];
      if (prior) return { code: 200 as const, body: prior.response };
      const pet = await ownedPet(tx as unknown as Db, userId, parsed.data.petId);
      if (!pet) return { code: 404 as const, body: { error: 'not_found' } };
      if (!pet.alive) return { code: 409 as const, body: { error: 'pet_unavailable' } };
      const active = (await tx.select({ session: postSessions, puzzle: postPuzzles, pet: pets }).from(postSessions)
        .innerJoin(postPuzzles, eq(postSessions.puzzleId, postPuzzles.id)).innerJoin(pets, eq(postSessions.petId, pets.id))
        .where(and(eq(postSessions.userId, userId), eq(postSessions.status, 'active'))))[0];
      let session = active?.session; let puzzle = active?.puzzle; let host = active?.pet; let created = false;
      if (!session || !puzzle || !host) {
        puzzle = await ensurePuzzle(tx as unknown as Db, parsed.data.mode); const definition = definitionFrom(puzzle.definition);
        session = (await tx.insert(postSessions).values({ userId, petId: pet.id, puzzleId: puzzle.id, mode: parsed.data.mode, playerPosition: definition.initialPlayerPosition, boxPositions: definition.initialBoxPositions, history: [] }).returning())[0]!;
        host = pet; created = true;
      }
      const body = { game: publicGame(session, puzzle, host), outcome: 'started' };
      await tx.insert(postActions).values({ sessionId: session.id, userId, idempotencyKey: parsed.data.actionId, action: { action: 'start', petId: parsed.data.petId, mode: parsed.data.mode }, response: body });
      return { code: created ? 201 as const : 200 as const, body };
    });
    return reply.code(result.code).send(result.body);
  });

  app.get('/games/pocket-post/:id', async (request, reply) => {
    const params = paramsSchema.safeParse(request.params); if (!params.success) return reply.code(400).send({ error: 'invalid_request' });
    const row = (await db.select({ session: postSessions, puzzle: postPuzzles, pet: pets }).from(postSessions)
      .innerJoin(postPuzzles, eq(postSessions.puzzleId, postPuzzles.id)).innerJoin(pets, eq(postSessions.petId, pets.id))
      .where(and(eq(postSessions.id, params.data.id), eq(postSessions.userId, request.user!.id))))[0];
    return row ? reply.send({ game: publicGame(row.session, row.puzzle, row.pet) }) : reply.code(404).send({ error: 'not_found' });
  });

  app.post('/games/pocket-post/:id/actions', { config: { rateLimit: { max: 120, timeWindow: '1 minute' } } }, async (request, reply) => {
    const params = paramsSchema.safeParse(request.params); const parsed = actionBody.safeParse(request.body);
    if (!params.success || !parsed.success) return reply.code(400).send({ error: 'invalid_request' });
    const userId = request.user!.id;
    const result = await db.transaction(async (tx) => {
      await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
      const row = (await tx.select({ session: postSessions, puzzle: postPuzzles, pet: pets }).from(postSessions)
        .innerJoin(postPuzzles, eq(postSessions.puzzleId, postPuzzles.id)).innerJoin(pets, eq(postSessions.petId, pets.id))
        .where(and(eq(postSessions.id, params.data.id), eq(postSessions.userId, userId))).for('update'))[0];
      if (!row) return { code: 404 as const, body: { error: 'not_found' } };
      const prior = (await tx.select().from(postActions).where(and(eq(postActions.userId, userId), eq(postActions.idempotencyKey, parsed.data.actionId))))[0];
      if (prior) return prior.sessionId === row.session.id ? { code: 200 as const, body: prior.response } : { code: 409 as const, body: { error: 'idempotency_conflict' } };
      if (row.session.status !== 'active') return { code: 409 as const, body: { error: 'game_completed' } };
      const puzzle = definitionFrom(row.puzzle.definition); let playerPosition = row.session.playerPosition;
      let boxPositions = positionsFrom(row.session.boxPositions); let history = historyFrom(row.session.history);
      let moves = row.session.moves; let pushes = row.session.pushes; let undos = row.session.undos; let resets = row.session.resets;
      let status: PostSession['status'] = 'active'; let stars = 0; let rewardMinor = 0n; let journalCredited = false;
      let completedAt: Date | null = null; let outcome = 'moved'; let message = `${row.pet.name} crosses the sorting room.`; let remaining: bigint | undefined;
      if (parsed.data.action === 'abandon') { status = 'abandoned'; completedAt = new Date(); outcome = 'abandoned'; message = 'The post office is safely closed.'; }
      else if (parsed.data.action === 'reset') {
        playerPosition = puzzle.initialPlayerPosition; boxPositions = [...puzzle.initialBoxPositions]; history = [];
        moves = 0; pushes = 0; undos = 0; resets += 1; outcome = 'reset'; message = 'The sorting room returns to its opening layout.';
      } else if (parsed.data.action === 'undo') {
        const previous = history.pop();
        if (!previous) { outcome = 'blocked'; message = 'There is no earlier move to restore.'; }
        else { playerPosition = previous.playerPosition; boxPositions = positionsFrom(previous.boxPositions); moves = previous.moves; pushes = previous.pushes; undos += 1; outcome = 'undone'; message = 'One move is neatly taken back.'; }
      } else {
        const moved = applyPostMove(puzzle, playerPosition, boxPositions, parsed.data.direction);
        if (!moved.moved) { outcome = 'blocked'; message = 'That way is blocked.'; }
        else {
          history = [...history, { playerPosition, boxPositions: [...boxPositions], moves, pushes }].slice(-200);
          playerPosition = moved.playerPosition; boxPositions = moved.boxPositions; moves += 1;
          if (moved.pushed) { pushes += 1; outcome = 'pushed'; message = 'The parcel slides one square.'; }
          if (moved.completed) {
            status = 'completed'; completedAt = new Date(); stars = pushes <= row.puzzle.minimumPushes ? 3 : pushes <= row.puzzle.minimumPushes + 2 ? 2 : 1;
            outcome = 'completed';
            if (row.session.mode === 'daily') {
              const priorWins = await tx.select({ id: postSessions.id }).from(postSessions).where(and(eq(postSessions.userId, userId), eq(postSessions.puzzleId, row.puzzle.id), eq(postSessions.mode, 'daily'), eq(postSessions.status, 'completed'), ne(postSessions.id, row.session.id)));
              journalCredited = priorWins.length === 0;
              if (journalCredited) {
                const settled = await settleDailyGame(tx as unknown as Db, { userId, petId: row.session.petId, gameType: 'pocket_post', ledgerKind: 'pocket_post_reward', sessionId: row.session.id, stars, metadata: { puzzleKey: row.puzzle.puzzleKey, pushes, moves } });
                rewardMinor = settled.rewardMinor; remaining = settled.remainingMinor;
              }
            }
            message = rewardMinor > 0n ? `${row.pet.name} dispatches the morning mail.` : `${row.pet.name} dispatches the morning mail. This shift was for fun.`;
          }
        }
      }
      const updated = (await tx.update(postSessions).set({ playerPosition, boxPositions, history, moves, pushes, undos, resets, status, stars, rewardMinor, journalCredited, updatedAt: new Date(), completedAt }).where(eq(postSessions.id, row.session.id)).returning())[0]!;
      const body: Record<string, unknown> = { game: publicGame(updated, row.puzzle, row.pet), outcome, message };
      if (remaining !== undefined) body['dailyRewardMinorRemaining'] = remaining.toString();
      await tx.insert(postActions).values({ sessionId: row.session.id, userId, idempotencyKey: parsed.data.actionId, action: parsed.data, response: body });
      return { code: 200 as const, body };
    });
    return reply.code(result.code).send(result.body);
  });
}
