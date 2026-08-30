import { and, eq } from 'drizzle-orm';
import { sql } from 'drizzle-orm';
import { dailyGameRewards, petGameProgress, type Db } from '@paws/db';
import { ensureSystemAccount, ensureUserAccount, transfer } from '@paws/ledger';

export const DAILY_GAME_REWARD_CAP = 125n;

function utcDate(now: Date): string {
  return now.toISOString().slice(0, 10);
}

/**
 * Reserve a fixed game reward under the platform-wide UTC daily ceiling.
 * Callers must lock the owning user row first so all games serialize on the
 * same economic boundary.
 */
export async function reserveGameReward(
  db: Db,
  userId: string,
  requestedMinor: bigint,
  now = new Date(),
): Promise<{ awardedMinor: bigint; remainingMinor: bigint }> {
  const rewardDate = utcDate(now);
  await db
    .insert(dailyGameRewards)
    .values({ userId, rewardDate })
    .onConflictDoNothing({ target: [dailyGameRewards.userId, dailyGameRewards.rewardDate] });

  const rows = await db
    .select()
    .from(dailyGameRewards)
    .where(
      and(
        eq(dailyGameRewards.userId, userId),
        eq(dailyGameRewards.rewardDate, rewardDate),
      ),
    )
    .for('update');
  const current = rows[0];
  if (!current) throw new Error('daily game reward row missing after insert');

  const available =
    current.awardedMinor >= DAILY_GAME_REWARD_CAP
      ? 0n
      : DAILY_GAME_REWARD_CAP - current.awardedMinor;
  // Reward tiers remain fixed. We do not issue a surprising partial tier when
  // only a smaller remainder is available.
  const awardedMinor = requestedMinor <= available ? requestedMinor : 0n;
  if (awardedMinor > 0n) {
    await db
      .update(dailyGameRewards)
      .set({
        awardedMinor: current.awardedMinor + awardedMinor,
        rewardedCompletions: current.rewardedCompletions + 1,
        updatedAt: now,
      })
      .where(
        and(
          eq(dailyGameRewards.userId, userId),
          eq(dailyGameRewards.rewardDate, rewardDate),
        ),
      );
  }

  return {
    awardedMinor,
    remainingMinor: available - awardedMinor,
  };
}

export function fixedRewardForStars(stars: number): bigint {
  return stars === 3 ? 10n : stars === 2 ? 8n : 5n;
}

export function fixedBondForStars(stars: number): number {
  return stars === 3 ? 20 : stars === 2 ? 15 : 10;
}

/** Settle a first daily completion after the caller locks the user row. */
export async function settleDailyGame(
  db: Db,
  input: {
    userId: string;
    petId: string;
    gameType: 'lantern_lines' | 'pocket_post' | 'parade_practice';
    ledgerKind: 'lantern_lines_reward' | 'pocket_post_reward' | 'parade_practice_reward';
    sessionId: string;
    stars: number;
    metadata: Record<string, unknown>;
  },
): Promise<{ rewardMinor: bigint; remainingMinor: bigint }> {
  const reserved = await reserveGameReward(db, input.userId, fixedRewardForStars(input.stars));
  const now = new Date();
  await db.insert(petGameProgress).values({
    petId: input.petId,
    gameType: input.gameType,
    bondXp: fixedBondForStars(input.stars),
  }).onConflictDoUpdate({
    target: [petGameProgress.petId, petGameProgress.gameType],
    set: { bondXp: sql`${petGameProgress.bondXp} + ${fixedBondForStars(input.stars)}`, updatedAt: now },
  });
  if (reserved.awardedMinor > 0n) {
    const treasury = await ensureSystemAccount(db, 'game_rewards', 'PAWS');
    const player = await ensureUserAccount(db, input.userId, 'PAWS');
    await transfer(db, {
      idempotencyKey: `${input.ledgerKind}:${input.sessionId}`,
      kind: input.ledgerKind,
      fromAccountId: treasury,
      toAccountId: player,
      amountMinor: reserved.awardedMinor,
      metadata: { ...input.metadata, sessionId: input.sessionId, petId: input.petId, stars: input.stars },
    });
  }
  return { rewardMinor: reserved.awardedMinor, remainingMinor: reserved.remainingMinor };
}
