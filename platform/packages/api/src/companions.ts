/**
 * Threats: prevents client-assigned XP, rerolled identities, duplicate daily credit,
 * fabricated completion and duplicate keepsakes. Does not detect legal bot play.
 * All exported mutation helpers run inside a transaction after locking the user.
 */
import { randomInt } from 'node:crypto';
import { and, desc, eq, sql } from 'drizzle-orm';
import { type Db, inventory, petCompanions, petDailyAdventures, petMemories, storeItems } from '@paws/db';

type Tx = Parameters<Parameters<Db['transaction']>[0]>[0];
type Companion = typeof petCompanions.$inferSelect;
type Adventure = typeof petDailyAdventures.$inferSelect;
export const BANDANAS = { moss: 0, sunflower: 40, berry: 100, midnight: 200 } as const;
const GAME_NAMES: Record<string, string> = { paw_match: 'Paw Match', trail_tails: 'Trail Tails', midnight_pantry: 'Midnight Pantry', lantern_lines: 'Lantern Lines', pocket_post: 'Pocket Post', parade_practice: 'Parade Practice' };
const KEEPSAKES = [
  { id: 'd37304c1-11b0-40a2-9e10-000000000001', name: 'Pressed Clover', emoji: '🍀', description: 'A tiny reminder of a day spent together.' },
  { id: 'd37304c1-11b0-40a2-9e10-000000000002', name: 'Sunlit Pebble', emoji: '🪨', description: 'A smooth little stone from a shared adventure.' },
  { id: 'd37304c1-11b0-40a2-9e10-000000000003', name: 'Little Trail Map', emoji: '🗺️', description: 'All the best paths lead back home.' },
] as const;

function pick<T>(values: readonly T[]): T { return values[randomInt(values.length)]!; }
function dayOf(now: Date) { return now.toISOString().slice(0, 10); }
function adventureKey(userId: string, date: string) { return and(eq(petDailyAdventures.userId, userId), eq(petDailyAdventures.adventureDate, date)); }
function keepsakeFor(date: string) { return KEEPSAKES[Math.floor(Date.parse(`${date}T00:00:00Z`) / 86_400_000) % KEEPSAKES.length]!; }

export async function ensureCompanion(tx: Tx, petId: string): Promise<Companion> {
  let [profile] = await tx.select().from(petCompanions).where(eq(petCompanions.petId, petId)).for('update');
  if (!profile) {
    [profile] = await tx.insert(petCompanions).values({
      petId, personality: pick(['curious', 'gentle', 'playful'] as const),
      coat: pick(['honey', 'silver', 'cocoa', 'cream'] as const), marking: pick(['blaze', 'socks', 'speckles'] as const),
      favoriteFood: pick(['Clover Crunch', 'Sunbeam Nibbles']),
      favoriteToy: pick(['Squeaky Moon', 'Rolling Acorn', 'Ribbon Comet']),
      favoriteGame: pick(Object.keys(GAME_NAMES)),
    }).returning();
    await tx.insert(petMemories).values({ petId, eventKey: 'first-home', kind: 'milestone', title: 'A place to belong', detail: 'The first page of your story together.', xpDelta: 0 });
  }
  if (!profile) throw new Error('companion initialization failed');
  return profile;
}

async function remember(tx: Tx, petId: string, eventKey: string, kind: string, title: string, detail: string, xp: number, now: Date) {
  const profile = await ensureCompanion(tx, petId);
  const delta = Math.min(xp, 10_000 - profile.bondXp);
  const inserted = await tx.insert(petMemories).values({ petId, eventKey, kind, title, detail, xpDelta: delta, createdAt: now })
    .onConflictDoNothing({ target: [petMemories.petId, petMemories.eventKey] }).returning({ id: petMemories.id });
  if (inserted.length === 0) return 0;
  const total = profile.bondXp + delta;
  if (delta > 0) await tx.update(petCompanions).set({ bondXp: total }).where(eq(petCompanions.petId, petId));
  for (const [bandana, threshold] of Object.entries(BANDANAS)) {
    if (threshold > profile.bondXp && threshold <= total) {
      await tx.insert(petMemories).values({ petId, eventKey: `unlock:${bandana}`, kind: 'milestone', title: `The ${bandana} bandana`, detail: 'A little symbol of a growing friendship. Equip it from your companion card.', xpDelta: 0, createdAt: now }).onConflictDoNothing();
    }
  }
  return delta;
}

export async function recordCompanionCare(tx: Tx, userId: string, petId: string, action: 'pet' | 'feed' | 'play', itemName: string | null, now: Date): Promise<string> {
  const profile = await ensureCompanion(tx, petId);
  const date = dayOf(now);
  const favorite = (action === 'feed' && itemName === profile.favoriteFood) || (action === 'play' && itemName === profile.favoriteToy);
  const copy = action === 'pet' ? { title: 'A little love', detail: profile.personality === 'gentle' ? 'A quiet nuzzle, perfectly content.' : profile.personality === 'curious' ? 'A curious tilt of the head, then a happy nuzzle.' : 'A happy bounce and an enthusiastic greeting.' } : action === 'feed' ? { title: 'A meal together', detail: favorite ? 'That favorite snack gets an extra-happy wiggle.' : 'A full bowl and a moment worth remembering.' } : { title: 'Time to play', detail: favorite ? 'The favorite toy! Your companion can hardly sit still.' : 'A little playtime brightens the whole room.' };
  const awarded = await remember(tx, petId, `care:${date}:${action}`, 'care', copy.title, copy.detail, action === 'feed' ? 4 : 3, now);
  if (favorite) await remember(tx, petId, `favorite:${action}`, 'discovery', `A favorite ${action === 'feed' ? 'snack' : 'toy'}`, `${itemName} has a special place in this pet’s heart.`, 0, now);
  if (action !== 'play') {
    const column = action === 'pet' ? petDailyAdventures.affectionAt : petDailyAdventures.fedAt;
    await tx.update(petDailyAdventures).set(action === 'pet' ? { affectionAt: sql`COALESCE(${column}, ${now})` } : { fedAt: sql`COALESCE(${column}, ${now})` })
      .where(and(adventureKey(userId, date), eq(petDailyAdventures.petId, petId)));
  }
  return `${copy.detail}${awarded ? ` +${awarded} bond.` : ' Today’s bond for this care activity is already recorded.'}`;
}

async function completedGame(tx: Tx, adventure: Adventure, now: Date) {
  const end = new Date(Date.parse(`${adventure.adventureDate}T00:00:00Z`) + 86_400_000);
  // The active daily companion accompanies Paw Match (which has no pet field).
  // All hosted games must belong to that same pet. A saved game begun before
  // the adventure cannot be stockpiled for later credit. Practice is eligible.
  const result = await tx.execute<{ id: string; game_type: string }>(sql`
    SELECT id, game_type FROM (
      SELECT id, 'paw_match' AS game_type, created_at, completed_at FROM game_sessions
      WHERE user_id = ${adventure.userId} AND status = 'completed'
      UNION ALL SELECT id, 'trail_tails', created_at, completed_at FROM trail_sessions
      WHERE user_id = ${adventure.userId} AND pet_id = ${adventure.petId} AND status = 'completed'
      UNION ALL SELECT id, 'midnight_pantry', created_at, completed_at FROM pantry_sessions
      WHERE user_id = ${adventure.userId} AND pet_id = ${adventure.petId} AND status = 'completed'
      UNION ALL SELECT id, 'lantern_lines', created_at, completed_at FROM lantern_sessions
      WHERE user_id = ${adventure.userId} AND pet_id = ${adventure.petId} AND status = 'completed'
      UNION ALL SELECT id, 'pocket_post', created_at, completed_at FROM post_sessions
      WHERE user_id = ${adventure.userId} AND pet_id = ${adventure.petId} AND status = 'completed'
      UNION ALL SELECT id, 'parade_practice', created_at, completed_at FROM parade_sessions
      WHERE user_id = ${adventure.userId} AND pet_id = ${adventure.petId} AND status = 'completed'
    ) AS completed
    WHERE created_at >= ${adventure.startedAt} AND completed_at >= ${adventure.startedAt}
      AND completed_at < ${end} AND completed_at <= ${now}
    ORDER BY completed_at, id LIMIT 1
  `);
  return result.rows[0];
}

export async function syncAdventure(tx: Tx, userId: string, now: Date): Promise<Adventure | null> {
  const key = adventureKey(userId, dayOf(now));
  let [adventure] = await tx.select().from(petDailyAdventures).where(key).for('update');
  if (!adventure) return null;
  if (!adventure.gameSessionId) {
    const game = await completedGame(tx, adventure, now);
    if (game) {
      await remember(tx, adventure.petId, `adventure-game:${adventure.adventureDate}`, 'game', `An outing to ${GAME_NAMES[game.game_type]}`, 'A completed game, verified and remembered. +8 bond for today’s outing.', 8, now);
      const profile = await ensureCompanion(tx, adventure.petId);
      if (profile.favoriteGame === game.game_type) await remember(tx, adventure.petId, 'favorite:game', 'discovery', 'A favorite little world', `${GAME_NAMES[game.game_type]} always brings out this companion’s adventurous side.`, 0, now);
      [adventure] = await tx.update(petDailyAdventures).set({ gameSessionId: game.id, gameType: game.game_type }).where(key).returning();
    }
  }
  return adventure ?? null;
}

export async function beginAdventure(tx: Tx, userId: string, petId: string, now: Date): Promise<string | null> {
  const [existing] = await tx.select().from(petDailyAdventures).where(adventureKey(userId, dayOf(now)));
  if (existing) return 'adventure_already_started';
  await ensureCompanion(tx, petId);
  await tx.insert(petDailyAdventures).values({ userId, petId, adventureDate: dayOf(now), startedAt: now });
  return null;
}

export async function claimAdventure(tx: Tx, userId: string, petId: string, now: Date): Promise<string | null> {
  const adventure = await syncAdventure(tx, userId, now);
  if (!adventure || adventure.petId !== petId) return 'adventure_not_found';
  if (adventure.claimedAt) return 'adventure_already_claimed';
  if (!adventure.affectionAt || !adventure.fedAt || !adventure.gameSessionId) return 'adventure_not_ready';
  const keepsake = keepsakeFor(adventure.adventureDate);
  await tx.insert(storeItems).values({ id: keepsake.id, name: keepsake.name, description: keepsake.description, category: 'decor', priceMinor: 0n, currency: 'PAWS', effect: { emoji: keepsake.emoji }, active: false }).onConflictDoNothing({ target: storeItems.id });
  await tx.insert(inventory).values({ userId, itemId: keepsake.id, quantity: 1 }).onConflictDoUpdate({ target: [inventory.userId, inventory.itemId], set: { quantity: sql`${inventory.quantity} + 1` } });
  await tx.update(petDailyAdventures).set({ claimedAt: now, keepsakeId: keepsake.id }).where(adventureKey(userId, adventure.adventureDate));
  await remember(tx, petId, `adventure:${adventure.adventureDate}`, 'keepsake', keepsake.name, `${keepsake.description} Added to your furniture bag.`, 10, now);
  return null;
}

export function publicAdventure(adventure: Adventure | null, now: Date) {
  return {
    date: dayOf(now), resetsAt: new Date(Date.parse(`${dayOf(now)}T00:00:00Z`) + 86_400_000).toISOString(),
    petId: adventure?.petId ?? null, startedAt: adventure?.startedAt.toISOString() ?? null,
    affection: !!adventure?.affectionAt, fed: !!adventure?.fedAt, game: !!adventure?.gameSessionId,
    gameName: adventure?.gameType ? GAME_NAMES[adventure.gameType] ?? null : null,
    claimed: !!adventure?.claimedAt, keepsake: keepsakeFor(dayOf(now)),
  };
}

export async function publicCompanion(tx: Tx, petId: string, now: Date) {
  const profile = await ensureCompanion(tx, petId);
  const recent = await tx.select().from(petMemories).where(eq(petMemories.petId, petId)).orderBy(desc(petMemories.createdAt), desc(petMemories.id)).limit(12);
  const today = await tx.select({ eventKey: petMemories.eventKey }).from(petMemories).where(and(eq(petMemories.petId, petId), sql`${petMemories.eventKey} IN (${`care:${dayOf(now)}:pet`}, ${`care:${dayOf(now)}:feed`}, ${`care:${dayOf(now)}:play`})`));
  const levels = [{ at: 0, name: 'New friends' }, { at: 40, name: 'Finding our rhythm' }, { at: 100, name: 'Trusted companions' }, { at: 200, name: 'Inseparable' }];
  const level = [...levels].reverse().find((l) => profile.bondXp >= l.at)!;
  return {
    personality: profile.personality,
    appearance: { coat: profile.coat, marking: profile.marking, bandana: profile.bandana },
    favorites: { food: profile.favoriteFood, toy: profile.favoriteToy, game: profile.favoriteGame, gameName: GAME_NAMES[profile.favoriteGame] },
    bond: { xp: profile.bondXp, level: level.name, levelStart: level.at, nextLevelAt: levels.find((l) => l.at > profile.bondXp)?.at ?? null },
    unlocks: Object.entries(BANDANAS).map(([key, xp]) => ({ key, xp, unlocked: profile.bondXp >= xp })),
    expressions: { smile: profile.bondXp >= 20, hearts: profile.bondXp >= 60 },
    dailyCare: { affection: today.some((e) => e.eventKey.endsWith(':pet')), feed: today.some((e) => e.eventKey.endsWith(':feed')), play: today.some((e) => e.eventKey.endsWith(':play')) },
    journal: recent.map((entry) => ({ id: entry.id, kind: entry.kind, title: entry.title, detail: entry.detail, xp: entry.xpDelta, createdAt: entry.createdAt.toISOString() })),
  };
}
