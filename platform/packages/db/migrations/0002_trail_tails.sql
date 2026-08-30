-- Trail Tails keeps all authoritative map and reward state on the server.
CREATE TABLE daily_game_rewards (
  user_id                uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  reward_date            date NOT NULL,
  awarded_minor          bigint NOT NULL DEFAULT 0 CHECK (awarded_minor >= 0),
  rewarded_completions   integer NOT NULL DEFAULT 0 CHECK (rewarded_completions >= 0),
  updated_at              timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (user_id, reward_date)
);

-- Preserve rewards already granted by Paw Match when this migration lands.
INSERT INTO daily_game_rewards (user_id, reward_date, awarded_minor, rewarded_completions)
SELECT user_id,
       (completed_at AT TIME ZONE 'UTC')::date,
       SUM(reward_minor),
       COUNT(*)::integer
FROM game_sessions
WHERE status = 'completed' AND reward_minor > 0 AND completed_at IS NOT NULL
GROUP BY user_id, (completed_at AT TIME ZONE 'UTC')::date
ON CONFLICT (user_id, reward_date) DO NOTHING;

CREATE TABLE trail_sessions (
  id                    uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id               uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  pet_id                uuid NOT NULL REFERENCES pets(id) ON DELETE CASCADE,
  status                text NOT NULL DEFAULT 'active'
                        CHECK (status IN ('active', 'completed', 'failed', 'abandoned')),
  private_map           jsonb NOT NULL
                        CHECK (jsonb_typeof(private_map) = 'array'
                               AND jsonb_array_length(private_map) = 25),
  discovered_positions  jsonb NOT NULL DEFAULT '[]'
                        CHECK (jsonb_typeof(discovered_positions) = 'array'),
  position              integer NOT NULL CHECK (position BETWEEN 0 AND 24),
  start_position        integer NOT NULL CHECK (start_position BETWEEN 0 AND 24),
  home_position         integer NOT NULL CHECK (home_position BETWEEN 0 AND 24),
  keepsake_position     integer NOT NULL CHECK (keepsake_position BETWEEN 0 AND 24),
  rescue_position       integer NOT NULL CHECK (rescue_position BETWEEN 0 AND 24),
  energy                integer NOT NULL DEFAULT 12 CHECK (energy BETWEEN 0 AND 14),
  sniff_charges         integer NOT NULL DEFAULT 2 CHECK (sniff_charges BETWEEN 0 AND 2),
  dash_charges          integer NOT NULL DEFAULT 1 CHECK (dash_charges BETWEEN 0 AND 1),
  rest_charges          integer NOT NULL DEFAULT 1 CHECK (rest_charges BETWEEN 0 AND 1),
  keepsake_found        boolean NOT NULL DEFAULT false,
  rescue_found          boolean NOT NULL DEFAULT false,
  turns                 integer NOT NULL DEFAULT 0 CHECK (turns >= 0),
  stars                 integer NOT NULL DEFAULT 0 CHECK (stars BETWEEN 0 AND 3),
  reward_minor          bigint NOT NULL DEFAULT 0 CHECK (reward_minor >= 0),
  created_at            timestamptz NOT NULL DEFAULT now(),
  updated_at            timestamptz NOT NULL DEFAULT now(),
  completed_at          timestamptz
);

CREATE UNIQUE INDEX trail_sessions_one_active_per_user
  ON trail_sessions(user_id) WHERE status = 'active';
CREATE INDEX trail_sessions_user_created_idx ON trail_sessions(user_id, created_at DESC);
CREATE INDEX trail_sessions_pet_idx ON trail_sessions(pet_id);

CREATE TABLE trail_actions (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id      uuid NOT NULL REFERENCES trail_sessions(id) ON DELETE CASCADE,
  user_id         uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  idempotency_key uuid NOT NULL,
  action          jsonb NOT NULL,
  response        jsonb NOT NULL,
  created_at      timestamptz NOT NULL DEFAULT now(),
  UNIQUE (user_id, idempotency_key)
);

CREATE INDEX trail_actions_session_idx ON trail_actions(session_id);

CREATE TABLE pet_game_progress (
  pet_id       uuid NOT NULL REFERENCES pets(id) ON DELETE CASCADE,
  game_type    text NOT NULL CHECK (game_type IN ('trail_tails')),
  bond_xp      integer NOT NULL DEFAULT 0 CHECK (bond_xp >= 0),
  bond_level   integer NOT NULL DEFAULT 1 CHECK (bond_level >= 1),
  updated_at   timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (pet_id, game_type)
);
