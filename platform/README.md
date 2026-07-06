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
| `@paws/api` | Fastify server: auth, pets, store, inventory, balances | 🚧 in progress |
| `@paws/core` | Typed API client SDK (browser + node) | 🚧 in progress |
| `@paws/web` | Preact SPA: login, dashboard, pets, store, wallet | 🚧 in progress |

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

## Remaining for Tuesday-night cutover (Tier 1)

1. **@paws/api** — Fastify server + routes (in flight).
2. **@paws/core + @paws/web** — SDK and web UI (in flight).
3. **Wallet routes** — wire `@paws/payments` into the API:
   `POST /wallet/deposit` (createCharge + createDeposit), `POST /webhooks/coinbase`
   (raw-body HMAC verify → handleWebhookEvent), `POST/GET /wallet/withdrawals`,
   admin review endpoints (requestWithdrawal/reviewWithdrawal/markWithdrawalPaid).
4. **Integration smoke** — register → deposit (mocked webhook) → buy → feed.
5. **Deploy** — Docker compose (postgres + api + static web), point the domain,
   put the PHP app's pages behind a redirect.

Deferred (come back post-cutover, no downtime): tournaments, breeding, quests,
social, adventures, marketplace, AI generator, metaverse, desktop app, CLI.
