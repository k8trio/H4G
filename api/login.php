<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_scoring.php';

if (current_user()) { header('Location: index.php'); exit; }
$csrf = csrf_token();

$mode  = (($_GET['mode'] ?? '') === 'register') ? 'register' : 'login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mode     = ($_POST['action'] ?? '') === 'register' ? 'register' : 'login';
  $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
  $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
  ensure_schema();
  $pdo      = db();

  if (!csrf_valid()) {
    $error = 'Session expired. Please try again.';
  } elseif ($mode === 'register') {
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
      $error = 'Username: 3-20 letters, numbers or underscore.';
    } elseif (strlen($password) < 6) {
      $error = 'Password must be at least 6 characters.';
    } elseif ($password !== ($_POST['confirm'] ?? '')) {
      $error = 'Passwords do not match.';
    } else {
      try {
        $st = $pdo->prepare('INSERT INTO players (username, pass_hash) VALUES (?, ?) RETURNING id');
        $st->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        login_user((int)$st->fetchColumn());
        header('Location: index.php'); exit;
      } catch (PDOException $e) {
        $error = $e->getCode() === '23505' ? 'That username is already taken.' : 'Something went wrong. Try again.';
      }
    }
  } else {
    // Rate limit failed logins per username + IP. Checked before the password, so a locked-out
    // guesser learns nothing even with the right password.
    $user_lc = strtolower(substr($username, 0, 64));
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_failures
                         WHERE username_lc = ? AND ip = ? AND created_at > now() - make_interval(secs => ?)');
    $st->execute([$user_lc, client_ip(), LOGIN_FAIL_WINDOW]);

    if ((int)$st->fetchColumn() >= LOGIN_FAIL_LIMIT) {
      http_response_code(429);
      $error = 'Too many failed logins. Try again in ' . intdiv(LOGIN_FAIL_WINDOW, 60) . ' minutes.';
    } else {
      $st = $pdo->prepare('SELECT id, pass_hash FROM players WHERE lower(username) = lower(?)');
      $st->execute([$username]);
      $row = $st->fetch();
      if ($row && password_verify($password, $row['pass_hash'])) {
        login_user((int)$row['id']);
        header('Location: index.php'); exit;
      }
      $pdo->prepare('INSERT INTO login_failures (username_lc, ip) VALUES (?, ?)')->execute([$user_lc, client_ip()]);
      $error = 'Invalid username or password.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>H4G :: <?= $mode === 'register' ? 'REGISTER' : 'LOGIN' ?></title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body>
  <canvas id="matrix"></canvas>
  <div class="scanline"></div>

  <div class="hud-top">
    <div class="logo">H4G_CTF<span class="blink">_</span></div>
  </div>

  <div class="auth-wrap">
    <form class="auth-box" method="post" action="login.php<?= $mode === 'register' ? '?mode=register' : '' ?>" autocomplete="off">
      <h2><?= $mode === 'register' ? '// CREATE ACCOUNT //' : '// ACCESS TERMINAL //' ?></h2>
      <input type="hidden" name="action" value="<?= $mode ?>"/>
      <?= csrf_field() ?>

      <label for="username">USERNAME</label>
      <input id="username" name="username" type="text" maxlength="20" required autofocus value="<?= h($_POST['username'] ?? '') ?>"/>

      <label for="password">PASSWORD</label>
      <input id="password" name="password" type="password" required/>

      <?php if ($mode === 'register'): ?>
        <label for="confirm">CONFIRM PASSWORD</label>
        <input id="confirm" name="confirm" type="password" required/>
      <?php endif; ?>

      <?php if ($error): ?><div class="auth-err">[ ERROR ] <?= h($error) ?></div><?php endif; ?>

      <button type="submit"><?= $mode === 'register' ? 'REGISTER' : 'LOGIN' ?></button>

      <div class="auth-switch">
        <?php if ($mode === 'register'): ?>
          Already have an account? <a href="login.php">Log in</a>
        <?php else: ?>
          New here? <a href="login.php?mode=register">Create an account</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <script src="matrix.js"></script>
</body>
</html>