import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { randomBytes } from 'node:crypto';
import { sql } from 'drizzle-orm';
import { createDb, users, type DbHandle } from '@paws/db';
import { runMigrations } from '@paws/db/migrate';
import {
  createSession,
  currentTotp,
  generateTotpSecret,
  hashPassword,
  openSecret,
  randomToken,
  revokeSession,
  sealSecret,
  validateSession,
  verifyPassword,
  verifyTotp,
} from './index.js';

const TEST_URL = process.env['TEST_DATABASE_URL'] ?? 'postgres://localhost:5432/paws_test';

let handle: DbHandle;
let userId: string;

beforeAll(async () => {
  await runMigrations(TEST_URL);
  handle = createDb(TEST_URL);
});

afterAll(async () => {
  await handle.pool.end();
});

beforeEach(async () => {
  await handle.db.execute(sql`TRUNCATE sessions, user_2fa, users CASCADE`);
  const inserted = await handle.db
    .insert(users)
    .values({ email: 'auth@paws.money', username: 'authuser', passwordHash: 'x' })
    .returning({ id: users.id });
  userId = inserted[0]!.id;
});

describe('passwords', () => {
  it('hashes with argon2id using the mandated params and verifies', async () => {
    const hash = await hashPassword('correct horse battery');
    expect(hash).toMatch(/^\$argon2id\$/);
    expect(hash).toContain('m=65536,t=1,p=4');
    expect(await verifyPassword(hash, 'correct horse battery')).toBe(true);
  });

  // Negative: wrong password, malformed hash, oversized input all rejected.
  it('rejects wrong passwords and malformed input, failing closed', async () => {
    const hash = await hashPassword('correct horse battery');
    expect(await verifyPassword(hash, 'wrong password!')).toBe(false);
    expect(await verifyPassword(hash, '')).toBe(false);
    expect(await verifyPassword('not-a-hash', 'correct horse battery')).toBe(false);
    expect(await verifyPassword(hash, 'x'.repeat(600))).toBe(false);
  });

  it('rejects too-short and too-long passwords at hash time', async () => {
    await expect(hashPassword('short')).rejects.toThrow(RangeError);
    await expect(hashPassword('x'.repeat(600))).rejects.toThrow(RangeError);
  });
});

describe('sessions', () => {
  it('creates and validates a session', async () => {
    const { token } = await createSession(handle.db, userId);
    const user = await validateSession(handle.db, token);
    expect(user?.id).toBe(userId);
  });

  // Negative: tampered, truncated, and garbage tokens are rejected.
  it('rejects tampered and malformed tokens', async () => {
    const { token } = await createSession(handle.db, userId);
    const flipped = (token[0] === 'A' ? 'B' : 'A') + token.slice(1);
    expect(await validateSession(handle.db, flipped)).toBeNull();
    expect(await validateSession(handle.db, token.slice(0, -2))).toBeNull();
    expect(await validateSession(handle.db, '')).toBeNull();
    expect(await validateSession(handle.db, null)).toBeNull();
    expect(await validateSession(handle.db, 'x'.repeat(600))).toBeNull();
  });

  // Negative: expiry is enforced.
  it('rejects expired sessions', async () => {
    const { token } = await createSession(handle.db, userId, -1);
    expect(await validateSession(handle.db, token)).toBeNull();
  });

  // Negative: revocation is immediate.
  it('rejects revoked sessions', async () => {
    const { token } = await createSession(handle.db, userId);
    await revokeSession(handle.db, token);
    expect(await validateSession(handle.db, token)).toBeNull();
  });

  it('refuses to mint weak tokens', () => {
    expect(() => randomToken(8)).toThrow(RangeError);
    expect(() => randomToken(2.5)).toThrow(RangeError);
  });
});

describe('sealed TOTP secrets', () => {
  const key = randomBytes(32).toString('base64');

  it('round-trips a secret', () => {
    const sealed = sealSecret('JBSWY3DPEHPK3PXP', key);
    expect(openSecret(sealed, key)).toBe('JBSWY3DPEHPK3PXP');
  });

  // Negative: tampered ciphertext, wrong key, truncated blob all throw.
  it('rejects tampered, truncated, and wrong-key input', () => {
    const sealed = sealSecret('JBSWY3DPEHPK3PXP', key);
    const raw = Buffer.from(sealed, 'base64');
    raw[raw.length - 1]! ^= 0xff;
    expect(() => openSecret(raw.toString('base64'), key)).toThrow();
    expect(() => openSecret(sealed, randomBytes(32).toString('base64'))).toThrow();
    expect(() => openSecret(Buffer.from('tiny').toString('base64'), key)).toThrow(/too short/);
    expect(() => sealSecret('x', 'short-key')).toThrow(RangeError);
  });

  it('uses a unique nonce per encryption', () => {
    const a = sealSecret('same-secret', key);
    const b = sealSecret('same-secret', key);
    expect(a).not.toBe(b);
  });
});

describe('TOTP', () => {
  it('verifies the current code and rejects wrong or malformed codes', () => {
    const secret = generateTotpSecret();
    expect(verifyTotp(secret, currentTotp(secret))).toBe(true);
    expect(verifyTotp(secret, '000000')).toBe(false);
    expect(verifyTotp(secret, 'abcdef')).toBe(false);
    expect(verifyTotp(secret, null)).toBe(false);
    expect(verifyTotp(secret, '12345')).toBe(false);
  });
});
