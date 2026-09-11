-- One private room per account. All commands serialize on its owner's row.
CREATE TABLE pet_homes (
  user_id uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  version integer NOT NULL DEFAULT 0 CHECK (version >= 0),
  placements jsonb NOT NULL DEFAULT '[]' CHECK (jsonb_typeof(placements) = 'array' AND jsonb_array_length(placements) <= 20),
  next_care_at timestamptz NOT NULL DEFAULT now(),
  window_started_at timestamptz NOT NULL DEFAULT now(),
  action_count integer NOT NULL DEFAULT 0 CHECK (action_count >= 0)
);

CREATE TABLE home_actions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  action_id uuid NOT NULL,
  request_hash text NOT NULL,
  response jsonb NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (user_id, action_id)
);

-- Receipts are historical facts, including the original response to a retry.
CREATE FUNCTION protect_home_action() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  RAISE EXCEPTION 'home action receipts are immutable';
END;
$$;
CREATE TRIGGER home_actions_no_update BEFORE UPDATE ON home_actions
FOR EACH ROW EXECUTE FUNCTION protect_home_action();
