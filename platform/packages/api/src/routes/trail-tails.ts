/** Server-authoritative Trail Tails exploration puzzle. */
import { randomInt } from 'node:crypto';
import type { FastifyInstance } from 'fastify';
import { and, eq, sql } from 'drizzle-orm';
import {
  type Db,
  type Pet,
  type TrailPrivateTile,
  type TrailSession,
  petGameProgress,
  pets,
  trailActions,
  trailSessions,
  users,
} from '@paws/db';
import { ensureSystemAccount, ensureUserAccount, transfer } from '@paws/ledger';
import { z } from 'zod';
import { reserveGameReward } from '../game-rewards.js';

const BOARD_SIDE = 5;
const BOARD_SIZE = BOARD_SIDE * BOARD_SIDE;
const START_POSITION = 20;
const HOME_POSITION = 24;
const INITIAL_ENERGY = 12;
const MAX_ENERGY = 14;
const TERRAINS = ['meadow', 'creek', 'brambles', 'lookout'] as const;
const DIRECTIONS = ['north', 'east', 'south', 'west'] as const;

type Direction = (typeof DIRECTIONS)[number];
type TrailAction =
  | { action: 'move'; direction: Direction; actionId: string }
  | { action: 'dash'; direction: Direction; actionId: string }
  | { action: 'sniff'; actionId: string }
  | { action: 'rest'; actionId: string };

const startBody = z
  .object({ petId: z.string().uuid(), actionId: z.string().uuid() })
  .strict();
const sessionParams = z.object({ id: z.string().uuid() }).strict();
const actionBody = z.discriminatedUnion('action', [
  z.object({ action: z.literal('move'), direction: z.enum(DIRECTIONS), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('dash'), direction: z.enum(DIRECTIONS), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('sniff'), actionId: z.string().uuid() }).strict(),
  z.object({ action: z.literal('rest'), actionId: z.string().uuid() }).strict(),
]);

interface TrailResponse {
  game: ReturnType<typeof publicGame>;
  outcome?: 'started' | 'moved' | 'sniffed' | 'dashed' | 'rested' | 'completed' | 'failed';
  message?: string;
  dailyRewardMinorRemaining?: string;
}

function shuffled<T>(values: readonly T[]): T[] {
  const result = [...values];
  for (let index = result.length - 1; index > 0; index -= 1) {
    const other = randomInt(index + 1);
    [result[index], result[other]] = [result[other]!, result[index]!];
  }
  return result;
}

function positionAfter(position: number, direction: Direction): number | null {
  const row = Math.floor(position / BOARD_SIDE);
  const column = position % BOARD_SIDE;
  if (direction === 'north') return row === 0 ? null : position - BOARD_SIDE;
  if (direction === 'south') return row === BOARD_SIDE - 1 ? null : position + BOARD_SIDE;
  if (direction === 'west') return column === 0 ? null : position - 1;
  return column === BOARD_SIDE - 1 ? null : position + 1;
}

function neighbors(position: number): number[] {
  return DIRECTIONS.map((direction) => positionAfter(position, direction)).filter(
    (value): value is number => value !== null,
  );
}

function directPath(from: number, to: number): number[] {
  const path: number[] = [from];
  let current = from;
  while (Math.floor(current / BOARD_SIDE) !== Math.floor(to / BOARD_SIDE)) {
    current += current < to ? BOARD_SIDE : -BOARD_SIDE;
    path.push(current);
  }
  while (current % BOARD_SIDE !== to % BOARD_SIDE) {
    current += current % BOARD_SIDE < to % BOARD_SIDE ? 1 : -1;
    path.push(current);
  }
  return path;
}

export function generateTrail(): {
  privateMap: TrailPrivateTile[];
  keepsakePosition: number;
  rescuePosition: number;
} {
  const keepsakePosition = [12, 16, 17, 18][randomInt(4)]!;
  const rescuePosition = [13, 14, 19, 23][randomInt(4)]!;
  const safe = new Set([
    ...directPath(START_POSITION, keepsakePosition),
    ...directPath(keepsakePosition, rescuePosition),
    ...directPath(rescuePosition, HOME_POSITION),
  ]);
  const terrainBag = shuffled(
    Array.from({ length: BOARD_SIZE }, (_, index) => TERRAINS[index % TERRAINS.length]!),
  );
  const privateMap = terrainBag.map((terrain, position) => ({
    position,
    terrain: safe.has(position) ? ('meadow' as const) : terrain,
  }));
  if (!trailSupportsThreeStars(privateMap, keepsakePosition, rescuePosition)) {
    throw new Error('generated Trail Tails map has no three-star route');
  }
  return { privateMap, keepsakePosition, rescuePosition };
}

export function trailSupportsThreeStars(
  privateMap: TrailPrivateTile[],
  keepsakePosition: number,
  rescuePosition: number,
): boolean {
  const terrainByPosition = new Map(privateMap.map((tile) => [tile.position, tile.terrain]));
  const route = [
    ...directPath(START_POSITION, keepsakePosition),
    ...directPath(keepsakePosition, rescuePosition).slice(1),
    ...directPath(rescuePosition, HOME_POSITION).slice(1),
  ];
  const cost = route.slice(1).reduce((total, position) => {
    const terrain = terrainByPosition.get(position);
    if (terrain === undefined) return Number.POSITIVE_INFINITY;
    return total + terrainCost(terrain);
  }, 0);
  return cost <= INITIAL_ENERGY - 3;
}

function validatedMap(value: unknown): TrailPrivateTile[] {
  if (!Array.isArray(value) || value.length !== BOARD_SIZE) {
    throw new Error('invalid persisted Trail Tails map');
  }
  const seen = new Set<number>();
  for (const [index, tile] of value.entries()) {
    if (
      typeof tile !== 'object' || tile === null ||
      !Number.isInteger((tile as TrailPrivateTile).position) ||
      (tile as TrailPrivateTile).position < 0 ||
      (tile as TrailPrivateTile).position >= BOARD_SIZE ||
      (tile as TrailPrivateTile).position !== index ||
      !TERRAINS.includes((tile as TrailPrivateTile).terrain) ||
      seen.has((tile as TrailPrivateTile).position)
    ) throw new Error('invalid persisted Trail Tails tile');
    seen.add((tile as TrailPrivateTile).position);
  }
  return value as TrailPrivateTile[];
}

function validatedPositions(value: unknown): number[] {
  if (!Array.isArray(value) || value.some((position) => !Number.isInteger(position) || position < 0 || position >= BOARD_SIZE)) {
    throw new Error('invalid persisted Trail Tails discoveries');
  }
  return [...new Set(value as number[])].sort((a, b) => a - b);
}

function objectiveAt(row: TrailSession, position: number): 'home' | 'keepsake' | 'rescue' | undefined {
  if (position === row.homePosition) return 'home';
  if (!row.keepsakeFound && position === row.keepsakePosition) return 'keepsake';
  if (!row.rescueFound && position === row.rescuePosition) return 'rescue';
  return undefined;
}

function publicGame(row: TrailSession, pet: Pet) {
  const map = validatedMap(row.privateMap);
  const discovered = new Set(validatedPositions(row.discoveredPositions));
  discovered.add(row.position);
  discovered.add(row.homePosition);
  return {
    id: row.id,
    status: row.status,
    pet: { id: pet.id, name: pet.name, species: pet.species },
    position: row.position,
    homePosition: row.homePosition,
    energy: row.energy,
    turns: row.turns,
    abilities: { sniff: row.sniffCharges, dash: row.dashCharges, rest: row.restCharges },
    keepsakeFound: row.keepsakeFound,
    rescueFound: row.rescueFound,
    stars: row.stars,
    rewardMinor: row.rewardMinor.toString(),
    tiles: map.map((tile) => {
      if (!discovered.has(tile.position)) return { position: tile.position, discovered: false };
      const objective = objectiveAt(row, tile.position);
      return {
        position: tile.position,
        discovered: true,
        terrain: tile.terrain,
        ...(objective === undefined ? {} : { objective }),
      };
    }),
    legalDirections: DIRECTIONS.filter((direction) => positionAfter(row.position, direction) !== null),
    createdAt: row.createdAt.toISOString(),
    completedAt: row.completedAt?.toISOString() ?? null,
  };
}

function terrainCost(terrain: TrailPrivateTile['terrain']): number {
  return terrain === 'creek' || terrain === 'brambles' ? 2 : 1;
}

function completionValues(keepsakeFound: boolean, rescueFound: boolean, energy: number) {
  if (!keepsakeFound) return { stars: 0, reward: 0n, bondXp: 0 };
  const stars = rescueFound ? (energy >= 3 ? 3 : 2) : 1;
  return {
    stars,
    reward: stars === 3 ? 10n : stars === 2 ? 8n : 5n,
    bondXp: stars === 3 ? 20 : stars === 2 ? 15 : 10,
  };
}

async function loadPet(db: Db, userId: string, petId: string): Promise<Pet | undefined> {
  const rows = await db.select().from(pets).where(and(eq(pets.id, petId), eq(pets.userId, userId)));
  return rows[0];
}

export function registerTrailTailsRoutes(app: FastifyInstance, db: Db): void {
  app.post(
    '/games/trail-tails',
    { config: { rateLimit: { max: 10, timeWindow: '1 minute' } } },
    async (request, reply) => {
      const body = startBody.safeParse(request.body);
      if (!body.success) return reply.code(400).send({ error: 'invalid_request' });
      const userId = request.user!.id;
      const result = await db.transaction(async (tx) => {
        await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
        const priorRows = await tx
          .select()
          .from(trailActions)
          .where(and(eq(trailActions.userId, userId), eq(trailActions.idempotencyKey, body.data.actionId)));
        const prior = priorRows[0];
        if (prior) {
          return prior.action['petId'] === body.data.petId
            ? { status: 200 as const, body: prior.response }
            : { status: 409 as const, body: { error: 'idempotency_conflict' } };
        }

        const pet = await loadPet(tx as unknown as Db, userId, body.data.petId);
        if (!pet) return { status: 404 as const, body: { error: 'not_found' } };
        if (!pet.alive) return { status: 409 as const, body: { error: 'pet_unavailable' } };

        const activeRows = await tx
          .select()
          .from(trailSessions)
          .where(and(eq(trailSessions.userId, userId), eq(trailSessions.status, 'active')));
        let row = activeRows[0];
        let created = false;
        if (!row) {
          const generated = generateTrail();
          const inserted = await tx
            .insert(trailSessions)
            .values({
              userId,
              petId: pet.id,
              privateMap: generated.privateMap,
              discoveredPositions: [START_POSITION, ...neighbors(START_POSITION)],
              position: START_POSITION,
              startPosition: START_POSITION,
              homePosition: HOME_POSITION,
              keepsakePosition: generated.keepsakePosition,
              rescuePosition: generated.rescuePosition,
            })
            .returning();
          row = inserted[0]!;
          created = true;
        }
        const activePet = row.petId === pet.id ? pet : await loadPet(tx as unknown as Db, userId, row.petId);
        if (!activePet) throw new Error('active Trail Tails pet missing');
        const response: TrailResponse = { game: publicGame(row, activePet), outcome: 'started' };
        await tx.insert(trailActions).values({
          sessionId: row.id,
          userId,
          idempotencyKey: body.data.actionId,
          action: { action: 'start', petId: body.data.petId },
          response: response as unknown as Record<string, unknown>,
        });
        return { status: created ? 201 as const : 200 as const, body: response };
      });
      return reply.code(result.status).send(result.body);
    },
  );

  app.get('/games/trail-tails/:id', async (request, reply) => {
    const params = sessionParams.safeParse(request.params);
    if (!params.success) return reply.code(400).send({ error: 'invalid_request' });
    const rows = await db
      .select({ session: trailSessions, pet: pets })
      .from(trailSessions)
      .innerJoin(pets, eq(trailSessions.petId, pets.id))
      .where(and(eq(trailSessions.id, params.data.id), eq(trailSessions.userId, request.user!.id)));
    const result = rows[0];
    if (!result) return reply.code(404).send({ error: 'not_found' });
    return reply.send({ game: publicGame(result.session, result.pet) });
  });

  app.post(
    '/games/trail-tails/:id/actions',
    { config: { rateLimit: { max: 120, timeWindow: '1 minute' } } },
    async (request, reply) => {
      const params = sessionParams.safeParse(request.params);
      const body = actionBody.safeParse(request.body);
      if (!params.success || !body.success) return reply.code(400).send({ error: 'invalid_request' });
      const userId = request.user!.id;
      const result = await db.transaction(async (tx) => {
        await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
        const sessionRows = await tx
          .select({ session: trailSessions, pet: pets })
          .from(trailSessions)
          .innerJoin(pets, eq(trailSessions.petId, pets.id))
          .where(and(eq(trailSessions.id, params.data.id), eq(trailSessions.userId, userId)))
          .for('update');
        const joined = sessionRows[0];
        if (!joined) return { status: 404 as const, body: { error: 'not_found' } };
        const session = joined.session;

        const priorRows = await tx.select().from(trailActions).where(
          and(eq(trailActions.userId, userId), eq(trailActions.idempotencyKey, body.data.actionId)),
        );
        const prior = priorRows[0];
        if (prior) {
          if (prior.sessionId !== session.id) return { status: 409 as const, body: { error: 'idempotency_conflict' } };
          return { status: 200 as const, body: prior.response };
        }
        if (session.status !== 'active') return { status: 409 as const, body: { error: 'game_completed' } };

        const map = validatedMap(session.privateMap);
        const discovered = new Set(validatedPositions(session.discoveredPositions));
        let position = session.position;
        let energy = session.energy;
        let sniffCharges = session.sniffCharges;
        let dashCharges = session.dashCharges;
        let restCharges = session.restCharges;
        let keepsakeFound = session.keepsakeFound;
        let rescueFound = session.rescueFound;
        let outcome: TrailResponse['outcome'];
        let message: string;

        if (body.data.action === 'sniff') {
          if (sniffCharges < 1) return { status: 409 as const, body: { error: 'ability_unavailable' } };
          sniffCharges -= 1;
          for (const neighbor of neighbors(position)) discovered.add(neighbor);
          outcome = 'sniffed';
          message = 'The nearby trail comes into view.';
        } else if (body.data.action === 'rest') {
          if (restCharges < 1) return { status: 409 as const, body: { error: 'ability_unavailable' } };
          const terrain = map[position]!.terrain;
          if (terrain !== 'meadow' && terrain !== 'lookout') {
            return { status: 409 as const, body: { error: 'unsafe_to_rest' } };
          }
          restCharges -= 1;
          energy = Math.min(MAX_ENERGY, energy + 2);
          outcome = 'rested';
          message = 'A quiet pause restores two energy.';
        } else {
          const target = positionAfter(position, body.data.direction);
          if (target === null) return { status: 409 as const, body: { error: 'invalid_move' } };
          const targetTile = map[target]!;
          const dash = body.data.action === 'dash';
          if (dash) {
            if (dashCharges < 1) return { status: 409 as const, body: { error: 'ability_unavailable' } };
            if (!discovered.has(target) || targetTile.terrain !== 'meadow') {
              return { status: 409 as const, body: { error: 'dash_requires_revealed_meadow' } };
            }
            dashCharges -= 1;
          } else {
            const cost = terrainCost(targetTile.terrain);
            if (energy < cost) return { status: 409 as const, body: { error: 'not_enough_energy' } };
            energy -= cost;
          }
          position = target;
          discovered.add(target);
          if (position === session.keepsakePosition) keepsakeFound = true;
          if (position === session.rescuePosition) rescueFound = true;
          outcome = dash ? 'dashed' : 'moved';
          message = position === session.keepsakePosition
            ? 'You found the lost keepsake!'
            : position === session.rescuePosition
              ? 'You stopped to help a lost trail friend.'
              : `${joined.pet.name} reaches the next trail tile.`;
        }

        let status: TrailSession['status'] = 'active';
        let stars = 0;
        let rewardMinor = 0n;
        let completedAt: Date | null = null;
        let dailyRewardMinorRemaining: bigint | undefined;
        let bondXp = 0;
        if (position === session.homePosition && keepsakeFound) {
          status = 'completed';
          const completion = completionValues(keepsakeFound, rescueFound, energy);
          stars = completion.stars;
          bondXp = completion.bondXp;
          const reserved = await reserveGameReward(tx as unknown as Db, userId, completion.reward);
          rewardMinor = reserved.awardedMinor;
          dailyRewardMinorRemaining = reserved.remainingMinor;
          completedAt = new Date();
          outcome = 'completed';
          message = rewardMinor > 0n
            ? `${joined.pet.name} made it home with ${stars} stars.`
            : `${joined.pet.name} made it home. Today's PAWS reward cap is already reached.`;
        } else if (energy === 0) {
          status = 'failed';
          completedAt = new Date();
          outcome = 'failed';
          message = `${joined.pet.name} is out of trail energy. The pet is safe, and you can try again.`;
        }

        const now = new Date();
        const updatedRows = await tx
          .update(trailSessions)
          .set({
            status,
            discoveredPositions: [...discovered].sort((a, b) => a - b),
            position,
            energy,
            sniffCharges,
            dashCharges,
            restCharges,
            keepsakeFound,
            rescueFound,
            turns: session.turns + 1,
            stars,
            rewardMinor,
            updatedAt: now,
            completedAt,
          })
          .where(eq(trailSessions.id, session.id))
          .returning();
        const updated = updatedRows[0]!;

        if (status === 'completed') {
          await tx
            .insert(petGameProgress)
            .values({ petId: session.petId, gameType: 'trail_tails', bondXp })
            .onConflictDoUpdate({
              target: [petGameProgress.petId, petGameProgress.gameType],
              set: { bondXp: sql`${petGameProgress.bondXp} + ${bondXp}`, updatedAt: now },
            });
          if (rewardMinor > 0n) {
            const txDb = tx as unknown as Db;
            const treasury = await ensureSystemAccount(txDb, 'game_rewards', 'PAWS');
            const player = await ensureUserAccount(txDb, userId, 'PAWS');
            await transfer(txDb, {
              idempotencyKey: `trail_tails_reward:${session.id}`,
              kind: 'trail_tails_reward',
              fromAccountId: treasury,
              toAccountId: player,
              amountMinor: rewardMinor,
              metadata: { trailSessionId: session.id, petId: session.petId, stars },
            });
          }
        }

        const response: TrailResponse = { game: publicGame(updated, joined.pet), outcome, message };
        if (dailyRewardMinorRemaining !== undefined) {
          response.dailyRewardMinorRemaining = dailyRewardMinorRemaining.toString();
        }
        await tx.insert(trailActions).values({
          sessionId: session.id,
          userId,
          idempotencyKey: body.data.actionId,
          action: body.data as unknown as Record<string, unknown>,
          response: response as unknown as Record<string, unknown>,
        });
        return { status: 200 as const, body: response };
      });
      return reply.code(result.status).send(result.body);
    },
  );
}
