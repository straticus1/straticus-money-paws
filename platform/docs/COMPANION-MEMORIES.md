# A pet that remembers you

Implemented locally on 2026-09-11. This milestone uses the existing PostgreSQL,
Fastify and Preact stack. It needs no paid AI provider or production deployment.

## Playable loop

1. Open the Clover Room and choose a pet.
2. Start that pet’s daily adventure. One companion is locked in per account for
   that UTC day; changing the selected pet does not restart the adventure.
3. Give affection and feed that companion in the home after starting the adventure.
4. Start and finish a new game. All six games count, including practice. Hosted
   games must use the daily companion. Paw Match accompanies the account’s active
   daily companion because it has no separate host selector.
5. Return home to record the outing, then collect the keepsake before UTC reset.
6. Place the keepsake in the room and see the new journal entry and bond progress.

The daily checklist expires at midnight UTC. Return home before the reset to
record game credit and collect the keepsake. There is no streak mechanic, bond
decay, or missed-day deduction. Already-recorded bond and memories persist.

## Persistent identity

Each pet receives a server-generated personality, coat, markings, and favorite
food, toy, and game on its first home visit. This also backfills existing pets.
The assignment is persisted under the account lock and cannot be rerolled through
the API. New appearances do not alter species or existing care stats.

- Personalities: curious, gentle, playful; reflected in care messages and animation
  timing.
- Coats: honey, silver, cocoa, cream. Markings: blaze, socks, speckles.
- Favorite foods: Clover Crunch or Sunbeam Nibbles. Both cost 5 PAWS and have
  equal care effects, keeping the favorite-snack experience accessible through play.
- Favorite toys: Squeaky Moon, Rolling Acorn, Ribbon Comet, already in the catalog.
- Favorite discoveries add journal entries and distinct messages, with no XP bonus.

Dogs, cats, foxes, and rabbits render their coat and markings on the SVG character.
Other species use a persistent colored/patterned identity badge around their
species icon, plus the earned bandana. Fully illustrated bodies for those species
remain art work for a later milestone. AI images are not generated here.

## Bond and rewards

| Verified event | Bond | Frequency |
|---|---:|---|
| Affection in the home | 3 | Once per pet per UTC day |
| Feeding in the home | 4 | Once per pet per UTC day |
| Playing with an owned toy | 3 | Once per pet per UTC day |
| Recorded game objective | 8 | Once for the account’s daily companion per UTC day |
| Collecting the adventure keepsake | 10 | Once per account per UTC day |

The daily companion can gain at most 28 bond per day through this milestone;
other pets can gain 10 through care. Lifetime bond is capped at 10,000. Care
remains possible after its daily credit is used, subject to the home cooldown;
feeding still consumes an item. Legacy dashboard feeding does not grant companion
bond or complete home adventure objectives.

Bandanas unlock at 0 (moss), 40 (sunflower), 100 (berry), and 200 (midnight) bond.
The server checks eligibility on every equip request. A smile unlocks at 20 bond
for illustrated characters; heart greetings unlock at 60. These are cosmetic.

Daily keepsakes rotate among Pressed Clover, Sunlit Pebble, and Little Trail Map.
They are inactive store entries, granted only by the server after verifying all
objectives, and can be placed using existing inventory checks. Claims grant no
PAWS or USD. Existing game payouts, per-game bond tables, and leaderboard scoring
remain unchanged; companion bond is a separate relationship measure.

## Trust boundary

The browser submits only the existing strict home command envelope. New commands
are `begin_adventure`, `claim_adventure`, and `equip_bandana`. It cannot submit XP,
personality, appearance, objective flags, dates, game IDs, or rewards.

The home transaction locks the account, room and relevant records. Event keys
uniquely identify each daily credit and discovery. Journal entries cannot be
updated in place. Claims atomically update the adventure, inventory and journal;
failures roll back all grants. Existing action receipts bind exact command content
to an action ID and room version, preventing duplicate effects on retries.

Game credit is reconciled from persisted completed game sessions when the home is
read or a home command returns a snapshot. It requires ownership, the correct
host where applicable, creation after the adventure started, and completion
within the adventure’s UTC day and no later than server time. Old saved games,
another account’s games, active/failed/abandoned games, and future timestamps do
not qualify. Reconciliation itself is idempotent. No game engine rewrite or
client-supplied completion endpoint was introduced.

Reading the home initializes missing identities and records newly verified game
credit. This is a bounded server reconciliation, not a browser claim of success.
Only the latest 12 memories per pet are returned; older entries remain stored.
Private home responses remain non-cacheable and owner-scoped.

These controls do not distinguish a person from a bot making legal moves.
Unlocks have no cash value or transfer path in this milestone.

## Local setup and verification

With a local `DATABASE_URL` set, from `platform/`:

```sh
pnpm --filter @paws/db migrate
pnpm --filter @paws/db seed
pnpm -r build
pnpm dev
```

Run `pnpm --filter @paws/web dev` in another terminal. Migration
`0009_companion_memories.sql` adds identity, journal, adventure tables and indexed
completion lookups. Seeding adds Sunbeam Nibbles. No live infrastructure is changed.

`companions.test.ts` covers persisted identity, input tampering, daily care caps,
favorites, cosmetic eligibility, privacy, UTC rollover, one daily companion,
premature claims, a real Paw Match/care/keepsake journey, concurrent claim replay,
all five hosted-game adapters, exclusion of old/future/foreign sessions, and
transaction rollback. Hosted adapter fixtures test credit attribution; their
engines remain covered by the existing game suites.

Browser discovery reported no available browser in this session. Build and API
verification do not establish visual or hands-on interaction correctness; a
browser review remains necessary before release.

Final verification: all 94 API tests passed on a fresh local database, all 26
SDK tests passed, and all seven packages built and typechecked. The API run used
a 15-second test timeout for the existing cold puzzle-generation checks. No
production code timeout was changed and no dependencies were added.
