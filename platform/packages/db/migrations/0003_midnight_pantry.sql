-- The Midnight Pantry: verified deduction puzzles and server-owned sessions.
ALTER TABLE pet_game_progress DROP CONSTRAINT pet_game_progress_game_type_check;
ALTER TABLE pet_game_progress ADD CONSTRAINT pet_game_progress_game_type_check
  CHECK (game_type IN ('trail_tails', 'midnight_pantry'));

CREATE TABLE pantry_puzzles (
  id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  puzzle_key         text NOT NULL UNIQUE,
  mode               text NOT NULL CHECK (mode IN ('daily', 'practice')),
  generator_version  integer NOT NULL DEFAULT 1 CHECK (generator_version > 0),
  public_definition  jsonb NOT NULL CHECK (jsonb_typeof(public_definition) = 'object'),
  private_solution   jsonb NOT NULL CHECK (jsonb_typeof(private_solution) = 'object'),
  verification       jsonb NOT NULL CHECK (jsonb_typeof(verification) = 'object'),
  active             boolean NOT NULL DEFAULT true,
  created_at         timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE pantry_sessions (
  id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id            uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  pet_id             uuid NOT NULL REFERENCES pets(id) ON DELETE CASCADE,
  puzzle_id          uuid NOT NULL REFERENCES pantry_puzzles(id),
  mode               text NOT NULL CHECK (mode IN ('daily', 'practice')),
  status             text NOT NULL DEFAULT 'active'
                     CHECK (status IN ('active', 'completed', 'failed', 'abandoned')),
  placements         jsonb NOT NULL DEFAULT '{}'
                     CHECK (jsonb_typeof(placements) = 'object'),
  locked_guests      jsonb NOT NULL DEFAULT '[]'
                     CHECK (jsonb_typeof(locked_guests) = 'array'),
  bell_rings         integer NOT NULL DEFAULT 0 CHECK (bell_rings BETWEEN 0 AND 6),
  mistakes           integer NOT NULL DEFAULT 0 CHECK (mistakes BETWEEN 0 AND 6),
  stars              integer NOT NULL DEFAULT 0 CHECK (stars BETWEEN 0 AND 3),
  reward_minor       bigint NOT NULL DEFAULT 0 CHECK (reward_minor >= 0),
  journal_credited   boolean NOT NULL DEFAULT false,
  created_at         timestamptz NOT NULL DEFAULT now(),
  updated_at         timestamptz NOT NULL DEFAULT now(),
  completed_at       timestamptz
);

CREATE UNIQUE INDEX pantry_sessions_one_active_per_user
  ON pantry_sessions(user_id) WHERE status = 'active';
CREATE INDEX pantry_sessions_user_created_idx ON pantry_sessions(user_id, created_at DESC);
CREATE INDEX pantry_sessions_puzzle_idx ON pantry_sessions(puzzle_id);

CREATE TABLE pantry_actions (
  id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id       uuid NOT NULL REFERENCES pantry_sessions(id) ON DELETE CASCADE,
  user_id          uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  idempotency_key  uuid NOT NULL,
  action           jsonb NOT NULL,
  response         jsonb NOT NULL,
  created_at       timestamptz NOT NULL DEFAULT now(),
  UNIQUE (user_id, idempotency_key)
);

CREATE INDEX pantry_actions_session_idx ON pantry_actions(session_id);
