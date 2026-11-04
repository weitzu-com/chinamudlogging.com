PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS sessions (
 id TEXT PRIMARY KEY, created_at TEXT NOT NULL, expires_at TEXT NOT NULL,
 landing_path TEXT NOT NULL, source TEXT NOT NULL, is_test INTEGER NOT NULL CHECK(is_test IN (0,1))
);
CREATE INDEX IF NOT EXISTS sessions_cohort ON sessions(is_test,created_at);
CREATE TABLE IF NOT EXISTS events (
 id TEXT PRIMARY KEY, session_id TEXT NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
 kind TEXT NOT NULL CHECK(kind IN ('page_view','product_view','contact_view')), path TEXT NOT NULL, created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS events_session ON events(session_id);
CREATE TABLE IF NOT EXISTS intents (
 id TEXT PRIMARY KEY, session_id TEXT NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
 request_id TEXT NOT NULL, kind TEXT NOT NULL CHECK(kind IN ('email_prepare','email_click','phone_click')),
 path TEXT NOT NULL, created_at TEXT NOT NULL, UNIQUE(session_id,request_id)
);
CREATE INDEX IF NOT EXISTS intents_session ON intents(session_id);
CREATE TABLE IF NOT EXISTS receipts (
 inquiry_id TEXT PRIMARY KEY REFERENCES intents(id) ON DELETE CASCADE,
 message_hash TEXT NOT NULL UNIQUE, delivered_at TEXT NOT NULL, verified_at TEXT NOT NULL,
 evidence_hash TEXT NOT NULL, source TEXT NOT NULL CHECK(source='zoho_delivery_log')
);
CREATE TABLE IF NOT EXISTS qualifications (
 id TEXT PRIMARY KEY, inquiry_id TEXT NOT NULL REFERENCES receipts(inquiry_id) ON DELETE CASCADE,
 decision TEXT NOT NULL CHECK(decision IN ('qualified','rejected')),
 reason TEXT NOT NULL, decided_at TEXT NOT NULL, sequence INTEGER NOT NULL UNIQUE
);
CREATE INDEX IF NOT EXISTS qualifications_inquiry ON qualifications(inquiry_id,sequence DESC);
