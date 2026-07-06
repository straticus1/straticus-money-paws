/**
 * Authentication primitives: password hashing, session tokens, TOTP 2FA.
 *
 * Threats: Protects against offline password cracking (Argon2id, proven
 * params), predictable/forgeable session tokens (CSPRNG, hashed at rest so a
 * DB leak reveals no usable tokens), token timing probes (lookup by SHA-256
 * digest), TOTP secret exposure at rest (AES-256-GCM sealed with a server
 * key), and expired/revoked session reuse (fail-closed validation). Does NOT
 * handle rate limiting (API layer), account lockout, or password reset flows.
 *
 * Token generation/comparison adapted from ads-fable-utils node/auth/token
 * (tested asset).
 */
import {
  createCipheriv,
  createDecipheriv,
  createHash,
  randomBytes,
} from 'node:crypto';
import { hash as argon2Hash, verify as argon2Verify, Algorithm } from '@node-rs/argon2';
import { authenticator } from 'otplib';
import { and, eq, gt } from 'drizzle-orm';
import { type Db, sessions, users, type User } from '@paws/db';

// === Passwords (SECURITY-RULES.md rule 3: Argon2id t=1, m=64MB, p=4, 32-byte key) ===

const ARGON2_OPTS = {
  algorithm: Algorithm.Argon2id,
  timeCost: 1,
  memoryCost: 65536,
  parallelism: 4,
  outputLen: 32,
} as const;

export const MIN_PASSWORD_LENGTH = 8;
export const MAX_PASSWORD_LENGTH = 512;

export async function hashPassword(password: string): Promise<string> {
  if (password.length < MIN_PASSWORD_LENGTH || password.length > MAX_PASSWORD_LENGTH) {
    throw new RangeError(
      `password must be ${MIN_PASSWORD_LENGTH}-${MAX_PASSWORD_LENGTH} characters`,
    );
  }
  return argon2Hash(password, ARGON2_OPTS);
}

export async function verifyPassword(storedHash: string, password: string): Promise<boolean> {
  if (
    typeof password !== 'string' ||
    password.length === 0 ||
    password.length > MAX_PASSWORD_LENGTH
  ) {
    return false;
  }
  try {
    return await argon2Verify(storedHash, password);
  } catch {
    return false; // malformed stored hash — fail closed
  }
}

// === Session tokens (adapted from node/auth/token asset) ===

export const MIN_TOKEN_BYTES = 16;

export function randomToken(nBytes = 32): string {
  if (!Number.isInteger(nBytes) || nBytes < MIN_TOKEN_BYTES) {
    throw new RangeError(`randomToken: ${nBytes} bytes is below the ${MIN_TOKEN_BYTES}-byte minimum`);
  }
  return randomBytes(nBytes).toString('base64url');
}

/** Tokens are stored and looked up by digest; the raw token never hits disk. */
export function tokenDigest(token: string): string {
  return createHash('sha256').update(token).digest('hex');
}

const DEFAULT_SESSION_TTL_HOURS = 24 * 14;

export async function createSession(
  db: Db,
  userId: string,
  ttlHours = DEFAULT_SESSION_TTL_HOURS,
): Promise<{ token: string; expiresAt: Date }> {
  const token = randomToken();
  const expiresAt = new Date(Date.now() + ttlHours * 3_600_000);
  await db.insert(sessions).values({ userId, tokenHash: tokenDigest(token), expiresAt });
  return { token, expiresAt };
}

/** Returns the session user, or null for missing/expired/revoked tokens. */
export async function validateSession(db: Db, token: unknown): Promise<User | null> {
  if (typeof token !== 'string' || token.length < 16 || token.length > 512) return null;
  const rows = await db
    .select({ user: users })
    .from(sessions)
    .innerJoin(users, eq(users.id, sessions.userId))
    .where(and(eq(sessions.tokenHash, tokenDigest(token)), gt(sessions.expiresAt, new Date())));
  return rows[0]?.user ?? null;
}

export async function revokeSession(db: Db, token: string): Promise<void> {
  await db.delete(sessions).where(eq(sessions.tokenHash, tokenDigest(token)));
}

// === TOTP secrets sealed at rest (AES-256-GCM, unique nonce per encryption) ===

function sealKey(keyB64: string): Buffer {
  const key = Buffer.from(keyB64, 'base64');
  if (key.length !== 32) {
    throw new RangeError('AUTH_SECRET must be 32 bytes of base64');
  }
  return key;
}

export function sealSecret(plaintext: string, keyB64: string): string {
  const key = sealKey(keyB64);
  const nonce = randomBytes(12);
  const cipher = createCipheriv('aes-256-gcm', key, nonce);
  const ct = Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]);
  return Buffer.concat([nonce, cipher.getAuthTag(), ct]).toString('base64');
}

/** Throws on tampered, truncated, or wrong-key input — never returns garbage. */
export function openSecret(sealed: string, keyB64: string): string {
  const key = sealKey(keyB64);
  const raw = Buffer.from(sealed, 'base64');
  if (raw.length < 12 + 16 + 1) {
    throw new Error('sealed secret too short');
  }
  const nonce = raw.subarray(0, 12);
  const tag = raw.subarray(12, 28);
  const ct = raw.subarray(28);
  const decipher = createDecipheriv('aes-256-gcm', key, nonce);
  decipher.setAuthTag(tag);
  return Buffer.concat([decipher.update(ct), decipher.final()]).toString('utf8');
}

// === TOTP ===

export function generateTotpSecret(): string {
  return authenticator.generateSecret(20);
}

export function verifyTotp(secret: string, code: unknown): boolean {
  if (typeof code !== 'string' || !/^\d{6}$/.test(code)) return false;
  try {
    return authenticator.check(code, secret);
  } catch {
    return false;
  }
}

export function currentTotp(secret: string): string {
  return authenticator.generate(secret);
}
