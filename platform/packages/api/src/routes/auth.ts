import type { FastifyInstance } from 'fastify';
import { eq } from 'drizzle-orm';
import { type Db, users, user2fa } from '@paws/db';
import {
  hashPassword,
  verifyPassword,
  createSession,
  revokeSession,
  generateTotpSecret,
  verifyTotp,
  sealSecret,
  openSecret,
} from '@paws/auth';
import { ensureUserAccount } from '@paws/ledger';
import { z } from 'zod';
import { toPublicUser } from '../types.js';
import { bearerToken, requireAuthSecret, isUniqueViolation } from '../util.js';

const registerBody = z.object({
  email: z.string().trim().toLowerCase().email().max(254),
  username: z
    .string()
    .min(3)
    .max(30)
    .regex(/^[A-Za-z0-9_]+$/, 'username must be alphanumeric or underscore'),
  password: z.string().min(1).max(512),
});

const loginBody = z.object({
  email: z.string().trim().toLowerCase().email().max(254),
  password: z.string().min(1).max(512),
  totp: z.string().max(16).optional(),
});

const totpBody = z.object({
  totp: z.string().max(16),
});

// Constant-work guard against a timing oracle on unknown emails. Computed once,
// lazily, then reused; verifying against it costs the same as a real verify.
let dummyHashPromise: Promise<string> | null = null;
function dummyHash(): Promise<string> {
  if (!dummyHashPromise) {
    dummyHashPromise = hashPassword('timing-oracle-guard-not-a-real-password');
  }
  return dummyHashPromise;
}

export function registerAuthRoutes(app: FastifyInstance, db: Db): void {
  app.post(
    '/auth/register',
    { config: { public: true, rateLimit: { max: 10, timeWindow: '1 minute' } } },
    async (request, reply) => {
      const parsed = registerBody.safeParse(request.body);
      if (!parsed.success) {
        return reply.code(400).send({ error: 'invalid_request' });
      }
      const { email, username, password } = parsed.data;

      let passwordHash: string;
      try {
        passwordHash = await hashPassword(password);
      } catch (err) {
        if (err instanceof RangeError) {
          return reply.code(400).send({ error: 'invalid_password' });
        }
        throw err;
      }

      let userId: string;
      let role: 'user' | 'admin';
      try {
        const inserted = await db
          .insert(users)
          .values({ email, username, passwordHash })
          .returning({ id: users.id, role: users.role });
        userId = inserted[0]!.id;
        role = inserted[0]!.role;
      } catch (err) {
        if (isUniqueViolation(err)) {
          return reply.code(409).send({ error: 'conflict' });
        }
        throw err;
      }

      const { token } = await createSession(db, userId);
      await ensureUserAccount(db, userId, 'PAWS');
      await ensureUserAccount(db, userId, 'USD');

      return reply.code(201).send({
        token,
        user: { id: userId, email, username, role },
      });
    },
  );

  app.post(
    '/auth/login',
    { config: { public: true, rateLimit: { max: 10, timeWindow: '1 minute' } } },
    async (request, reply) => {
      const parsed = loginBody.safeParse(request.body);
      if (!parsed.success) {
        return reply.code(400).send({ error: 'invalid_request' });
      }
      const { email, password, totp } = parsed.data;

      const rows = await db.select().from(users).where(eq(users.email, email));
      const user = rows[0];

      if (!user) {
        // Spend the same work as a real verify so absence is not observable.
        await verifyPassword(await dummyHash(), password);
        return reply.code(401).send({ error: 'invalid_credentials' });
      }

      const ok = await verifyPassword(user.passwordHash, password);
      if (!ok) {
        return reply.code(401).send({ error: 'invalid_credentials' });
      }

      const twoFa = await db
        .select()
        .from(user2fa)
        .where(eq(user2fa.userId, user.id));
      const row = twoFa[0];
      if (row && row.enabled) {
        const secret = openSecret(row.totpSecret, requireAuthSecret());
        if (!totp || !verifyTotp(secret, totp)) {
          return reply.code(401).send({ error: 'totp_required' });
        }
      }

      const { token } = await createSession(db, user.id);
      return reply.send({ token, user: toPublicUser(user) });
    },
  );

  app.post('/auth/logout', async (request, reply) => {
    const token = bearerToken(request);
    if (token) {
      await revokeSession(db, token);
    }
    return reply.code(204).send();
  });

  app.post('/auth/2fa/setup', async (request, reply) => {
    const user = request.user!;
    const secret = generateTotpSecret();
    const sealed = sealSecret(secret, requireAuthSecret());
    await db
      .insert(user2fa)
      .values({ userId: user.id, totpSecret: sealed, enabled: false })
      .onConflictDoUpdate({
        target: user2fa.userId,
        set: { totpSecret: sealed, enabled: false },
      });
    const otpauthUrl =
      `otpauth://totp/paws.money:${encodeURIComponent(user.email)}` +
      `?secret=${secret}&issuer=paws.money`;
    return reply.send({ secret, otpauthUrl });
  });

  app.post('/auth/2fa/enable', async (request, reply) => {
    const user = request.user!;
    const parsed = totpBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({ error: 'invalid_request' });
    }
    const rows = await db.select().from(user2fa).where(eq(user2fa.userId, user.id));
    const row = rows[0];
    if (!row) {
      return reply.code(400).send({ error: 'totp_not_setup' });
    }
    const secret = openSecret(row.totpSecret, requireAuthSecret());
    if (!verifyTotp(secret, parsed.data.totp)) {
      return reply.code(400).send({ error: 'invalid_totp' });
    }
    await db.update(user2fa).set({ enabled: true }).where(eq(user2fa.userId, user.id));
    return reply.send({ enabled: true });
  });
}
