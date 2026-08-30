-- Lantern Lines and Pocket Post: verified public puzzles with server-owned play state.
ALTER TABLE pet_game_progress DROP CONSTRAINT pet_game_progress_game_type_check;
ALTER TABLE pet_game_progress ADD CONSTRAINT pet_game_progress_game_type_check
  CHECK (game_type IN ('trail_tails', 'midnight_pantry', 'lantern_lines', 'pocket_post'));

CREATE TABLE lantern_puzzles (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  puzzle_key text NOT NULL UNIQUE,
  mode text NOT NULL CHECK (mode IN ('daily', 'practice')),
  version integer NOT NULL DEFAULT 1 CHECK (version > 0),
  definition jsonb NOT NULL CHECK (jsonb_typeof(definition) = 'object'),
  minimum_turns integer NOT NULL CHECK (minimum_turns >= 0),
  verification jsonb NOT NULL CHECK (jsonb_typeof(verification) = 'object'),
  active boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE lantern_sessions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  pet_id uuid NOT NULL REFERENCES pets(id) ON DELETE CASCADE,
  puzzle_id uuid NOT NULL REFERENCES lantern_puzzles(id),
  mode text NOT NULL CHECK (mode IN ('daily', 'practice')),
  status text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'completed', 'abandoned')),
  orientations jsonb NOT NULL CHECK (jsonb_typeof(orientations) = 'array'),
  rotations integer NOT NULL DEFAULT 0 CHECK (rotations >= 0),
  resets integer NOT NULL DEFAULT 0 CHECK (resets >= 0),
  stars integer NOT NULL DEFAULT 0 CHECK (stars BETWEEN 0 AND 3),
  reward_minor bigint NOT NULL DEFAULT 0 CHECK (reward_minor >= 0),
  journal_credited boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  completed_at timestamptz
);
CREATE UNIQUE INDEX lantern_sessions_one_active_per_user ON lantern_sessions(user_id) WHERE status = 'active';
CREATE INDEX lantern_sessions_user_created_idx ON lantern_sessions(user_id, created_at DESC);

CREATE TABLE lantern_actions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id uuid NOT NULL REFERENCES lantern_sessions(id) ON DELETE CASCADE,
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  idempotency_key uuid NOT NULL,
  action jsonb NOT NULL,
  response jsonb NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (user_id, idempotency_key)
);
CREATE INDEX lantern_actions_session_idx ON lantern_actions(session_id);

CREATE TABLE post_puzzles (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  puzzle_key text NOT NULL UNIQUE,
  mode text NOT NULL CHECK (mode IN ('daily', 'practice')),
  version integer NOT NULL DEFAULT 1 CHECK (version > 0),
  definition jsonb NOT NULL CHECK (jsonb_typeof(definition) = 'object'),
  minimum_pushes integer NOT NULL CHECK (minimum_pushes >= 0),
  minimum_moves integer NOT NULL CHECK (minimum_moves >= 0),
  verification jsonb NOT NULL CHECK (jsonb_typeof(verification) = 'object'),
  active boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE post_sessions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  pet_id uuid NOT NULL REFERENCES pets(id) ON DELETE CASCADE,
  puzzle_id uuid NOT NULL REFERENCES post_puzzles(id),
  mode text NOT NULL CHECK (mode IN ('daily', 'practice')),
  status text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'completed', 'abandoned')),
  player_position integer NOT NULL CHECK (player_position >= 0),
  box_positions jsonb NOT NULL CHECK (jsonb_typeof(box_positions) = 'array'),
  history jsonb NOT NULL DEFAULT '[]' CHECK (jsonb_typeof(history) = 'array'),
  moves integer NOT NULL DEFAULT 0 CHECK (moves >= 0),
  pushes integer NOT NULL DEFAULT 0 CHECK (pushes >= 0),
  undos integer NOT NULL DEFAULT 0 CHECK (undos >= 0),
  resets integer NOT NULL DEFAULT 0 CHECK (resets >= 0),
  stars integer NOT NULL DEFAULT 0 CHECK (stars BETWEEN 0 AND 3),
  reward_minor bigint NOT NULL DEFAULT 0 CHECK (reward_minor >= 0),
  journal_credited boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  completed_at timestamptz
);
CREATE UNIQUE INDEX post_sessions_one_active_per_user ON post_sessions(user_id) WHERE status = 'active';
CREATE INDEX post_sessions_user_created_idx ON post_sessions(user_id, created_at DESC);

CREATE TABLE post_actions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id uuid NOT NULL REFERENCES post_sessions(id) ON DELETE CASCADE,
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  idempotency_key uuid NOT NULL,
  action jsonb NOT NULL,
  response jsonb NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (user_id, idempotency_key)
);
CREATE INDEX post_actions_session_idx ON post_actions(session_id);
