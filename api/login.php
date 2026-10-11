<?php
require_once __DIR__ . '/_auth.php';

if (current_user()) { header('Location: index.php'); exit; }

$mode  = (($_GET['mode'] ?? '') === 'register') ? 'register' : 'login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mode     = ($_POST['action'] ?? '') === 'register' ? 'register' : 'login';
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $pdo      = db();

  if ($mode === 'register') {
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
    $st = $pdo->prepare('SELECT id, pass_hash FROM players WHERE lower(username) = lower(?)');
    $st->execute([$username]);
    $row = $st->fetch();
    if ($row && password_verify($password, $row['pass_hash'])) {
      login_user((int)$row['id']);
      header('Location: index.php'); exit;
    }
    $error = 'Invalid username or password.';
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
  <style>
    .auth-wrap { position: relative; z-index: 2; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 90px 16px 60px; }
    .auth-box { position: relative; width: 100%; max-width: 420px; background: var(--panel); border: 1px solid var(--border); padding: 32px 28px; box-shadow: 0 0 25px var(--green-glow); }
    .auth-box h2 { font-family: 'Orbitron', sans-serif; font-size: 1.1rem; letter-spacing: 3px; margin-bottom: 22px; text-align: center; }
    .auth-box label { display: block; font-size: .8rem; margin: 14px 0 6px; color: var(--green-dim); }
    .auth-box input { width: 100%; background: #000; border: 1px solid var(--green-dim); color: var(--green); font-family: inherit; font-size: 1rem; padding: 10px 12px; outline: none; }
    .auth-box input:focus { border-color: var(--green); box-shadow: 0 0 8px var(--green-glow); }
    .auth-box button { width: 100%; margin-top: 24px; padding: 11px; background: transparent; border: 1px solid var(--green); color: var(--green); font-family: 'Orbitron', sans-serif; letter-spacing: 2px; cursor: pointer; }
    .auth-box button:hover { background: var(--green); color: #000; }
    .auth-err { margin-top: 16px; color: var(--red); text-shadow: 0 0 8px rgba(255,34,34,.6); font-size: .85rem; text-align: center; }
    .auth-switch { margin-top: 20px; text-align: center; font-size: .85rem; }
    .auth-switch a { color: var(--green); }
  </style>
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

      <label for="username">USERNAME</label>
      <input id="username" name="username" type="text" maxlength="20" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"/>

      <label for="password">PASSWORD</label>
      <input id="password" name="password" type="password" required/>

      <?php if ($mode === 'register'): ?>
        <label for="confirm">CONFIRM PASSWORD</label>
        <input id="confirm" name="confirm" type="password" required/>
      <?php endif; ?>

      <?php if ($error): ?><div class="auth-err">[ ERROR ] <?= htmlspecialchars($error) ?></div><?php endif; ?>

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