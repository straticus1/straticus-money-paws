# Lantern Lines

Status: concept proposal
Product: paws.money v5
Format: solo, turn-based network puzzle
Business model: free to play; capped rewards in internal PAWS only

## Product promise

High above the sleeping neighborhood, the player's pet tends an old signal
house. Rotate carved glass tiles to carry moonlight from the beacon to three
porch lanterns. Finish with a clean, unbroken circuit before closing the little
station for the night.

The image players should remember is their pet pulling a brass lever as every
window below comes alive at once, connected by a glowing line across a dark
blue hillside.

## Why this belongs in paws.money

- The owned pet has a new role: keeper, engineer, and neighbor.
- It adds a visual network-planning game beside memory, exploration, and logic
  deduction.
- A complete board is understandable at a glance and works well on touch,
  keyboard, and desktop pointer input.
- It has no timer, twitch requirement, wager, inventory consumption, or random
  reward outcome.
- Signal-house frames, glass patterns, lever handles, and skyline treatments
  can become cosmetic bond unlocks without selling puzzle power.

## Core loop

1. **Choose the keeper.** Select one living owned pet to operate the signal
   house.
2. **Read the board.** A moonwell source, three neighborhood lanterns, fixed
   stone blocks, and rotatable glass-path tiles are all visible.
3. **Trace the light.** The currently connected network glows. Open ends and
   unlit lanterns remain clearly marked.
4. **Turn one tile.** Rotate a movable tile clockwise or counterclockwise. The
   server applies exactly one turn and recomputes the public light network.
5. **Refine the circuit.** Connect every lantern, remove all edge leaks, and
   stay near the published careful-turn target.
6. **Pull the lever.** Submit the finished network for server validation. A
   successful circuit awards one to three stars and closes the shift.

A standard round should take three to six minutes. Players may rotate tiles as
often as they like. There is no failure timer and no harm to the selected pet.

## Board language

The MVP board is a 5×5 square grid. Each cell contains one public tile:

| Tile | Connections | Behavior |
|---|---|---|
| Moonwell | one edge | Fixed source of the light network |
| Lantern | one edge | Fixed destination that must be powered |
| Straight glass | two opposite edges | Rotates between horizontal and vertical |
| Elbow glass | two adjacent edges | Four possible orientations |
| Splitter glass | three edges | Routes one line into two branches |
| Crossing glass | four edges | Both axes connect; fixed in the MVP |
| Stone | no edges | Fixed blocker |
| Empty hillside | no edges | Decorative non-interactive space |

Every connection is represented by both shape and direction. Lit paths add
brightness and a small pulse, but color is never the only indication. Screen
reader labels describe a tile as, for example, “row two, column four, elbow
open north and east, lit.”

### Network rules

- Light starts at the moonwell and travels only across matching tile edges.
- A lantern is powered only if its inward edge joins the lit network.
- A path ending at the outer board edge is a leak.
- A path ending against stone, empty hillside, or a mismatched neighbor is a
  break. Breaks are allowed while editing but not in a clean final network.
- All three lanterns must belong to the same network as the moonwell.
- Rotating an unrelated tile still counts as a turn. Resetting the board
  restores the initial server state and resets turns only before completion.
- Pulling the lever never changes the board. It asks the server to grade the
  current persisted state.

The server returns which public paths are lit, broken, or leaking. There is no
private answer to accidentally expose and no need to obscure legal board data.

## Puzzle generation and proof

Rewarded puzzles are generated offline from a solved network:

1. Place one moonwell and three lanterns on distinct board edges.
2. Construct a connected, acyclic path from the source to every lantern.
3. Replace junctions and turns with the corresponding glass tiles.
4. Add fixed crossings, stone, and empty cells to shape the composition.
5. Choose eight to ten meaningful tiles and scramble their orientations.
6. Search the full rotation state space to find the minimum number of turns.
7. Reject boards that have no solution, exceed the search budget, can be
   completed without using every intended branch, or begin already solved.
8. Store the puzzle version, initial orientations, minimum turns, solution
   count, and verification report.

The MVP does not require a unique final orientation. Multiple clean solutions
are welcome if the verifier proves each one powers the same three lanterns and
the published turn target remains fair.

With at most ten rotatable tiles and four orientations per tile, the verifier
can exhaustively search at most 4¹⁰ states before publishing. Symmetry
normalization for straight tiles cuts the practical state space further.

Practice puzzles may be created on demand only if the same verifier approves
them before the API creates a session. Daily rewarded puzzles should be
versioned and published ahead of time by an explicit job.

## Scoring

Stars describe visible accomplishments:

- **1 star:** power all three neighborhood lanterns.
- **2 stars:** power all lanterns with no broken or leaking edge in the lit
  network.
- **3 stars:** achieve the clean circuit within the published careful-turn
  target, set to the verified minimum plus two turns.

Suggested rewards are 5 PAWS, 8 PAWS, and 10 PAWS. They draw from the same
row-locked 125 PAWS per-account UTC daily game budget as the existing games.
After the cap is exhausted, Lantern Lines remains fully playable and clearly
labels the shift as reward-free.

The client never submits lit paths, connected lanterns, breaks, leaks, minimum
turns, stars, completion, reward, currency, bond XP, or journal state.

## Pet integration

The selected pet appears in the signal-house window and operates the closing
lever. Its name appears in the shift card and completion scene. Species and
care stats do not change the board, turn target, score, or reward.

The first rewarded completion of the daily board grants bounded bond XP and a
journal stamp to the keeper. Later bond milestones may unlock:

- stained-glass tile faces;
- brass, copper, or painted lever handles;
- signal-house roof shapes;
- distant skyline silhouettes;
- completion chimes and reduced-motion light effects.

All unlocks are account-bound, cosmetic, non-transferable, and carry no PAWS,
USD, cryptocurrency, or resale value.

## Daily structure

- Every account receives the same verified daily board.
- The careful-turn target is visible before the first move.
- One daily completion grants journal credit, bond XP, and a capped PAWS
  reward.
- Replays are allowed for a better displayed star result but cannot mint a
  second reward or repeat bond XP or journal credit.
- Practice boards award no PAWS and may be reset without penalty.
- A seven-day skyline shows one newly lit window for each completed daily
  puzzle. Missed days remain dark without breaking a streak or demanding a
  paid repair.

Shared boards invite discussion without a speed leaderboard. The product may
show personal best turns, but should not rank accounts by speed or volume.

## Interaction and accessibility

### Pointer and touch

- Tapping the center of a tile rotates clockwise.
- Two explicit buttons below the focused tile rotate clockwise or
  counterclockwise, so the game never depends on gesture discovery.
- Pulling the lever is a separate confirmation action.

### Keyboard

- Arrow keys move focus between tiles.
- `R` or Enter rotates clockwise; Shift+`R` rotates counterclockwise.
- A persistent instruction panel lists these controls.
- Focus never moves after a rotation, enabling rapid local comparison.

### Assistive technology

- The board uses a grid with row and column labels.
- Every tile exposes type, orientation, fixed or movable state, and current
  connectivity in its accessible name.
- A textual circuit summary reports “two of three lanterns lit, one break, no
  edge leaks” after each move through a polite live region.
- Reduced-motion mode removes path pulses and replaces the completion sweep
  with an immediate high-contrast state change.
- Directions and connection shapes always accompany color and glow.

## Visual direction

Lantern Lines should look like an electromechanical observatory built inside a
storybook attic:

- midnight ultramarine walls rather than Midnight Pantry's near-black shop;
- translucent cyan glass paths with warm ivory light at connected edges;
- oxidized brass frames, knurled controls, and tiny engraved row numbers;
- a deep hillside cutaway below the board with three miniature porch windows;
- a large physical lever on the right, not a generic primary button;
- narrow technical lettering for labels paired with a round, old-style serif
  for the title.

The composition centers the network board. The pet watches from a small offset
window, so it feels present without covering puzzle information. Desktop uses
the board and lever as one wide console. Mobile stacks the circuit summary and
lever below a square board with at least 44×44 pixel tile targets.

Avoid neon cyberpunk, casino bulbs, particle explosions, and electrical danger
language. This is moonlight through glass, not a power grid under stress.

## Trust boundary and anti-cheat model

The board may be public because understanding the full network is the game.
The browser remains an untrusted renderer and input device. The API owns the
initial orientations, current orientations, rotation count, reset count,
connectivity calculation, grading, best result, journal credit, bond XP, and
reward settlement.

```text
untrusted browser
    -> authenticated action { sessionId, action, actionId }
    -> strict schema + ownership check
    -> user/session row locks
    -> server applies one legal rotation or reset
    -> server recomputes the graph from persisted orientations
    -> server derives circuit summary, stars, and capped reward
    -> idempotent PAWS ledger settlement
```

Required controls:

- Use versioned, server-owned puzzle definitions.
- Verify every rewarded definition before publication.
- Re-run structural validation when loading persisted definitions and fail
  closed if a board is malformed.
- Allow the browser to submit only a tile position and direction for rotation,
  or a parameter-free `submit`, `reset`, or `abandon` action.
- Reject unknown properties including `board`, `orientation`, `lit`, `turns`,
  `minimumTurns`, `stars`, `reward`, `currency`, `completed`, and pet state.
- Scope every pet, session, and action query to the authenticated user.
- Lock user and session rows in a consistent order for all mutations.
- Store and replay the exact first response for every UUID action key.
- Derive reward-ledger idempotency from the server-created session ID.
- Reserve PAWS through the existing shared daily reward row.
- Grant daily bond XP and journal credit once, regardless of resets or replays.
- Rate-limit starts, rotations, resets, and submissions.
- Treat impossible tile positions and fixed-tile rotations as rejected actions,
  not clues about a private solution.

A modified client can draw a solved board or compute the best route locally.
That is acceptable: it still cannot alter persisted orientations, forge a
completion, exceed the shared reward cap, or create PAWS. The economic defense
is server authority, idempotency, and a bounded daily reward, not obscurity.

## Suggested API

```text
POST /api/v1/games/lantern-lines
  { petId, mode: "daily" | "practice", actionId }

GET /api/v1/games/lantern-lines/:sessionId

POST /api/v1/games/lantern-lines/:sessionId/actions
  { action: "rotate", position, direction: "clockwise" | "counterclockwise", actionId }
  { action: "submit", actionId }
  { action: "reset", actionId }
  { action: "abandon", actionId }
```

Responses contain the public tile definitions, persisted orientations, lit
edges, powered lantern IDs, public circuit summary, rotation count, careful-turn
target, session status, and already-settled result. They never accept or echo a
browser-calculated grade.

## Data model direction

Use purpose-built tables rather than a generic JSON game engine:

- `lantern_puzzles`: key, mode, version, public board definition, minimum
  rotations, solution count, verification report, active flag, timestamps.
- `lantern_sessions`: owner, pet, puzzle, mode, status, orientations, rotations,
  resets, best stars, reward, journal flag, timestamps.
- `lantern_actions`: session, owner, UUID idempotency key, strict action, exact
  response, timestamp.
- existing `pet_game_progress`: add `lantern_lines` as a game type.
- existing `daily_game_rewards`: reserve the PAWS award under the shared cap.

The graph evaluator and verifier should be pure TypeScript modules with no
database imports. Tests can then prove connectivity, rotation normalization,
leak detection, minimum-turn calculation, and public serialization separately
from HTTP and settlement behavior.

## MVP scope

Build:

- one authored 5×5 daily board and one verified practice generator;
- straight, elbow, splitter, fixed crossing, stone, source, and lantern tiles;
- clockwise and counterclockwise rotation, reset, submit, and abandon actions;
- live circuit summary and explicit accessible controls;
- one to three server-derived stars;
- first-completion journal credit and bounded bond XP;
- 5, 8, and 10 PAWS tiers through the shared cap;
- personal best-turn display for the current puzzle;
- ownership, idempotency, concurrency, graph, verifier, and reward-cap tests.

Defer:

- community puzzle creation;
- competitive leaderboards;
- real-time multiplayer;
- timed challenges;
- species abilities or care-stat bonuses;
- purchasable hints, turns, or functional tiles;
- tradable cosmetics or any real-money feature.

## Acceptance criteria

- Every published daily board is proven solvable and has a stored minimum turn
  count before a session can start.
- Every accepted action changes at most one server-owned orientation or performs
  one clearly defined session transition.
- Connectivity, breaks, leaks, stars, and completion are recalculated by the
  server from persisted state.
- Repeating an action UUID returns the exact original response without applying
  a second rotation, reward, bond grant, or journal stamp.
- Another account cannot view or mutate a session or use its pet.
- Extra economic, result, board, or pet-state fields are rejected.
- A clean three-star solution can be completed without relying on color,
  animation, a pointer, audio, or a browser clock.
- Completion posts at most one PAWS ledger transfer and never exceeds the shared
  daily cap.
- Practice and cap-exhausted rounds remain complete, satisfying games.

## Design decision

Lantern Lines deliberately keeps the full board public. Hiding the network
would turn a calm planning puzzle into trial and error. Server authority protects
the economic outcome; secrecy is used only by games whose play actually depends
on hidden information. This makes the trust model simpler and the game more
accessible without weakening the PAWS boundary.
