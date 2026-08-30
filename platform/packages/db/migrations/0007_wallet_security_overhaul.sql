ALTER TABLE withdrawal_requests
  ADD COLUMN destination_network text NOT NULL DEFAULT 'bitcoin'
    CHECK (destination_network IN ('bitcoin', 'ethereum', 'solana')),
  ADD COLUMN request_key text;

UPDATE withdrawal_requests SET request_key = 'legacy:' || id::text WHERE request_key IS NULL;
ALTER TABLE withdrawal_requests ALTER COLUMN request_key SET NOT NULL;
CREATE UNIQUE INDEX withdrawal_requests_user_key_idx
  ON withdrawal_requests(user_id, request_key);

-- Apply the shorter session policy to already-issued sessions as well as new
-- ones, avoiding a two-week legacy replay window after deployment.
UPDATE sessions
SET expires_at = created_at + interval '24 hours'
WHERE expires_at > created_at + interval '24 hours';

-- Security audit records are evidence. The application may append them but
-- neither application bugs nor compromised admin sessions may rewrite them.
CREATE OR REPLACE FUNCTION forbid_security_audit_mutation() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'security audit events are append-only';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER security_audit_events_immutable
  BEFORE UPDATE OR DELETE ON security_audit_events
  FOR EACH ROW EXECUTE FUNCTION forbid_security_audit_mutation();
