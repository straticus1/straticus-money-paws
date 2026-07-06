-- paws.money v5 initial schema
-- Money is stored as bigint minor units (cents for USD, whole coins for PAWS).

CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE users (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  email         text NOT NULL UNIQUE,
  username      text NOT NULL UNIQUE,
  password_hash text NOT NULL,
  role          text NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
  created_at    timestamptz NOT NULL DEFAULT now()
);

-- Session tokens are stored hashed (SHA-256); the raw token never touches disk.
CREATE TABLE sessions (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id    uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash text NOT NULL UNIQUE,
  expires_at timestamptz NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX sessions_user_idx ON sessions(user_id);

CREATE TABLE user_2fa (
  user_id     uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  totp_secret text NOT NULL,
  enabled     boolean NOT NULL DEFAULT false,
  created_at  timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE pets (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id    uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  name       text NOT NULL CHECK (char_length(name) BETWEEN 1 AND 50),
  species    text NOT NULL,
  hunger     integer NOT NULL DEFAULT 100 CHECK (hunger BETWEEN 0 AND 100),
  happiness  integer NOT NULL DEFAULT 100 CHECK (happiness BETWEEN 0 AND 100),
  health     integer NOT NULL DEFAULT 100 CHECK (health BETWEEN 0 AND 100),
  alive      boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX pets_user_idx ON pets(user_id);

CREATE TABLE store_items (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name        text NOT NULL UNIQUE,
  description text NOT NULL DEFAULT '',
  category    text NOT NULL,
  price_minor bigint NOT NULL CHECK (price_minor >= 0),
  currency    text NOT NULL DEFAULT 'PAWS' CHECK (currency IN ('PAWS', 'USD')),
  effect      jsonb NOT NULL DEFAULT '{}',
  active      boolean NOT NULL DEFAULT true
);

CREATE TABLE inventory (
  id       uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id  uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  item_id  uuid NOT NULL REFERENCES store_items(id),
  quantity integer NOT NULL DEFAULT 0 CHECK (quantity >= 0),
  UNIQUE (user_id, item_id)
);

-- === Double-entry ledger ===
-- Balances are never stored on users; they are the sum of entries per account.

CREATE TABLE ledger_accounts (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  owner_user_id uuid REFERENCES users(id) ON DELETE RESTRICT,
  kind          text NOT NULL CHECK (kind IN ('user', 'system')),
  system_name   text,
  currency      text NOT NULL CHECK (currency IN ('PAWS', 'USD')),
  created_at    timestamptz NOT NULL DEFAULT now(),
  CHECK ((kind = 'user') = (owner_user_id IS NOT NULL)),
  CHECK ((kind = 'system') = (system_name IS NOT NULL))
);
CREATE UNIQUE INDEX ledger_accounts_user_ccy ON ledger_accounts(owner_user_id, currency)
  WHERE owner_user_id IS NOT NULL;
CREATE UNIQUE INDEX ledger_accounts_system_ccy ON ledger_accounts(system_name, currency)
  WHERE system_name IS NOT NULL;

CREATE TABLE ledger_transactions (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  idempotency_key text NOT NULL UNIQUE,
  kind            text NOT NULL,
  metadata        jsonb NOT NULL DEFAULT '{}',
  created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE ledger_entries (
  id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  transaction_id uuid NOT NULL REFERENCES ledger_transactions(id),
  account_id     uuid NOT NULL REFERENCES ledger_accounts(id),
  amount_minor   bigint NOT NULL CHECK (amount_minor <> 0),
  created_at     timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ledger_entries_account_idx ON ledger_entries(account_id);
CREATE INDEX ledger_entries_tx_idx ON ledger_entries(transaction_id);

-- Enforce double-entry at the database level: every transaction's entries
-- must sum to zero per currency by the time the enclosing tx commits.
CREATE OR REPLACE FUNCTION check_ledger_balanced() RETURNS trigger AS $$
DECLARE bad record;
BEGIN
  SELECT a.currency, sum(e.amount_minor) AS total
    INTO bad
    FROM ledger_entries e
    JOIN ledger_accounts a ON a.id = e.account_id
   WHERE e.transaction_id = COALESCE(NEW.transaction_id, OLD.transaction_id)
   GROUP BY a.currency
  HAVING sum(e.amount_minor) <> 0
   LIMIT 1;
  IF FOUND THEN
    RAISE EXCEPTION 'ledger transaction % unbalanced: % %',
      COALESCE(NEW.transaction_id, OLD.transaction_id), bad.total, bad.currency;
  END IF;
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER ledger_entries_balanced
  AFTER INSERT OR UPDATE OR DELETE ON ledger_entries
  DEFERRABLE INITIALLY DEFERRED
  FOR EACH ROW EXECUTE FUNCTION check_ledger_balanced();

-- Ledger rows are append-only.
CREATE OR REPLACE FUNCTION forbid_ledger_mutation() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'ledger rows are append-only';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER ledger_entries_immutable
  BEFORE UPDATE OR DELETE ON ledger_entries
  FOR EACH ROW EXECUTE FUNCTION forbid_ledger_mutation();
CREATE TRIGGER ledger_transactions_immutable
  BEFORE UPDATE OR DELETE ON ledger_transactions
  FOR EACH ROW EXECUTE FUNCTION forbid_ledger_mutation();

-- === Payments ===

CREATE TABLE deposits (
  id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id            uuid NOT NULL REFERENCES users(id),
  provider           text NOT NULL DEFAULT 'coinbase',
  provider_charge_id text NOT NULL UNIQUE,
  status             text NOT NULL DEFAULT 'pending'
                     CHECK (status IN ('pending', 'confirmed', 'failed', 'expired')),
  amount_minor       bigint NOT NULL CHECK (amount_minor > 0),
  currency           text NOT NULL CHECK (currency IN ('PAWS', 'USD')),
  created_at         timestamptz NOT NULL DEFAULT now(),
  confirmed_at       timestamptz
);
CREATE INDEX deposits_user_idx ON deposits(user_id);

CREATE TABLE withdrawal_requests (
  id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id      uuid NOT NULL REFERENCES users(id),
  amount_minor bigint NOT NULL CHECK (amount_minor > 0),
  currency     text NOT NULL CHECK (currency IN ('PAWS', 'USD')),
  destination  text NOT NULL,
  status       text NOT NULL DEFAULT 'pending'
               CHECK (status IN ('pending', 'approved', 'denied', 'paid')),
  reviewed_by  uuid REFERENCES users(id),
  review_note  text,
  created_at   timestamptz NOT NULL DEFAULT now(),
  reviewed_at  timestamptz
);
CREATE INDEX withdrawal_requests_status_idx ON withdrawal_requests(status);
