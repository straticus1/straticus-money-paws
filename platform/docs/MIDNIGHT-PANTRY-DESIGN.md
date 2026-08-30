# The Midnight Pantry

Status: concept proposal
Product: paws.money v5
Format: solo, turn-based deduction puzzle
Business model: free to play; capped rewards in internal PAWS only

## Product promise

After the neighborhood goes quiet, your pet opens a tiny pantry window for
hungry night visitors. Read each guest's clues, assemble the right snack bowls,
and close the pantry with every visitor cared for and as little food wasted as
possible.

The image players should remember is their own pet behind a glowing counter at
midnight, reading a final handwritten order while animal silhouettes wait
under paper lanterns.

## Why this belongs in paws.money

- The player's pet becomes a host and caregiver rather than another game token.
- It turns food, treats, and care into a playful theme without consuming the
  player's real inventory or making neglected care stats a penalty.
- It adds deduction and resource planning beside Paw Match's memory challenge
  and Trail Tails' exploration challenge.
- Successful rounds can grant pet bond XP and capped internal PAWS through the
  existing game reward boundary.
- Future store cosmetics can decorate the pantry without changing solutions,
  odds, scores, or rewards.

## Core loop

1. **Choose the host.** Select one living owned pet to run tonight's pantry.
2. **Read the guest board.** Three visitors arrive with visible preferences,
   allergies, and indirect clues about the bowl each one needs.
3. **Inspect the pantry.** The round provides a fixed set of ingredient tokens.
   Every puzzle has one verified solution and enough ingredients to solve it.
4. **Build a bowl.** Assign two ingredients to a visitor, revise freely, then
   ring the service bell to lock that order.
5. **Read the reaction.** The server reports which visible requirements were
   satisfied. It never reveals another guest's hidden answer.
6. **Close the pantry.** Serve all three visitors within six bell rings. Stars
   come from correct service, avoiding waste, and solving without a correction.

A round should last four to seven minutes. There is no countdown clock, so
latency, accessibility tools, and DevTools cannot influence the score.

## The puzzle language

### Ingredients

The MVP uses six fictional, non-branded ingredients:

| Ingredient | Family | Texture | Temperature | Symbol |
|---|---|---|---|---|
| Moonberry | fruit | soft | cool | crescent |
| Sunroot | vegetable | crunchy | warm | sun |
| Cloud Oats | grain | soft | warm | cloud |
| River Kelp | green | chewy | cool | wave |
| Star Biscuit | grain | crunchy | warm | star |
| Meadow Mint | green | soft | cool | leaf |

Ingredient names are flavor. The family, texture, temperature, and symbol are
the stable logical properties used by the puzzle generator.

### Guest information

Each visitor shows three kinds of information:

- **Need:** a direct requirement, such as “one ingredient must be crunchy.”
- **Avoid:** a direct exclusion, such as “nothing from the grain family.”
- **Clue:** a relational statement, such as “my bowl shares exactly one
  property with the rabbit's bowl.”

Clues use icons and plain text together. The game never requires color vision,
species knowledge, or guessing an animal stereotype.

### Bowl rules

- Every bowl contains exactly two different ingredients.
- An ingredient token can be used only as many times as it appears in tonight's
  pantry.
- The player may rearrange unlocked bowls without spending a bell ring.
- Ringing the bell locks one guest's bowl and spends one of six bell rings.
- An incorrect bowl stays editable, but the correction costs another bell ring.
- The server accepts a bowl only when it satisfies that guest's complete rule
  set and the pantry still permits a valid solution for remaining guests.

That last rule prevents a technically valid early bowl from making the rest of
the puzzle impossible.

## Difficulty progression

### First Shift

- Two guests.
- Four ingredients.
- Direct needs and avoids only.
- Four bell rings.
- Designed as the tutorial and always available for practice.

### Lantern Shift

- Three guests.
- Six ingredients.
- One relational clue.
- Six bell rings.
- Standard rewarded daily mode.

### Owl Shift

- Four guests.
- Eight ingredients.
- Multiple relational clues and one shared resource constraint.
- Practice-only until the generator and accessibility tests prove it is fair.

Difficulty changes the logic, not the reward randomness. Every generated puzzle
must be solved and graded by the server before it can be offered to a player.

## Scoring

Stars describe explicit accomplishments:

- **1 star:** every visitor receives a correct bowl.
- **2 stars:** finish with no more than one incorrect bell ring.
- **3 stars:** finish without submitting an incorrect bowl.

Suggested rewards are 5 PAWS, 8 PAWS, and 10 PAWS. They draw from the existing
shared 125 PAWS per-account UTC daily game budget. When the cap is exhausted,
the pantry remains fully playable and still grants bounded bond XP and journal
progress.

The client never submits a star count, correctness result, remaining-token
count, reward amount, currency, completion flag, or elapsed time.

## Pet integration

The chosen pet appears as tonight's host, with its name used in visitor notes
and completion copy. Every species is equally capable. Hunger, happiness, and
health are shown only as care context and never change puzzle difficulty,
ingredient supply, scoring, or rewards.

Completing a shift grants bond XP to the host. Pantry-specific bond milestones
can later unlock:

- counter signs;
- apron colors;
- lantern shapes;
- guest-book stamps;
- closing-time animations.

These unlocks are cosmetic, account-bound, non-transferable, and carry no PAWS
or real-money value.

## Daily structure

- Every account receives the same authored daily puzzle definition, but each
  session stores its own server-owned state.
- A personal practice puzzle can be generated at any time for zero PAWS.
- The daily puzzle can be completed once for journal credit. Replaying it is
  allowed, but cannot repeat the journal or PAWS reward.
- A seven-day guest book shows solved shifts and star ratings without a loss
  streak, punishment, or paid recovery mechanic.

Sharing the same daily logic puzzle gives players something to discuss without
introducing direct competition, wagering, or leaderboards that reward bots.

## Trust boundary and anti-cheat model

The browser renders the counter and submits deliberate choices. The API owns
the puzzle definition, hidden solution set, token supply, bell count, locked
orders, correctness, stars, journal credit, and reward settlement.

```text
untrusted browser
    -> authenticated action { sessionId, action, actionId }
    -> strict schema + ownership check
    -> user/session row locks
    -> server validates one placement or submission
    -> solver confirms remaining puzzle is still satisfiable
    -> server derives public reaction, stars, and reward
    -> shared cap + idempotent PAWS ledger settlement
```

Required controls:

- Generate puzzles from versioned server-side definitions.
- Run an exhaustive solver before publishing or instantiating a puzzle.
- Require exactly one solution for rewarded daily puzzles.
- Store the canonical solution and unrevealed constraints only in PostgreSQL.
- Return only visible clues, public pantry tokens, and the player's own legal
  placements.
- Reject unknown fields, including `correct`, `solution`, `stars`, `reward`,
  `currency`, `inventory`, `bellRings`, and `completed`.
- Scope every pet, session, and action lookup to the authenticated user.
- Lock the user and session rows in a consistent order.
- Store and replay the exact response for each UUID action key.
- Derive ledger idempotency from the server-created pantry session ID.
- Reserve rewards through the shared `daily_game_rewards` row.
- Enforce one active pantry session per user and one rewarded completion per
  daily puzzle key.
- Rate-limit session creation, placements, and bell submissions independently.
- Do not use browser clocks, animation completion, local storage, or page
  visibility as authoritative inputs.

Changing JavaScript in DevTools may redraw ingredients or fake a local success
screen, but it cannot create a legal locked order, advance the guest book, or
mint PAWS.

## Puzzle generation and verification

A rewarded puzzle is built backward from a complete assignment:

1. Choose a legal pair of ingredients for each guest within the token supply.
2. Derive a mix of direct, exclusion, and relational clues from that assignment.
3. Enumerate every assignment allowed by the published clues.
4. Reject the puzzle unless exactly one complete assignment remains.
5. Simulate legal orderings to ensure no accepted early bowl can dead-end the
   remaining guests.
6. Store the generator version, puzzle key, solution hash, and verification
   report with the daily definition.

The solution hash is an audit aid, not a browser-verifiable commitment. Sending
it to the client would add complexity without preventing a compromised client.

## Suggested API

```text
POST /api/v1/games/midnight-pantry
  { petId, mode: "daily" | "practice", actionId }

GET /api/v1/games/midnight-pantry/:sessionId

POST /api/v1/games/midnight-pantry/:sessionId/actions
  { action: "place", guestId, slot: 0 | 1, ingredientId, actionId }
  { action: "remove", guestId, slot: 0 | 1, actionId }
  { action: "serve", guestId, actionId }
  { action: "abandon", actionId }
```

The action schema is a strict discriminated union. Placement responses contain
only the new public counter state. A serve response may report satisfied visible
requirements and a general “something is still off” result, but never returns
the hidden solution or a more precise oracle that can enumerate it cheaply.

## Data model direction

Use purpose-built tables rather than adding pantry fields to Paw Match or Trail
Tails sessions:

- `pantry_puzzles`: date/key, mode, generator version, published clues, private
  solution, verification report, active flag.
- `pantry_sessions`: owner, host pet, puzzle, status, placements, locked bowls,
  remaining tokens, bell rings, mistakes, stars, reward, timestamps.
- `pantry_actions`: session, owner, idempotency key, action, exact response.
- existing `pet_game_progress`: add `midnight_pantry` as a game type.
- existing `daily_game_rewards`: reserve the PAWS award under the shared cap.

Daily puzzle publishing should be an explicit server job or admin command. The
game route may create practice puzzles, but it must never silently publish an
unverified rewarded puzzle during a request.

## Experience and visual direction

The Midnight Pantry should feel like a tiny stage set seen through a shop
window. Use deep ink-blue surroundings, warm amber lanterns, chalky recipe
tickets, enamel bowls, and small pools of light. The visual focus is the counter,
not a dashboard.

The layout has three layers:

1. visitors and their pinned clue cards across the top;
2. bowls and ingredient placements at counter level;
3. the shared pantry tray and service bell along the bottom.

Ingredients must be draggable and fully operable through click, touch, and
keyboard placement. Motion should make tokens feel physical, but reduced-motion
mode uses a quick opacity change. Incorrect service uses copy, iconography, and
the guest's expression rather than a red flash or screen shake.

This deliberately departs from Trail Tails' daylight paper-map treatment. Both
games feel handmade, but one is an outdoor field journal and the other is a
glowing nocturnal shop window.

## MVP boundary

Build only:

- First Shift tutorial and Lantern Shift daily mode;
- six ingredients with fixed logical properties;
- three reusable visitor characters plus generated names;
- direct, exclusion, and one relational clue type;
- placement, removal, serving, correction, and abandonment actions;
- unique-solution generator and exhaustive verifier;
- one daily journal completion and shared-cap PAWS settlement;
- pantry bond XP and one cosmetic guest-book stamp;
- responsive keyboard, touch, and reduced-motion support;
- security, ownership, idempotency, concurrency, solver, and reward-cap tests.

Defer real-time service, multiplayer, leaderboards, player-authored puzzles,
inventory consumption, purchasable ingredients, rare drops, tradable cosmetics,
and any real-money or crypto interaction.

## Success criteria

- A first-time player understands how to place an ingredient within 30 seconds.
- Players can explain every clue and why the final assignment is correct.
- Standard rounds last four to seven minutes without a timer.
- At least 70% of tutorial starters complete First Shift without external help.
- Daily puzzles have exactly one solution and a stored verification report.
- Repeated, concurrent, cross-user, forged, and out-of-order actions cannot
  duplicate state, journal credit, bond XP, or PAWS.
- The game remains satisfying after the daily PAWS budget is exhausted.

## First prototype question

Can players solve a three-guest puzzle from the clue cards without opening a
rules panel, and does ringing the bell feel tense even though there is no clock?
Prototype that interaction before building the daily publishing pipeline or
cosmetic progression.
