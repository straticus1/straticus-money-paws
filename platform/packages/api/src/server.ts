import { randomBytes } from 'node:crypto';
import { createDb } from '@paws/db';
import { buildApp } from './app.js';

/**
 * Ensure AUTH_SECRET (32-byte base64) is present. In production we refuse to
 * start without it; elsewhere we mint an ephemeral one so local dev works, but
 * warn because it invalidates sealed secrets across restarts.
 */
function ensureAuthSecret(): void {
  if (process.env['AUTH_SECRET']) {
    return;
  }
  if (process.env['NODE_ENV'] === 'production') {
    throw new Error('AUTH_SECRET is required in production');
  }
  const ephemeral = randomBytes(32).toString('base64');
  process.env['AUTH_SECRET'] = ephemeral;
  console.warn(
    'AUTH_SECRET not set — generated an ephemeral key. Sealed TOTP secrets ' +
      'will not survive a restart. Set AUTH_SECRET for anything but local dev.',
  );
}

async function main(): Promise<void> {
  ensureAuthSecret();

  const connectionString = process.env['DATABASE_URL'];
  if (!connectionString) {
    throw new Error('DATABASE_URL is required');
  }
  const port = Number(process.env['PORT'] ?? 3000);

  const { db } = createDb(connectionString);
  // rateLimit enabled here (buildApp registers @fastify/cors and
  // @fastify/rate-limit before routes so their hooks actually apply — see the
  // note in app.ts).
  const app = buildApp({ db, rateLimit: true });

  await app.listen({ port, host: '0.0.0.0' });
  console.log(`paws api listening on :${port}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
