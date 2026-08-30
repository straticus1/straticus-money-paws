# Pocket Post

Status: concept proposal
Product: paws.money v5
Format: solo, turn-based parcel-pushing puzzle
Business model: free to play; capped rewards in internal PAWS only

## Product promise

At first light, the player's pet opens a tiny neighborhood post office. Walk
through the sorting room, push every parcel onto a stamped dispatch mat, and
send the morning mail before opening the front shutters.

The memorable moment is the final parcel sliding home with a satisfying paper
stamp while the player's pet, wearing an oversized postal cap, raises the
shutters on a peach-colored dawn.

## Place in the five-game collection

| Game | Primary skill |
|---|---|
| Paw Match | Memory |
| Trail Tails | Exploration and resource planning |
| Midnight Pantry | Deduction |
| Lantern Lines | Network construction |
| Pocket Post | Sequencing and spatial foresight |

Pocket Post uses familiar box-pushing rules, but presents them as a calm postal
chore rather than a warehouse abstraction. Nothing is timed, lost, purchased,
or wagered.

## Core loop

1. Choose one living owned pet as postmaster.
2. Read a fully visible sorting-room grid containing the pet, walls, parcels,
   and dispatch mats.
3. Move one orthogonal cell at a time.
4. Walking into a parcel pushes it one cell if the destination is free.
5. Plan around corners because parcels cannot be pulled.
6. Deliver every parcel, then receive a server-derived star rating.

The tutorial board should take two to four minutes. Standard boards should take
four to eight minutes. Reset and undo are always free and never harm the pet.

## Rules

- The board is a 7×7 grid with fixed walls.
- The pet, parcels, and dispatch mats occupy public cells.
- The pet cannot cross walls or parcels.
- A parcel can be pushed only into an empty walkable cell.
- A parcel on a dispatch mat remains movable; completion occurs when every mat
  contains one parcel.
- Walking counts as a move. Pushing counts as both one move and one push.
- Undo restores exactly one prior persisted state and increments the undo
  counter. It cannot cross a completed session boundary.
- Reset restores the authored initial state and clears move/push/undo counts.
- There is no failure state, move limit, or browser clock.

Directions are shown with arrows, names, and grid relationships. Parcel and mat
identity never relies on color.

## Puzzle proof

Every rewarded board is authored or generated from a solved position, then
verified by a server-side state search. The solver stores:

- whether the board is solvable;
- the minimum number of pushes;
- the minimum moves among minimum-push solutions;
- unreachable cells and static corner deadlocks;
- the generator or authoring version.

Boards are rejected if a parcel begins in a non-goal corner, a mat is
unreachable, the solution exceeds the search budget, or the initial state is
already complete. Practice generation uses the same proof before a session is
created.

## Scoring and rewards

- **1 star:** deliver every parcel.
- **2 stars:** finish within the verified minimum pushes plus two.
- **3 stars:** match the verified minimum push count.

Walking and undo never reduce stars. This keeps scoring focused on irreversible
planning rather than penalizing accessibility tools or careful inspection.

Suggested rewards are 5, 8, and 10 PAWS. Pocket Post uses the existing shared
125 PAWS per-account UTC daily cap. Practice, replay, and cap-exhausted rounds
remain fully playable for zero PAWS.

The browser never supplies pushes, moves, positions, completion, stars, reward,
currency, journal credit, bond XP, or pet state.

## Pet and journal integration

The chosen pet is the board character and appears on the completion receipt.
Species and care stats never alter collision rules, board difficulty, stars, or
rewards. The first daily completion grants bounded bond XP and a journal stamp.

Future bond cosmetics may include postal caps, satchels, parcel paper, wall
posters, dispatch stamps, and shutter animations. They remain account-bound,
non-transferable, and economically inert.

## Daily structure

- One verified shared daily board.
- One rewarded completion and journal stamp per account per board.
- Unlimited reward-free replays and practice boards.
- Personal best pushes may be displayed without a public leaderboard.
- A seven-day mail rack fills with postcards; missed days do not break a streak.

## Interaction and accessibility

- Arrow keys and WASD move the pet.
- Four large direction buttons provide equal touch and pointer access.
- Undo, reset, and abandon are explicit labeled controls.
- The board is exposed as an accessible grid; every cell names its row, column,
  floor type, and occupant.
- A polite live summary reports parcel progress, pushes, and blocked moves.
- Completed parcels use a stamp, texture, and text in addition to color.
- Reduced-motion mode removes sliding and shutter animations.
- Input is serialized while a server action is pending.

## Visual direction

Pocket Post is a letterpress toy at sunrise:

- warm cream paper and peach dawn light;
- postal red, ink blue, and dark graphite outlines;
- slightly misregistered print layers and perforated stamp edges;
- a compact top-down room that looks assembled from thick cardboard;
- parcel labels with shapes and marks, never real addresses;
- an oversized mechanical date stamp for the completion moment.

It should feel bright and tactile, clearly separate from Trail Tails' field
journal, Midnight Pantry's dark counter, and Lantern Lines' glass observatory.

## Server authority and anti-cheat model

The full board is public because spatial planning is the game. The API owns and
persists the pet position, parcel positions, history stack, move count, push
count, undo count, completion, stars, journal credit, bond XP, and reward.

```text
untrusted browser
    -> authenticated action { sessionId, action, actionId }
    -> strict schema + ownership check
    -> user/session row locks
    -> server applies one legal move, undo, or reset
    -> server derives completion and stars from persisted state
    -> shared reward cap + idempotent PAWS ledger settlement
```

Required controls:

- Load only versioned, verified puzzle definitions.
- Reject unknown fields, especially positions, boxes, pushes, score, reward,
  currency, completion, and pet stats.
- Scope every pet, session, and action to the authenticated user.
- Lock the user then session in a consistent transaction order.
- Store and replay the exact response for each UUID action key.
- Derive ledger idempotency from the server-created session ID.
- Grant journal credit and bond XP once per daily puzzle.
- Reserve rewards through the shared daily reward row.
- Rate-limit starts and actions.
- Treat blocked moves as valid no-state-change outcomes, not errors that expose
  hidden information.

DevTools can redraw the room or calculate a solution, but cannot move persisted
parcels, forge completion, repeat progress grants, or mint PAWS.

## Suggested API

```text
POST /api/v1/games/pocket-post
  { petId, mode: "daily" | "practice", actionId }

GET /api/v1/games/pocket-post/:sessionId

POST /api/v1/games/pocket-post/:sessionId/actions
  { action: "move", direction: "north" | "east" | "south" | "west", actionId }
  { action: "undo", actionId }
  { action: "reset", actionId }
  { action: "abandon", actionId }
```

## Data model direction

- `post_puzzles`: puzzle key, mode, version, board definition, minimum pushes,
  minimum moves, verification report, active flag, timestamps.
- `post_sessions`: owner, pet, puzzle, mode, status, pet position, parcel
  positions, bounded history, moves, pushes, undos, stars, reward, journal flag,
  timestamps.
- `post_actions`: session, owner, UUID key, strict action, exact response.
- existing `pet_game_progress`: add `pocket_post`.
- existing `daily_game_rewards`: shared capped settlement.

The movement engine and solver remain pure TypeScript modules without database
imports.

## MVP acceptance criteria

- The authored 7×7 board has a server-proven solution and minimum push count.
- Every action changes at most one pet move, one parcel push, or one session
  transition.
- Blocked moves do not mutate counters.
- Undo and reset restore server-owned states exactly.
- Completion and stars are derived only from persisted positions and verified
  puzzle metadata.
- Repeated UUIDs cannot duplicate moves, rewards, journal credit, or bond XP.
- Cross-account pets and sessions are inaccessible.
- Forged economic, board, result, and pet fields are rejected.
- The game is completable without color, animation, pointer input, audio, or a
  clock.
- At most one PAWS transfer occurs and the shared daily cap always wins.

## Design decision

Stars measure pushes rather than total walking. Pushing changes the puzzle's
future; walking mostly changes viewpoint and accessibility cost. This makes the
score legible, rewards thoughtful planning, and avoids punishing players who
need extra navigation steps.
