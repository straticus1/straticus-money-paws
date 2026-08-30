-- Privacy-first community features. Scores are always derived from
-- authoritative game tables; clients cannot write trophy or leaderboard data.
CREATE TABLE user_settings (
  user_id             uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  leaderboard_opt_in  boolean NOT NULL DEFAULT false,
  trophy_showcase     boolean NOT NULL DEFAULT true,
  updated_at          timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE user_trophies (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id     uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  trophy_key  text NOT NULL CHECK (trophy_key ~ '^[a-z0-9_]{3,40}$'),
  earned_at   timestamptz NOT NULL DEFAULT now(),
  metadata    jsonb NOT NULL DEFAULT '{}'::jsonb,
  UNIQUE (user_id, trophy_key)
);
CREATE INDEX user_trophies_user_earned_idx ON user_trophies(user_id, earned_at DESC);

CREATE TABLE security_audit_events (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id     uuid REFERENCES users(id) ON DELETE SET NULL,
  event_type  text NOT NULL,
  metadata    jsonb NOT NULL DEFAULT '{}'::jsonb,
  created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX security_audit_events_user_created_idx
  ON security_audit_events(user_id, created_at DESC);
