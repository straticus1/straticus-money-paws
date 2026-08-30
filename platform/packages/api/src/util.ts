import type { FastifyReply, FastifyRequest } from 'fastify';

export const SESSION_COOKIE_NAME = 'paws_session';

/** Extract a Bearer token from the Authorization header, or null. */
export function bearerToken(request: FastifyRequest): string | null {
  const header = request.headers.authorization;
  if (typeof header !== 'string' || !header.startsWith('Bearer ')) {
    return null;
  }
  const token = header.slice('Bearer '.length).trim();
  return token.length > 0 ? token : null;
}

export function requestSessionToken(request: FastifyRequest): {
  token: string | null;
  mode: 'bearer' | 'cookie' | 'none';
} {
  const bearer = bearerToken(request);
  if (bearer) return { token: bearer, mode: 'bearer' };
  const cookie = request.cookies?.[SESSION_COOKIE_NAME];
  return cookie ? { token: cookie, mode: 'cookie' } : { token: null, mode: 'none' };
}

export function wantsCookieSession(request: FastifyRequest): boolean {
  return request.headers['x-paws-session-mode'] === 'cookie';
}

export function setSessionCookie(reply: FastifyReply, token: string): void {
  reply.setCookie(SESSION_COOKIE_NAME, token, {
    path: '/',
    httpOnly: true,
    sameSite: 'strict',
    secure: process.env['NODE_ENV'] === 'production',
    maxAge: 60 * 60 * 24,
  });
}

export function clearSessionCookie(reply: FastifyReply): void {
  reply.clearCookie(SESSION_COOKIE_NAME, {
    path: '/',
    httpOnly: true,
    sameSite: 'strict',
    secure: process.env['NODE_ENV'] === 'production',
  });
}

/**
 * The server-side key that seals TOTP secrets at rest. Read at call time so
 * server.ts can set/validate it before any request is served.
 */
export function requireAuthSecret(): string {
  const secret = process.env['AUTH_SECRET'];
  if (!secret) {
    throw new Error('AUTH_SECRET is not configured');
  }
  return secret;
}

/**
 * Postgres unique_violation (SQLSTATE 23505). Used to map duplicate inserts to
 * HTTP 409. Drizzle wraps driver errors, so the pg code can sit on the thrown
 * error or on its `.cause`; check the chain.
 */
export function isUniqueViolation(err: unknown): boolean {
  let current: unknown = err;
  for (let depth = 0; depth < 5 && current != null; depth++) {
    if (typeof current === 'object' && (current as { code?: string }).code === '23505') {
      return true;
    }
    current = (current as { cause?: unknown }).cause;
  }
  return false;
}
