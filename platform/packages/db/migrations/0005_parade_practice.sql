-- Parade Practice: verified command-programming puzzles.
ALTER TABLE pet_game_progress DROP CONSTRAINT pet_game_progress_game_type_check;
ALTER TABLE pet_game_progress ADD CONSTRAINT pet_game_progress_game_type_check
  CHECK (game_type IN ('trail_tails', 'midnight_pantry', 'lantern_lines', 'pocket_post', 'parade_practice'));

CREATE TABLE parade_puzzles (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  puzzle_key text NOT NULL UNIQUE,
  mode text NOT NULL CHECK (mode IN ('daily', 'practice')),
  version integer NOT NULL DEFAULT 1 CHECK (version > 0),
  definition jsonb NOT NULL CHECK (jsonb_typeof(definition) = 'object'),
  minimum_commands integer NOT NULL CHECK (minimum_commands BETWEEN 1 AND 12),
  verification jsonb NOT NULL CHECK (jsonb_typeof(verification) = 'object'),
  active boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE parade_sessions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  pet_id uuid NOT NULL REFERENCES pets(id) ON DELETE CASCADE,
  puzzle_id uuid NOT NULL REFERENCES parade_puzzles(id),
  mode text NOT NULL CHECK (mode IN ('daily', 'practice')),
  status text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'completed', 'abandoned')),
  runs integer NOT NULL DEFAULT 0 CHECK (runs >= 0),
  best_command_count integer,
  stars integer NOT NULL DEFAULT 0 CHECK (stars BETWEEN 0 AND 3),
  reward_minor bigint NOT NULL DEFAULT 0 CHECK (reward_minor >= 0),
  journal_credited boolean NOT NULL DEFAULT false,
  last_result jsonb,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  completed_at timestamptz
);
CREATE UNIQUE INDEX parade_sessions_one_active_per_user ON parade_sessions(user_id) WHERE status = 'active';

CREATE TABLE parade_actions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id uuid NOT NULL REFERENCES parade_sessions(id) ON DELETE CASCADE,
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  idempotency_key uuid NOT NULL,
  action jsonb NOT NULL,
  response jsonb NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (user_id, idempotency_key)
);
CREATE INDEX parade_actions_session_idx ON parade_actions(session_id);
