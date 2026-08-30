import type { FastifyInstance } from 'fastify';
import { eq, sql } from 'drizzle-orm';
import {
  type Db,
  securityAuditEvents,
  sessions,
  user2fa,
  userSettings,
  userTrophies,
  users,
} from '@paws/db';
import { createSession, hashPassword, verifyPassword } from '@paws/auth';
import { z } from 'zod';
import { setSessionCookie } from '../util.js';

const settingsBody = z.object({
  leaderboardOptIn: z.boolean(),
  trophyShowcase: z.boolean(),
}).strict();

const passwordBody = z.object({
  currentPassword: z.string().min(1).max(512),
  newPassword: z.string().min(8).max(512),
}).strict();

const TROPHIES = [
  { key: 'welcome_home', name: 'Welcome Home', icon: '🔑', description: 'Join the paws.money neighborhood.' },
  { key: 'pet_parent', name: 'Pet Parent', icon: '🐾', description: 'Adopt your first companion.' },
  { key: 'memory_keeper', name: 'Memory Keeper', icon: '🃏', description: 'Complete Paw Match.' },
  { key: 'trailblazer', name: 'Trailblazer', icon: '🧭', description: 'Guide a pet home in Trail Tails.' },
  { key: 'pantry_pro', name: 'Pantry Pro', icon: '🥣', description: 'Serve every guest in Midnight Pantry.' },
  { key: 'lantern_lighter', name: 'Lantern Lighter', icon: '🏮', description: 'Light every lantern.' },
  { key: 'postmaster', name: 'Pocket Postmaster', icon: '📮', description: 'Deliver every parcel.' },
  { key: 'parade_star', name: 'Parade Star', icon: '🎺', description: 'Reach the bandstand.' },
  { key: 'six_worlds', name: 'Six Worlds', icon: '🌟', description: 'Complete all six games.' },
  { key: 'perfect_day', name: 'Perfect Day', icon: '🏆', description: 'Earn a three-star finish.' },
] as const;

async function ensureSettings(db: Db, userId: string) {
  await db.insert(userSettings).values({ userId }).onConflictDoNothing();
  return (await db.select().from(userSettings).where(eq(userSettings.userId, userId)))[0]!;
}

async function syncTrophies(db: Db, userId: string): Promise<void> {
  const result = await db.execute(sql`
    SELECT
      EXISTS (SELECT 1 FROM pets WHERE user_id = ${userId}) AS pet_parent,
      EXISTS (SELECT 1 FROM game_sessions WHERE user_id = ${userId} AND status = 'completed') AS memory_keeper,
      EXISTS (SELECT 1 FROM trail_sessions WHERE user_id = ${userId} AND status = 'completed') AS trailblazer,
      EXISTS (SELECT 1 FROM pantry_sessions WHERE user_id = ${userId} AND status = 'completed') AS pantry_pro,
      EXISTS (SELECT 1 FROM lantern_sessions WHERE user_id = ${userId} AND status = 'completed') AS lantern_lighter,
      EXISTS (SELECT 1 FROM post_sessions WHERE user_id = ${userId} AND status = 'completed') AS postmaster,
      EXISTS (SELECT 1 FROM parade_sessions WHERE user_id = ${userId} AND status = 'completed') AS parade_star,
      EXISTS (
        SELECT 1 FROM (
          SELECT stars FROM trail_sessions WHERE user_id = ${userId} AND status = 'completed'
          UNION ALL SELECT stars FROM pantry_sessions WHERE user_id = ${userId} AND status = 'completed'
          UNION ALL SELECT stars FROM lantern_sessions WHERE user_id = ${userId} AND status = 'completed'
          UNION ALL SELECT stars FROM post_sessions WHERE user_id = ${userId} AND status = 'completed'
          UNION ALL SELECT stars FROM parade_sessions WHERE user_id = ${userId} AND status = 'completed'
        ) starred WHERE stars = 3
      ) AS perfect_day
  `);
  const facts = result.rows[0] as Record<string, boolean>;
  const earned = ['welcome_home', ...Object.entries(facts).filter(([, value]) => value).map(([key]) => key)];
  const sixWorlds = ['memory_keeper', 'trailblazer', 'pantry_pro', 'lantern_lighter', 'postmaster', 'parade_star']
    .every((key) => earned.includes(key));
  if (sixWorlds) earned.push('six_worlds');
  for (const trophyKey of earned) {
    await db.insert(userTrophies).values({ userId, trophyKey }).onConflictDoNothing();
  }
}

export function registerCommunityRoutes(app: FastifyInstance, db: Db): void {
  app.get('/me/settings', async (request, reply) => {
    const settings = await ensureSettings(db, request.user!.id);
    const twoFactor = (await db.select({ enabled: user2fa.enabled }).from(user2fa)
      .where(eq(user2fa.userId, request.user!.id)))[0]?.enabled ?? false;
    return reply.send({
      settings: {
        email: request.user!.email,
        username: request.user!.username,
        leaderboardOptIn: settings.leaderboardOptIn,
        trophyShowcase: settings.trophyShowcase,
        twoFactorEnabled: twoFactor,
      },
    });
  });

  app.put('/me/settings', async (request, reply) => {
    const parsed = settingsBody.safeParse(request.body);
    if (!parsed.success) return reply.code(400).send({ error: 'invalid_request' });
    const updated = (await db.insert(userSettings)
      .values({ userId: request.user!.id, ...parsed.data, updatedAt: new Date() })
      .onConflictDoUpdate({
        target: userSettings.userId,
        set: { ...parsed.data, updatedAt: new Date() },
      }).returning())[0]!;
    await db.insert(securityAuditEvents).values({
      userId: request.user!.id,
      eventType: 'account.settings_updated',
      metadata: parsed.data,
    });
    return reply.send({ settings: {
      leaderboardOptIn: updated.leaderboardOptIn,
      trophyShowcase: updated.trophyShowcase,
    } });
  });

  app.post('/me/password', async (request, reply) => {
    const parsed = passwordBody.safeParse(request.body);
    if (!parsed.success) return reply.code(400).send({ error: 'invalid_request' });
    if (!await verifyPassword(request.user!.passwordHash, parsed.data.currentPassword)) {
      await db.insert(securityAuditEvents).values({
        userId: request.user!.id,
        eventType: 'account.password_change_rejected',
      });
      return reply.code(401).send({ error: 'invalid_credentials' });
    }
    const passwordHash = await hashPassword(parsed.data.newPassword);
    await db.transaction(async (tx) => {
      await tx.update(users).set({ passwordHash }).where(eq(users.id, request.user!.id));
      await tx.delete(sessions).where(eq(sessions.userId, request.user!.id));
      await tx.insert(securityAuditEvents).values({
        userId: request.user!.id,
        eventType: 'account.password_changed',
      });
    });
    const replacement = await createSession(db, request.user!.id);
    if (request.authMode === 'cookie') {
      setSessionCookie(reply, replacement.token);
      return reply.send({ changed: true });
    }
    return reply.send({ changed: true, token: replacement.token });
  });

  app.get('/me/trophies', async (request, reply) => {
    await syncTrophies(db, request.user!.id);
    const rows = await db.select().from(userTrophies).where(eq(userTrophies.userId, request.user!.id));
    const earnedAt = new Map(rows.map((row) => [row.trophyKey, row.earnedAt]));
    return reply.send({ trophies: TROPHIES.map((trophy) => ({
      ...trophy,
      earned: earnedAt.has(trophy.key),
      earnedAt: earnedAt.get(trophy.key) ?? null,
    })) });
  });

  app.get('/leaderboard', { config: { public: true } }, async (_request, reply) => {
    const result = await db.execute(sql`
      WITH activity AS (
        SELECT user_id, 1 AS completion, 1 AS stars FROM game_sessions WHERE status = 'completed'
        UNION ALL SELECT user_id, 1, stars FROM trail_sessions WHERE status = 'completed'
        UNION ALL SELECT user_id, 1, stars FROM pantry_sessions WHERE status = 'completed'
        UNION ALL SELECT user_id, 1, stars FROM lantern_sessions WHERE status = 'completed'
        UNION ALL SELECT user_id, 1, stars FROM post_sessions WHERE status = 'completed'
        UNION ALL SELECT user_id, 1, stars FROM parade_sessions WHERE status = 'completed'
      ), totals AS (
        SELECT user_id, COUNT(*)::int AS completions, COALESCE(SUM(stars), 0)::int AS stars
        FROM activity GROUP BY user_id
      )
      SELECT u.username,
        COALESCE(t.completions, 0)::int AS completions,
        COALESCE(t.stars, 0)::int AS stars,
        (COALESCE(t.completions, 0) * 100 + COALESCE(t.stars, 0) * 10)::int AS score
      FROM users u
      JOIN user_settings s ON s.user_id = u.id AND s.leaderboard_opt_in = true
      LEFT JOIN totals t ON t.user_id = u.id
      ORDER BY score DESC, u.username ASC
      LIMIT 100
    `);
    return reply.send({
      entries: result.rows.map((row, index) => ({
        rank: index + 1,
        username: row['username'],
        score: Number(row['score']),
        completions: Number(row['completions']),
        stars: Number(row['stars']),
      })),
      scoring: '100 per verified completion + 10 per star',
    });
  });
}
