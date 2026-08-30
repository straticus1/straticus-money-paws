# Platform security hardening

This document describes the controls added with trophies, the leaderboard, and
account settings. It is a threat model, not a claim that the application can
never be compromised.

## Trust boundaries and protected assets

```text
browser (untrusted)
  -> nginx: CSP, clickjacking and content-sniffing defenses
  -> API: session + origin check + strict schemas + authorization
  -> PostgreSQL: password/session hashes, settings, game facts, append-only ledger
  -> Coinbase Commerce: outbound HTTPS charge request / inbound signed webhook
```

The browser may request actions. It never supplies trophy awards, leaderboard
scores, game outcomes, balances, rewards, roles, or ledger entries. The API
derives those from owner-scoped database records.

## Threats and controls

| Threat | Controls in this repository |
|---|---|
| XSS and token theft | The SPA uses an `HttpOnly`, `SameSite=Strict` cookie and never stores its session secret in `localStorage`. Preact escapes rendered text. CSP permits scripts only from this origin; objects, frames, and external connections are blocked. No third-party scripts, fonts, or pixels are loaded. |
| CSRF / watering-hole requests | Cookie-authenticated mutations require an exact trusted `Origin`; production origins come from `WEB_ORIGIN`. Strict SameSite cookies provide a second layer. CORS is allowlisted. |
| Pass-the-hash / session replay | Passwords use Argon2id and are never accepted as hashes. Raw session tokens are random, stored only as SHA-256 digests server-side, revocable, and unavailable to browser JavaScript. Password changes revoke every session. Sensitive wallet and 2FA setup operations require the current password again. |
| Dependency watering hole | Production pages load no remote executable content. pnpm's committed lockfile and `--frozen-lockfile` make container builds reproducible; dependency audits are part of release verification. Base images should be refreshed and re-scanned regularly. |
| Wallet tampering | Bigint minor units, double-entry postings, append-only ledger triggers, row locks, idempotency keys, bounds, manual withdrawal review, re-authentication, and TOTP when enabled. Webhooks are verified over raw bytes with constant-time HMAC comparison. |
| Malicious payment redirect | Provider charge IDs are bounded and checkout URLs must use HTTPS on `commerce.coinbase.com`. |
| Leaderboard privacy | Participation defaults off. Only username and aggregated verified-play totals are returned. Email, IDs, balances, destinations, and reward amounts are excluded. |
| Forged trophies | There is no trophy-write API. Awards are idempotently derived from authoritative account, pet, and completed-game rows. |

## Operational requirements

- Set `WEB_ORIGIN` to the exact HTTPS public origin (comma-separated only when
  multiple origins are intentional). Do not use a wildcard.
- Terminate TLS before nginx. HSTS is sent by nginx and takes effect over HTTPS.
- Keep `AUTH_SECRET`, Coinbase credentials, and the database URL in a secrets
  manager or protected environment file, never in the image or repository.
- Run `pnpm audit --prod`, image scanning, and the test suite before release.
- Monitor `security_audit_events`, authentication rate limits, failed webhook
  signatures, and withdrawal review actions.
- Never make PAWS convertible or introduce real-money chance mechanics without
  legal review and a new abuse/fraud model.

## Residual risks

- CSP still allows inline **styles** because game boards use dynamic style
  attributes. Inline scripts remain forbidden. Moving all dynamic visuals to
  classes would allow tightening `style-src` further.
- A compromised same-origin server or malicious browser extension can act as
  the signed-in user. Hardware-bound/WebAuthn sessions are not implemented.
- Rate limiting is per API instance. A distributed deployment needs a shared
  limiter and edge/WAF controls.
- Browser automation can solve legal game actions; the shared daily PAWS cap
  limits economic impact but is not bot detection.
- The database and host still require patching, backups, least-privilege access,
  monitoring, and container/image vulnerability scanning outside this repo.
