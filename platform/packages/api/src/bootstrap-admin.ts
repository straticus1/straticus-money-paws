import { eq } from 'drizzle-orm';
import { z } from 'zod';
import { hashPassword, MAX_PASSWORD_LENGTH, MIN_PASSWORD_LENGTH } from '@paws/auth';
import { createDb, type Db, users } from '@paws/db';
import { ensureUserAccount } from '@paws/ledger';

const adminInputSchema = z.object({
  email: z.string().trim().toLowerCase().email().max(254),
  username: z
    .string()
    .min(3)
    .max(30)
    .regex(/^[A-Za-z0-9_]+$/, 'username must be alphanumeric or underscore'),
  password: z.string().min(MIN_PASSWORD_LENGTH).max(MAX_PASSWORD_LENGTH),
});

export type AdminBootstrapInput = z.infer<typeof adminInputSchema>;

export interface AdminBootstrapResult {
  id: string;
  email: string;
  username: string;
  action: 'created' | 'updated';
}

/**
 * Create an admin account, or update the account already using the requested
 * email. Refuse to reuse a username owned by another email so the bootstrap
 * command cannot accidentally take over an unrelated account.
 */
export async function bootstrapAdmin(
  db: Db,
  input: AdminBootstrapInput,
): Promise<AdminBootstrapResult> {
  const parsed = adminInputSchema.parse(input);
  const passwordHash = await hashPassword(parsed.password);

  return db.transaction(async (tx) => {
    const byEmail = await tx.select().from(users).where(eq(users.email, parsed.email));
    const byUsername = await tx.select().from(users).where(eq(users.username, parsed.username));
    const emailUser = byEmail[0];
    const usernameUser = byUsername[0];

    if (usernameUser && usernameUser.id !== emailUser?.id) {
      throw new Error(
        `username ${parsed.username} already belongs to a different account; choose another username`,
      );
    }

    let user: typeof users.$inferSelect;
    let action: AdminBootstrapResult['action'];

    if (emailUser) {
      const updated = await tx
        .update(users)
        .set({
          username: parsed.username,
          passwordHash,
          role: 'admin',
        })
        .where(eq(users.id, emailUser.id))
        .returning();
      user = updated[0]!;
      action = 'updated';
    } else {
      const inserted = await tx
        .insert(users)
        .values({
          email: parsed.email,
          username: parsed.username,
          passwordHash,
          role: 'admin',
        })
        .returning();
      user = inserted[0]!;
      action = 'created';
    }

    const txDb = tx as unknown as Db;
    await ensureUserAccount(txDb, user.id, 'PAWS');
    await ensureUserAccount(txDb, user.id, 'USD');

    return {
      id: user.id,
      email: user.email,
      username: user.username,
      action,
    };
  });
}

function requiredEnv(name: string): string {
  const value = process.env[name];
  if (!value) throw new Error(`${name} is required`);
  return value;
}

async function main(): Promise<void> {
  const databaseUrl = requiredEnv('DATABASE_URL');
  const { db, pool } = createDb(databaseUrl);

  try {
    const result = await bootstrapAdmin(db, {
      email: requiredEnv('ADMIN_EMAIL'),
      username: requiredEnv('ADMIN_USERNAME'),
      password: requiredEnv('ADMIN_PASSWORD'),
    });
    console.log(
      `Admin ${result.action}: ${result.email} (${result.username}), id=${result.id}`,
    );
  } finally {
    await pool.end();
  }
}

main().catch((error: unknown) => {
  console.error(error instanceof Error ? error.message : error);
  process.exitCode = 1;
});
