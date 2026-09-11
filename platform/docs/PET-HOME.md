# Clover Room: first playable milestone

Implemented in the working tree on 2026-09-11. Not deployed by this change.

The follow-on [companion milestone](COMPANION-MEMORIES.md) now adds persistent
identity, relationship bond, daily adventures, journal memories and earned
cosmetics. This document records the original home foundation; the follow-on
document describes its additional progression behavior.

## Player experience

The default signed-in destination and `#/home` open a private room. Existing pet
adoption and management remain at `#/pets`. Players select a pet, give affection,
feed owned food, play with an owned toy, and arrange owned furniture in 20 floor
positions. Furniture placement persists across visits. Putting furniture away
does not grant another copy; it simply frees the existing copy for placement.

Dogs, cats, foxes, and rabbits have original SVG character art with blinking,
head movement, and reactions to accepted interactions. Other species retain
animated species icons. The room uses HTML, SVG, and CSS; a general-purpose
rendering engine and individual AI portraits have not been integrated.

Room controls are native buttons and selects, with named positions, focus
indicators, live action feedback, labeled stat meters, and reduced-motion styles.
These implementation measures still need a hands-on browser/accessibility check.

The catalog adds Clover Crunch (5 PAWS). It supplies a complete earned-currency
care loop: play a game → buy food → feed a pet. No cash deposit is needed.
New accounts must earn PAWS before buying it; no starter currency is granted.

## Server guarantees and limits

- Every read and action derives its owner from the authenticated session.
  Private home responses use `Cache-Control: no-store`.
- Strict commands accept only an action, permitted IDs/position, UUID action ID,
  and expected room version. Bodies are limited to 2 KiB.
- All home commands lock the account and room in PostgreSQL. Pet and inventory
  rows are also locked before changes. This works across API processes.
- One immutable response receipt per account/action ID binds a SHA-256 hash of
  the validated payload. Equivalent JSON key order is accepted. A changed
  payload with an old key is rejected, and an exact retry replays its original
  response. The UI then retrieves current state to avoid displaying an old replay.
- Successful commands increment the room version. Stale tabs receive a conflict
  and refresh, rather than overwriting newer room edits.
- Placement checks category, occupancy, ownership, and quantity. Current cosmetics
  remain in inventory. Before introducing trading or item revocation, extend those
  workflows to reconcile or reserve placed items transactionally.
- One care interaction per account per 10 seconds, using database time. Switching
  pets or API instances does not reset it. Food is consumed once; toys are reusable.
  Affection adds two happiness points, toys five, and food uses its catalog effects.
  Stats cap at 100; deceased pets cannot receive care.
- A shared PostgreSQL window limits validated home commands to 30 attempts per
  account per minute. Invalid semantic attempts count; exact successful retries
  bypass that counter to permit recovery. Existing HTTP limits still apply.
- Rejected validated commands emit `home.action_rejected` audit events. These are
  evidence for future analysis, not a bot classification or automatic ban system.
- No home action awards PAWS, USD, trophies, or game progression.

The legacy feed endpoint retains its prior behavior; the new care cooldown is
specific to home commands. Existing feeding still requires and consumes food.
The shared home limiter does not replace process-local limits on other endpoints.
Legal automation and copying visible graphics remain possible.

## Applying locally

With `DATABASE_URL` set to the intended local database, from `platform/`:

```sh
pnpm --filter @paws/db migrate
pnpm --filter @paws/db seed
pnpm -r build
pnpm dev
```

In another terminal, run `pnpm --filter @paws/web dev` and open the Vite URL.
The seed is idempotent by item name. Migration `0008_pet_home.sql` is additive.
The schema must be migrated before the updated API serves home requests.

## Verification

Final checks: all seven packages build and typecheck; 11 home API tests and 26
SDK tests pass; production dependency audit reports no known vulnerabilities.
Existing API coverage also passed, with the timeout retry described below.

The home API suite covers authentication, cross-account access, strict schemas,
oversized bodies, modified retries, exact concurrent retries, stale state,
inventory quantity and categories, server cooldowns, cross-instance limits,
rollback, cross-origin cookie mutations, audit evidence, and unchanged balances.
Its journey test solves Paw Match using only legally revealed cards, spends the
earned PAWS on catalog food, and feeds the pet through the home API.

The SDK suite tests uncertain-delivery retries retaining their action ID and
version, and exposes conflicts without silently resubmitting them.

Full API regression testing initially hit the existing five-second timeout in
the first new-games puzzle test. That suite passed on retry with a 15-second
allowance. No timeout configuration or game rules were changed.

The browser integration reported no connected browsers, so visual and interactive
browser verification was unavailable. Build/type checks and API tests do not
substitute for that check. No claim of a completed visual audit is made.

## Still on the roadmap

Paid cosmetics/membership checkout, AI generation jobs, inherited appearances and
breeding, passkeys/recovery, bot classification and appeals, and protected media
delivery remain unimplemented. The broader requirements are tracked in
[the next-generation plan](NEXT-GENERATION-PET-GAME.md).
