CREATE TABLE pet_companions (
  pet_id uuid PRIMARY KEY REFERENCES pets(id) ON DELETE CASCADE,
  personality text NOT NULL CHECK (personality IN ('curious', 'gentle', 'playful')),
  coat text NOT NULL CHECK (coat IN ('honey', 'silver', 'cocoa', 'cream')),
  marking text NOT NULL CHECK (marking IN ('blaze', 'socks', 'speckles')),
  bandana text NOT NULL DEFAULT 'moss' CHECK (bandana IN ('moss', 'sunflower', 'berry', 'midnight')),
  favorite_food text NOT NULL CHECK (favorite_food IN ('Clover Crunch', 'Sunbeam Nibbles')),
  favorite_toy text NOT NULL CHECK (favorite_toy IN ('Squeaky Moon', 'Rolling Acorn', 'Ribbon Comet')),
  favorite_game text NOT NULL CHECK (favorite_game IN ('paw_match', 'trail_tails', 'midnight_pantry', 'lantern_lines', 'pocket_post', 'parade_practice')),
  bond_xp integer NOT NULL DEFAULT 0 CHECK (bond_xp BETWEEN 0 AND 10000),
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE pet_memories (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  pet_id uuid NOT NULL REFERENCES pets(id) ON DELETE CASCADE,
  event_key text NOT NULL,
  kind text NOT NULL,
  title text NOT NULL,
  detail text NOT NULL,
  xp_delta integer NOT NULL CHECK (xp_delta BETWEEN 0 AND 10),
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (pet_id, event_key)
);
CREATE INDEX pet_memories_recent ON pet_memories (pet_id, created_at DESC);
CREATE TRIGGER pet_memories_no_update BEFORE UPDATE ON pet_memories
FOR EACH ROW EXECUTE FUNCTION protect_home_action();

CREATE TABLE pet_daily_adventures (
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  adventure_date date NOT NULL,
  pet_id uuid NOT NULL REFERENCES pets(id) ON DELETE CASCADE,
  started_at timestamptz NOT NULL DEFAULT now(),
  affection_at timestamptz,
  fed_at timestamptz,
  game_session_id uuid,
  game_type text,
  claimed_at timestamptz,
  keepsake_id uuid REFERENCES store_items(id),
  PRIMARY KEY (user_id, adventure_date),
  CHECK ((game_session_id IS NULL) = (game_type IS NULL)),
  CHECK (claimed_at IS NULL OR (affection_at IS NOT NULL AND fed_at IS NOT NULL AND game_session_id IS NOT NULL AND keepsake_id IS NOT NULL))
);

-- Bound verified-completion lookups to one account and the current adventure.
CREATE INDEX game_companion_completions ON game_sessions (user_id, completed_at) WHERE status = 'completed';
CREATE INDEX trail_companion_completions ON trail_sessions (user_id, pet_id, completed_at) WHERE status = 'completed';
CREATE INDEX pantry_companion_completions ON pantry_sessions (user_id, pet_id, completed_at) WHERE status = 'completed';
CREATE INDEX lantern_companion_completions ON lantern_sessions (user_id, pet_id, completed_at) WHERE status = 'completed';
CREATE INDEX post_companion_completions ON post_sessions (user_id, pet_id, completed_at) WHERE status = 'completed';
CREATE INDEX parade_companion_completions ON parade_sessions (user_id, pet_id, completed_at) WHERE status = 'completed';
