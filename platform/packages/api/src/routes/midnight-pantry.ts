/** Server-authoritative Midnight Pantry deduction game. */
import type { FastifyInstance } from 'fastify';
import { and, eq, ne } from 'drizzle-orm';
import {
  type Db,
  type PantryPlacements,
  type PantryPuzzle,
  type PantrySession,
  type Pet,
  pantryActions,
  pantryPuzzles,
  pantrySessions,
  petGameProgress,
  pets,
  users,
} from '@paws/db';
import { ensureSystemAccount, ensureUserAccount, transfer } from '@paws/ledger';
import { sql } from 'drizzle-orm';
import { z } from 'zod';
import { reserveGameReward } from '../game-rewards.js';
import {
  PANTRY_GUEST_IDS,
  PANTRY_INGREDIENT_IDS,
  dailyPantryPuzzle,
  solvePantryPuzzle,
  type PantryGuestId,
  type PantryIngredientId,
  type PantryPuzzleDefinition,
  type PantrySolution,
} from '../pantry-engine.js';

const startBody = z.object({
  petId: z.string().uuid(),
  mode: z.enum(['daily', 'practice']),
  actionId: z.string().uuid(),
}).strict();
const sessionParams = z.object({ id: z.string().uuid() }).strict();
const actionBody = z.discriminatedUnion('action', [
  z.object({
    action: z.literal('place'),
    guestId: z.enum(PANTRY_GUEST_IDS),
    slot: z.union([z.literal(0), z.literal(1)]),
    ingredientId: z.enum(PANTRY_INGREDIENT_IDS),
    actionId: z.string().uuid(),
  }).strict(),
  z.object({
    action: z.literal('remove'),
    guestId: z.enum(PANTRY_GUEST_IDS),
    slot: z.union([z.literal(0), z.literal(1)]),
    actionId: z.string().uuid(),
  }).strict(),
  z.object({ action: z.literal('serve'), guestId: z.enum(PANTRY_GUEST_IDS), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('abandon'), actionId: z.string().uuid() }).strict(),
]);

type PantryOutcome = 'started' | 'placed' | 'removed' | 'served' | 'incorrect' | 'completed' | 'failed' | 'abandoned';

interface PantryResponse {
  game: ReturnType<typeof publicGame>;
  outcome?: PantryOutcome;
  message?: string;
  journalCredited?: boolean;
  dailyRewardMinorRemaining?: string;
}

function utcDate(now = new Date()): string {
  return now.toISOString().slice(0, 10);
}

function emptyPlacements(): PantryPlacements {
  return { moth: [null, null], fox: [null, null], owl: [null, null] };
}

function validatedDefinition(value: unknown): PantryPuzzleDefinition {
  if (typeof value !== 'object' || value === null) throw new Error('invalid pantry definition');
  const definition = value as PantryPuzzleDefinition;
  if (
    definition.version !== 1 || definition.bellLimit !== 6 ||
    !Array.isArray(definition.ingredients) || definition.ingredients.length !== PANTRY_INGREDIENT_IDS.length ||
    !Array.isArray(definition.guests) || definition.guests.length !== PANTRY_GUEST_IDS.length ||
    definition.ingredients.some((ingredient) => !PANTRY_INGREDIENT_IDS.includes(ingredient.id)) ||
    definition.guests.some((guest) => !PANTRY_GUEST_IDS.includes(guest.id))
  ) throw new Error('invalid persisted pantry definition');
  if (solvePantryPuzzle(definition).length !== 1) throw new Error('persisted pantry puzzle is not uniquely solvable');
  return definition;
}

function validatedSolution(value: unknown): PantrySolution {
  if (typeof value !== 'object' || value === null) throw new Error('invalid pantry solution');
  const record = value as Record<string, unknown>;
  for (const guestId of PANTRY_GUEST_IDS) {
    const pair = record[guestId];
    if (
      !Array.isArray(pair) || pair.length !== 2 || pair[0] === pair[1] ||
      pair.some((ingredientId) => typeof ingredientId !== 'string' || !PANTRY_INGREDIENT_IDS.includes(ingredientId as PantryIngredientId))
    ) throw new Error('invalid persisted pantry solution');
  }
  return record as PantrySolution;
}

function validatedPlacements(value: unknown): PantryPlacements {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    throw new Error('invalid pantry placements');
  }
  const result = emptyPlacements();
  for (const guestId of PANTRY_GUEST_IDS) {
    const pair = (value as Record<string, unknown>)[guestId];
    if (
      !Array.isArray(pair) || pair.length !== 2 ||
      pair.some((ingredientId) => ingredientId !== null &&
        (typeof ingredientId !== 'string' || !PANTRY_INGREDIENT_IDS.includes(ingredientId as PantryIngredientId)))
    ) throw new Error('invalid persisted pantry placement pair');
    result[guestId] = [pair[0] as string | null, pair[1] as string | null];
  }
  return result;
}

function validatedGuests(value: unknown): PantryGuestId[] {
  if (!Array.isArray(value) || value.some((guestId) => typeof guestId !== 'string' || !PANTRY_GUEST_IDS.includes(guestId as PantryGuestId))) {
    throw new Error('invalid persisted locked pantry guests');
  }
  return [...new Set(value as PantryGuestId[])];
}

function usedIngredients(placements: PantryPlacements): Set<string> {
  const used = new Set<string>();
  for (const pair of Object.values(placements)) {
    for (const ingredientId of pair) if (ingredientId !== null) used.add(ingredientId);
  }
  return used;
}

function publicGame(session: PantrySession, puzzle: PantryPuzzle, pet: Pet) {
  const definition = validatedDefinition(puzzle.publicDefinition);
  const placements = validatedPlacements(session.placements);
  const lockedGuests = validatedGuests(session.lockedGuests);
  const used = usedIngredients(placements);
  return {
    id: session.id,
    mode: session.mode,
    status: session.status,
    puzzleKey: puzzle.puzzleKey,
    pet: { id: pet.id, name: pet.name, species: pet.species },
    guests: definition.guests.map(({ id, name, species, portrait, clues }) => ({
      id, name, species, portrait, clues,
    })),
    ingredients: definition.ingredients,
    placements,
    lockedGuests,
    availableIngredientIds: definition.ingredients.map(({ id }) => id).filter((id) => !used.has(id)),
    bellRings: session.bellRings,
    bellLimit: definition.bellLimit,
    mistakes: session.mistakes,
    stars: session.stars,
    rewardMinor: session.rewardMinor.toString(),
    journalCredited: session.journalCredited,
    createdAt: session.createdAt.toISOString(),
    completedAt: session.completedAt?.toISOString() ?? null,
  };
}

function samePair(actual: Array<string | null>, expected: string[]): boolean {
  if (actual.some((value) => value === null)) return false;
  return [...actual].sort().join('|') === [...expected].sort().join('|');
}

function rewardForStars(stars: number): bigint {
  return stars === 3 ? 10n : stars === 2 ? 8n : 5n;
}

function bondForStars(stars: number): number {
  return stars === 3 ? 20 : stars === 2 ? 15 : 10;
}

async function loadOwnedPet(db: Db, userId: string, petId: string): Promise<Pet | undefined> {
  const rows = await db.select().from(pets).where(and(eq(pets.id, petId), eq(pets.userId, userId)));
  return rows[0];
}

async function ensurePuzzle(db: Db, mode: 'daily' | 'practice'): Promise<PantryPuzzle> {
  const date = utcDate();
  const verified = dailyPantryPuzzle(date);
  const puzzleKey = mode === 'daily' ? `daily:${date}:v1` : 'practice:first-shift:v1';
  await db.insert(pantryPuzzles).values({
    puzzleKey,
    mode,
    generatorVersion: 1,
    publicDefinition: verified.publicDefinition as unknown as Record<string, unknown>,
    privateSolution: verified.privateSolution,
    verification: verified.verification as unknown as Record<string, unknown>,
  }).onConflictDoNothing({ target: pantryPuzzles.puzzleKey });
  const rows = await db.select().from(pantryPuzzles).where(and(eq(pantryPuzzles.puzzleKey, puzzleKey), eq(pantryPuzzles.active, true)));
  const puzzle = rows[0];
  if (!puzzle) throw new Error('verified pantry puzzle unavailable');
  // Fail closed if persisted data no longer passes the current verifier.
  validatedDefinition(puzzle.publicDefinition);
  validatedSolution(puzzle.privateSolution);
  return puzzle;
}

export function registerMidnightPantryRoutes(app: FastifyInstance, db: Db): void {
  app.post('/games/midnight-pantry', { config: { rateLimit: { max: 10, timeWindow: '1 minute' } } }, async (request, reply) => {
    const body = startBody.safeParse(request.body);
    if (!body.success) return reply.code(400).send({ error: 'invalid_request' });
    const userId = request.user!.id;
    const result = await db.transaction(async (tx) => {
      await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
      const priorRows = await tx.select().from(pantryActions).where(
        and(eq(pantryActions.userId, userId), eq(pantryActions.idempotencyKey, body.data.actionId)),
      );
      const prior = priorRows[0];
      if (prior) {
        return prior.action['petId'] === body.data.petId && prior.action['mode'] === body.data.mode
          ? { status: 200 as const, body: prior.response }
          : { status: 409 as const, body: { error: 'idempotency_conflict' } };
      }
      const pet = await loadOwnedPet(tx as unknown as Db, userId, body.data.petId);
      if (!pet) return { status: 404 as const, body: { error: 'not_found' } };
      if (!pet.alive) return { status: 409 as const, body: { error: 'pet_unavailable' } };

      const activeRows = await tx.select({ session: pantrySessions, puzzle: pantryPuzzles, pet: pets })
        .from(pantrySessions)
        .innerJoin(pantryPuzzles, eq(pantrySessions.puzzleId, pantryPuzzles.id))
        .innerJoin(pets, eq(pantrySessions.petId, pets.id))
        .where(and(eq(pantrySessions.userId, userId), eq(pantrySessions.status, 'active')));
      let session = activeRows[0]?.session;
      let puzzle = activeRows[0]?.puzzle;
      let host = activeRows[0]?.pet;
      let created = false;
      if (!session || !puzzle || !host) {
        puzzle = await ensurePuzzle(tx as unknown as Db, body.data.mode);
        const inserted = await tx.insert(pantrySessions).values({
          userId,
          petId: pet.id,
          puzzleId: puzzle.id,
          mode: body.data.mode,
          placements: emptyPlacements(),
        }).returning();
        session = inserted[0]!;
        host = pet;
        created = true;
      }
      const response: PantryResponse = { game: publicGame(session, puzzle, host), outcome: 'started' };
      await tx.insert(pantryActions).values({
        sessionId: session.id,
        userId,
        idempotencyKey: body.data.actionId,
        action: { action: 'start', petId: body.data.petId, mode: body.data.mode },
        response: response as unknown as Record<string, unknown>,
      });
      return { status: created ? 201 as const : 200 as const, body: response };
    });
    return reply.code(result.status).send(result.body);
  });

  app.get('/games/midnight-pantry/:id', async (request, reply) => {
    const params = sessionParams.safeParse(request.params);
    if (!params.success) return reply.code(400).send({ error: 'invalid_request' });
    const rows = await db.select({ session: pantrySessions, puzzle: pantryPuzzles, pet: pets })
      .from(pantrySessions)
      .innerJoin(pantryPuzzles, eq(pantrySessions.puzzleId, pantryPuzzles.id))
      .innerJoin(pets, eq(pantrySessions.petId, pets.id))
      .where(and(eq(pantrySessions.id, params.data.id), eq(pantrySessions.userId, request.user!.id)));
    const row = rows[0];
    if (!row) return reply.code(404).send({ error: 'not_found' });
    return reply.send({ game: publicGame(row.session, row.puzzle, row.pet) });
  });

  app.post('/games/midnight-pantry/:id/actions', { config: { rateLimit: { max: 120, timeWindow: '1 minute' } } }, async (request, reply) => {
    const params = sessionParams.safeParse(request.params);
    const body = actionBody.safeParse(request.body);
    if (!params.success || !body.success) return reply.code(400).send({ error: 'invalid_request' });
    const userId = request.user!.id;
    const result = await db.transaction(async (tx) => {
      await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
      const rows = await tx.select({ session: pantrySessions, puzzle: pantryPuzzles, pet: pets })
        .from(pantrySessions)
        .innerJoin(pantryPuzzles, eq(pantrySessions.puzzleId, pantryPuzzles.id))
        .innerJoin(pets, eq(pantrySessions.petId, pets.id))
        .where(and(eq(pantrySessions.id, params.data.id), eq(pantrySessions.userId, userId)))
        .for('update');
      const row = rows[0];
      if (!row) return { status: 404 as const, body: { error: 'not_found' } };

      const priorRows = await tx.select().from(pantryActions).where(
        and(eq(pantryActions.userId, userId), eq(pantryActions.idempotencyKey, body.data.actionId)),
      );
      const prior = priorRows[0];
      if (prior) {
        if (prior.sessionId !== row.session.id) return { status: 409 as const, body: { error: 'idempotency_conflict' } };
        return { status: 200 as const, body: prior.response };
      }
      if (row.session.status !== 'active') return { status: 409 as const, body: { error: 'game_completed' } };

      const definition = validatedDefinition(row.puzzle.publicDefinition);
      const solution = validatedSolution(row.puzzle.privateSolution);
      const placements = validatedPlacements(row.session.placements);
      let lockedGuests = validatedGuests(row.session.lockedGuests);
      let bellRings = row.session.bellRings;
      let mistakes = row.session.mistakes;
      let status: PantrySession['status'] = 'active';
      let stars = 0;
      let rewardMinor = 0n;
      let journalCredited = false;
      let dailyRewardMinorRemaining: bigint | undefined;
      let outcome: PantryOutcome;
      let message: string;
      let completedAt: Date | null = null;

      if (body.data.action === 'abandon') {
        status = 'abandoned';
        outcome = 'abandoned';
        message = 'The pantry is closed for tonight. No pet or inventory was harmed.';
        completedAt = new Date();
      } else if (body.data.action === 'place') {
        const { guestId, slot, ingredientId } = body.data;
        if (lockedGuests.includes(guestId)) return { status: 409 as const, body: { error: 'guest_already_served' } };
        const target = placements[guestId]!;
        const current = target[slot];
        const usedElsewhere = Object.entries(placements).some(([placementGuestId, pair]) =>
          pair.some((placedIngredientId, placedSlot) => placedIngredientId === ingredientId &&
            !(placementGuestId === guestId && placedSlot === slot)),
        );
        if (usedElsewhere) return { status: 409 as const, body: { error: 'ingredient_unavailable' } };
        target[slot] = ingredientId;
        if (target[0] !== null && target[0] === target[1]) target[slot] = current;
        outcome = 'placed';
        message = `${row.pet.name} sets an ingredient into the bowl.`;
      } else if (body.data.action === 'remove') {
        if (lockedGuests.includes(body.data.guestId)) return { status: 409 as const, body: { error: 'guest_already_served' } };
        placements[body.data.guestId]![body.data.slot] = null;
        outcome = 'removed';
        message = 'The ingredient returns to the pantry tray.';
      } else {
        if (lockedGuests.includes(body.data.guestId)) return { status: 409 as const, body: { error: 'guest_already_served' } };
        const guestId = body.data.guestId;
        const bowl = placements[guestId]!;
        if (bowl.some((ingredientId) => ingredientId === null)) {
          return { status: 409 as const, body: { error: 'bowl_incomplete' } };
        }
        bellRings += 1;
        if (samePair(bowl, solution[guestId])) {
          lockedGuests = [...lockedGuests, guestId];
          outcome = 'served';
          message = `${definition.guests.find((guest) => guest.id === guestId)!.name} loves the bowl.`;
        } else {
          mistakes += 1;
          outcome = 'incorrect';
          message = 'Something is still off. Read the clues and adjust the bowl.';
        }

        if (lockedGuests.length === PANTRY_GUEST_IDS.length) {
          status = 'completed';
          stars = mistakes === 0 ? 3 : mistakes === 1 ? 2 : 1;
          completedAt = new Date();
          outcome = 'completed';
          if (row.session.mode === 'daily') {
            const priorCompletions = await tx.select({ id: pantrySessions.id }).from(pantrySessions).where(
              and(
                eq(pantrySessions.userId, userId),
                eq(pantrySessions.puzzleId, row.puzzle.id),
                eq(pantrySessions.mode, 'daily'),
                eq(pantrySessions.status, 'completed'),
                ne(pantrySessions.id, row.session.id),
              ),
            );
            journalCredited = priorCompletions.length === 0;
            if (journalCredited) {
              const reserved = await reserveGameReward(tx as unknown as Db, userId, rewardForStars(stars));
              rewardMinor = reserved.awardedMinor;
              dailyRewardMinorRemaining = reserved.remainingMinor;
            }
          }
          message = rewardMinor > 0n
            ? `${row.pet.name} closes the pantry with ${stars} stars.`
            : `${row.pet.name} closes the pantry. Tonight's shift was for fun.`;
        } else if (bellRings >= definition.bellLimit) {
          status = 'failed';
          completedAt = new Date();
          outcome = 'failed';
          message = 'The last bell has rung. The guests are safe, and you can try another shift.';
        }
      }

      const now = new Date();
      const updatedRows = await tx.update(pantrySessions).set({
        placements,
        lockedGuests,
        bellRings,
        mistakes,
        status,
        stars,
        rewardMinor,
        journalCredited,
        updatedAt: now,
        completedAt,
      }).where(eq(pantrySessions.id, row.session.id)).returning();
      const updated = updatedRows[0]!;

      if (status === 'completed' && journalCredited) {
        const bondXp = bondForStars(stars);
        await tx.insert(petGameProgress).values({
          petId: row.session.petId,
          gameType: 'midnight_pantry',
          bondXp,
        }).onConflictDoUpdate({
          target: [petGameProgress.petId, petGameProgress.gameType],
          set: { bondXp: sql`${petGameProgress.bondXp} + ${bondXp}`, updatedAt: now },
        });
        if (rewardMinor > 0n) {
          const txDb = tx as unknown as Db;
          const treasury = await ensureSystemAccount(txDb, 'game_rewards', 'PAWS');
          const player = await ensureUserAccount(txDb, userId, 'PAWS');
          await transfer(txDb, {
            idempotencyKey: `midnight_pantry_reward:${row.session.id}`,
            kind: 'midnight_pantry_reward',
            fromAccountId: treasury,
            toAccountId: player,
            amountMinor: rewardMinor,
            metadata: { pantrySessionId: row.session.id, petId: row.session.petId, stars, puzzleKey: row.puzzle.puzzleKey },
          });
        }
      }

      const response: PantryResponse = {
        game: publicGame(updated, row.puzzle, row.pet),
        outcome,
        message,
      };
      if (status === 'completed') response.journalCredited = journalCredited;
      if (dailyRewardMinorRemaining !== undefined) response.dailyRewardMinorRemaining = dailyRewardMinorRemaining.toString();
      await tx.insert(pantryActions).values({
        sessionId: row.session.id,
        userId,
        idempotencyKey: body.data.actionId,
        action: body.data as unknown as Record<string, unknown>,
        response: response as unknown as Record<string, unknown>,
      });
      return { status: 200 as const, body: response };
    });
    return reply.code(result.status).send(result.body);
  });
}
