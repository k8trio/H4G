<?php
// Local dev router for PHP's built-in server. Mimics vercel.json:
//   /_x.php and /api/_x.php -> 404, static files served as-is, / and /x.php -> api/x.php
//
// From the repo root (Git Bash):
//   DATABASE_URL='postgres://user:pass@host/db?sslmode=require' AUTH_SECRET='any-long-random-string' \
//   php -d extension=pdo_pgsql -S 127.0.0.1:8000 -t . tests/dev-router.php
// then open http://127.0.0.1:8000
//
// The built-in server handles one request at a time: fine for clicking around,
// useless for tests/race_test.sh (run that against a Vercel preview).

$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (preg_match('#^/(api/)?_[^/]*$#', $path)) { http_response_code(404); echo 'Not found'; return true; }
if (!str_ends_with($path, '.php') && is_file($root . $path)) return false; // static file
if ($path === '/') $path = '/index.php';

if (preg_match('#^/(?:api/)?([A-Za-z0-9_-]+)\.php$#', $path, $m) && is_file("$root/api/$m[1].php")) {
  chdir("$root/api");
  require "$root/api/$m[1].php";
  return true;
}

http_response_code(404);
echo 'Not found';
return true;
