# paws.money v5 platform

Greenfield TypeScript rewrite of the paws.money PHP app. pnpm monorepo,
Fastify + Postgres + Drizzle backend, Preact web client. The legacy PHP app
(repo root) is frozen — security patches only.

## Packages

| Package | Purpose | Status |
|---|---|---|
| `@paws/db` | Postgres schema, SQL migrations, seed from legacy SQLite | ✅ tested (migration + seed verified) |
| `@paws/ledger` | Double-entry ledger: idempotency keys, row-locked overdraft checks | ✅ 10 tests |
| `@paws/auth` | Argon2id passwords, CSPRNG session tokens hashed at rest, sealed TOTP 2FA | ✅ 12 tests |
| `@paws/payments` | Coinbase Commerce deposits, manually-reviewed withdrawals | ✅ 14 tests (HTTP routes not wired yet) |
| `@paws/api` | Fastify server: auth, pets, store, wallet, admin queue | ✅ 26 tests |
| `@paws/core` | Typed API client SDK (browser + node) | ✅ 16 tests |
| `@paws/web` | Preact SPA: login, dashboard, pets, store, wallet | ✅ builds, 25KB |

## Money invariants (do not weaken)

- All amounts are **bigint minor units** (cents). No floats, ever. Wire format is strings.
- Balances are **derived** from `ledger_entries`; there is no balance column.
- Every money mutation goes through `postTransaction`/`transfer` with an
  **idempotency key**. The DB enforces balanced entries (deferred constraint
  trigger) and append-only ledger rows.
- User accounts can never go negative (row-locked overdraft check); system
  accounts (`treasury`, `revenue`, `withholding`) may.
- Withdrawals hold funds in `withholding` at request time; denial refunds,
  payout moves the hold to `treasury`.
- Security rules: `~/development/ads-fable-utils/SECURITY-RULES.md` is binding
  for anything touching crypto/auth/tokens/SQL/randomness.

## Dev setup

```sh
cd platform
pnpm install
createdb paws_dev
cd packages/db
DATABASE_URL=postgres://localhost:5432/paws_dev pnpm migrate
DATABASE_URL=postgres://localhost:5432/paws_dev pnpm seed   # 31 legacy store items
```

Run tests (each suite provisions its own test DB via `createdb paws_test`,
`paws_test_pay`, `paws_test_api`):

```sh
pnpm -r test
```

Run the stack (once @paws/api and @paws/web land):

```sh
AUTH_SECRET=$(openssl rand -base64 32) DATABASE_URL=postgres://localhost:5432/paws_dev pnpm dev
pnpm --filter @paws/web dev   # Vite on :5173, proxies /api -> :3000
```

Required env for production: `DATABASE_URL`, `AUTH_SECRET` (32-byte base64),
`COINBASE_API_KEY`, `COINBASE_WEBHOOK_SECRET`, `PORT`.

## Status: Tier 1 complete — 78 tests green, Docker stack smoke-tested

The full flow was verified through the compose stack (nginx → api → postgres):
seeded store items, register, derived balances, pet creation, fail-closed
deposit (503 without Coinbase creds), SPA serving.

## Tuesday-night cutover runbook

1. On the server: `git clone -b v5-platform`, `cd platform`.
2. Create `.env`: `POSTGRES_PASSWORD` (strong), `AUTH_SECRET`
   (`openssl rand -base64 32`), `COINBASE_API_KEY`, `COINBASE_WEBHOOK_SECRET`,
   optional `WEB_PORT`.
3. `docker compose up -d --build` — migrations and seed run on api boot.
4. Configure the Coinbase Commerce webhook to POST
   `https://paws.money/api/v1/webhooks/coinbase`.
5. Point the domain / reverse proxy at the `web` service (port 8080 by
   default). TLS terminates at your proxy — the API sets no cookies; the
   bearer token flow works unchanged behind HTTPS.
6. Make yourself admin: `docker compose exec postgres psql -U paws -d paws
   -c "UPDATE users SET role='admin' WHERE username='<you>'"`.
7. Decommission the PHP vhost (redirect to the new site). Do NOT run both
   against the same domain.

Deferred (come back post-cutover, no downtime): tournaments, breeding, quests,
social, adventures, marketplace, AI generator, metaverse, desktop app, CLI.
