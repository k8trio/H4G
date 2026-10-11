<?php
// Shared helpers: database connection + signed-cookie login.
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
  ]);

  // Tables are created automatically the first time.
  $pdo->exec("CREATE TABLE IF NOT EXISTS players (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(20) NOT NULL,
    pass_hash TEXT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
  )");
  $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS players_username_lc ON players (lower(username))");
  $pdo->exec("CREATE TABLE IF NOT EXISTS solves (
    player_id BIGINT NOT NULL REFERENCES players(id) ON DELETE CASCADE,
    problem VARCHAR(100) NOT NULL,
    solved_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (player_id, problem)
  )");
  return $pdo;
}

function auth_secret(): string {
  $s = getenv('AUTH_SECRET');
  if (!$s) { http_response_code(500); exit('AUTH_SECRET is not set.'); }
  return $s;
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

function current_user(): ?array {
  static $user = false;
  if ($user !== false) return $user;
  $user = null;

  $parts = explode('.', $_COOKIE['h4g_auth'] ?? '');
  if (count($parts) !== 3) return null;
  [$id, $exp, $sig] = $parts;
  if (!ctype_digit($id) || !ctype_digit($exp) || (int)$exp < time()) return null;
  if (!hash_equals(hash_hmac('sha256', "$id.$exp", auth_secret()), $sig)) return null;

  $st = db()->prepare('SELECT id, username, points FROM players WHERE id = ?');
  $st->execute([(int)$id]);
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

function solved_keys(int $player_id): array {
  $st = db()->prepare('SELECT problem FROM solves WHERE player_id = ?');
  $st->execute([$player_id]);
  return $st->fetchAll(PDO::FETCH_COLUMN);
}