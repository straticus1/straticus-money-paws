# Parade Practice

Status: concept proposal
Product: paws.money v5
Format: solo, turn-based command-programming puzzle
Business model: free to play; capped rewards in internal PAWS only

## Product promise

The neighborhood parade begins soon. Arrange a short row of direction cards,
then watch the player's pet follow the whole routine across a tabletop street,
collecting three pennants before reaching the bandstand.

The memorable moment is the plan running in one continuous paper-theater
sequence: the pet turns the last corner, catches the final pennant, and arrives
at a cheering miniature bandstand.

## Place in the six-game grid

| Game | Primary skill |
|---|---|
| Paw Match | Memory |
| Trail Tails | Exploration |
| Midnight Pantry | Deduction |
| Lantern Lines | Network construction |
| Pocket Post | Spatial sequencing |
| Parade Practice | Planning and computational thinking |

## Core rules

- A public 6×6 board contains a start, bandstand, three pennants, and fixed
  obstacles.
- The pet begins facing one cardinal direction.
- The player fills up to twelve command slots with `forward`, `turn-left`,
  `turn-right`, or `hop`.
- `forward` moves one cell. `hop` crosses one obstacle into the clear cell
  beyond it. Turns change facing without moving.
- Running the routine submits the complete command list once. The server
  simulates it from the authored start state and returns each public frame.
- Hitting a wall or using an illegal hop ends that run safely. The player may
  revise the program and run again.
- A session completes only when all pennants are collected and the pet finishes
  on the bandstand.
- There is no timer, damage, entry fee, or consumed inventory.

## Puzzle proof and scoring

An offline breadth-first solver searches `(position, facing, pennant mask)` and
stores the shortest valid program length. Published boards must be solvable in
twelve commands or fewer.

- **1 star:** collect all pennants and reach the bandstand.
- **2 stars:** finish within the verified minimum plus two commands.
- **3 stars:** match the verified minimum command count.

Runs and edits do not reduce stars. Suggested rewards are 5, 8, and 10 PAWS
through the shared 125 PAWS daily cap. Practice and replay remain reward-free.

## Pet, accessibility, and visual direction

The owned pet is the parade leader. Species and care stats never alter commands,
obstacles, scoring, or rewards. First daily completion grants bounded bond XP
and one journal stamp.

Every command is a labeled button with an icon and text. Slots can be reordered
with move-left/move-right controls, not drag alone. The board exposes row,
column, facing, pennants, and obstacles to assistive technology. Reduced-motion
mode shows the final frame plus a textual run log instead of animation.

The interface looks like a 1930s screen-printed parade poster assembled as a
paper toy theater: tomato red, marching-band gold, faded sky blue, cream stock,
bold slab lettering, visible tabs, and a scalloped proscenium. It must not look
like a developer console despite teaching program sequencing.

## Server authority

The browser sends only an ordered list of allowed command names plus a UUID. The
API owns the puzzle, start state, simulation, run count, shortest solution,
completion, stars, journal credit, bond XP, and capped reward.

- Strict schemas reject positions, facing, pennants, frames, success, stars,
  reward, currency, pet state, and extra commands.
- Programs are capped at twelve commands and simulated with a fixed step budget.
- User and session rows are locked before mutation.
- Pet and session lookups are owner-scoped.
- Exact responses are persisted by action UUID.
- Ledger idempotency derives from the server session ID.
- Rewards use the shared daily reward row.
- A locally forged animation cannot persist a win or mint PAWS.

## Suggested API

```text
POST /api/v1/games/parade-practice
  { petId, mode: "daily" | "practice", actionId }

GET /api/v1/games/parade-practice/:sessionId

POST /api/v1/games/parade-practice/:sessionId/actions
  { action: "run", commands: ["forward", "turn-left", ...], actionId }
  { action: "abandon", actionId }
```

## Data model

- `parade_puzzles`: key, mode, version, public definition, minimum commands,
  verification report, active flag, timestamps.
- `parade_sessions`: owner, pet, puzzle, mode, status, runs, best command count,
  stars, reward, journal flag, timestamps.
- `parade_actions`: session, owner, UUID key, strict action, exact response.
- existing pet progress and shared daily reward tables.

## MVP acceptance criteria

- The authored board is server-proven solvable within twelve commands.
- Simulation is deterministic and bounded.
- The client receives public run frames but cannot submit them.
- Invalid commands, oversized programs, and forged result/economic fields fail.
- Repeated UUIDs cannot duplicate runs, progress, or value.
- Completion works without color, sound, animation, pointer input, or a clock.
- One completion creates at most one capped PAWS transfer.
