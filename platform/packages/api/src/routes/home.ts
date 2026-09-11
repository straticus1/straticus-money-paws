/**
 * Threats: rejects forged ownership/state, action replay with changed payloads,
 * inventory duplication and concurrent cooldown/limit bypass across API instances.
 * Does not identify humans or prevent legal automation or copying rendered art.
 */
import { createHash, timingSafeEqual } from 'node:crypto';
import type { FastifyInstance } from 'fastify';
import { and, asc, eq, gte, sql } from 'drizzle-orm';
import { type Db, homeActions, inventory, petCompanions, petHomes, pets, securityAuditEvents, storeItems, users } from '@paws/db';
import { z } from 'zod';
import { BANDANAS, beginAdventure, claimAdventure, ensureCompanion, publicAdventure, publicCompanion, recordCompanionCare, syncAdventure } from '../companions.js';

const envelope = { actionId: z.string().uuid(), expectedVersion: z.number().int().min(0).max(2147483646) };
const petId = z.string().uuid();
const itemId = z.string().uuid();
const position = z.number().int().min(0).max(19);
const commandSchema = z.discriminatedUnion('action', [
  z.object({ ...envelope, action: z.literal('pet'), petId }).strict(),
  z.object({ ...envelope, action: z.literal('play'), petId, itemId }).strict(),
  z.object({ ...envelope, action: z.literal('feed'), petId, itemId }).strict(),
  z.object({ ...envelope, action: z.literal('place'), itemId, position }).strict(),
  z.object({ ...envelope, action: z.literal('remove'), position }).strict(),
  z.object({ ...envelope, action: z.literal('begin_adventure'), petId }).strict(),
  z.object({ ...envelope, action: z.literal('claim_adventure'), petId }).strict(),
  z.object({ ...envelope, action: z.literal('equip_bandana'), petId, bandana: z.enum(['moss', 'sunflower', 'berry', 'midnight']) }).strict(),
]);
const PLACEABLE = new Set(['toy', 'house', 'tree', 'carrier', 'habitat', 'decor']);
const effectSchema = z.object({ hungerRestore: z.number().int().min(0).max(100).default(0), happinessBoost: z.number().int().min(0).max(100).default(0) });
type Tx = Parameters<Parameters<Db['transaction']>[0]>[0];
type HomeRow = typeof petHomes.$inferSelect;

async function lockHome(tx: Tx, userId: string): Promise<HomeRow> {
  // Same first lock as the games: orders concurrent commands across processes.
  await tx.select({ id: users.id }).from(users).where(eq(users.id, userId)).for('update');
  await tx.insert(petHomes).values({ userId }).onConflictDoNothing();
  const [home] = await tx.select().from(petHomes).where(eq(petHomes.userId, userId)).for('update');
  if (!home) throw new Error('home initialization failed');
  return home;
}

async function snapshot(tx: Tx, userId: string, home: HomeRow) {
  const clock = await tx.execute<{ now: Date }>(sql`SELECT clock_timestamp() AS now`);
  const now = new Date(clock.rows[0]!.now);
  const adventure = await syncAdventure(tx, userId, now);
  const ownedPets = await tx.select({ id: pets.id, name: pets.name, species: pets.species, hunger: pets.hunger, happiness: pets.happiness, health: pets.health, alive: pets.alive })
    .from(pets).where(eq(pets.userId, userId)).orderBy(asc(pets.createdAt), asc(pets.id));
  const companions = [];
  for (const pet of ownedPets) companions.push({ ...pet, companion: await publicCompanion(tx, pet.id, now) });
  const items = await tx.select({ itemId: inventory.itemId, quantity: inventory.quantity, name: storeItems.name, category: storeItems.category, effect: storeItems.effect })
    .from(inventory).innerJoin(storeItems, eq(storeItems.id, inventory.itemId))
    .where(and(eq(inventory.userId, userId), gte(inventory.quantity, 1))).orderBy(asc(storeItems.name));
  return {
    version: home.version, placements: home.placements, pets: companions,
    adventure: publicAdventure(adventure, now),
    items: items.map((item) => ({ ...item, placeable: PLACEABLE.has(item.category) })),
    nextCareAt: home.nextCareAt.toISOString(), serverNow: now.toISOString(),
  };
}

export function registerHomeRoutes(app: FastifyInstance, db: Db): void {
  app.get('/home', async (request, reply) => {
    reply.header('Cache-Control', 'no-store');
    return db.transaction(async (tx) => {
      const userId = request.user!.id;
      return { home: await snapshot(tx, userId, await lockHome(tx, userId)) };
    });
  });

  app.post('/home/actions', { bodyLimit: 2048 }, async (request, reply) => {
    reply.header('Cache-Control', 'no-store');
    const parsed = commandSchema.safeParse(request.body);
    if (!parsed.success) return reply.code(400).send({ error: 'invalid_request' });
    // Zod creates keys in schema order: equivalent JSON key ordering hashes alike.
    const command = parsed.data;
    const hash = createHash('sha256').update(JSON.stringify(command)).digest('hex');
    const userId = request.user!.id;
    const result = await db.transaction(async (tx) => {
      const home = await lockHome(tx, userId);
      const [receipt] = await tx.select().from(homeActions).where(and(eq(homeActions.userId, userId), eq(homeActions.actionId, command.actionId)));
      if (receipt) {
        const stored = Buffer.from(receipt.requestHash, 'hex');
        const current = Buffer.from(hash, 'hex');
        if (stored.length !== current.length || !timingSafeEqual(stored, current)) {
          return { status: 409, body: { error: 'action_conflict' } };
        }
        return { status: 200, body: receipt.response };
      }
      const clock = await tx.execute<{ now: Date }>(sql`SELECT clock_timestamp() AS now`);
      const now = new Date(clock.rows[0]!.now);
      const freshWindow = now.getTime() - home.windowStartedAt.getTime() >= 60_000;
      const count = freshWindow ? 0 : home.actionCount;
      if (count >= 30) return { status: 429, body: { error: 'home_rate_limited' } };
      // PostgreSQL is the shared limiter. Failed validated attempts also count.
      await tx.update(petHomes).set({ actionCount: count + 1, windowStartedAt: freshWindow ? now : home.windowStartedAt }).where(eq(petHomes.userId, userId));
      if (command.expectedVersion !== home.version) return { status: 409, body: { error: 'stale_home' } };

      let nextCareAt = home.nextCareAt;
      let placements = [...home.placements];
      let message: string | undefined;
      if (command.action === 'place') {
        const [owned] = await tx.select({ quantity: inventory.quantity, category: storeItems.category }).from(inventory)
          .innerJoin(storeItems, eq(storeItems.id, inventory.itemId))
          .where(and(eq(inventory.userId, userId), eq(inventory.itemId, command.itemId))).for('update', { of: inventory });
        if (!owned || owned.quantity <= 0) return { status: 403, body: { error: 'item_not_owned' } };
        if (!PLACEABLE.has(owned.category)) return { status: 400, body: { error: 'item_not_placeable' } };
        if (placements.some((p) => p.position === command.position)) return { status: 409, body: { error: 'position_occupied' } };
        if (placements.filter((p) => p.itemId === command.itemId).length >= owned.quantity) return { status: 409, body: { error: 'all_copies_placed' } };
        placements.push({ position: command.position, itemId: command.itemId });
        placements.sort((a, b) => a.position - b.position);
      } else if (command.action === 'remove') {
        if (!placements.some((p) => p.position === command.position)) return { status: 404, body: { error: 'placement_not_found' } };
        placements = placements.filter((p) => p.position !== command.position);
      } else {
        const [pet] = await tx.select().from(pets).where(and(eq(pets.id, command.petId), eq(pets.userId, userId))).for('update');
        if (!pet) return { status: 404, body: { error: 'pet_not_found' } };
        if (!pet.alive) return { status: 409, body: { error: 'pet_dead' } };
        if (command.action === 'begin_adventure') {
          const error = await beginAdventure(tx, userId, pet.id, now);
          if (error) return { status: 409, body: { error } };
          message = `${pet.name} is ready for a day together. Give affection, share a meal, and finish a new game.`;
        } else if (command.action === 'claim_adventure') {
          const error = await claimAdventure(tx, userId, pet.id, now);
          if (error) return { status: 409, body: { error } };
          message = 'A keepsake for your room, and another page in your story. +10 bond.';
        } else if (command.action === 'equip_bandana') {
          const profile = await ensureCompanion(tx, pet.id);
          if (profile.bondXp < BANDANAS[command.bandana]) return { status: 403, body: { error: 'bandana_locked' } };
          await tx.update(petCompanions).set({ bandana: command.bandana }).where(eq(petCompanions.petId, pet.id));
          message = `${pet.name} is wearing the ${command.bandana} bandana.`;
        } else {
          if (now < home.nextCareAt) return { status: 429, body: { error: 'care_cooldown' } };
          let hunger = pet.hunger;
          let happiness = Math.min(100, pet.happiness + 2);
          let careItemName: string | null = null;
          if (command.action !== 'pet') {
            const [owned] = await tx.select({ quantity: inventory.quantity, name: storeItems.name, category: storeItems.category, effect: storeItems.effect }).from(inventory)
              .innerJoin(storeItems, eq(storeItems.id, inventory.itemId))
              .where(and(eq(inventory.userId, userId), eq(inventory.itemId, command.itemId))).for('update', { of: inventory });
            if (!owned || owned.quantity <= 0) return { status: 403, body: { error: 'item_not_owned' } };
            careItemName = owned.name;
            if (command.action === 'play') {
              if (owned.category !== 'toy') return { status: 400, body: { error: 'item_not_toy' } };
              // All toys have the same care benefit. Price cannot buy ranked advantage.
              happiness = Math.min(100, pet.happiness + 5);
            } else {
              if (!['food', 'treat'].includes(owned.category)) return { status: 400, body: { error: 'item_not_edible' } };
              const effect = effectSchema.parse(owned.effect);
              hunger = Math.min(100, pet.hunger + effect.hungerRestore);
              happiness = Math.min(100, pet.happiness + effect.happinessBoost);
              await tx.update(inventory).set({ quantity: sql`${inventory.quantity} - 1` }).where(and(eq(inventory.userId, userId), eq(inventory.itemId, command.itemId)));
            }
          }
          await tx.update(pets).set({ hunger, happiness }).where(eq(pets.id, pet.id));
          message = await recordCompanionCare(tx, userId, pet.id, command.action, careItemName, now);
          nextCareAt = new Date(now.getTime() + 10_000);
        }
      }
      const [updated] = await tx.update(petHomes).set({ version: home.version + 1, placements, nextCareAt }).where(eq(petHomes.userId, userId)).returning();
      if (!updated) throw new Error('home disappeared');
      const body = { home: await snapshot(tx, userId, updated), action: command.action, ...(message ? { message } : {}) };
      await tx.insert(homeActions).values({ userId, actionId: command.actionId, requestHash: hash, response: body });
      return { status: 200, body };
    });
    if (result.status !== 200) {
      const rejected = z.object({ error: z.string() }).parse(result.body);
      await db.insert(securityAuditEvents).values({
        userId, eventType: 'home.action_rejected',
        metadata: { action: command.action, reason: rejected.error },
      });
      if (result.status === 429) reply.header('Retry-After', rejected.error === 'home_rate_limited' ? '60' : '10');
    }
    return reply.code(result.status).send(result.body);
  });
}
