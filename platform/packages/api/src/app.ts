import Fastify, { type FastifyInstance } from 'fastify';
import cors from '@fastify/cors';
import cookie from '@fastify/cookie';
import rateLimit from '@fastify/rate-limit';
import type { Db } from '@paws/db';
import { validateSession } from '@paws/auth';
import './types.js';
import { requestSessionToken } from './util.js';
import { registerAuthRoutes } from './routes/auth.js';
import { registerMeRoutes } from './routes/me.js';
import { registerPetRoutes } from './routes/pets.js';
import { registerStoreRoutes } from './routes/store.js';
import { registerWalletRoutes, type WalletDeps } from './routes/wallet.js';
import { registerGameRoutes } from './routes/games.js';
import { registerTrailTailsRoutes } from './routes/trail-tails.js';
import { registerMidnightPantryRoutes } from './routes/midnight-pantry.js';
import { registerLanternLinesRoutes } from './routes/lantern-lines.js';
import { registerPocketPostRoutes } from './routes/pocket-post.js';
import { registerParadePracticeRoutes } from './routes/parade-practice.js';
import { registerCommunityRoutes } from './routes/community.js';

const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

function allowedOrigin(origin: string): boolean {
  const configured = (process.env['WEB_ORIGIN'] ?? '')
    .split(',')
    .map((value) => value.trim())
    .filter(Boolean);
  const defaults = process.env['NODE_ENV'] === 'production'
    ? []
    : ['http://localhost:5173', 'http://localhost:8092', 'http://localhost'];
  return configured.includes(origin) || defaults.includes(origin);
}

export interface AppDeps {
  db: Db;
  /**
   * Enable @fastify/rate-limit (global 200/min, plus per-route 10/min on
   * /auth/login and /auth/register). Off by default so the test suite, which
   * shares one client IP across many register/login calls, is not throttled.
   * server.ts turns it on.
   */
  rateLimit?: boolean;
  /** Injected fetch for payment-provider calls (tests). */
  providerFetch?: WalletDeps['providerFetch'];
}

/**
 * Build the Fastify app with all routes wired but no network listener. The DB
 * is injected so tests can pass a throwaway database.
 *
 * Auth is fail-closed: a global preHandler resolves the session and rejects
 * every request by default. Routes opt out with `config.public === true`.
 *
 * NOTE ON PLUGIN ORDER (deviation from the original contract): @fastify/cors
 * and @fastify/rate-limit are registered HERE, before the routes, rather than
 * in server.ts after buildApp(). Fastify only propagates a plugin's hooks to
 * routes registered *after* the plugin loads, so registering them after the
 * routes (as the contract literally described) is a proven no-op — neither the
 * global nor the per-route limits fire. Registering them first (register() is
 * queued, not awaited, so buildApp stays synchronous) makes both work while
 * keeping the single buildApp entry point server.ts calls.
 */
export function buildApp(deps: AppDeps): FastifyInstance {
  const { db } = deps;
  const app = Fastify({
    logger: false,
    // Only trust forwarding headers when the deployment explicitly enables it.
    // The bundled nginx proxy overwrites X-Forwarded-For rather than appending
    // attacker-provided values.
    trustProxy: process.env['TRUST_PROXY'] === 'true',
  });

  app.decorateRequest('user', null);
  app.decorateRequest('authMode', 'none');
  app.decorateRequest('sessionToken', null);

  // Registered before routes so their onRequest hooks reach every route.
  app.register(cookie);
  app.register(cors, {
    credentials: true,
    origin(origin, callback) {
      if (!origin || allowedOrigin(origin)) return callback(null, true);
      return callback(null, false);
    },
  });
  if (deps.rateLimit) {
    app.register(rateLimit, { global: true, max: 200, timeWindow: '1 minute' });
  }

  app.addHook('onSend', async (_request, reply) => {
    reply.header('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
    reply.header('X-Content-Type-Options', 'nosniff');
    reply.header('X-Frame-Options', 'DENY');
    reply.header('Referrer-Policy', 'no-referrer');
    reply.header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
  });

  app.addHook('preHandler', async (request, reply) => {
    const session = requestSessionToken(request);
    const token = session.token;
    const user = token ? await validateSession(db, token) : null;
    request.user = user;
    request.authMode = user ? session.mode : 'none';
    request.sessionToken = user ? token : null;

    const isPublic = request.routeOptions.config?.public === true;
    if (!isPublic && !user) {
      return reply.code(401).send({ error: 'unauthorized' });
    }
    if (user && session.mode === 'cookie' && !SAFE_METHODS.has(request.method)) {
      const origin = request.headers.origin;
      if (typeof origin !== 'string' || !allowedOrigin(origin)) {
        return reply.code(403).send({ error: 'cross_site_request' });
      }
    }
  });

  app.register(
    async (v1) => {
      registerAuthRoutes(v1, db);
      registerMeRoutes(v1, db);
      registerPetRoutes(v1, db);
      registerStoreRoutes(v1, db);
      registerGameRoutes(v1, db);
      registerTrailTailsRoutes(v1, db);
      registerMidnightPantryRoutes(v1, db);
      registerLanternLinesRoutes(v1, db);
      registerPocketPostRoutes(v1, db);
      registerParadePracticeRoutes(v1, db);
      registerCommunityRoutes(v1, db);
      const walletDeps: WalletDeps = {};
      if (deps.providerFetch) walletDeps.providerFetch = deps.providerFetch;
      registerWalletRoutes(v1, db, walletDeps);
    },
    { prefix: '/api/v1' },
  );

  return app;
}
