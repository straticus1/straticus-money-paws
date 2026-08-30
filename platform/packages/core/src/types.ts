export interface User {
  id: string;
  email: string;
  username: string;
  createdAt: string;
}

export interface Pet {
  id: string;
  name: string;
  species: string;
  hunger: number;
  happiness: number;
  health: number;
  alive: boolean;
}

export interface PetEffect {
  hungerRestore: number;
  happinessBoost: number;
  durationHours: number;
  emoji: string;
  ageRestricted: boolean;
}

export interface StoreItem {
  id: string;
  name: string;
  description: string;
  category: string;
  priceMinor: string;
  currency: 'USD' | 'PAWS';
  effect: PetEffect;
}

export interface Balance {
  currency: 'USD' | 'PAWS';
  amountMinor: string;
}

export interface InventoryItem {
  itemId: string;
  name: string;
  category?: string;
  quantity: number;
  effect?: PetEffect;
}

export interface AuthResponse {
  token: string;
  user: User;
}

export interface PawMatchCard {
  position: number;
  matched: boolean;
  symbol?: string;
}

export interface PawMatchGame {
  id: string;
  status: 'active' | 'completed' | 'abandoned';
  moves: number;
  matchedPairs: number;
  firstPosition: number | null;
  rewardMinor: string;
  cards: PawMatchCard[];
  createdAt: string;
  completedAt: string | null;
}

export interface PawMatchResponse {
  game: PawMatchGame;
  outcome?: 'first_pick' | 'match' | 'miss' | 'completed';
  dailyRewardsRemaining?: number;
  dailyRewardMinorRemaining?: string;
}

export type TrailTerrain = 'meadow' | 'creek' | 'brambles' | 'lookout';
export type TrailDirection = 'north' | 'east' | 'south' | 'west';

export interface TrailTile {
  position: number;
  discovered: boolean;
  terrain?: TrailTerrain;
  objective?: 'home' | 'keepsake' | 'rescue';
}

export interface TrailTailsGame {
  id: string;
  status: 'active' | 'completed' | 'failed' | 'abandoned';
  pet: Pick<Pet, 'id' | 'name' | 'species'>;
  position: number;
  homePosition: number;
  energy: number;
  turns: number;
  abilities: { sniff: number; dash: number; rest: number };
  keepsakeFound: boolean;
  rescueFound: boolean;
  stars: number;
  rewardMinor: string;
  tiles: TrailTile[];
  legalDirections: TrailDirection[];
  createdAt: string;
  completedAt: string | null;
}

export interface TrailTailsResponse {
  game: TrailTailsGame;
  outcome?: 'started' | 'moved' | 'sniffed' | 'dashed' | 'rested' | 'completed' | 'failed';
  message?: string;
  dailyRewardMinorRemaining?: string;
}

export type TrailTailsAction =
  | { action: 'move'; direction: TrailDirection }
  | { action: 'dash'; direction: TrailDirection }
  | { action: 'sniff' }
  | { action: 'rest' };

export type PantryIngredientId =
  | 'moonberry'
  | 'sunroot'
  | 'cloud-oats'
  | 'river-kelp'
  | 'star-biscuit'
  | 'meadow-mint';
export type PantryGuestId = 'moth' | 'fox' | 'owl';

export interface PantryIngredient {
  id: PantryIngredientId;
  name: string;
  family: 'fruit' | 'vegetable' | 'grain' | 'green';
  texture: 'soft' | 'crunchy' | 'chewy';
  temperature: 'cool' | 'warm';
  symbol: 'crescent' | 'sun' | 'cloud' | 'wave' | 'star' | 'leaf';
  icon: string;
}

export interface PantryGuest {
  id: PantryGuestId;
  name: string;
  species: string;
  portrait: string;
  clues: string[];
}

export interface MidnightPantryGame {
  id: string;
  mode: 'daily' | 'practice';
  status: 'active' | 'completed' | 'failed' | 'abandoned';
  puzzleKey: string;
  pet: Pick<Pet, 'id' | 'name' | 'species'>;
  guests: PantryGuest[];
  ingredients: PantryIngredient[];
  placements: Record<PantryGuestId, [PantryIngredientId | null, PantryIngredientId | null]>;
  lockedGuests: PantryGuestId[];
  availableIngredientIds: PantryIngredientId[];
  bellRings: number;
  bellLimit: number;
  mistakes: number;
  stars: number;
  rewardMinor: string;
  journalCredited: boolean;
  createdAt: string;
  completedAt: string | null;
}

export interface MidnightPantryResponse {
  game: MidnightPantryGame;
  outcome?: 'started' | 'placed' | 'removed' | 'served' | 'incorrect' | 'completed' | 'failed' | 'abandoned';
  message?: string;
  journalCredited?: boolean;
  dailyRewardMinorRemaining?: string;
}

export type MidnightPantryAction =
  | { action: 'place'; guestId: PantryGuestId; slot: 0 | 1; ingredientId: PantryIngredientId }
  | { action: 'remove'; guestId: PantryGuestId; slot: 0 | 1 }
  | { action: 'serve'; guestId: PantryGuestId }
  | { action: 'abandon' };

export type LanternDirection = 'north' | 'east' | 'south' | 'west';
export interface LanternTile {
  position: number;
  kind: 'moonwell' | 'lantern' | 'straight' | 'elbow' | 'splitter' | 'crossing' | 'stone' | 'empty';
  fixed: boolean;
  lanternId?: 'north' | 'east' | 'south';
  orientation: number;
  lit: boolean;
}
export interface LanternLinesGame {
  id: string; mode: 'daily' | 'practice'; status: 'active' | 'completed' | 'abandoned'; puzzleKey: string;
  pet: Pick<Pet, 'id' | 'name' | 'species'>; size: 5; tiles: LanternTile[]; orientations: number[];
  rotatablePositions: number[]; poweredLanternIds: string[]; breaks: number; leaks: number;
  rotations: number; resets: number; carefulTurnTarget: number; stars: number; rewardMinor: string;
  journalCredited: boolean; createdAt: string; completedAt: string | null;
}
export interface LanternLinesResponse { game: LanternLinesGame; outcome?: 'started' | 'rotated' | 'reset' | 'incomplete' | 'completed' | 'abandoned'; message?: string; dailyRewardMinorRemaining?: string; }
export type LanternLinesAction =
  | { action: 'rotate'; position: number; direction: 'clockwise' | 'counterclockwise' }
  | { action: 'submit' } | { action: 'reset' } | { action: 'abandon' };

export type PostDirection = 'north' | 'east' | 'south' | 'west';
export interface PocketPostGame {
  id: string; mode: 'daily' | 'practice'; status: 'active' | 'completed' | 'abandoned'; puzzleKey: string;
  pet: Pick<Pet, 'id' | 'name' | 'species'>; size: 7; walls: number[]; goals: number[];
  playerPosition: number; boxPositions: number[]; delivered: number; totalParcels: number;
  moves: number; pushes: number; undos: number; resets: number; minimumPushes: number; canUndo: boolean;
  stars: number; rewardMinor: string; journalCredited: boolean; createdAt: string; completedAt: string | null;
}
export interface PocketPostResponse { game: PocketPostGame; outcome?: 'started' | 'moved' | 'pushed' | 'blocked' | 'undone' | 'reset' | 'completed' | 'abandoned'; message?: string; dailyRewardMinorRemaining?: string; }
export type PocketPostAction = { action: 'move'; direction: PostDirection } | { action: 'undo' } | { action: 'reset' } | { action: 'abandon' };

export type ParadeDirection = 'north' | 'east' | 'south' | 'west';
export type ParadeCommand = 'forward' | 'turn-left' | 'turn-right' | 'hop';
export interface ParadeFrame { position: number; facing: ParadeDirection; collectedPennantPositions: number[]; event: 'start' | ParadeCommand | 'blocked'; }
export interface ParadeResult { completed: boolean; failure?: 'blocked' | 'illegal_hop'; frames: ParadeFrame[]; finalPosition: number; finalFacing: ParadeDirection; collectedPennantPositions: number[]; }
export interface ParadePracticeGame {
  id: string; mode: 'daily' | 'practice'; status: 'active' | 'completed' | 'abandoned'; puzzleKey: string;
  pet: Pick<Pet, 'id' | 'name' | 'species'>; size: 6; startPosition: number; startFacing: ParadeDirection;
  bandstandPosition: number; pennantPositions: number[]; obstaclePositions: number[]; minimumCommands: number;
  maxCommands: number; runs: number; bestCommandCount: number | null; lastResult: ParadeResult | null;
  stars: number; rewardMinor: string; journalCredited: boolean; createdAt: string; completedAt: string | null;
}
export interface ParadePracticeResponse { game: ParadePracticeGame; outcome?: 'started' | 'ran' | 'blocked' | 'completed' | 'abandoned'; message?: string; dailyRewardMinorRemaining?: string; }
export type ParadePracticeAction = { action: 'run'; commands: ParadeCommand[] } | { action: 'abandon' };
