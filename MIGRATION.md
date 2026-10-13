# Migration: single-player → team mode

This upgrades an existing H4G database (single-player `players.points` + `solves(player_id, problem)`)
to team mode. Old solves are **not** converted to teams: they are kept as `solves_legacy` for reference only.

## 0. Back up first

Take a Neon / Vercel Postgres snapshot (branch), or:

```bash
pg_dump "$DATABASE_URL" -Fc -f h4g-before-team-mode.dump
```

## 1. Apply the schema

You have two options. Both give the same result.

**A. Automatic.** Deploy, then log in once (or load any page with a valid login cookie).
`ensure_schema()` runs in one transaction behind an advisory lock and is idempotent, so concurrent
first requests are safe. It does nothing once the schema is in place.

**B. Manual SQL** (recommended, so the first player is not the one waiting for the DDL):

```sql
BEGIN;

-- Keep the old table for reference. Its primary key index must be renamed too, otherwise
-- creating the new solves table fails with: relation "solves_pkey" already exists.
ALTER TABLE solves RENAME TO solves_legacy;
ALTER INDEX solves_pkey RENAME TO solves_legacy_pkey;

CREATE TABLE IF NOT EXISTS teams (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  invite_code VARCHAR(64) UNIQUE NOT NULL,
  points INT NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS teams_name_lc ON teams (lower(name));
ALTER TABLE teams ADD COLUMN IF NOT EXISTS is_solo BOOLEAN NOT NULL DEFAULT false;

ALTER TABLE players ADD COLUMN IF NOT EXISTS team_id BIGINT REFERENCES teams(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS players_team ON players(team_id);
ALTER TABLE players DROP COLUMN IF EXISTS points;

CREATE TABLE IF NOT EXISTS solves (
  team_id BIGINT NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
  problem VARCHAR(100) NOT NULL,
  solved_by BIGINT REFERENCES players(id),
  points_awarded INT NOT NULL,
  solve_rank INT NOT NULL,
  first_blood BOOLEAN NOT NULL DEFAULT false,
  solved_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  PRIMARY KEY (team_id, problem)
);
CREATE INDEX IF NOT EXISTS solves_problem ON solves(problem);

CREATE TABLE IF NOT EXISTS submissions (
  id BIGSERIAL PRIMARY KEY,
  team_id BIGINT, player_id BIGINT, problem VARCHAR(100),
  correct BOOLEAN NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS submissions_player_time ON submissions(player_id, created_at);

CREATE TABLE IF NOT EXISTS event_first_blood (
  id SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  team_id BIGINT NOT NULL,
  team_name VARCHAR(50) NOT NULL,
  player_id BIGINT,
  problem VARCHAR(100) NOT NULL,
  awarded INT NOT NULL,
  claimed_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS team_join_attempts (
  id BIGSERIAL PRIMARY KEY,
  player_id BIGINT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS team_join_attempts_player_time ON team_join_attempts(player_id, created_at);

CREATE TABLE IF NOT EXISTS login_failures (
  id BIGSERIAL PRIMARY KEY,
  username_lc VARCHAR(64) NOT NULL,
  ip VARCHAR(64) NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS login_failures_user_ip_time ON login_failures(username_lc, ip, created_at);

COMMIT;
```

Skip the two `RENAME` lines if `solves_legacy` already exists, or if `solves` already has a `team_id` column.

## 2. After the upgrade

Existing accounts keep their passwords. On their next visit they are sent to **Team Setup**.

## Clean-slate alternative

If you would rather have everyone re-register before the event (the old data is test data anyway):

```sql
DROP TABLE IF EXISTS solves_legacy;
TRUNCATE players, teams, solves, submissions, event_first_blood, team_join_attempts, login_failures RESTART IDENTITY CASCADE;
```

## Solo players

A player who picks **PLAY SOLO** gets a private one-member team (`teams.is_solo = true`, named
`<username> [SOLO]`). It goes through the same scoring, decay and First Blood logic as any team,
but it cannot be joined and is listed under Individual Rankings, not Team Rankings.
Existing databases get the column automatically on the next page load (or run the `ALTER` above).

## Before launch day (required): reset scores

Every test solve before the event counts. In particular, **the first test solve uses up the event's
only First Blood**. Clear all test data right before the event starts, with `tests/reset_scores.sql`:

```bash
psql "$DATABASE_URL" -f tests/reset_scores.sql
```

or paste its contents into the Neon / Vercel Postgres SQL editor. It keeps accounts and teams, and
resets points, solves, First Blood, the audit log and every rate limit.

Also delete any test teams and accounts you made (for example the `RACETEST …` teams and `rt…` users
from `tests/race_test.sh`). Delete teams first, because `solves.solved_by` references players:

```sql
DELETE FROM teams   WHERE name LIKE 'RACETEST %';
DELETE FROM players WHERE username ~ '^rt[0-9]{6}[a-z][0-9]+$';  -- race_test.sh users only
```

Then run `tests/verify.sql`: every query should return zero rows.

## Unlocking a player

Failed logins are limited to 10 per 15 minutes per username + IP (`LOGIN_FAIL_LIMIT` /
`LOGIN_FAIL_WINDOW` in `api/_scoring.php`). To unlock someone early:

```sql
DELETE FROM login_failures WHERE username_lc = lower('their_username');
```

## Rollback

Restore the backup from step 0, then redeploy the previous commit.
