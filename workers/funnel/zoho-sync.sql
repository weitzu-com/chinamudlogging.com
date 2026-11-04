CREATE TABLE IF NOT EXISTS zoho_sync_state (
 id TEXT PRIMARY KEY CHECK(id='smtp'), cursor_ms INTEGER NOT NULL,
 lease_owner TEXT, lease_until INTEGER NOT NULL DEFAULT 0,
 last_attempt TEXT, last_success TEXT, last_error TEXT,
 scanned INTEGER NOT NULL DEFAULT 0, matched INTEGER NOT NULL DEFAULT 0,
 inserted INTEGER NOT NULL DEFAULT 0, duplicates INTEGER NOT NULL DEFAULT 0,
 ignored INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS zoho_delivery_evidence (
 event_hash TEXT PRIMARY KEY,
 inquiry_id TEXT NOT NULL REFERENCES intents(id) ON DELETE CASCADE,
 message_hash TEXT NOT NULL, evidence_hash TEXT NOT NULL,
 encrypted_evidence TEXT NOT NULL, observed_at TEXT NOT NULL,
 outcome TEXT NOT NULL CHECK(outcome IN ('inserted','duplicate','conflict'))
);
CREATE INDEX IF NOT EXISTS zoho_evidence_inquiry ON zoho_delivery_evidence(inquiry_id,observed_at);
