# paws.money v5 platform

Active TypeScript application replacing the original paws.money PHP app. pnpm
monorepo, Fastify + Postgres + Drizzle backend, Preact web client. The legacy
PHP app is archived in `../legacy/` and frozen — security patches only.

## Packages

| Package | Purpose | Status |
|---|---|---|
| `@paws/db` | Postgres schema, SQL migrations, seed from legacy SQLite | ✅ tested (migration + seed verified) |
| `@paws/ledger` | Double-entry ledger: idempotency keys, row-locked overdraft checks | ✅ 10 tests |
| `@paws/auth` | Argon2id passwords, CSPRNG session tokens hashed at rest, sealed TOTP 2FA | ✅ 12 tests |
| `@paws/payments` | Coinbase Commerce deposits, manually-reviewed withdrawals | ✅ 14 tests (HTTP routes not wired yet) |
| `@paws/api` | Fastify server: auth, pets, six server-authoritative games, store, wallet, admin queue | ✅ tested |
| `@paws/core` | Typed API client SDK (browser + node) | ✅ 24 tests |
| `@paws/web` | Preact SPA: account settings, honors, pets, store, wallet, and games | ✅ builds |

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
- All six games are server-authoritative and free-to-play. They can award only
  PAWS, share a 125 PAWS per-account UTC daily cap, and never accept USD or
  crypto wagers. See
  [`docs/GAME-SECURITY.md`](docs/GAME-SECURITY.md),
  [`docs/TRAIL-TAILS-DESIGN.md`](docs/TRAIL-TAILS-DESIGN.md), and
  [`docs/MIDNIGHT-PANTRY-DESIGN.md`](docs/MIDNIGHT-PANTRY-DESIGN.md),
  [`docs/LANTERN-LINES-DESIGN.md`](docs/LANTERN-LINES-DESIGN.md),
  [`docs/POCKET-POST-DESIGN.md`](docs/POCKET-POST-DESIGN.md), and
  [`docs/PARADE-PRACTICE-DESIGN.md`](docs/PARADE-PRACTICE-DESIGN.md).
- The store includes food plus PAWS-priced cosmetic props, houses, toys, trees,
  carriers, habitats, and décor. Cosmetic inventory never changes game rules or
  rewards.
- Security rules: `~/development/ads-fable-utils/SECURITY-RULES.md` is binding
  for anything touching crypto/auth/tokens/SQL/randomness.
- Browser sessions use HttpOnly cookies, community rankings are private by
  default, and the application security model is documented in
  [`docs/SECURITY-HARDENING.md`](docs/SECURITY-HARDENING.md).

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
`WEB_ORIGIN` (the exact HTTPS site origin), `COINBASE_API_KEY`,
`COINBASE_WEBHOOK_SECRET`, `PORT`.

### Bootstrap an admin account

The bootstrap command creates an admin or updates the account matching
`ADMIN_EMAIL`. It resets that account's username and password, promotes it to
`admin`, and ensures its PAWS and USD ledger accounts exist. It is safe to rerun
with the same email. It refuses to reuse a username owned by another account.

```sh
DATABASE_URL=postgres://localhost:5432/paws_dev \
ADMIN_EMAIL=admin@example.com \
ADMIN_USERNAME=admin \
ADMIN_PASSWORD='choose-a-strong-password' \
pnpm bootstrap:admin
```

Against the Docker Compose stack:

```sh
docker compose exec \
  -e ADMIN_EMAIL=admin@example.com \
  -e ADMIN_USERNAME=admin \
  -e ADMIN_PASSWORD='choose-a-strong-password' \
  api node packages/api/dist/bootstrap-admin.js
```

## Status: Tier 1 complete — 78 tests green, Docker stack smoke-tested

The full flow was verified through the compose stack (nginx → api → postgres):
seeded store items, register, derived balances, pet creation, fail-closed
deposit (503 without Coinbase creds), SPA serving.

## Deployment state (deployed overnight July 6)

**DEPLOYED and running on apps2.afterdarksys.com** (chosen for most free
resources: 24 cores / 96GB RAM / 418GB disk):

- Stack at `~/paws.money/platform`, `.env` on the host (0600), web bound to
  `127.0.0.1:8091` only — deliberately unreachable from outside until TLS.
- Caddy vhost for `paws.money, www.paws.money` appended to
  `/opt/domainshots/caddy/Caddyfile` (backup: `Caddyfile.bak-pre-paws`),
  validated + reloaded. Auto-TLS will kick in as soon as DNS resolves.
- Smoke-tested on the host: register, derived balances, deposit fails closed
  (503, no Coinbase creds yet).

## Remaining human steps (blocked on credentials only)

1. **DNS — pick one:**
   - paws.money is delegated to OCI DNS pool `p201`, but no zone exists and
     the OCI account's `global-zone-count` limit is 0/full. Either raise the
     limit in the OCI Console and create a `paws.money` zone with
     `A @ -> 108.165.123.5` and `A www -> 108.165.123.5` (nameservers must
     come out as `ns*.p201.dns.oraclecloud.net` — same pool), or
   - change the delegation at the registrar to nameservers you control
     (e.g. ns1/ns2.idoms.net) and add the zone there.
2. **Coinbase Commerce**: put `COINBASE_API_KEY` and
   `COINBASE_WEBHOOK_SECRET` into `~/paws.money/platform/.env` on apps2,
   `docker compose up -d api`, and set the webhook URL to
   `https://paws.money/api/v1/webhooks/coinbase`. Until then deposits are
   503 by design.
3. Make yourself admin: `docker compose exec postgres psql -U paws -d paws
   -c "UPDATE users SET role='admin' WHERE username='<you>'"`.

Note: the legacy PHP app is not deployed anywhere reachable (domain was
dead); nothing to decommission. Also: `app.domainshots.ai` on apps2 was
found already broken (backend port 3000 not listening) BEFORE any changes
tonight — untouched, flagging for a separate look.

Deferred (come back post-cutover, no downtime): tournaments, breeding, quests,
social, adventures, marketplace, AI generator, metaverse, desktop app, CLI,
and any real-money/chance gaming.
