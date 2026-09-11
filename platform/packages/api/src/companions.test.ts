import { randomBytes, randomUUID } from 'node:crypto';
import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { and, eq, sql } from 'drizzle-orm';
import type { FastifyInstance } from 'fastify';
import { createDb, gameSessions, inventory, lanternSessions, pantrySessions, paradeSessions, petCompanions, petDailyAdventures, petHomes, petMemories, pets, postSessions, storeItems, trailSessions, users, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import { buildApp } from './app.js';
import { beginAdventure, publicAdventure, publicCompanion, recordCompanionCare, syncAdventure } from './companions.js';

const TEST_URL = process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_home';
let handle: DbHandle;
let app: FastifyInstance;
let second: FastifyInstance;
beforeAll(async () => {
  process.env['AUTH_SECRET'] = randomBytes(32).toString('base64');
  await runMigrations(TEST_URL);
  handle = createDb(TEST_URL);
  app = buildApp({ db: handle.db }); second = buildApp({ db: handle.db });
  await Promise.all([app.ready(), second.ready()]);
});
afterAll(async () => { await Promise.all([app?.close(), second?.close()]); await handle?.pool.end(); });
beforeEach(async () => { await handle.db.execute(sql`TRUNCATE users, store_items CASCADE`); });
function auth(token: string) { return { authorization: `Bearer ${token}` }; }
async function player(name: string) {
  const registered = await app.inject({ method: 'POST', url: '/api/v1/auth/register', payload: { email: `${name}@example.test`, username: name, password: 'correct horse battery staple' } });
  const { token, user } = registered.json();
  const created = await app.inject({ method: 'POST', url: '/api/v1/pets', headers: auth(token), payload: { name: 'Clover', species: 'dog' } });
  expect(created.statusCode).toBe(201);
  return { token: token as string, userId: user.id as string, petId: created.json().pet.id as string };
}
async function home(token: string) { const response = await app.inject({ method: 'GET', url: '/api/v1/home', headers: auth(token) }); expect(response.statusCode).toBe(200); return response.json().home; }
async function act(token: string, action: Record<string, unknown>, version: number, id = randomUUID(), server = app) { return server.inject({ method: 'POST', url: '/api/v1/home/actions', headers: auth(token), payload: { ...action, expectedVersion: version, actionId: id } }); }
async function freshAct(token: string, action: Record<string, unknown>) { return act(token, action, (await home(token)).version); }
async function resetCooldown(userId: string) { await handle.db.update(petHomes).set({ nextCareAt: new Date(0) }).where(eq(petHomes.userId, userId)); }
async function item(userId: string, name: string, category: string, quantity = 4) {
  const [row] = await handle.db.insert(storeItems).values({ name, category, priceMinor: 5n, currency: 'PAWS', effect: { emoji: '🌿', hungerRestore: 20, happinessBoost: 5 } }).returning();
  await handle.db.insert(inventory).values({ userId, itemId: row!.id, quantity });
  return row!.id;
}
async function solveMatch(token: string) {
  const started = await app.inject({ method: 'POST', url: '/api/v1/games/paw-match', headers: auth(token) });
  let game = started.json().game as { id: string; status: string; cards: Array<{ position: number; symbol?: string; matched: boolean }> };
  const seen = new Map<number, string>();
  async function flip(position: number) {
    const response = await app.inject({ method: 'POST', url: `/api/v1/games/paw-match/${game.id}/flip`, headers: auth(token), payload: { position, actionId: randomUUID() } });
    expect(response.statusCode).toBe(200); game = response.json().game;
    for (const card of game.cards) if (card.symbol) seen.set(card.position, card.symbol);
  }
  for (let position = 0; position < 12; position++) await flip(position);
  for (const symbol of new Set(seen.values())) {
    const pair = [...seen].filter(([, value]) => value === symbol).map(([position]) => position);
    if (!game.cards[pair[0]!]!.matched) { await flip(pair[0]!); await flip(pair[1]!); }
  }
  expect(game.status).toBe('completed');
  return game.id;
}

describe('companion identity and bounded memories', () => {
  it('persists appearance and preferences across visits, actions and API instances', async () => {
    const user = await player('identity');
    const first = (await home(user.token)).pets[0].companion;
    expect(['curious', 'gentle', 'playful']).toContain(first.personality);
    expect(first.journal).toHaveLength(1);
    expect(first.bond.xp).toBe(0);
    const another = await second.inject({ method: 'GET', url: '/api/v1/home', headers: auth(user.token) });
    expect(another.json().home.pets[0].companion).toEqual(first);
    for (const extra of [{ coat: 'gold' }, { bondXp: 9999 }, { favoriteFood: 'hacked' }, { completed: true }, { adventureDate: '2030-01-01' }]) expect((await freshAct(user.token, { action: 'pet', petId: user.petId, ...extra })).statusCode).toBe(400);
    expect((await freshAct(user.token, { action: 'equip_bandana', petId: user.petId, bandana: 'midnight' })).statusCode).toBe(403);
    expect((await home(user.token)).pets[0].companion).toEqual(first);
  });

  it('credits each care activity once per UTC day and discovers favorites without an XP bonus', async () => {
    const user = await player('boundedcare');
    const initial = (await home(user.token)).pets[0].companion;
    const foodId = await item(user.userId, initial.favorites.food, 'food');
    const toyId = await item(user.userId, initial.favorites.toy, 'toy');
    for (let repetition = 0; repetition < 2; repetition++) {
      for (const action of [{ action: 'pet', petId: user.petId }, { action: 'feed', petId: user.petId, itemId: foodId }, { action: 'play', petId: user.petId, itemId: toyId }]) {
        await resetCooldown(user.userId);
        const response = await freshAct(user.token, action);
        expect(response.statusCode).toBe(200);
        if (action.action === 'feed') expect(response.json().message).toContain('favorite');
      }
    }
    const current = (await home(user.token)).pets[0].companion;
    expect(current.bond.xp).toBe(10);
    expect(current.dailyCare).toEqual({ affection: true, feed: true, play: true });
    expect(current.journal.filter((e: { kind: string }) => e.kind === 'discovery')).toHaveLength(2);
    expect(current.appearance).toEqual(initial.appearance);
  });

  it('unlocks and equips earned cosmetics while keeping other accounts out', async () => {
    const alice = await player('unlockalice'); const bob = await player('unlockbob');
    await home(alice.token);
    await handle.db.update(petCompanions).set({ bondXp: 39 }).where(eq(petCompanions.petId, alice.petId));
    expect((await freshAct(alice.token, { action: 'pet', petId: alice.petId })).statusCode).toBe(200);
    expect((await freshAct(alice.token, { action: 'equip_bandana', petId: alice.petId, bandana: 'sunflower' })).statusCode).toBe(200);
    const profile = (await home(alice.token)).pets[0].companion;
    expect(profile.appearance.bandana).toBe('sunflower');
    expect(profile.expressions.smile).toBe(true);
    expect(profile.journal.filter((e: { title: string }) => e.title === 'The sunflower bandana')).toHaveLength(1);
    expect((await freshAct(bob.token, { action: 'equip_bandana', petId: alice.petId, bandana: 'sunflower' })).statusCode).toBe(404);
    expect(JSON.stringify(await home(bob.token))).not.toContain(alice.petId);
    await expect(handle.db.update(petMemories).set({ xpDelta: 10 })).rejects.toThrow();
  });

  it('resets daily credit at UTC midnight without resetting identity or losing memories', async () => {
    const user = await player('midnightbond');
    const initial = (await home(user.token)).pets[0].companion;
    await handle.db.transaction(async (tx) => {
      await tx.select().from(users).where(eq(users.id, user.userId)).for('update');
      const before = new Date('2026-01-01T23:59:59Z'); const after = new Date('2026-01-02T00:00:00Z');
      await beginAdventure(tx, user.userId, user.petId, before);
      await recordCompanionCare(tx, user.userId, user.petId, 'pet', null, before);
      await recordCompanionCare(tx, user.userId, user.petId, 'pet', null, before);
      expect(publicAdventure(await syncAdventure(tx, user.userId, before), before).affection).toBe(true);
      expect(publicAdventure(await syncAdventure(tx, user.userId, after), after).petId).toBeNull();
      await beginAdventure(tx, user.userId, user.petId, after);
      expect(publicAdventure(await syncAdventure(tx, user.userId, after), after).affection).toBe(false);
      await recordCompanionCare(tx, user.userId, user.petId, 'pet', null, after);
      const profile = await publicCompanion(tx, user.petId, after);
      expect(profile.bond.xp).toBe(6); expect(profile.appearance).toEqual(initial.appearance);
    });
  });
});

describe('daily adventure authority', () => {
  it('locks one companion per account/day and rejects premature or fabricated claims', async () => {
    const alice = await player('dayalice'); const bob = await player('daybob');
    expect((await freshAct(bob.token, { action: 'begin_adventure', petId: alice.petId })).statusCode).toBe(404);
    expect((await freshAct(alice.token, { action: 'begin_adventure', petId: alice.petId })).statusCode).toBe(200);
    expect((await freshAct(alice.token, { action: 'begin_adventure', petId: alice.petId })).json().error).toBe('adventure_already_started');
    expect((await freshAct(alice.token, { action: 'claim_adventure', petId: alice.petId })).json().error).toBe('adventure_not_ready');
    expect((await freshAct(alice.token, { action: 'claim_adventure', petId: alice.petId, gameSessionId: randomUUID(), affection: true, fed: true })).statusCode).toBe(400);
    expect((await home(alice.token)).pets[0].companion.bond.xp).toBe(0);
  });

  it('completes the real care/game/keepsake loop with exactly one grant under concurrent retries', async () => {
    const user = await player('adventureloop');
    const foodId = await item(user.userId, 'Clover Crunch', 'food');
    expect((await freshAct(user.token, { action: 'begin_adventure', petId: user.petId })).statusCode).toBe(200);
    expect((await freshAct(user.token, { action: 'pet', petId: user.petId })).statusCode).toBe(200);
    await resetCooldown(user.userId);
    expect((await freshAct(user.token, { action: 'feed', petId: user.petId, itemId: foodId })).statusCode).toBe(200);
    await solveMatch(user.token);
    const ready = await home(user.token);
    expect(ready.adventure).toMatchObject({ affection: true, fed: true, game: true, claimed: false });
    expect(ready.pets[0].companion.bond.xp).toBe(15);
    expect((await home(user.token)).pets[0].companion.bond.xp).toBe(15);
    const balancesBefore = await app.inject({ method: 'GET', url: '/api/v1/me/balances', headers: auth(user.token) });
    const actionId = randomUUID();
    const command = { action: 'claim_adventure', petId: user.petId };
    const claims = await Promise.all([act(user.token, command, ready.version, actionId), act(user.token, command, ready.version, actionId, second)]);
    expect(claims.map((r) => r.statusCode)).toEqual([200, 200]); expect(claims[0]!.json()).toEqual(claims[1]!.json());
    const claimed = await home(user.token);
    expect(claimed.pets[0].companion.bond.xp).toBe(25);
    expect(claimed.adventure.claimed).toBe(true);
    const keepsake = claimed.items.find((i: { itemId: string }) => i.itemId === claimed.adventure.keepsake.id);
    expect(keepsake.quantity).toBe(1);
    expect((await freshAct(user.token, command)).json().error).toBe('adventure_already_claimed');
    expect((await freshAct(user.token, { action: 'place', itemId: keepsake.itemId, position: 4 })).statusCode).toBe(200);
    const balancesAfter = await app.inject({ method: 'GET', url: '/api/v1/me/balances', headers: auth(user.token) });
    expect(balancesAfter.json()).toEqual(balancesBefore.json());
    const shop = await app.inject({ method: 'GET', url: '/api/v1/store/items' });
    expect(shop.json().items.some((i: { id: string }) => i.id === keepsake.itemId)).toBe(false);
  });

  it('does not carry pre-adventure care, old games, future games or another account’s games into objectives', async () => {
    const user = await player('oldgames'); const other = await player('othergames');
    await freshAct(user.token, { action: 'pet', petId: user.petId });
    const oldGameId = await solveMatch(user.token);
    await freshAct(user.token, { action: 'begin_adventure', petId: user.petId });
    await solveMatch(other.token);
    let current = await home(user.token);
    expect(current.adventure).toMatchObject({ affection: false, fed: false, game: false });
    await handle.db.update(gameSessions).set({ createdAt: new Date('2099-01-01'), completedAt: new Date('2099-01-01') }).where(eq(gameSessions.id, oldGameId));
    current = await home(user.token); expect(current.adventure.game).toBe(false);
    expect(current.pets[0].companion.bond.xp).toBe(3);
  });

  it.each([
    ['trail-tails', trailSessions], ['midnight-pantry', pantrySessions], ['lantern-lines', lanternSessions], ['pocket-post', postSessions], ['parade-practice', paradeSessions],
  ] as const)('only credits verified %s completions hosted by the daily companion', async (slug, table) => {
    const user = await player(`host${slug.replaceAll('-', '')}`);
    await freshAct(user.token, { action: 'begin_adventure', petId: user.petId });
    const another = await app.inject({ method: 'POST', url: '/api/v1/pets', headers: auth(user.token), payload: { name: 'Buddy', species: 'cat' } });
    const buddyId = another.json().pet.id as string;
    const started = await app.inject({ method: 'POST', url: `/api/v1/games/${slug}`, headers: auth(user.token), payload: { petId: buddyId, actionId: randomUUID(), ...(slug === 'trail-tails' ? {} : { mode: 'practice' }) } });
    expect([200, 201]).toContain(started.statusCode);
    const id = started.json().game.id as string;
    await handle.db.update(table).set({ status: 'completed', completedAt: new Date() }).where(eq(table.id, id));
    expect((await home(user.token)).adventure.game).toBe(false);
    // Test only the adapter here; game engines have their own outcome tests.
    await handle.db.update(table).set({ petId: user.petId, status: 'active' }).where(eq(table.id, id));
    expect((await home(user.token)).adventure.game).toBe(false);
    await handle.db.update(table).set({ status: 'completed' }).where(eq(table.id, id));
    expect((await home(user.token)).adventure.game).toBe(true);
    expect((await home(user.token)).pets.find((p: { id: string }) => p.id === user.petId).companion.bond.xp).toBe(8);
  }, 15000);

  it('rolls back keepsake ownership, claim status and XP when fulfillment fails', async () => {
    const user = await player('claimrollback');
    await freshAct(user.token, { action: 'begin_adventure', petId: user.petId });
    const current = await home(user.token);
    await handle.db.update(petDailyAdventures).set({ affectionAt: new Date(), fedAt: new Date(), gameSessionId: randomUUID(), gameType: 'paw_match' }).where(eq(petDailyAdventures.userId, user.userId));
    // An inconsistent trusted catalog must halt the transaction, not grant a substitute.
    await handle.db.insert(storeItems).values({ name: current.adventure.keepsake.name, category: 'decor', priceMinor: 1n });
    expect((await freshAct(user.token, { action: 'claim_adventure', petId: user.petId })).statusCode).toBe(500);
    const after = await home(user.token);
    expect(after.adventure.claimed).toBe(false); expect(after.pets[0].companion.bond.xp).toBe(0);
    expect(after.items).toHaveLength(0);
    expect((await handle.db.select().from(petMemories).where(and(eq(petMemories.petId, user.petId), eq(petMemories.kind, 'keepsake'))))).toHaveLength(0);
  });
});
