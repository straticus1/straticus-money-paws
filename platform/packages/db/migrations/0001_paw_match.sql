-- Server-authoritative Paw Match sessions. The board never leaves the server;
-- clients receive symbols only for cards they have legitimately revealed.
CREATE TABLE game_sessions (
  id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id           uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  game_type         text NOT NULL DEFAULT 'paw_match' CHECK (game_type = 'paw_match'),
  status            text NOT NULL DEFAULT 'active'
                    CHECK (status IN ('active', 'completed', 'abandoned')),
  board             jsonb NOT NULL
                    CHECK (jsonb_typeof(board) = 'array' AND jsonb_array_length(board) = 12),
  matched_positions jsonb NOT NULL DEFAULT '[]'
                    CHECK (jsonb_typeof(matched_positions) = 'array'),
  first_position    integer CHECK (first_position BETWEEN 0 AND 11),
  moves             integer NOT NULL DEFAULT 0 CHECK (moves >= 0),
  reward_minor      bigint NOT NULL DEFAULT 0 CHECK (reward_minor >= 0),
  created_at        timestamptz NOT NULL DEFAULT now(),
  updated_at        timestamptz NOT NULL DEFAULT now(),
  completed_at      timestamptz
);

CREATE UNIQUE INDEX game_sessions_one_active_per_user
  ON game_sessions(user_id, game_type) WHERE status = 'active';
CREATE INDEX game_sessions_user_created_idx ON game_sessions(user_id, created_at DESC);

-- Exact responses are retained for safe network retries. A caller chooses the
-- UUID action key, but cannot use it to influence game state or settlement.
CREATE TABLE game_actions (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id      uuid NOT NULL REFERENCES game_sessions(id) ON DELETE CASCADE,
  user_id         uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  idempotency_key uuid NOT NULL,
  response        jsonb NOT NULL,
  created_at      timestamptz NOT NULL DEFAULT now(),
  UNIQUE (user_id, idempotency_key)
);

CREATE INDEX game_actions_session_idx ON game_actions(session_id);
