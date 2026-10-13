<?php
// Shared helpers: database connection, schema, signed-cookie login, CSRF, team lookups.
// Needs two env vars on Vercel: DATABASE_URL (Postgres) and AUTH_SECRET (random string).

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $u = parse_url(getenv('DATABASE_URL') ?: '');
  if (!$u || empty($u['host'])) { http_response_code(500); exit('DATABASE_URL is not set.'); }
  parse_str($u['query'] ?? '', $q);

  $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
    $u['host'], $u['port'] ?? 5432, ltrim($u['path'] ?? '', '/'), $q['sslmode'] ?? 'require');

  $pdo = new PDO($dsn, urldecode($u['user'] ?? ''), urldecode($u['pass'] ?? ''), [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // No named server-side prepared statements (parameters are still sent separately via
    // PQexecParams). Behind a connection pooler (Neon/PgBouncer) the DEALLOCATE that PDO sends
    // when a statement is freed fails silently and aborts the open transaction (SQLSTATE 25P02).
    PDO::PGSQL_ATTR_DISABLE_PREPARES => true,
  ]);

  return $pdo;
}

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// True when every team-mode table/column is in place (cheap catalog check, no locks).
function schema_ready(): bool {
  $col = "EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = '%s' AND column_name = '%s')";
  return (bool)db()->query('SELECT to_regclass(\'teams\') IS NOT NULL
      AND to_regclass(\'event_first_blood\') IS NOT NULL
      AND to_regclass(\'submissions\') IS NOT NULL
      AND to_regclass(\'team_join_attempts\') IS NOT NULL
      AND to_regclass(\'login_failures\') IS NOT NULL
      AND ' . sprintf($col, 'solves', 'team_id') . '
      AND ' . sprintf($col, 'players', 'team_id') . '
      AND NOT ' . sprintf($col, 'players', 'points'))->fetchColumn();
}

// Creates/upgrades the tables. Idempotent, and serialized with an advisory lock so two
// concurrent first requests cannot race on the DDL. Called from login.php and, as a fallback,
// from current_user() when a table is missing. The fast path skips the DDL (ALTER TABLE takes
// an exclusive lock on players even when it is a no-op).
function ensure_schema(): void {
  static $done = false;
  if ($done) return;
  $pdo = db();
  if (schema_ready()) { $done = true; return; }

  $pdo->beginTransaction();
  try {
    $pdo->query("SELECT pg_advisory_xact_lock(hashtext('h4g:ensure_schema'))");

    $pdo->exec("CREATE TABLE IF NOT EXISTS players (
      id BIGSERIAL PRIMARY KEY,
      username VARCHAR(20) NOT NULL,
      pass_hash TEXT NOT NULL,
      created_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS players_username_lc ON players (lower(username))");

    // Old single-player solves table (keyed by player_id): keep it as solves_legacy, do not convert.
    // Its primary key index must be renamed too, or the new table's solves_pkey would collide.
    $pdo->exec("DO \$\$ BEGIN
      IF to_regclass('solves') IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = current_schema() AND table_name = 'solves' AND column_name = 'team_id'
      ) THEN
        ALTER TABLE solves RENAME TO solves_legacy;
        IF to_regclass('solves_pkey') IS NOT NULL THEN
          ALTER INDEX solves_pkey RENAME TO solves_legacy_pkey;
        END IF;
      END IF;
    END \$\$");

    $pdo->exec("CREATE TABLE IF NOT EXISTS teams (
      id BIGSERIAL PRIMARY KEY,
      name VARCHAR(50) NOT NULL,
      invite_code VARCHAR(64) UNIQUE NOT NULL,
      points INT NOT NULL DEFAULT 0,
      created_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS teams_name_lc ON teams (lower(name))");

    $pdo->exec("ALTER TABLE players ADD COLUMN IF NOT EXISTS team_id BIGINT REFERENCES teams(id) ON DELETE SET NULL");
    $pdo->exec("CREATE INDEX IF NOT EXISTS players_team ON players(team_id)");
    $pdo->exec("ALTER TABLE players DROP COLUMN IF EXISTS points");

    $pdo->exec("CREATE TABLE IF NOT EXISTS solves (
      team_id BIGINT NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
      problem VARCHAR(100) NOT NULL,
      solved_by BIGINT REFERENCES players(id),
      points_awarded INT NOT NULL,
      solve_rank INT NOT NULL,
      first_blood BOOLEAN NOT NULL DEFAULT false,
      solved_at TIMESTAMPTZ NOT NULL DEFAULT now(),
      PRIMARY KEY (team_id, problem)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS solves_problem ON solves(problem)");

    // Audit trail for judges. Never stores the submitted flag text.
    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
      id BIGSERIAL PRIMARY KEY,
      team_id BIGINT, player_id BIGINT, problem VARCHAR(100),
      correct BOOLEAN NOT NULL,
      created_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS submissions_player_time ON submissions(player_id, created_at)");

    // One row max (id = 1). No FK to teams on purpose: deleting a team never frees the bonus.
    $pdo->exec("CREATE TABLE IF NOT EXISTS event_first_blood (
      id SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
      team_id BIGINT NOT NULL,
      team_name VARCHAR(50) NOT NULL,
      player_id BIGINT,
      problem VARCHAR(100) NOT NULL,
      awarded INT NOT NULL,
      claimed_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )");

    // Invalid invite-code attempts, for rate limiting joins.
    $pdo->exec("CREATE TABLE IF NOT EXISTS team_join_attempts (
      id BIGSERIAL PRIMARY KEY,
      player_id BIGINT NOT NULL,
      created_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS team_join_attempts_player_time ON team_join_attempts(player_id, created_at)");

    // Failed logins, for rate limiting. Keyed by username + IP so nobody off-site can lock you out.
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_failures (
      id BIGSERIAL PRIMARY KEY,
      username_lc VARCHAR(64) NOT NULL,
      ip VARCHAR(64) NOT NULL,
      created_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS login_failures_user_ip_time ON login_failures(username_lc, ip, created_at)");

    $pdo->commit();
    $done = true;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

function auth_secret(): string {
  $s = getenv('AUTH_SECRET');
  if (!$s) { http_response_code(500); exit('AUTH_SECRET is not set.'); }
  return $s;
}

// Client IP. On Vercel, x-real-ip / x-forwarded-for are set by the edge (client values are overwritten).
function client_ip(): string {
  $ip = $_SERVER['HTTP_X_REAL_IP'] ?? '';
  if ($ip === '') $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]);
  if ($ip === '') $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  return substr($ip, 0, 64);
}

function is_https(): bool {
  return !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

// Cookie format: <user id>.<expiry>.<hmac signature>  (no server-side session needed)
function login_user(int $id): void {
  $exp = time() + 60 * 60 * 24 * 7;
  $payload = $id . '.' . $exp;
  $sig = hash_hmac('sha256', $payload, auth_secret());
  setcookie('h4g_auth', $payload . '.' . $sig, [
    'expires' => $exp, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
  ]);
}

function logout_user(): void {
  setcookie('h4g_auth', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

// CSRF: signed double-submit. A random HttpOnly cookie, and a form token = HMAC(secret, cookie).
// Works before login too. Call csrf_token() before any output (it may set the cookie).
function csrf_token(): string {
  static $tok = null;
  if ($tok !== null) return $tok;
  $c = $_COOKIE['h4g_csrf'] ?? '';
  if (!is_string($c) || !preg_match('/^[a-f0-9]{64}$/', $c)) {
    $c = bin2hex(random_bytes(32));
    setcookie('h4g_csrf', $c, [
      'expires' => time() + 60 * 60 * 24 * 7, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    $_COOKIE['h4g_csrf'] = $c;
  }
  return $tok = hash_hmac('sha256', 'csrf|' . $c, auth_secret());
}

function csrf_field(): string {
  return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '"/>';
}

function csrf_valid(): bool {
  $c = $_COOKIE['h4g_csrf'] ?? '';
  $t = $_POST['csrf'] ?? '';
  if (!is_string($c) || !is_string($t) || $c === '' || $t === '') return false;
  return hash_equals(hash_hmac('sha256', 'csrf|' . $c, auth_secret()), $t);
}

function current_user(): ?array {
  static $user = false;
  if ($user !== false) return $user;
  $user = null;

  $parts = explode('.', $_COOKIE['h4g_auth'] ?? '');
  if (count($parts) !== 3) return null;
  [$id, $exp, $sig] = $parts;
  if (!ctype_digit($id) || !ctype_digit($exp) || (int)$exp < time()) return null;
  if (!hash_equals(hash_hmac('sha256', "$id.$exp", auth_secret()), $sig)) return null;

  $sql = 'SELECT p.id, p.username, p.team_id, t.name AS team_name, t.points AS team_points
          FROM players p LEFT JOIN teams t ON t.id = p.team_id WHERE p.id = ?';
  try {
    $st = db()->prepare($sql);
    $st->execute([(int)$id]);
  } catch (PDOException $e) {
    // First request after deploying team mode: tables/columns missing. Upgrade once and retry.
    if (!in_array($e->getCode(), ['42P01', '42703'], true)) throw $e;
    ensure_schema();
    $st = db()->prepare($sql);
    $st->execute([(int)$id]);
  }
  $user = $st->fetch() ?: null;
  return $user;
}

function require_login(bool $json = false): array {
  $user = current_user();
  if ($user) return $user;
  if ($json) {
    http_response_code(401);
    echo json_encode(['correct' => false, 'message' => '[ ERROR ] Please log in first.']);
  } else {
    header('Location: login.php');
  }
  exit;
}

// Requires login and a team. Players without a team go to team-setup.php.
function require_team(bool $json = false): array {
  $user = require_login($json);
  if ($user['team_id'] !== null) return $user;
  if ($json) {
    http_response_code(403);
    echo json_encode(['correct' => false, 'message' => '[ ERROR ] Create or join a team first.']);
  } else {
    header('Location: team-setup.php');
  }
  exit;
}

// problem => [solved_by (username), first_blood, points_awarded, solve_rank] for one team.
function solved_map(int $team_id): array {
  $st = db()->prepare('SELECT s.problem, p.username AS solved_by, s.first_blood, s.points_awarded, s.solve_rank
                       FROM solves s LEFT JOIN players p ON p.id = s.solved_by
                       WHERE s.team_id = ?');
  $st->execute([$team_id]);
  $map = [];
  foreach ($st->fetchAll() as $r) {
    $map[$r['problem']] = [
      'solved_by'      => $r['solved_by'],
      'first_blood'    => (bool)$r['first_blood'],
      'points_awarded' => (int)$r['points_awarded'],
      'solve_rank'     => (int)$r['solve_rank'],
    ];
  }
  return $map;
}

// problem => number of teams that solved it.
function solve_counts(): array {
  return array_map('intval', db()->query('SELECT problem, COUNT(*) FROM solves GROUP BY problem')->fetchAll(PDO::FETCH_KEY_PAIR));
}

// The event-wide First Blood row, or null while it is still unclaimed.
function event_first_blood(): ?array {
  return db()->query('SELECT team_id, team_name, problem, awarded, claimed_at FROM event_first_blood WHERE id = 1')->fetch() ?: null;
}

// TEAM / LOGGED IN / SCORE block for the top HUD.
function hud_stats(array $user): string {
  return '<div class="hud-stats">'
    . '<a class="hud-stat" href="team.php"><span class="pts-label">TEAM</span><span class="hud-stat-val">' . h($user['team_name']) . '</span></a>'
    . '<div class="hud-stat"><span class="pts-label">LOGGED IN</span><span class="hud-stat-val">' . h($user['username']) . '</span></div>'
    . '<div class="points-display"><span class="pts-label">SCORE</span><span class="pts-value" id="points">' . (int)$user['team_points'] . '</span></div>'
    . '</div>';
}
