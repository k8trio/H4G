-- Wipes all scores after testing. Keeps accounts, teams, solo players and invite codes,
-- so everyone can log straight back in. Run against the production DATABASE_URL:
--   psql "$DATABASE_URL" -f tests/reset_scores.sql
-- (or paste it into the Neon / Vercel Postgres SQL editor).
BEGIN;
DELETE FROM event_first_blood;   -- frees the event's First Blood bonus again
DELETE FROM solves;              -- clears every capture, so decay ranks start from #1 again
DELETE FROM submissions;         -- audit log + wrong-flag rate limits
DELETE FROM team_join_attempts;  -- invalid-invite rate limits
DELETE FROM login_failures;      -- login lockouts
UPDATE teams SET points = 0;
COMMIT;
