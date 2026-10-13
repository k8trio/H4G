<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_scoring.php';

$user = require_login();
if ($user['team_id'] !== null) { header('Location: team.php'); exit; }
$csrf = csrf_token();

const INVITE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

// XXXX-XXXX from a CSPRNG. random_int (backed by random_bytes) avoids modulo bias.
function new_invite_code(): string {
  $c = '';
  for ($i = 0; $i < 8; $i++) {
    if ($i === 4) $c .= '-';
    $c .= INVITE_ALPHABET[random_int(0, strlen(INVITE_ALPHABET) - 1)];
  }
  return $c;
}

function joined(): void {
  header('Location: team.php', true, 303);
  exit;
}

$action     = '';
$create_err = '';
$join_err   = '';
$name_in    = '';
$code_in    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = ($_POST['action'] ?? '') === 'join' ? 'join' : 'create';
  $pdo = db();

  if (!csrf_valid()) {
    if ($action === 'join') $join_err = '[ ERROR ] Session expired. Please try again.';
    else $create_err = '[ ERROR ] Session expired. Please try again.';

  } elseif ($action === 'create') {
    $name_in = is_string($_POST['team_name'] ?? null) ? $_POST['team_name'] : '';
    $name = preg_replace('/\s+/', ' ', trim($name_in));
    $len = strlen($name);

    if ($len < 3 || $len > 30 || !preg_match('/^[A-Za-z0-9_ \-]+$/', $name)) {
      $create_err = '[ ERROR ] Team name: 3-30 letters, numbers, spaces, _ or -.';
    } else {
      // Retry only on an invite-code collision; a name collision is reported to the user.
      for ($try = 0; $try < 5 && !$create_err; $try++) {
        try {
          $pdo->beginTransaction();
          $st = $pdo->prepare('INSERT INTO teams (name, invite_code) VALUES (?, ?) RETURNING id');
          $st->execute([$name, new_invite_code()]);
          $team_id = (int)$st->fetchColumn();

          $st = $pdo->prepare('UPDATE players SET team_id = ? WHERE id = ? AND team_id IS NULL');
          $st->execute([$team_id, $user['id']]);
          if ($st->rowCount() !== 1) { $pdo->rollBack(); joined(); } // already joined a team in another tab

          $pdo->commit();
          joined();
        } catch (PDOException $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          if ($e->getCode() !== '23505') { error_log('team create failed: ' . $e->getMessage()); $create_err = '[ ERROR ] Something went wrong. Try again.'; }
          elseif (str_contains($e->getMessage(), 'teams_name_lc')) $create_err = '[ ERROR ] That team name is already taken.';
          // otherwise: invite code collision, loop and retry with a new code
        }
      }
      if (!$create_err) $create_err = '[ ERROR ] Could not create the team. Try again.';
    }

  } else {
    $code_in = is_string($_POST['invite_code'] ?? null) ? $_POST['invite_code'] : '';
    $code = strtoupper(preg_replace('/\s+/', '', $code_in));
    if (preg_match('/^[A-Z0-9]{8}$/', $code)) $code = substr($code, 0, 4) . '-' . substr($code, 4);

    $st = $pdo->prepare('SELECT COUNT(*) FROM team_join_attempts WHERE player_id = ? AND created_at > now() - make_interval(secs => ?)');
    $st->execute([$user['id'], BAD_INVITE_WINDOW]);

    if ((int)$st->fetchColumn() >= BAD_INVITE_LIMIT) {
      $join_err = '[ ERROR ] Too many invalid codes. Try again in a few minutes.';
    } else {
      $bad_code = false;
      try {
        $pdo->beginTransaction();
        // Lock the team row: concurrent joins for the last slot queue here, and each one
        // counts members only after the previous join has committed.
        $st = $pdo->prepare('SELECT id FROM teams WHERE invite_code = ? FOR UPDATE');
        $st->execute([$code]);
        $team_id = $st->fetchColumn();

        if ($team_id === false) {
          $pdo->rollBack();
          $bad_code = true;
        } else {
          $st = $pdo->prepare('SELECT COUNT(*) FROM players WHERE team_id = ?');
          $st->execute([$team_id]);
          if ((int)$st->fetchColumn() >= MAX_TEAM_SIZE) {
            $pdo->rollBack();
            $join_err = '[ ERROR ] TEAM ALREADY HAS ' . MAX_TEAM_SIZE . ' MEMBERS.';
          } else {
            $st = $pdo->prepare('UPDATE players SET team_id = ? WHERE id = ? AND team_id IS NULL');
            $st->execute([$team_id, $user['id']]);
            $pdo->commit();
            joined(); // also covers "already in a team" (0 rows): team.php shows their real team
          }
        }
      } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('team join failed: ' . $e->getMessage());
        $join_err = '[ ERROR ] Something went wrong. Try again.';
      }

      if ($bad_code) {
        $pdo->prepare('INSERT INTO team_join_attempts (player_id) VALUES (?)')->execute([$user['id']]);
        $join_err = '[ ERROR ] INVALID INVITE CODE.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>H4G :: TEAM SETUP</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body>
  <canvas id="matrix"></canvas>
  <div class="scanline"></div>

  <div class="hud-top">
    <div class="logo">H4G_CTF<span class="blink">_</span></div>
    <div class="hud-stats">
      <div class="hud-stat"><span class="pts-label">LOGGED IN</span><span class="hud-stat-val"><?= h($user['username']) ?></span></div>
      <a href="logout.php" class="back-btn">LOGOUT</a>
    </div>
  </div>

  <div class="auth-wrap">
    <div style="width:100%;text-align:center;">
      <p class="glitch" data-text="YOU ARE NOT CURRENTLY IN A TEAM">YOU ARE NOT CURRENTLY IN A TEAM</p>
      <p class="sub">Create a team (1 to <?= MAX_TEAM_SIZE ?> members, solo is fine) or join one with an invite code.</p>
    </div>

    <form class="auth-box" method="post" action="team-setup.php" autocomplete="off">
      <h2>// CREATE TEAM //</h2>
      <input type="hidden" name="action" value="create"/>
      <?= csrf_field() ?>
      <label for="team_name">TEAM NAME</label>
      <input id="team_name" name="team_name" type="text" minlength="3" maxlength="30" required value="<?= h($name_in) ?>"/>
      <div class="auth-hint">3-30 characters: letters, numbers, spaces, _ or -. You will get an invite code for your teammates.</div>
      <?php if ($create_err): ?><div class="auth-err"><?= h($create_err) ?></div><?php endif; ?>
      <button type="submit">CREATE TEAM</button>
    </form>

    <form class="auth-box" method="post" action="team-setup.php" autocomplete="off">
      <h2>// JOIN TEAM //</h2>
      <input type="hidden" name="action" value="join"/>
      <?= csrf_field() ?>
      <label for="invite_code">INVITE CODE</label>
      <input id="invite_code" name="invite_code" type="text" maxlength="20" placeholder="XXXX-XXXX" required value="<?= h($code_in) ?>" style="text-transform:uppercase;"/>
      <div class="auth-hint">Ask your team captain for the code shown in their team lobby.</div>
      <?php if ($join_err): ?><div class="auth-err"><?= h($join_err) ?></div><?php endif; ?>
      <button type="submit">JOIN TEAM</button>
    </form>
  </div>

  <script src="matrix.js"></script>
</body>
</html>
