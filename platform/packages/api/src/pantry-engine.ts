export const PANTRY_INGREDIENT_IDS = [
  'moonberry',
  'sunroot',
  'cloud-oats',
  'river-kelp',
  'star-biscuit',
  'meadow-mint',
] as const;

export const PANTRY_GUEST_IDS = ['moth', 'fox', 'owl'] as const;

export type PantryIngredientId = (typeof PANTRY_INGREDIENT_IDS)[number];
export type PantryGuestId = (typeof PANTRY_GUEST_IDS)[number];
type IngredientProperty = 'family' | 'texture' | 'temperature' | 'symbol';

export interface PantryIngredient {
  id: PantryIngredientId;
  name: string;
  family: 'fruit' | 'vegetable' | 'grain' | 'green';
  texture: 'soft' | 'crunchy' | 'chewy';
  temperature: 'cool' | 'warm';
  symbol: 'crescent' | 'sun' | 'cloud' | 'wave' | 'star' | 'leaf';
  icon: string;
}

type LocalConstraint =
  | { kind: 'contains'; property: IngredientProperty; value: string }
  | { kind: 'excludes'; property: IngredientProperty; value: string }
  | { kind: 'exactlyOne'; property: IngredientProperty; value: string };

type RelationalConstraint = {
  kind: 'sharesExactlyOne';
  guestId: PantryGuestId;
  property: IngredientProperty;
};

export interface PantryGuest {
  id: PantryGuestId;
  name: string;
  species: string;
  portrait: string;
  clues: string[];
  constraints: Array<LocalConstraint | RelationalConstraint>;
}

export interface PantryPuzzleDefinition {
  version: 1;
  ingredients: PantryIngredient[];
  guests: PantryGuest[];
  bellLimit: number;
}

export type PantrySolution = Record<PantryGuestId, PantryIngredientId[]>;

export interface VerifiedPantryPuzzle {
  publicDefinition: PantryPuzzleDefinition;
  privateSolution: PantrySolution;
  verification: { solutionCount: 1; verifiedAt: string; engineVersion: 1 };
}

function propertyValue(ingredient: PantryIngredient, property: IngredientProperty): string {
  return ingredient[property];
}

function satisfiesLocal(pair: PantryIngredient[], constraint: LocalConstraint): boolean {
  const matches = pair.filter(
    (ingredient) => propertyValue(ingredient, constraint.property) === constraint.value,
  ).length;
  if (constraint.kind === 'contains') return matches >= 1;
  if (constraint.kind === 'excludes') return matches === 0;
  return matches === 1;
}

function satisfiesRelational(
  solution: Partial<PantrySolution>,
  ingredients: Map<PantryIngredientId, PantryIngredient>,
  guestId: PantryGuestId,
  constraint: RelationalConstraint,
): boolean {
  const own = solution[guestId];
  const other = solution[constraint.guestId];
  if (own === undefined || other === undefined) return true;
  const ownValues = new Set(own.map((id) => propertyValue(ingredients.get(id)!, constraint.property)));
  const otherValues = new Set(other.map((id) => propertyValue(ingredients.get(id)!, constraint.property)));
  let shared = 0;
  for (const value of ownValues) if (otherValues.has(value)) shared += 1;
  return shared === 1;
}

function pairs<T>(values: T[]): Array<[T, T]> {
  const result: Array<[T, T]> = [];
  for (let first = 0; first < values.length; first += 1) {
    for (let second = first + 1; second < values.length; second += 1) {
      result.push([values[first]!, values[second]!]);
    }
  }
  return result;
}

export function solvePantryPuzzle(definition: PantryPuzzleDefinition): PantrySolution[] {
  const ingredientMap = new Map(definition.ingredients.map((ingredient) => [ingredient.id, ingredient]));
  const pairCandidates = new Map<PantryGuestId, PantryIngredientId[][]>();
  for (const guest of definition.guests) {
    const local = guest.constraints.filter(
      (constraint): constraint is LocalConstraint => constraint.kind !== 'sharesExactlyOne',
    );
    const candidates = pairs(definition.ingredients)
      .filter((pair) => local.every((constraint) => satisfiesLocal(pair, constraint)))
      .map((pair) => pair.map((ingredient) => ingredient.id));
    pairCandidates.set(guest.id, candidates);
  }

  const solutions: PantrySolution[] = [];
  function assign(index: number, used: Set<PantryIngredientId>, partial: Partial<PantrySolution>) {
    if (index === definition.guests.length) {
      const relationallyValid = definition.guests.every((guest) =>
        guest.constraints
          .filter((constraint): constraint is RelationalConstraint => constraint.kind === 'sharesExactlyOne')
          .every((constraint) => satisfiesRelational(partial, ingredientMap, guest.id, constraint)),
      );
      if (relationallyValid) solutions.push(partial as PantrySolution);
      return;
    }
    const guest = definition.guests[index]!;
    for (const candidate of pairCandidates.get(guest.id) ?? []) {
      if (candidate.some((ingredientId) => used.has(ingredientId))) continue;
      const nextUsed = new Set(used);
      candidate.forEach((ingredientId) => nextUsed.add(ingredientId));
      assign(index + 1, nextUsed, { ...partial, [guest.id]: candidate });
    }
  }
  assign(0, new Set(), {});
  return solutions;
}

export function dailyPantryPuzzle(date: string): VerifiedPantryPuzzle {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) throw new Error('invalid pantry puzzle date');
  const publicDefinition: PantryPuzzleDefinition = {
    version: 1,
    bellLimit: 6,
    ingredients: [
      { id: 'moonberry', name: 'Moonberry', family: 'fruit', texture: 'soft', temperature: 'cool', symbol: 'crescent', icon: '☾' },
      { id: 'sunroot', name: 'Sunroot', family: 'vegetable', texture: 'crunchy', temperature: 'warm', symbol: 'sun', icon: '☀' },
      { id: 'cloud-oats', name: 'Cloud Oats', family: 'grain', texture: 'soft', temperature: 'warm', symbol: 'cloud', icon: '☁' },
      { id: 'river-kelp', name: 'River Kelp', family: 'green', texture: 'chewy', temperature: 'cool', symbol: 'wave', icon: '≈' },
      { id: 'star-biscuit', name: 'Star Biscuit', family: 'grain', texture: 'crunchy', temperature: 'warm', symbol: 'star', icon: '★' },
      { id: 'meadow-mint', name: 'Meadow Mint', family: 'green', texture: 'soft', temperature: 'cool', symbol: 'leaf', icon: '❧' },
    ],
    guests: [
      {
        id: 'moth', name: 'Mallow', species: 'Luna moth', portrait: '🦋',
        clues: ['I always choose a fruit.', 'No vegetables, please.', 'Exactly one ingredient should crunch.'],
        constraints: [
          { kind: 'contains', property: 'family', value: 'fruit' },
          { kind: 'excludes', property: 'family', value: 'vegetable' },
          { kind: 'exactlyOne', property: 'texture', value: 'crunchy' },
        ],
      },
      {
        id: 'fox', name: 'Bramble', species: 'Red fox', portrait: '🦊',
        clues: ['One vegetable and one green.', 'Exactly one ingredient should be soft.'],
        constraints: [
          { kind: 'contains', property: 'family', value: 'vegetable' },
          { kind: 'contains', property: 'family', value: 'green' },
          { kind: 'exactlyOne', property: 'texture', value: 'soft' },
        ],
      },
      {
        id: 'owl', name: 'Professor Hoot', species: 'Barn owl', portrait: '🦉',
        clues: ['I need a grain and something chewy.', "My bowl shares exactly one texture with Bramble's."],
        constraints: [
          { kind: 'contains', property: 'family', value: 'grain' },
          { kind: 'contains', property: 'texture', value: 'chewy' },
          { kind: 'sharesExactlyOne', guestId: 'fox', property: 'texture' },
        ],
      },
    ],
  };
  const solutions = solvePantryPuzzle(publicDefinition);
  if (solutions.length !== 1) throw new Error(`pantry puzzle must have one solution; found ${solutions.length}`);
  return {
    publicDefinition,
    privateSolution: solutions[0]!,
    verification: {
      solutionCount: 1,
      verifiedAt: `${date}T00:00:00.000Z`,
      engineVersion: 1,
    },
  };
}
