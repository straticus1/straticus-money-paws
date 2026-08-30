export type LanternDirection = 'north' | 'east' | 'south' | 'west';
export type LanternTileKind = 'moonwell' | 'lantern' | 'straight' | 'elbow' | 'splitter' | 'crossing' | 'stone' | 'empty';
export type LanternOrientation = 0 | 1 | 2 | 3;

export interface LanternTile {
  position: number;
  kind: LanternTileKind;
  fixed: boolean;
  lanternId?: 'north' | 'east' | 'south';
}

export interface LanternPuzzleDefinition {
  version: 1;
  size: 5;
  tiles: LanternTile[];
  initialOrientations: LanternOrientation[];
  rotatablePositions: number[];
  carefulTurnTarget: number;
}

export interface LanternEvaluation {
  litPositions: number[];
  poweredLanternIds: string[];
  breaks: number;
  leaks: number;
  complete: boolean;
  clean: boolean;
}

const DIRECTIONS: LanternDirection[] = ['north', 'east', 'south', 'west'];
const DELTA: Record<LanternDirection, [number, number]> = {
  north: [-1, 0], east: [0, 1], south: [1, 0], west: [0, -1],
};
const OPPOSITE: Record<LanternDirection, LanternDirection> = {
  north: 'south', east: 'west', south: 'north', west: 'east',
};
const BASE_OPENINGS: Record<LanternTileKind, LanternDirection[]> = {
  moonwell: ['north'], lantern: ['north'], straight: ['north', 'south'],
  elbow: ['north', 'east'], splitter: ['north', 'east', 'west'],
  crossing: ['north', 'east', 'south', 'west'], stone: [], empty: [],
};

function rotateDirection(direction: LanternDirection, orientation: number): LanternDirection {
  return DIRECTIONS[(DIRECTIONS.indexOf(direction) + orientation) % 4]!;
}

export function tileOpenings(tile: LanternTile, orientation: number): LanternDirection[] {
  return BASE_OPENINGS[tile.kind].map((direction) => rotateDirection(direction, orientation));
}

export function evaluateLanternBoard(
  puzzle: LanternPuzzleDefinition,
  orientations: readonly number[],
): LanternEvaluation {
  if (orientations.length !== puzzle.tiles.length) throw new Error('invalid lantern orientations');
  const source = puzzle.tiles.find((tile) => tile.kind === 'moonwell');
  if (!source) throw new Error('lantern puzzle missing moonwell');
  const lit = new Set<number>([source.position]);
  const queue = [source.position];
  let breaks = 0;
  let leaks = 0;

  while (queue.length > 0) {
    const position = queue.shift()!;
    const tile = puzzle.tiles[position]!;
    const row = Math.floor(position / puzzle.size);
    const column = position % puzzle.size;
    for (const direction of tileOpenings(tile, orientations[position]!)) {
      const [dr, dc] = DELTA[direction];
      const nextRow = row + dr;
      const nextColumn = column + dc;
      if (nextRow < 0 || nextRow >= puzzle.size || nextColumn < 0 || nextColumn >= puzzle.size) {
        leaks += 1;
        continue;
      }
      const nextPosition = nextRow * puzzle.size + nextColumn;
      const nextTile = puzzle.tiles[nextPosition]!;
      if (!tileOpenings(nextTile, orientations[nextPosition]!).includes(OPPOSITE[direction])) {
        breaks += 1;
        continue;
      }
      if (!lit.has(nextPosition)) {
        lit.add(nextPosition);
        queue.push(nextPosition);
      }
    }
  }

  const poweredLanternIds = puzzle.tiles
    .filter((tile) => tile.kind === 'lantern' && lit.has(tile.position))
    .map((tile) => tile.lanternId!);
  const complete = poweredLanternIds.length === 3;
  return {
    litPositions: [...lit].sort((a, b) => a - b), poweredLanternIds,
    breaks, leaks, complete, clean: complete && breaks === 0 && leaks === 0,
  };
}

function expanded(puzzle: LanternPuzzleDefinition, state: readonly number[]): LanternOrientation[] {
  const result = [...puzzle.initialOrientations];
  puzzle.rotatablePositions.forEach((position, index) => {
    result[position] = state[index]! as LanternOrientation;
  });
  return result;
}

export function solveLanternPuzzle(
  puzzle: LanternPuzzleDefinition,
): { minimumTurns: number; solution: LanternOrientation[] } {
  const cached = lanternProofCache.get(puzzle);
  if (cached) return { minimumTurns: cached.minimumTurns, solution: [...cached.solution] };
  const initial = puzzle.rotatablePositions.map((position) => puzzle.initialOrientations[position]!);
  const key = (state: readonly number[]) => state.join('');
  const queue: Array<{ state: number[]; turns: number }> = [{ state: initial, turns: 0 }];
  const seen = new Set([key(initial)]);
  while (queue.length > 0) {
    const current = queue.shift()!;
    const full = expanded(puzzle, current.state);
    if (evaluateLanternBoard(puzzle, full).clean) {
      const proof = { minimumTurns: current.turns, solution: full };
      lanternProofCache.set(puzzle, proof);
      return { minimumTurns: proof.minimumTurns, solution: [...proof.solution] };
    }
    for (let index = 0; index < current.state.length; index += 1) {
      for (const step of [1, 3]) {
        const next = [...current.state];
        next[index] = (next[index]! + step) % 4;
        const nextKey = key(next);
        if (!seen.has(nextKey)) {
          seen.add(nextKey);
          queue.push({ state: next, turns: current.turns + 1 });
        }
      }
    }
  }
  throw new Error('lantern puzzle is unsolvable');
}

let cachedDailyPuzzle: LanternPuzzleDefinition | undefined;
const lanternProofCache = new WeakMap<LanternPuzzleDefinition, { minimumTurns: number; solution: LanternOrientation[] }>();
export function dailyLanternPuzzle(_date: string): LanternPuzzleDefinition {
  if (cachedDailyPuzzle) return cachedDailyPuzzle;
  const kinds: LanternTileKind[] = Array.from({ length: 25 }, () => 'empty');
  const fixed = new Set([10, 12, 4, 14, 24]);
  const lanterns = new Map<number, LanternTile['lanternId']>([[4, 'north'], [14, 'east'], [24, 'south']]);
  kinds[10] = 'moonwell'; kinds[12] = 'crossing';
  kinds[4] = 'lantern'; kinds[14] = 'lantern'; kinds[24] = 'lantern';
  kinds[2] = 'elbow'; kinds[3] = 'straight'; kinds[7] = 'straight';
  kinds[11] = 'straight'; kinds[13] = 'straight'; kinds[17] = 'straight';
  kinds[22] = 'elbow'; kinds[23] = 'straight';
  const initial: LanternOrientation[] = Array.from({ length: 25 }, () => 0 as LanternOrientation);
  initial[10] = 1; initial[4] = 3; initial[14] = 3; initial[24] = 3;
  initial[2] = 0; initial[3] = 0; initial[7] = 1; initial[11] = 0;
  initial[13] = 0; initial[17] = 1; initial[22] = 2; initial[23] = 0;
  const rotatablePositions = [2, 3, 7, 11, 13, 17, 22, 23];
  const base: LanternPuzzleDefinition = {
    version: 1,
    size: 5,
    tiles: kinds.map((kind, position) => ({
      position, kind, fixed: fixed.has(position) || kind === 'empty',
      ...(lanterns.has(position) ? { lanternId: lanterns.get(position)! } : {}),
    })),
    initialOrientations: initial,
    rotatablePositions,
    carefulTurnTarget: 11,
  };
  cachedDailyPuzzle = base;
  return cachedDailyPuzzle;
}
