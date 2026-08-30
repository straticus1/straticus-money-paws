import { describe, expect, it } from 'vitest';
import {
  dailyLanternPuzzle,
  evaluateLanternBoard,
  solveLanternPuzzle,
} from './lantern-engine.js';
import {
  applyPostMove,
  dailyPostPuzzle,
  solvePostPuzzle,
} from './post-engine.js';
import { dailyParadePuzzle, simulateParade, solveParadePuzzle } from './parade-engine.js';

describe('Lantern Lines engine', () => {
  it('proves the authored board and its careful-turn target', () => {
    const puzzle = dailyLanternPuzzle('2026-08-30');
    const proof = solveLanternPuzzle(puzzle);

    expect(proof.minimumTurns).toBeGreaterThan(0);
    expect(proof.solution).toHaveLength(puzzle.tiles.length);
    expect(puzzle.carefulTurnTarget).toBe(proof.minimumTurns + 2);
    expect(evaluateLanternBoard(puzzle, puzzle.initialOrientations).complete).toBe(false);
    expect(evaluateLanternBoard(puzzle, proof.solution).clean).toBe(true);
  });

  it('detects lit lanterns, breaks, and edge leaks from server state', () => {
    const puzzle = dailyLanternPuzzle('2026-08-30');
    const proof = solveLanternPuzzle(puzzle);
    const complete = evaluateLanternBoard(puzzle, proof.solution);

    expect(complete.poweredLanternIds).toEqual(['north', 'east', 'south']);
    expect(complete.breaks).toBe(0);
    expect(complete.leaks).toBe(0);
  });
});

describe('Pocket Post engine', () => {
  it('proves the authored board minimum push count', () => {
    const puzzle = dailyPostPuzzle('2026-08-30');
    const proof = solvePostPuzzle(puzzle);

    expect(proof.solvable).toBe(true);
    expect(proof.minimumPushes).toBe(6);
    expect(proof.moves.length).toBeGreaterThanOrEqual(6);
  });

  it('applies legal walking and pushing while blocked moves change nothing', () => {
    const puzzle = dailyPostPuzzle('2026-08-30');
    const walked = applyPostMove(puzzle, puzzle.initialPlayerPosition, puzzle.initialBoxPositions, 'north');
    expect(walked.moved).toBe(true);
    expect(walked.pushed).toBe(false);

    const blocked = applyPostMove(puzzle, walked.playerPosition, walked.boxPositions, 'north');
    expect(blocked.moved).toBe(false);
    expect(blocked.playerPosition).toBe(walked.playerPosition);
    expect(blocked.boxPositions).toEqual(walked.boxPositions);
  });
});

describe('Parade Practice engine', () => {
  it('proves the authored routine within the twelve-card limit', () => {
    const puzzle = dailyParadePuzzle('2026-08-30');
    const proof = solveParadePuzzle(puzzle);
    expect(proof.commands.length).toBeGreaterThan(0);
    expect(proof.commands.length).toBeLessThanOrEqual(12);
    expect(simulateParade(puzzle, proof.commands).completed).toBe(true);
  });

  it('ends an illegal routine safely with a public run log', () => {
    const result = simulateParade(dailyParadePuzzle('2026-08-30'), ['hop']);
    expect(result.completed).toBe(false);
    expect(result.failure).toBe('illegal_hop');
    expect(result.frames).toHaveLength(2);
  });
});
