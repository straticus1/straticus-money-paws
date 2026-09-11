import { randomBytes, randomUUID } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { eq, sql } from 'drizzle-orm';
import type { FastifyInstance } from 'fastify';
import { createDb, homeActions, inventory, petHomes, pets, securityAuditEvents, storeItems, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import { buildApp } from './app.js';

const TEST_URL = process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test_home';
let handle: DbHandle;
let app: FastifyInstance;
let otherApp: FastifyInstance;

beforeAll(async () => {
  process.env['AUTH_SECRET'] = randomBytes(32).toString('base64');
  await runMigrations(TEST_URL);
  handle = createDb(TEST_URL);
  app = buildApp({ db: handle.db });
  otherApp = buildApp({ db: handle.db });
  await Promise.all([app.ready(), otherApp.ready()]);
});
afterAll(async () => { await Promise.all([app?.close(), otherApp?.close()]); await handle?.pool.end(); });
beforeEach(async () => { await handle.db.execute(sql`TRUNCATE users, store_items CASCADE`); });

function auth(token: string) { return { authorization: `Bearer ${token}` }; }
async function player(name: string) {
  const result = await app.inject({ method: 'POST', url: '/api/v1/auth/register', payload: { email: `${name}@example.test`, username: name, password: 'correct horse battery staple' } });
  expect(result.statusCode).toBe(201);
  const { token, user } = result.json();
  const created = await app.inject({ method: 'POST', url: '/api/v1/pets', headers: auth(token), payload: { name: 'Clover', species: 'dog' } });
  expect(created.statusCode).toBe(201);
  const petId = created.json().pet.id as string;
  await handle.db.update(pets).set({ hunger: 20, happiness: 30 }).where(eq(pets.id, petId));
  return { token: token as string, userId: user.id as string, petId };
}
async function owned(userId: string, category: string, quantity = 1) {
  const [item] = await handle.db.insert(storeItems).values({ name: `${category} ${randomUUID()}`, category, priceMinor: 10n, currency: 'PAWS', effect: { hungerRestore: 30, happinessBoost: 10, emoji: '🌿' } }).returning();
  await handle.db.insert(inventory).values({ userId, itemId: item!.id, quantity });
  return item!.id;
}
async function read(token: string) { return app.inject({ method: 'GET', url: '/api/v1/home', headers: auth(token) }); }
async function act(token: string, action: Record<string, unknown>, version = 0, actionId = randomUUID(), server = app) {
  return server.inject({ method: 'POST', url: '/api/v1/home/actions', headers: auth(token), payload: { ...action, expectedVersion: version, actionId } });
}

describe('private home and authoritative actions', () => {
  it('lets a new player earn PAWS, buy catalog food, and feed at home without a deposit', async () => {
    const user = await player('homejourney');
    const catalog = JSON.parse(await readFile(new URL('../../db/seed/store-items.json', import.meta.url), 'utf8')) as Array<{ name: string; item_type: string; currency?: string; price_minor?: number; hunger_restore: number; happiness_boost: number; emoji: string }>;
    const food = catalog.find((item) => item.name === 'Clover Crunch')!;
    expect(food.currency).toBe('PAWS');
    const [item] = await handle.db.insert(storeItems).values({ name: food.name, category: food.item_type, priceMinor: BigInt(food.price_minor!), currency: 'PAWS', effect: { hungerRestore: food.hunger_restore, happinessBoost: food.happiness_boost, emoji: food.emoji } }).returning();
    const started = await app.inject({ method: 'POST', url: '/api/v1/games/paw-match', headers: auth(user.token) });
    type Board = { id: string; status: string; cards: Array<{ position: number; matched: boolean; symbol?: string }> };
    let game = started.json().game as Board;
    const seen = new Map<number, string>();
    async function flip(position: number) {
      const response = await app.inject({ method: 'POST', url: `/api/v1/games/paw-match/${game.id}/flip`, headers: auth(user.token), payload: { position, actionId: randomUUID() } });
      expect(response.statusCode).toBe(200);
      game = response.json().game;
      for (const card of game.cards) if (card.symbol) seen.set(card.position, card.symbol);
    }
    // Only use legally revealed cards, just like a player remembering the board.
    for (let position = 0; position < 12; position++) await flip(position);
    for (const symbol of new Set(seen.values())) {
      const pair = [...seen].filter(([, value]) => value === symbol).map(([position]) => position);
      if (!game.cards[pair[0]!]!.matched) { await flip(pair[0]!); await flip(pair[1]!); }
    }
    expect(game.status).toBe('completed');
    const purchase = await app.inject({ method: 'POST', url: '/api/v1/store/purchase', headers: { ...auth(user.token), 'idempotency-key': randomUUID() }, payload: { itemId: item!.id, quantity: 1 } });
    expect(purchase.statusCode).toBe(200);
    const fed = await act(user.token, { action: 'feed', petId: user.petId, itemId: item!.id });
    expect(fed.statusCode).toBe(200);
    expect(fed.json().home.pets[0].hunger).toBe(40);
    expect(fed.json().home.items).toEqual([]);
  });

  it('requires authentication and only includes owned pets and inventory', async () => {
    expect((await app.inject({ method: 'GET', url: '/api/v1/home' })).statusCode).toBe(401);
    expect((await app.inject({ method: 'POST', url: '/api/v1/home/actions', payload: {} })).statusCode).toBe(401);
    const alice = await player('homealice');
    const bob = await player('homebob');
    const itemId = await owned(alice.userId, 'decor');
    const response = await read(alice.token);
    expect(response.headers['cache-control']).toBe('no-store');
    const home = response.json().home;
    expect(home.pets.map((p: { id: string }) => p.id)).toEqual([alice.petId]);
    expect(home.items.map((i: { itemId: string }) => i.itemId)).toEqual([itemId]);
    expect(JSON.stringify(home)).not.toContain(bob.petId);
    expect(home.version).toBe(0);
    expect((await act(bob.token, { action: 'pet', petId: alice.petId })).statusCode).toBe(404);
    expect((await act(bob.token, { action: 'place', itemId, position: 0 })).statusCode).toBe(403);
    const events = await handle.db.select().from(securityAuditEvents).where(eq(securityAuditEvents.eventType, 'home.action_rejected'));
    expect(events.map((e) => (e.metadata as { reason: string }).reason)).toContain('item_not_owned');
  });

  it('rejects forged state, unsupported actions, oversized bodies and invalid positions', async () => {
    const user = await player('homeforgery');
    const itemId = await owned(user.userId, 'decor');
    for (const extra of [{ happiness: 100 }, { rewardMinor: '1000' }, { currency: 'USD' }, { userId: randomUUID() }, { serverNow: '2099-01-01' }]) {
      expect((await act(user.token, { action: 'pet', petId: user.petId, ...extra })).statusCode).toBe(400);
    }
    for (const position of [-1, 20, 0.5]) expect((await act(user.token, { action: 'place', itemId, position })).statusCode).toBe(400);
    expect((await act(user.token, { action: 'award', petId: user.petId })).statusCode).toBe(400);
    expect((await act(user.token, { action: 'pet', petId: user.petId, padding: 'x'.repeat(3000) })).statusCode).toBe(413);
    expect((await read(user.token)).json().home.version).toBe(0);
  });

  it('consumes food once under concurrent retries and binds the payload to its action ID', async () => {
    const user = await player('homereplay');
    const itemId = await owned(user.userId, 'food', 2);
    const actionId = randomUUID();
    const command = { action: 'feed', petId: user.petId, itemId };
    const responses = await Promise.all([act(user.token, command, 0, actionId), act(user.token, command, 0, actionId, otherApp)]);
    expect(responses.map((r) => r.statusCode)).toEqual([200, 200]);
    expect(responses[0]!.json()).toEqual(responses[1]!.json());
    const home = (await read(user.token)).json().home;
    expect(home.items[0].quantity).toBe(1);
    expect(home.pets[0].hunger).toBe(50);
    expect(home.pets[0].happiness).toBe(40);
    expect(home.version).toBe(1);
    expect((await act(user.token, { action: 'pet', petId: user.petId }, 0, actionId)).json().error).toBe('action_conflict');
    expect((await handle.db.select().from(homeActions)).length).toBe(1);
  });

  it('canonicalizes JSON key order and keeps original receipt after newer changes', async () => {
    const user = await player('homecanonical');
    const itemId = await owned(user.userId, 'decor');
    const actionId = randomUUID();
    const original = await act(user.token, { action: 'place', itemId, position: 0 }, 0, actionId);
    expect((await act(user.token, { action: 'remove', position: 0 }, 1)).statusCode).toBe(200);
    const retry = await app.inject({ method: 'POST', url: '/api/v1/home/actions', headers: auth(user.token), payload: { position: 0, actionId, itemId, expectedVersion: 0, action: 'place' } });
    expect(retry.json()).toEqual(original.json());
    expect((await read(user.token)).json().home.placements).toEqual([]);
    expect((await read(user.token)).json().home.version).toBe(2);
  });

  it('prevents stale tabs and simultaneous actions from overwriting furniture', async () => {
    const user = await player('homestale');
    const itemId = await owned(user.userId, 'decor', 2);
    const responses = await Promise.all([act(user.token, { action: 'place', itemId, position: 0 }), act(user.token, { action: 'place', itemId, position: 1 }, 0, randomUUID(), otherApp)]);
    expect(responses.map((r) => r.statusCode).sort()).toEqual([200, 409]);
    expect(responses.find((r) => r.statusCode === 409)!.json().error).toBe('stale_home');
    expect((await read(user.token)).json().home.placements).toHaveLength(1);
  });

  it('enforces placement quantity, occupancy, category and removal without duplicating inventory', async () => {
    const user = await player('homefurniture');
    const itemId = await owned(user.userId, 'decor');
    const foodId = await owned(user.userId, 'food');
    expect((await act(user.token, { action: 'place', itemId: foodId, position: 0 })).json().error).toBe('item_not_placeable');
    expect((await act(user.token, { action: 'place', itemId, position: 0 })).statusCode).toBe(200);
    expect((await act(user.token, { action: 'place', itemId, position: 0 }, 1)).json().error).toBe('position_occupied');
    expect((await act(user.token, { action: 'place', itemId, position: 1 }, 1)).json().error).toBe('all_copies_placed');
    expect((await act(user.token, { action: 'remove', position: 19 }, 1)).statusCode).toBe(404);
    expect((await act(user.token, { action: 'remove', position: 0 }, 1)).statusCode).toBe(200);
    expect((await act(user.token, { action: 'place', itemId, position: 19 }, 2)).statusCode).toBe(200);
    const home = (await read(user.token)).json().home;
    expect(home.items.find((i: { itemId: string }) => i.itemId === itemId).quantity).toBe(1);
    expect(home.placements).toEqual([{ itemId, position: 19 }]);
  });

  it('requires an owned toy and enforces care cooldown across instances and pets', async () => {
    const user = await player('homecare');
    const decorId = await owned(user.userId, 'decor');
    const toyId = await owned(user.userId, 'toy');
    expect((await act(user.token, { action: 'play', petId: user.petId, itemId: decorId })).json().error).toBe('item_not_toy');
    expect((await act(user.token, { action: 'play', petId: user.petId, itemId: randomUUID() })).statusCode).toBe(403);
    const first = await act(user.token, { action: 'play', petId: user.petId, itemId: toyId });
    expect(first.statusCode).toBe(200);
    expect(first.json().home.pets[0].happiness).toBe(35);
    const second = await app.inject({ method: 'POST', url: '/api/v1/pets', headers: auth(user.token), payload: { name: 'Buddy', species: 'cat' } });
    expect((await act(user.token, { action: 'pet', petId: second.json().pet.id }, 1, randomUUID(), otherApp)).json().error).toBe('care_cooldown');
    const inv = await handle.db.select().from(inventory).where(eq(inventory.itemId, toyId));
    expect(inv[0]!.quantity).toBe(1);
    await handle.db.update(petHomes).set({ nextCareAt: new Date(0) }).where(eq(petHomes.userId, user.userId));
    expect((await act(user.token, { action: 'pet', petId: user.petId }, 1)).statusCode).toBe(200);
    expect((await read(user.token)).json().home.pets.find((p: { id: string }) => p.id === user.petId).happiness).toBe(37);
  });

  it('rejects care for deceased pets and rolls back on invalid configured effects', async () => {
    const user = await player('homedead');
    const itemId = await owned(user.userId, 'food');
    await handle.db.update(pets).set({ alive: false }).where(eq(pets.id, user.petId));
    expect((await act(user.token, { action: 'feed', petId: user.petId, itemId })).json().error).toBe('pet_dead');
    await handle.db.update(pets).set({ alive: true }).where(eq(pets.id, user.petId));
    await handle.db.update(storeItems).set({ effect: { hungerRestore: -999 } }).where(eq(storeItems.id, itemId));
    expect((await act(user.token, { action: 'feed', petId: user.petId, itemId })).statusCode).toBe(500);
    const home = (await read(user.token)).json().home;
    expect(home.version).toBe(0);
    expect(home.items[0].quantity).toBe(1);
    expect(home.pets[0].hunger).toBe(20);
  });

  it('shares the action budget across server instances and resets using server time', async () => {
    const user = await player('homelimit');
    await read(user.token);
    await handle.db.update(petHomes).set({ actionCount: 29 }).where(eq(petHomes.userId, user.userId));
    expect((await act(user.token, { action: 'remove', position: 0 }, 0, randomUUID(), otherApp)).statusCode).toBe(404);
    expect((await act(user.token, { action: 'pet', petId: user.petId })).json().error).toBe('home_rate_limited');
    await handle.db.update(petHomes).set({ windowStartedAt: new Date(0) }).where(eq(petHomes.userId, user.userId));
    expect((await act(user.token, { action: 'pet', petId: user.petId })).statusCode).toBe(200);
  });

  it('denies cross-origin cookie actions and does not issue any money for care', async () => {
    const registered = await app.inject({ method: 'POST', url: '/api/v1/auth/register', headers: { 'x-paws-session-mode': 'cookie' }, payload: { email: 'cookiehome@example.test', username: 'cookiehome', password: 'correct horse battery staple' } });
    const cookie = registered.cookies.map((c) => `${c.name}=${c.value}`).join('; ');
    expect((await app.inject({ method: 'POST', url: '/api/v1/home/actions', headers: { cookie, origin: 'https://attacker.example' }, payload: { action: 'remove', position: 0, expectedVersion: 0, actionId: randomUUID() } })).statusCode).toBe(403);
    const user = await player('homecurrency');
    const before = await app.inject({ method: 'GET', url: '/api/v1/me/balances', headers: auth(user.token) });
    expect((await act(user.token, { action: 'pet', petId: user.petId })).statusCode).toBe(200);
    const after = await app.inject({ method: 'GET', url: '/api/v1/me/balances', headers: auth(user.token) });
    expect(after.json()).toEqual(before.json());
    await expect(handle.db.update(homeActions).set({ requestHash: 'changed' })).rejects.toThrow();
  });
});
