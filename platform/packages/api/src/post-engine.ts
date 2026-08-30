export type PostDirection = 'north' | 'east' | 'south' | 'west';

export interface PostPuzzleDefinition {
  version: 1;
  size: 7;
  walls: number[];
  goals: number[];
  initialPlayerPosition: number;
  initialBoxPositions: number[];
  minimumPushes: number;
}

export interface PostMoveResult {
  playerPosition: number;
  boxPositions: number[];
  moved: boolean;
  pushed: boolean;
  completed: boolean;
}

const DELTA: Record<PostDirection, [number, number]> = {
  north: [-1, 0], east: [0, 1], south: [1, 0], west: [0, -1],
};

export function postComplete(puzzle: PostPuzzleDefinition, boxes: readonly number[]): boolean {
  const boxSet = new Set(boxes);
  return puzzle.goals.every((goal) => boxSet.has(goal));
}

export function applyPostMove(
  puzzle: PostPuzzleDefinition,
  playerPosition: number,
  boxPositions: readonly number[],
  direction: PostDirection,
): PostMoveResult {
  const [dr, dc] = DELTA[direction];
  const row = Math.floor(playerPosition / puzzle.size);
  const column = playerPosition % puzzle.size;
  const nextRow = row + dr;
  const nextColumn = column + dc;
  const unchanged = (): PostMoveResult => ({
    playerPosition, boxPositions: [...boxPositions], moved: false, pushed: false,
    completed: postComplete(puzzle, boxPositions),
  });
  if (nextRow < 0 || nextRow >= puzzle.size || nextColumn < 0 || nextColumn >= puzzle.size) return unchanged();
  const next = nextRow * puzzle.size + nextColumn;
  if (puzzle.walls.includes(next)) return unchanged();
  const boxIndex = boxPositions.indexOf(next);
  if (boxIndex < 0) {
    return { playerPosition: next, boxPositions: [...boxPositions], moved: true, pushed: false, completed: postComplete(puzzle, boxPositions) };
  }
  const beyondRow = nextRow + dr;
  const beyondColumn = nextColumn + dc;
  if (beyondRow < 0 || beyondRow >= puzzle.size || beyondColumn < 0 || beyondColumn >= puzzle.size) return unchanged();
  const beyond = beyondRow * puzzle.size + beyondColumn;
  if (puzzle.walls.includes(beyond) || boxPositions.includes(beyond)) return unchanged();
  const boxes = [...boxPositions];
  boxes[boxIndex] = beyond;
  boxes.sort((a, b) => a - b);
  return { playerPosition: next, boxPositions: boxes, moved: true, pushed: true, completed: postComplete(puzzle, boxes) };
}

export function solvePostPuzzle(
  puzzle: PostPuzzleDefinition,
): { solvable: boolean; minimumPushes: number; moves: PostDirection[] } {
  type Node = { player: number; boxes: number[]; pushes: number; moves: PostDirection[] };
  const start: Node = { player: puzzle.initialPlayerPosition, boxes: [...puzzle.initialBoxPositions].sort((a, b) => a - b), pushes: 0, moves: [] };
  const queue: Node[] = [start];
  const best = new Map<string, [number, number]>();
  const key = (node: Node) => `${node.player}:${node.boxes.join(',')}`;
  best.set(key(start), [0, 0]);
  while (queue.length > 0) {
    queue.sort((a, b) => a.pushes - b.pushes || a.moves.length - b.moves.length);
    const node = queue.shift()!;
    if (postComplete(puzzle, node.boxes)) return { solvable: true, minimumPushes: node.pushes, moves: node.moves };
    for (const direction of ['north', 'east', 'south', 'west'] as PostDirection[]) {
      const result = applyPostMove(puzzle, node.player, node.boxes, direction);
      if (!result.moved) continue;
      const next: Node = {
        player: result.playerPosition,
        boxes: result.boxPositions,
        pushes: node.pushes + (result.pushed ? 1 : 0),
        moves: [...node.moves, direction],
      };
      const nextKey = key(next);
      const prior = best.get(nextKey);
      if (!prior || next.pushes < prior[0] || (next.pushes === prior[0] && next.moves.length < prior[1])) {
        best.set(nextKey, [next.pushes, next.moves.length]);
        queue.push(next);
      }
    }
  }
  return { solvable: false, minimumPushes: 0, moves: [] };
}

export function dailyPostPuzzle(_date: string): PostPuzzleDefinition {
  const walls: number[] = [];
  for (let index = 0; index < 49; index += 1) {
    const row = Math.floor(index / 7);
    const column = index % 7;
    if (row === 0 || row === 6 || column === 0 || column === 6) walls.push(index);
  }
  walls.push(17);
  return {
    version: 1,
    size: 7,
    walls,
    goals: [37, 39],
    initialPlayerPosition: 31,
    initialBoxPositions: [16, 18],
    minimumPushes: 6,
  };
}
