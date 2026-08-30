export type ParadeDirection = 'north' | 'east' | 'south' | 'west';
export type ParadeCommand = 'forward' | 'turn-left' | 'turn-right' | 'hop';

export interface ParadePuzzleDefinition {
  version: 1;
  size: 6;
  startPosition: number;
  startFacing: ParadeDirection;
  bandstandPosition: number;
  pennantPositions: number[];
  obstaclePositions: number[];
  minimumCommands: number;
}

export interface ParadeFrame {
  position: number;
  facing: ParadeDirection;
  collectedPennantPositions: number[];
  event: 'start' | ParadeCommand | 'blocked';
}

export interface ParadeResult {
  completed: boolean;
  failure?: 'blocked' | 'illegal_hop';
  frames: ParadeFrame[];
  finalPosition: number;
  finalFacing: ParadeDirection;
  collectedPennantPositions: number[];
}

const DIRECTIONS: ParadeDirection[] = ['north', 'east', 'south', 'west'];
const DELTA: Record<ParadeDirection, [number, number]> = {
  north: [-1, 0], east: [0, 1], south: [1, 0], west: [0, -1],
};

function step(size: number, position: number, facing: ParadeDirection): number | null {
  const row = Math.floor(position / size); const column = position % size;
  const [dr, dc] = DELTA[facing]; const nextRow = row + dr; const nextColumn = column + dc;
  return nextRow < 0 || nextRow >= size || nextColumn < 0 || nextColumn >= size ? null : nextRow * size + nextColumn;
}

export function simulateParade(puzzle: ParadePuzzleDefinition, commands: readonly ParadeCommand[]): ParadeResult {
  if (commands.length > 12) throw new Error('parade program exceeds twelve commands');
  let position = puzzle.startPosition; let facing = puzzle.startFacing;
  const collected = new Set<number>();
  const frame = (event: ParadeFrame['event']): ParadeFrame => ({ position, facing, collectedPennantPositions: [...collected].sort((a, b) => a - b), event });
  const frames: ParadeFrame[] = [frame('start')];
  for (const command of commands) {
    if (command === 'turn-left' || command === 'turn-right') {
      const delta = command === 'turn-right' ? 1 : 3;
      facing = DIRECTIONS[(DIRECTIONS.indexOf(facing) + delta) % 4]!;
      frames.push(frame(command));
      continue;
    }
    const adjacent = step(puzzle.size, position, facing);
    if (command === 'forward') {
      if (adjacent === null || puzzle.obstaclePositions.includes(adjacent)) {
        frames.push(frame('blocked'));
        return { completed: false, failure: 'blocked', frames, finalPosition: position, finalFacing: facing, collectedPennantPositions: [...collected] };
      }
      position = adjacent;
    } else {
      const landing = adjacent === null ? null : step(puzzle.size, adjacent, facing);
      if (adjacent === null || !puzzle.obstaclePositions.includes(adjacent) || landing === null || puzzle.obstaclePositions.includes(landing)) {
        frames.push(frame('blocked'));
        return { completed: false, failure: 'illegal_hop', frames, finalPosition: position, finalFacing: facing, collectedPennantPositions: [...collected] };
      }
      position = landing;
    }
    if (puzzle.pennantPositions.includes(position)) collected.add(position);
    frames.push(frame(command));
  }
  const completed = position === puzzle.bandstandPosition && collected.size === puzzle.pennantPositions.length;
  return { completed, frames, finalPosition: position, finalFacing: facing, collectedPennantPositions: [...collected].sort((a, b) => a - b) };
}

export function solveParadePuzzle(puzzle: ParadePuzzleDefinition): { commands: ParadeCommand[] } {
  type Node = { position: number; facing: ParadeDirection; mask: number; commands: ParadeCommand[] };
  const bitAt = (position: number) => {
    const index = puzzle.pennantPositions.indexOf(position);
    return index < 0 ? 0 : 1 << index;
  };
  const targetMask = (1 << puzzle.pennantPositions.length) - 1;
  const start: Node = { position: puzzle.startPosition, facing: puzzle.startFacing, mask: bitAt(puzzle.startPosition), commands: [] };
  const queue = [start]; const seen = new Set([`${start.position}:${start.facing}:${start.mask}`]);
  while (queue.length > 0) {
    const current = queue.shift()!;
    if (current.position === puzzle.bandstandPosition && current.mask === targetMask) return { commands: current.commands };
    if (current.commands.length >= 12) continue;
    for (const command of ['forward', 'turn-left', 'turn-right', 'hop'] as ParadeCommand[]) {
      const oneStepPuzzle = { ...puzzle, startPosition: current.position, startFacing: current.facing, pennantPositions: puzzle.pennantPositions.filter((_, index) => (current.mask & (1 << index)) === 0) };
      const result = simulateParade(oneStepPuzzle, [command]);
      if (result.failure) continue;
      let mask = current.mask;
      for (const position of result.collectedPennantPositions) mask |= bitAt(position);
      const next: Node = { position: result.finalPosition, facing: result.finalFacing, mask, commands: [...current.commands, command] };
      const key = `${next.position}:${next.facing}:${next.mask}`;
      if (!seen.has(key)) { seen.add(key); queue.push(next); }
    }
  }
  throw new Error('parade puzzle is unsolvable within twelve commands');
}

let cachedPuzzle: ParadePuzzleDefinition | undefined;
export function dailyParadePuzzle(_date: string): ParadePuzzleDefinition {
  if (cachedPuzzle) return cachedPuzzle;
  const base: ParadePuzzleDefinition = {
    version: 1, size: 6, startPosition: 30, startFacing: 'east', bandstandPosition: 4,
    pennantPositions: [31, 26, 9], obstaclePositions: [20], minimumCommands: 0,
  };
  const proof = solveParadePuzzle(base);
  cachedPuzzle = { ...base, minimumCommands: proof.commands.length };
  return cachedPuzzle;
}
