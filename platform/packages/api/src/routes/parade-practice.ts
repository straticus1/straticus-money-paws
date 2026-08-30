import type { FastifyInstance } from 'fastify';
import { and, eq, ne } from 'drizzle-orm';
import {
  type Db, type ParadePuzzle, type ParadeSession, type Pet,
  paradeActions, paradePuzzles, paradeSessions, pets, users,
} from '@paws/db';
import { z } from 'zod';
import { settleDailyGame } from '../game-rewards.js';
import { dailyParadePuzzle, simulateParade, solveParadePuzzle, type ParadePuzzleDefinition } from '../parade-engine.js';

const startBody = z.object({ petId: z.string().uuid(), mode: z.enum(['daily', 'practice']), actionId: z.string().uuid() }).strict();
const paramsSchema = z.object({ id: z.string().uuid() }).strict();
const command = z.enum(['forward', 'turn-left', 'turn-right', 'hop']);
const actionBody = z.discriminatedUnion('action', [
  z.object({ action: z.literal('run'), commands: z.array(command).min(1).max(12), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('abandon'), actionId: z.string().uuid() }).strict(),
]);

function utcDate(): string { return new Date().toISOString().slice(0, 10); }
function definitionFrom(value: unknown): ParadePuzzleDefinition {
  const puzzle = value as ParadePuzzleDefinition;
  if (!puzzle || puzzle.version !== 1 || puzzle.size !== 6 || !Array.isArray(puzzle.pennantPositions) ||
      puzzle.pennantPositions.length !== 3 || !Array.isArray(puzzle.obstaclePositions) || puzzle.minimumCommands < 1 || puzzle.minimumCommands > 12) {
    throw new Error('invalid parade puzzle definition');
  }
  return puzzle;
}

async function ensurePuzzle(db: Db, mode: 'daily' | 'practice'): Promise<ParadePuzzle> {
  const date = utcDate(); const key = mode === 'daily' ? `daily:${date}:v1` : 'practice:v1';
  const definition = dailyParadePuzzle(date); const proof = solveParadePuzzle(definition);
  if (proof.commands.length !== definition.minimumCommands) throw new Error('parade puzzle verification failed');
  await db.insert(paradePuzzles).values({
    puzzleKey: key, mode, definition: definition as unknown as Record<string, unknown>, minimumCommands: proof.commands.length,
    verification: { solvable: true, minimumCommands: proof.commands.length, verifiedAt: new Date().toISOString() },
  }).onConflictDoNothing({ target: paradePuzzles.puzzleKey });
  const puzzle = (await db.select().from(paradePuzzles).where(and(eq(paradePuzzles.puzzleKey, key), eq(paradePuzzles.active, true))))[0];
  if (!puzzle) throw new Error('verified parade puzzle unavailable');
  definitionFrom(puzzle.definition); return puzzle;
}

function publicGame(session: ParadeSession, puzzleRow: ParadePuzzle, pet: Pet) {
  const puzzle = definitionFrom(puzzleRow.definition);
  return {
    id: session.id, mode: session.mode, status: session.status, puzzleKey: puzzleRow.puzzleKey,
    pet: { id: pet.id, name: pet.name, species: pet.species }, size: puzzle.size,
    startPosition: puzzle.startPosition, startFacing: puzzle.startFacing, bandstandPosition: puzzle.bandstandPosition,
    pennantPositions: puzzle.pennantPositions, obstaclePositions: puzzle.obstaclePositions,
    minimumCommands: puzzle.minimumCommands, maxCommands: 12, runs: session.runs,
    bestCommandCount: session.bestCommandCount, lastResult: session.lastResult,
    stars: session.stars, rewardMinor: session.rewardMinor.toString(), journalCredited: session.journalCredited,
    createdAt: session.createdAt.toISOString(), completedAt: session.completedAt?.toISOString() ?? null,
  };
}

async function ownedPet(db: Db, userId: string, petId: string) {
  return (await db.select().from(pets).where(and(eq(pets.id, petId), eq(pets.userId, userId))))[0];
}

export function registerParadePracticeRoutes(app: FastifyInstance, db: Db): void {
  app.post('/games/parade-practice', { config: { rateLimit: { max: 10, timeWindow: '1 minute' } } }, async (request, reply) => {
    const parsed = startBody.safeParse(request.body); if (!parsed.success) return reply.code(400).send({ error: 'invalid_request' });
    const userId = request.user!.id;
    const result = await db.transaction(async (tx) => {
      await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
      const prior = (await tx.select().from(paradeActions).where(and(eq(paradeActions.userId, userId), eq(paradeActions.idempotencyKey, parsed.data.actionId))))[0];
      if (prior) return { code: 200 as const, body: prior.response };
      const pet = await ownedPet(tx as unknown as Db, userId, parsed.data.petId);
      if (!pet) return { code: 404 as const, body: { error: 'not_found' } };
      if (!pet.alive) return { code: 409 as const, body: { error: 'pet_unavailable' } };
      const active = (await tx.select({ session: paradeSessions, puzzle: paradePuzzles, pet: pets }).from(paradeSessions)
        .innerJoin(paradePuzzles, eq(paradeSessions.puzzleId, paradePuzzles.id)).innerJoin(pets, eq(paradeSessions.petId, pets.id))
        .where(and(eq(paradeSessions.userId, userId), eq(paradeSessions.status, 'active'))))[0];
      let session = active?.session; let puzzle = active?.puzzle; let host = active?.pet; let created = false;
      if (!session || !puzzle || !host) {
        puzzle = await ensurePuzzle(tx as unknown as Db, parsed.data.mode);
        session = (await tx.insert(paradeSessions).values({ userId, petId: pet.id, puzzleId: puzzle.id, mode: parsed.data.mode }).returning())[0]!;
        host = pet; created = true;
      }
      const body = { game: publicGame(session, puzzle, host), outcome: 'started' };
      await tx.insert(paradeActions).values({ sessionId: session.id, userId, idempotencyKey: parsed.data.actionId, action: { action: 'start', petId: parsed.data.petId, mode: parsed.data.mode }, response: body });
      return { code: created ? 201 as const : 200 as const, body };
    });
    return reply.code(result.code).send(result.body);
  });

  app.get('/games/parade-practice/:id', async (request, reply) => {
    const params = paramsSchema.safeParse(request.params); if (!params.success) return reply.code(400).send({ error: 'invalid_request' });
    const row = (await db.select({ session: paradeSessions, puzzle: paradePuzzles, pet: pets }).from(paradeSessions)
      .innerJoin(paradePuzzles, eq(paradeSessions.puzzleId, paradePuzzles.id)).innerJoin(pets, eq(paradeSessions.petId, pets.id))
      .where(and(eq(paradeSessions.id, params.data.id), eq(paradeSessions.userId, request.user!.id))))[0];
    return row ? reply.send({ game: publicGame(row.session, row.puzzle, row.pet) }) : reply.code(404).send({ error: 'not_found' });
  });

  app.post('/games/parade-practice/:id/actions', { config: { rateLimit: { max: 40, timeWindow: '1 minute' } } }, async (request, reply) => {
    const params = paramsSchema.safeParse(request.params); const parsed = actionBody.safeParse(request.body);
    if (!params.success || !parsed.success) return reply.code(400).send({ error: 'invalid_request' });
    const userId = request.user!.id;
    const result = await db.transaction(async (tx) => {
      await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
      const row = (await tx.select({ session: paradeSessions, puzzle: paradePuzzles, pet: pets }).from(paradeSessions)
        .innerJoin(paradePuzzles, eq(paradeSessions.puzzleId, paradePuzzles.id)).innerJoin(pets, eq(paradeSessions.petId, pets.id))
        .where(and(eq(paradeSessions.id, params.data.id), eq(paradeSessions.userId, userId))).for('update'))[0];
      if (!row) return { code: 404 as const, body: { error: 'not_found' } };
      const prior = (await tx.select().from(paradeActions).where(and(eq(paradeActions.userId, userId), eq(paradeActions.idempotencyKey, parsed.data.actionId))))[0];
      if (prior) return prior.sessionId === row.session.id ? { code: 200 as const, body: prior.response } : { code: 409 as const, body: { error: 'idempotency_conflict' } };
      if (row.session.status !== 'active') return { code: 409 as const, body: { error: 'game_completed' } };
      let status: ParadeSession['status'] = 'active'; let runs = row.session.runs; let bestCommandCount = row.session.bestCommandCount;
      let stars = 0; let rewardMinor = 0n; let journalCredited = false; let lastResult = row.session.lastResult;
      let completedAt: Date | null = null; let outcome = 'ran'; let message = 'The routine is ready for another rehearsal.'; let remaining: bigint | undefined;
      if (parsed.data.action === 'abandon') { status = 'abandoned'; completedAt = new Date(); outcome = 'abandoned'; message = 'The parade props are packed away.'; }
      else {
        const puzzle = definitionFrom(row.puzzle.definition); const simulation = simulateParade(puzzle, parsed.data.commands);
        runs += 1; lastResult = simulation as unknown as Record<string, unknown>;
        if (simulation.completed) {
          status = 'completed'; completedAt = new Date(); bestCommandCount = bestCommandCount === null ? parsed.data.commands.length : Math.min(bestCommandCount, parsed.data.commands.length);
          stars = parsed.data.commands.length <= puzzle.minimumCommands ? 3 : parsed.data.commands.length <= puzzle.minimumCommands + 2 ? 2 : 1;
          outcome = 'completed';
          if (row.session.mode === 'daily') {
            const priorWins = await tx.select({ id: paradeSessions.id }).from(paradeSessions).where(and(eq(paradeSessions.userId, userId), eq(paradeSessions.puzzleId, row.puzzle.id), eq(paradeSessions.mode, 'daily'), eq(paradeSessions.status, 'completed'), ne(paradeSessions.id, row.session.id)));
            journalCredited = priorWins.length === 0;
            if (journalCredited) {
              const settled = await settleDailyGame(tx as unknown as Db, { userId, petId: row.session.petId, gameType: 'parade_practice', ledgerKind: 'parade_practice_reward', sessionId: row.session.id, stars, metadata: { puzzleKey: row.puzzle.puzzleKey, commands: parsed.data.commands.length } });
              rewardMinor = settled.rewardMinor; remaining = settled.remainingMinor;
            }
          }
          message = rewardMinor > 0n ? `${row.pet.name} leads the parade to the bandstand.` : `${row.pet.name} leads the parade to the bandstand. This rehearsal was for fun.`;
        } else if (simulation.failure) { outcome = 'blocked'; message = simulation.failure === 'illegal_hop' ? 'That hop needs one obstacle and a clear landing.' : 'The routine meets an obstacle.'; }
      }
      const updated = (await tx.update(paradeSessions).set({ status, runs, bestCommandCount, stars, rewardMinor, journalCredited, lastResult, updatedAt: new Date(), completedAt }).where(eq(paradeSessions.id, row.session.id)).returning())[0]!;
      const body: Record<string, unknown> = { game: publicGame(updated, row.puzzle, row.pet), outcome, message };
      if (remaining !== undefined) body['dailyRewardMinorRemaining'] = remaining.toString();
      await tx.insert(paradeActions).values({ sessionId: row.session.id, userId, idempotencyKey: parsed.data.actionId, action: parsed.data, response: body });
      return { code: 200 as const, body };
    });
    return reply.code(result.code).send(result.body);
  });
}
