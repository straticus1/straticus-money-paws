import type { User } from '@paws/db';

// Pull in @fastify/rate-limit's augmentation of FastifyContextConfig
// (adds the optional `rateLimit` route config) without a runtime import.
import type {} from '@fastify/rate-limit';

declare module 'fastify' {
  interface FastifyRequest {
    // Set by the global auth preHandler. Null on public routes with no token.
    user: User | null;
    authMode: 'bearer' | 'cookie' | 'none';
    sessionToken: string | null;
  }
  interface FastifyContextConfig {
    // Routes opt OUT of auth by setting this. Absence means auth required
    // (fail closed).
    public?: boolean;
  }
}

/** User shape returned to clients — never includes passwordHash. */
export interface PublicUser {
  id: string;
  email: string;
  username: string;
  role: 'user' | 'admin';
}

export function toPublicUser(user: User): PublicUser {
  return { id: user.id, email: user.email, username: user.username, role: user.role };
}
