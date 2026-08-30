/**
 * Seed the v5 database from the legacy catalog export (seed/store-items.json).
 * Legacy prices are floating-point USD; they are converted to integer cents
 * here, once, at the boundary. Idempotent: upserts by unique item name.
 */
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { sql } from 'drizzle-orm';
import { createDb, storeItems } from './index.js';

interface LegacyStoreItem {
  name: string;
  description: string | null;
  item_type: string;
  price_usd: number;
  hunger_restore: number;
  happiness_boost: number;
  duration_hours: number;
  emoji: string;
  age_restricted: 0 | 1;
  is_active: 0 | 1;
  currency?: 'USD' | 'PAWS';
  price_minor?: number;
}

function usdToCents(price: number): bigint {
  if (!Number.isFinite(price) || price < 0) {
    throw new Error(`seed: invalid legacy price ${price}`);
  }
  return BigInt(Math.round(price * 100));
}

export async function seed(connectionString: string): Promise<number> {
  const seedFile = path.join(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    'seed',
    'store-items.json',
  );
  const legacy = JSON.parse(await readFile(seedFile, 'utf8')) as LegacyStoreItem[];
  const { db, pool } = createDb(connectionString);
  try {
    for (const item of legacy) {
      await db
        .insert(storeItems)
        .values({
          name: item.name,
          description: item.description ?? '',
          category: item.item_type,
          priceMinor: item.currency === 'PAWS' && item.price_minor !== undefined
            ? BigInt(item.price_minor)
            : usdToCents(item.price_usd),
          currency: item.currency ?? 'USD',
          effect: {
            hungerRestore: item.hunger_restore,
            happinessBoost: item.happiness_boost,
            durationHours: item.duration_hours,
            emoji: item.emoji,
            ageRestricted: item.age_restricted === 1,
          },
          active: item.is_active === 1,
        })
        .onConflictDoUpdate({
          target: storeItems.name,
          set: {
            description: sql`excluded.description`,
            category: sql`excluded.category`,
            priceMinor: sql`excluded.price_minor`,
            currency: sql`excluded.currency`,
            effect: sql`excluded.effect`,
            active: sql`excluded.active`,
          },
        });
    }
    return legacy.length;
  } finally {
    await pool.end();
  }
}

if (process.argv[1] && fileURLToPath(import.meta.url) === path.resolve(process.argv[1])) {
  const url = process.env['DATABASE_URL'];
  if (!url) {
    console.error('DATABASE_URL is required');
    process.exit(1);
  }
  seed(url)
    .then((n) => console.log(`Seeded ${n} store items`))
    .catch((err) => {
      console.error(err);
      process.exit(1);
    });
}
