import type { FastifyInstance } from 'fastify';
import { and, eq, ne } from 'drizzle-orm';
import {
  type Db, type LanternPuzzle, type LanternSession, type Pet,
  lanternActions, lanternPuzzles, lanternSessions, pets, users,
} from '@paws/db';
import { z } from 'zod';
import { settleDailyGame } from '../game-rewards.js';
import {
  dailyLanternPuzzle, evaluateLanternBoard, solveLanternPuzzle,
  type LanternOrientation, type LanternPuzzleDefinition,
} from '../lantern-engine.js';

const startBody = z.object({ petId: z.string().uuid(), mode: z.enum(['daily', 'practice']), actionId: z.string().uuid() }).strict();
const paramsSchema = z.object({ id: z.string().uuid() }).strict();
const actionBody = z.discriminatedUnion('action', [
  z.object({ action: z.literal('rotate'), position: z.number().int().min(0).max(24), direction: z.enum(['clockwise', 'counterclockwise']), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('submit'), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('reset'), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('abandon'), actionId: z.string().uuid() }).strict(),
]);

function utcDate(): string { return new Date().toISOString().slice(0, 10); }

function definitionFrom(value: unknown): LanternPuzzleDefinition {
  const puzzle = value as LanternPuzzleDefinition;
  if (!puzzle || puzzle.version !== 1 || puzzle.size !== 5 || puzzle.tiles?.length !== 25 ||
      puzzle.initialOrientations?.length !== 25 || !Array.isArray(puzzle.rotatablePositions)) {
    throw new Error('invalid lantern puzzle definition');
  }
  return puzzle;
}

function orientationsFrom(value: unknown): LanternOrientation[] {
  if (!Array.isArray(value) || value.length !== 25 || value.some((item) => !Number.isInteger(item) || item < 0 || item > 3)) {
    throw new Error('invalid lantern orientations');
  }
  return value as LanternOrientation[];
}

async function ensurePuzzle(db: Db, mode: 'daily' | 'practice'): Promise<LanternPuzzle> {
  const date = utcDate();
  const key = mode === 'daily' ? `daily:${date}:v1` : 'practice:v1';
  const definition = dailyLanternPuzzle(date);
  const proof = solveLanternPuzzle(definition);
  await db.insert(lanternPuzzles).values({
    puzzleKey: key, mode, definition: definition as unknown as Record<string, unknown>,
    minimumTurns: proof.minimumTurns,
    verification: { solvable: true, minimumTurns: proof.minimumTurns, verifiedAt: new Date().toISOString() },
  }).onConflictDoNothing({ target: lanternPuzzles.puzzleKey });
  const rows = await db.select().from(lanternPuzzles).where(and(eq(lanternPuzzles.puzzleKey, key), eq(lanternPuzzles.active, true)));
  const puzzle = rows[0];
  if (!puzzle) throw new Error('verified lantern puzzle unavailable');
  definitionFrom(puzzle.definition);
  return puzzle;
}

function publicGame(session: LanternSession, puzzleRow: LanternPuzzle, pet: Pet) {
  const puzzle = definitionFrom(puzzleRow.definition);
  const orientations = orientationsFrom(session.orientations);
  const evaluation = evaluateLanternBoard(puzzle, orientations);
  return {
    id: session.id, mode: session.mode, status: session.status, puzzleKey: puzzleRow.puzzleKey,
    pet: { id: pet.id, name: pet.name, species: pet.species }, size: puzzle.size,
    tiles: puzzle.tiles.map((tile) => ({ ...tile, orientation: orientations[tile.position], lit: evaluation.litPositions.includes(tile.position) })),
    orientations, rotatablePositions: puzzle.rotatablePositions,
    poweredLanternIds: evaluation.poweredLanternIds, breaks: evaluation.breaks, leaks: evaluation.leaks,
    rotations: session.rotations, resets: session.resets, carefulTurnTarget: puzzle.carefulTurnTarget,
    stars: session.stars, rewardMinor: session.rewardMinor.toString(), journalCredited: session.journalCredited,
    createdAt: session.createdAt.toISOString(), completedAt: session.completedAt?.toISOString() ?? null,
  };
}

async function ownedPet(db: Db, userId: string, petId: string) {
  return (await db.select().from(pets).where(and(eq(pets.id, petId), eq(pets.userId, userId))))[0];
}

export function registerLanternLinesRoutes(app: FastifyInstance, db: Db): void {
  app.post('/games/lantern-lines', { config: { rateLimit: { max: 10, timeWindow: '1 minute' } } }, async (request, reply) => {
    const parsed = startBody.safeParse(request.body);
    if (!parsed.success) return reply.code(400).send({ error: 'invalid_request' });
    const userId = request.user!.id;
    const result = await db.transaction(async (tx) => {
      await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
      const prior = (await tx.select().from(lanternActions).where(and(eq(lanternActions.userId, userId), eq(lanternActions.idempotencyKey, parsed.data.actionId))))[0];
      if (prior) return { code: 200 as const, body: prior.response };
      const pet = await ownedPet(tx as unknown as Db, userId, parsed.data.petId);
      if (!pet) return { code: 404 as const, body: { error: 'not_found' } };
      if (!pet.alive) return { code: 409 as const, body: { error: 'pet_unavailable' } };
      const active = (await tx.select({ session: lanternSessions, puzzle: lanternPuzzles, pet: pets }).from(lanternSessions)
        .innerJoin(lanternPuzzles, eq(lanternSessions.puzzleId, lanternPuzzles.id)).innerJoin(pets, eq(lanternSessions.petId, pets.id))
        .where(and(eq(lanternSessions.userId, userId), eq(lanternSessions.status, 'active'))))[0];
      let session = active?.session; let puzzle = active?.puzzle; let host = active?.pet; let created = false;
      if (!session || !puzzle || !host) {
        puzzle = await ensurePuzzle(tx as unknown as Db, parsed.data.mode);
        const definition = definitionFrom(puzzle.definition);
        session = (await tx.insert(lanternSessions).values({ userId, petId: pet.id, puzzleId: puzzle.id, mode: parsed.data.mode, orientations: definition.initialOrientations }).returning())[0]!;
        host = pet; created = true;
      }
      const body = { game: publicGame(session, puzzle, host), outcome: 'started' };
      await tx.insert(lanternActions).values({ sessionId: session.id, userId, idempotencyKey: parsed.data.actionId, action: { action: 'start', petId: parsed.data.petId, mode: parsed.data.mode }, response: body });
      return { code: created ? 201 as const : 200 as const, body };
    });
    return reply.code(result.code).send(result.body);
  });

  app.get('/games/lantern-lines/:id', async (request, reply) => {
    const params = paramsSchema.safeParse(request.params);
    if (!params.success) return reply.code(400).send({ error: 'invalid_request' });
    const row = (await db.select({ session: lanternSessions, puzzle: lanternPuzzles, pet: pets }).from(lanternSessions)
      .innerJoin(lanternPuzzles, eq(lanternSessions.puzzleId, lanternPuzzles.id)).innerJoin(pets, eq(lanternSessions.petId, pets.id))
      .where(and(eq(lanternSessions.id, params.data.id), eq(lanternSessions.userId, request.user!.id))))[0];
    return row ? reply.send({ game: publicGame(row.session, row.puzzle, row.pet) }) : reply.code(404).send({ error: 'not_found' });
  });

  app.post('/games/lantern-lines/:id/actions', { config: { rateLimit: { max: 120, timeWindow: '1 minute' } } }, async (request, reply) => {
    const params = paramsSchema.safeParse(request.params); const parsed = actionBody.safeParse(request.body);
    if (!params.success || !parsed.success) return reply.code(400).send({ error: 'invalid_request' });
    const userId = request.user!.id;
    const result = await db.transaction(async (tx) => {
      await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
      const row = (await tx.select({ session: lanternSessions, puzzle: lanternPuzzles, pet: pets }).from(lanternSessions)
        .innerJoin(lanternPuzzles, eq(lanternSessions.puzzleId, lanternPuzzles.id)).innerJoin(pets, eq(lanternSessions.petId, pets.id))
        .where(and(eq(lanternSessions.id, params.data.id), eq(lanternSessions.userId, userId))).for('update'))[0];
      if (!row) return { code: 404 as const, body: { error: 'not_found' } };
      const prior = (await tx.select().from(lanternActions).where(and(eq(lanternActions.userId, userId), eq(lanternActions.idempotencyKey, parsed.data.actionId))))[0];
      if (prior) return prior.sessionId === row.session.id ? { code: 200 as const, body: prior.response } : { code: 409 as const, body: { error: 'idempotency_conflict' } };
      if (row.session.status !== 'active') return { code: 409 as const, body: { error: 'game_completed' } };
      const puzzle = definitionFrom(row.puzzle.definition);
      let orientations = orientationsFrom(row.session.orientations); let rotations = row.session.rotations; let resets = row.session.resets;
      let status: LanternSession['status'] = 'active'; let stars = 0; let rewardMinor = 0n; let journalCredited = false;
      let completedAt: Date | null = null; let outcome = 'rotated'; let message = 'The glass turns with a soft click.'; let remaining: bigint | undefined;
      if (parsed.data.action === 'rotate') {
        if (!puzzle.rotatablePositions.includes(parsed.data.position)) return { code: 409 as const, body: { error: 'tile_fixed' } };
        orientations = [...orientations];
        const step = parsed.data.direction === 'clockwise' ? 1 : 3;
        orientations[parsed.data.position] = ((orientations[parsed.data.position]! + step) % 4) as LanternOrientation;
        rotations += 1;
      } else if (parsed.data.action === 'reset') {
        orientations = [...puzzle.initialOrientations]; rotations = 0; resets += 1; outcome = 'reset'; message = 'The signal board returns to its opening pattern.';
      } else if (parsed.data.action === 'abandon') {
        status = 'abandoned'; completedAt = new Date(); outcome = 'abandoned'; message = 'The signal house is safely closed.';
      } else {
        const evaluation = evaluateLanternBoard(puzzle, orientations);
        if (!evaluation.complete) { outcome = 'incomplete'; message = 'Some neighborhood lanterns are still dark.'; }
        else {
          status = 'completed'; completedAt = new Date(); stars = evaluation.clean ? (rotations <= puzzle.carefulTurnTarget ? 3 : 2) : 1;
          outcome = 'completed';
          if (row.session.mode === 'daily') {
            const priorWins = await tx.select({ id: lanternSessions.id }).from(lanternSessions).where(and(eq(lanternSessions.userId, userId), eq(lanternSessions.puzzleId, row.puzzle.id), eq(lanternSessions.mode, 'daily'), eq(lanternSessions.status, 'completed'), ne(lanternSessions.id, row.session.id)));
            journalCredited = priorWins.length === 0;
            if (journalCredited) {
              const settled = await settleDailyGame(tx as unknown as Db, { userId, petId: row.session.petId, gameType: 'lantern_lines', ledgerKind: 'lantern_lines_reward', sessionId: row.session.id, stars, metadata: { puzzleKey: row.puzzle.puzzleKey, rotations } });
              rewardMinor = settled.rewardMinor; remaining = settled.remainingMinor;
            }
          }
          message = rewardMinor > 0n ? `${row.pet.name} lights the whole hillside.` : `${row.pet.name} lights the whole hillside. This shift was for fun.`;
        }
      }
      const updated = (await tx.update(lanternSessions).set({ orientations, rotations, resets, status, stars, rewardMinor, journalCredited, updatedAt: new Date(), completedAt }).where(eq(lanternSessions.id, row.session.id)).returning())[0]!;
      const body: Record<string, unknown> = { game: publicGame(updated, row.puzzle, row.pet), outcome, message };
      if (remaining !== undefined) body['dailyRewardMinorRemaining'] = remaining.toString();
      await tx.insert(lanternActions).values({ sessionId: row.session.id, userId, idempotencyKey: parsed.data.actionId, action: parsed.data, response: body });
      return { code: 200 as const, body };
    });
    return reply.code(result.code).send(result.body);
  });
}
