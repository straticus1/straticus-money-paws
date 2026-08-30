import {
  bigint,
  boolean,
  date,
  integer,
  jsonb,
  pgTable,
  text,
  timestamp,
  uuid,
} from 'drizzle-orm/pg-core';

export const users = pgTable('users', {
  id: uuid('id').primaryKey().defaultRandom(),
  email: text('email').notNull().unique(),
  username: text('username').notNull().unique(),
  passwordHash: text('password_hash').notNull(),
  role: text('role', { enum: ['user', 'admin'] }).notNull().default('user'),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const sessions = pgTable('sessions', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  tokenHash: text('token_hash').notNull().unique(),
  expiresAt: timestamp('expires_at', { withTimezone: true }).notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const user2fa = pgTable('user_2fa', {
  userId: uuid('user_id').primaryKey().references(() => users.id, { onDelete: 'cascade' }),
  totpSecret: text('totp_secret').notNull(),
  enabled: boolean('enabled').notNull().default(false),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const userSettings = pgTable('user_settings', {
  userId: uuid('user_id').primaryKey().references(() => users.id, { onDelete: 'cascade' }),
  leaderboardOptIn: boolean('leaderboard_opt_in').notNull().default(false),
  trophyShowcase: boolean('trophy_showcase').notNull().default(true),
  updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
});

export const userTrophies = pgTable('user_trophies', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  trophyKey: text('trophy_key').notNull(),
  earnedAt: timestamp('earned_at', { withTimezone: true }).notNull().defaultNow(),
  metadata: jsonb('metadata').$type<Record<string, unknown>>().notNull().default({}),
});

export const securityAuditEvents = pgTable('security_audit_events', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').references(() => users.id, { onDelete: 'set null' }),
  eventType: text('event_type').notNull(),
  metadata: jsonb('metadata').$type<Record<string, unknown>>().notNull().default({}),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const pets = pgTable('pets', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  name: text('name').notNull(),
  species: text('species').notNull(),
  hunger: integer('hunger').notNull().default(100),
  happiness: integer('happiness').notNull().default(100),
  health: integer('health').notNull().default(100),
  alive: boolean('alive').notNull().default(true),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const storeItems = pgTable('store_items', {
  id: uuid('id').primaryKey().defaultRandom(),
  name: text('name').notNull().unique(),
  description: text('description').notNull().default(''),
  category: text('category').notNull(),
  priceMinor: bigint('price_minor', { mode: 'bigint' }).notNull(),
  currency: text('currency', { enum: ['PAWS', 'USD'] }).notNull().default('PAWS'),
  effect: jsonb('effect').notNull().default({}),
  active: boolean('active').notNull().default(true),
});

export const inventory = pgTable('inventory', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  itemId: uuid('item_id').notNull().references(() => storeItems.id),
  quantity: integer('quantity').notNull().default(0),
});

export const ledgerAccounts = pgTable('ledger_accounts', {
  id: uuid('id').primaryKey().defaultRandom(),
  ownerUserId: uuid('owner_user_id').references(() => users.id),
  kind: text('kind', { enum: ['user', 'system'] }).notNull(),
  systemName: text('system_name'),
  currency: text('currency', { enum: ['PAWS', 'USD'] }).notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const ledgerTransactions = pgTable('ledger_transactions', {
  id: uuid('id').primaryKey().defaultRandom(),
  idempotencyKey: text('idempotency_key').notNull().unique(),
  kind: text('kind').notNull(),
  metadata: jsonb('metadata').notNull().default({}),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const ledgerEntries = pgTable('ledger_entries', {
  id: uuid('id').primaryKey().defaultRandom(),
  transactionId: uuid('transaction_id').notNull().references(() => ledgerTransactions.id),
  accountId: uuid('account_id').notNull().references(() => ledgerAccounts.id),
  amountMinor: bigint('amount_minor', { mode: 'bigint' }).notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const deposits = pgTable('deposits', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id),
  provider: text('provider').notNull().default('coinbase'),
  providerChargeId: text('provider_charge_id').notNull().unique(),
  status: text('status', { enum: ['pending', 'confirmed', 'failed', 'expired'] })
    .notNull()
    .default('pending'),
  amountMinor: bigint('amount_minor', { mode: 'bigint' }).notNull(),
  currency: text('currency', { enum: ['PAWS', 'USD'] }).notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
  confirmedAt: timestamp('confirmed_at', { withTimezone: true }),
});

export const withdrawalRequests = pgTable('withdrawal_requests', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id),
  amountMinor: bigint('amount_minor', { mode: 'bigint' }).notNull(),
  currency: text('currency', { enum: ['PAWS', 'USD'] }).notNull(),
  destination: text('destination').notNull(),
  status: text('status', { enum: ['pending', 'approved', 'denied', 'paid'] })
    .notNull()
    .default('pending'),
  reviewedBy: uuid('reviewed_by').references(() => users.id),
  reviewNote: text('review_note'),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
  reviewedAt: timestamp('reviewed_at', { withTimezone: true }),
});

export const gameSessions = pgTable('game_sessions', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  gameType: text('game_type', { enum: ['paw_match'] }).notNull().default('paw_match'),
  status: text('status', { enum: ['active', 'completed', 'abandoned'] })
    .notNull()
    .default('active'),
  board: jsonb('board').$type<string[]>().notNull(),
  matchedPositions: jsonb('matched_positions').$type<number[]>().notNull().default([]),
  firstPosition: integer('first_position'),
  moves: integer('moves').notNull().default(0),
  rewardMinor: bigint('reward_minor', { mode: 'bigint' }).notNull().default(0n),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
  updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
  completedAt: timestamp('completed_at', { withTimezone: true }),
});

export const gameActions = pgTable('game_actions', {
  id: uuid('id').primaryKey().defaultRandom(),
  sessionId: uuid('session_id')
    .notNull()
    .references(() => gameSessions.id, { onDelete: 'cascade' }),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  idempotencyKey: uuid('idempotency_key').notNull(),
  response: jsonb('response').$type<Record<string, unknown>>().notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export interface TrailPrivateTile {
  position: number;
  terrain: 'meadow' | 'creek' | 'brambles' | 'lookout';
}

export const dailyGameRewards = pgTable('daily_game_rewards', {
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  rewardDate: date('reward_date').notNull(),
  awardedMinor: bigint('awarded_minor', { mode: 'bigint' }).notNull().default(0n),
  rewardedCompletions: integer('rewarded_completions').notNull().default(0),
  updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
});

export const trailSessions = pgTable('trail_sessions', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  petId: uuid('pet_id').notNull().references(() => pets.id, { onDelete: 'cascade' }),
  status: text('status', { enum: ['active', 'completed', 'failed', 'abandoned'] })
    .notNull()
    .default('active'),
  privateMap: jsonb('private_map').$type<TrailPrivateTile[]>().notNull(),
  discoveredPositions: jsonb('discovered_positions').$type<number[]>().notNull().default([]),
  position: integer('position').notNull(),
  startPosition: integer('start_position').notNull(),
  homePosition: integer('home_position').notNull(),
  keepsakePosition: integer('keepsake_position').notNull(),
  rescuePosition: integer('rescue_position').notNull(),
  energy: integer('energy').notNull().default(12),
  sniffCharges: integer('sniff_charges').notNull().default(2),
  dashCharges: integer('dash_charges').notNull().default(1),
  restCharges: integer('rest_charges').notNull().default(1),
  keepsakeFound: boolean('keepsake_found').notNull().default(false),
  rescueFound: boolean('rescue_found').notNull().default(false),
  turns: integer('turns').notNull().default(0),
  stars: integer('stars').notNull().default(0),
  rewardMinor: bigint('reward_minor', { mode: 'bigint' }).notNull().default(0n),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
  updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
  completedAt: timestamp('completed_at', { withTimezone: true }),
});

export const trailActions = pgTable('trail_actions', {
  id: uuid('id').primaryKey().defaultRandom(),
  sessionId: uuid('session_id')
    .notNull()
    .references(() => trailSessions.id, { onDelete: 'cascade' }),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  idempotencyKey: uuid('idempotency_key').notNull(),
  action: jsonb('action').$type<Record<string, unknown>>().notNull(),
  response: jsonb('response').$type<Record<string, unknown>>().notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const petGameProgress = pgTable('pet_game_progress', {
  petId: uuid('pet_id').notNull().references(() => pets.id, { onDelete: 'cascade' }),
  gameType: text('game_type', { enum: ['trail_tails', 'midnight_pantry', 'lantern_lines', 'pocket_post', 'parade_practice'] }).notNull(),
  bondXp: integer('bond_xp').notNull().default(0),
  bondLevel: integer('bond_level').notNull().default(1),
  updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
});

export const pantryPuzzles = pgTable('pantry_puzzles', {
  id: uuid('id').primaryKey().defaultRandom(),
  puzzleKey: text('puzzle_key').notNull().unique(),
  mode: text('mode', { enum: ['daily', 'practice'] }).notNull(),
  generatorVersion: integer('generator_version').notNull().default(1),
  publicDefinition: jsonb('public_definition').$type<Record<string, unknown>>().notNull(),
  privateSolution: jsonb('private_solution').$type<Record<string, string[]>>().notNull(),
  verification: jsonb('verification').$type<Record<string, unknown>>().notNull(),
  active: boolean('active').notNull().default(true),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export type PantryPlacements = Record<string, [string | null, string | null]>;

export const pantrySessions = pgTable('pantry_sessions', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  petId: uuid('pet_id').notNull().references(() => pets.id, { onDelete: 'cascade' }),
  puzzleId: uuid('puzzle_id').notNull().references(() => pantryPuzzles.id),
  mode: text('mode', { enum: ['daily', 'practice'] }).notNull(),
  status: text('status', { enum: ['active', 'completed', 'failed', 'abandoned'] }).notNull().default('active'),
  placements: jsonb('placements').$type<PantryPlacements>().notNull().default({}),
  lockedGuests: jsonb('locked_guests').$type<string[]>().notNull().default([]),
  bellRings: integer('bell_rings').notNull().default(0),
  mistakes: integer('mistakes').notNull().default(0),
  stars: integer('stars').notNull().default(0),
  rewardMinor: bigint('reward_minor', { mode: 'bigint' }).notNull().default(0n),
  journalCredited: boolean('journal_credited').notNull().default(false),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
  updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
  completedAt: timestamp('completed_at', { withTimezone: true }),
});

export const pantryActions = pgTable('pantry_actions', {
  id: uuid('id').primaryKey().defaultRandom(),
  sessionId: uuid('session_id').notNull().references(() => pantrySessions.id, { onDelete: 'cascade' }),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  idempotencyKey: uuid('idempotency_key').notNull(),
  action: jsonb('action').$type<Record<string, unknown>>().notNull(),
  response: jsonb('response').$type<Record<string, unknown>>().notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const lanternPuzzles = pgTable('lantern_puzzles', {
  id: uuid('id').primaryKey().defaultRandom(),
  puzzleKey: text('puzzle_key').notNull().unique(),
  mode: text('mode', { enum: ['daily', 'practice'] }).notNull(),
  version: integer('version').notNull().default(1),
  definition: jsonb('definition').$type<Record<string, unknown>>().notNull(),
  minimumTurns: integer('minimum_turns').notNull(),
  verification: jsonb('verification').$type<Record<string, unknown>>().notNull(),
  active: boolean('active').notNull().default(true),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const lanternSessions = pgTable('lantern_sessions', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  petId: uuid('pet_id').notNull().references(() => pets.id, { onDelete: 'cascade' }),
  puzzleId: uuid('puzzle_id').notNull().references(() => lanternPuzzles.id),
  mode: text('mode', { enum: ['daily', 'practice'] }).notNull(),
  status: text('status', { enum: ['active', 'completed', 'abandoned'] }).notNull().default('active'),
  orientations: jsonb('orientations').$type<number[]>().notNull(),
  rotations: integer('rotations').notNull().default(0),
  resets: integer('resets').notNull().default(0),
  stars: integer('stars').notNull().default(0),
  rewardMinor: bigint('reward_minor', { mode: 'bigint' }).notNull().default(0n),
  journalCredited: boolean('journal_credited').notNull().default(false),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
  updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
  completedAt: timestamp('completed_at', { withTimezone: true }),
});

export const lanternActions = pgTable('lantern_actions', {
  id: uuid('id').primaryKey().defaultRandom(),
  sessionId: uuid('session_id').notNull().references(() => lanternSessions.id, { onDelete: 'cascade' }),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  idempotencyKey: uuid('idempotency_key').notNull(),
  action: jsonb('action').$type<Record<string, unknown>>().notNull(),
  response: jsonb('response').$type<Record<string, unknown>>().notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const postPuzzles = pgTable('post_puzzles', {
  id: uuid('id').primaryKey().defaultRandom(),
  puzzleKey: text('puzzle_key').notNull().unique(),
  mode: text('mode', { enum: ['daily', 'practice'] }).notNull(),
  version: integer('version').notNull().default(1),
  definition: jsonb('definition').$type<Record<string, unknown>>().notNull(),
  minimumPushes: integer('minimum_pushes').notNull(),
  minimumMoves: integer('minimum_moves').notNull(),
  verification: jsonb('verification').$type<Record<string, unknown>>().notNull(),
  active: boolean('active').notNull().default(true),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export interface PostHistoryEntry {
  playerPosition: number;
  boxPositions: number[];
  moves: number;
  pushes: number;
}

export const postSessions = pgTable('post_sessions', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  petId: uuid('pet_id').notNull().references(() => pets.id, { onDelete: 'cascade' }),
  puzzleId: uuid('puzzle_id').notNull().references(() => postPuzzles.id),
  mode: text('mode', { enum: ['daily', 'practice'] }).notNull(),
  status: text('status', { enum: ['active', 'completed', 'abandoned'] }).notNull().default('active'),
  playerPosition: integer('player_position').notNull(),
  boxPositions: jsonb('box_positions').$type<number[]>().notNull(),
  history: jsonb('history').$type<PostHistoryEntry[]>().notNull().default([]),
  moves: integer('moves').notNull().default(0),
  pushes: integer('pushes').notNull().default(0),
  undos: integer('undos').notNull().default(0),
  resets: integer('resets').notNull().default(0),
  stars: integer('stars').notNull().default(0),
  rewardMinor: bigint('reward_minor', { mode: 'bigint' }).notNull().default(0n),
  journalCredited: boolean('journal_credited').notNull().default(false),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
  updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
  completedAt: timestamp('completed_at', { withTimezone: true }),
});

export const postActions = pgTable('post_actions', {
  id: uuid('id').primaryKey().defaultRandom(),
  sessionId: uuid('session_id').notNull().references(() => postSessions.id, { onDelete: 'cascade' }),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  idempotencyKey: uuid('idempotency_key').notNull(),
  action: jsonb('action').$type<Record<string, unknown>>().notNull(),
  response: jsonb('response').$type<Record<string, unknown>>().notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const paradePuzzles = pgTable('parade_puzzles', {
  id: uuid('id').primaryKey().defaultRandom(),
  puzzleKey: text('puzzle_key').notNull().unique(),
  mode: text('mode', { enum: ['daily', 'practice'] }).notNull(),
  version: integer('version').notNull().default(1),
  definition: jsonb('definition').$type<Record<string, unknown>>().notNull(),
  minimumCommands: integer('minimum_commands').notNull(),
  verification: jsonb('verification').$type<Record<string, unknown>>().notNull(),
  active: boolean('active').notNull().default(true),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export const paradeSessions = pgTable('parade_sessions', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  petId: uuid('pet_id').notNull().references(() => pets.id, { onDelete: 'cascade' }),
  puzzleId: uuid('puzzle_id').notNull().references(() => paradePuzzles.id),
  mode: text('mode', { enum: ['daily', 'practice'] }).notNull(),
  status: text('status', { enum: ['active', 'completed', 'abandoned'] }).notNull().default('active'),
  runs: integer('runs').notNull().default(0),
  bestCommandCount: integer('best_command_count'),
  stars: integer('stars').notNull().default(0),
  rewardMinor: bigint('reward_minor', { mode: 'bigint' }).notNull().default(0n),
  journalCredited: boolean('journal_credited').notNull().default(false),
  lastResult: jsonb('last_result').$type<Record<string, unknown>>(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
  updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
  completedAt: timestamp('completed_at', { withTimezone: true }),
});

export const paradeActions = pgTable('parade_actions', {
  id: uuid('id').primaryKey().defaultRandom(),
  sessionId: uuid('session_id').notNull().references(() => paradeSessions.id, { onDelete: 'cascade' }),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  idempotencyKey: uuid('idempotency_key').notNull(),
  action: jsonb('action').$type<Record<string, unknown>>().notNull(),
  response: jsonb('response').$type<Record<string, unknown>>().notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});

export type User = typeof users.$inferSelect;
export type Pet = typeof pets.$inferSelect;
export type StoreItem = typeof storeItems.$inferSelect;
export type Deposit = typeof deposits.$inferSelect;
export type WithdrawalRequest = typeof withdrawalRequests.$inferSelect;
export type GameSession = typeof gameSessions.$inferSelect;
export type TrailSession = typeof trailSessions.$inferSelect;
export type PantryPuzzle = typeof pantryPuzzles.$inferSelect;
export type PantrySession = typeof pantrySessions.$inferSelect;
export type LanternPuzzle = typeof lanternPuzzles.$inferSelect;
export type LanternSession = typeof lanternSessions.$inferSelect;
export type PostPuzzle = typeof postPuzzles.$inferSelect;
export type PostSession = typeof postSessions.$inferSelect;
export type ParadePuzzle = typeof paradePuzzles.$inferSelect;
export type ParadeSession = typeof paradeSessions.$inferSelect;
