import Fastify, { type FastifyInstance } from 'fastify';
import cors from '@fastify/cors';
import rateLimit from '@fastify/rate-limit';
import type { Db } from '@paws/db';
import { validateSession } from '@paws/auth';
import './types.js';
import { bearerToken } from './util.js';
import { registerAuthRoutes } from './routes/auth.js';
import { registerMeRoutes } from './routes/me.js';
import { registerPetRoutes } from './routes/pets.js';
import { registerStoreRoutes } from './routes/store.js';
import { registerWalletRoutes, type WalletDeps } from './routes/wallet.js';

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
  const app = Fastify({ logger: false });

  app.decorateRequest('user', null);

  // Registered before routes so their onRequest hooks reach every route.
  app.register(cors, { origin: true, credentials: true });
  if (deps.rateLimit) {
    app.register(rateLimit, { global: true, max: 200, timeWindow: '1 minute' });
  }

  app.addHook('preHandler', async (request, reply) => {
    const token = bearerToken(request);
    const user = token ? await validateSession(db, token) : null;
    request.user = user;

    const isPublic = request.routeOptions.config?.public === true;
    if (!isPublic && !user) {
      return reply.code(401).send({ error: 'unauthorized' });
    }
  });

  app.register(
    async (v1) => {
      registerAuthRoutes(v1, db);
      registerMeRoutes(v1, db);
      registerPetRoutes(v1, db);
      registerStoreRoutes(v1, db);
      const walletDeps: WalletDeps = {};
      if (deps.providerFetch) walletDeps.providerFetch = deps.providerFetch;
      registerWalletRoutes(v1, db, walletDeps);
    },
    { prefix: '/api/v1' },
  );

  return app;
}
