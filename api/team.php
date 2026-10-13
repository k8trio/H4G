<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_scoring.php';
$user = require_team();
$pdo = db();

$st = $pdo->prepare('SELECT name, points, invite_code FROM teams WHERE id = ?');
$st->execute([$user['team_id']]);
$team = $st->fetch();

$st = $pdo->prepare('SELECT id, username FROM players WHERE team_id = ? ORDER BY id');
$st->execute([$user['team_id']]);
$members = $st->fetchAll();

$solved = solved_map((int)$user['team_id']);
$fb = event_first_blood();
$full = count($members) >= MAX_TEAM_SIZE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>PSU :: TEAM LOBBY</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body>
  <canvas id="matrix"></canvas>
  <div class="scanline"></div>

  <div class="hud-top">
    <a href="index.php" class="back-btn">← CHALLENGES</a>
    <div class="logo">TEAM LOBBY</div>
    <?= hud_stats($user) ?>
  </div>

  <main>
    <div class="terminal-header">
      <p class="glitch" data-text="<?= h(strtoupper($team['name'])) ?>"><?= h(strtoupper($team['name'])) ?></p>
      <p class="sub"><?= count($members) ?> / <?= MAX_TEAM_SIZE ?> MEMBERS</p>
      <div class="nav-links">
        <a href="index.php">[ CHALLENGES ]</a>
        <a href="scoreboard.php">[ SCOREBOARD ]</a>
        <a href="logout.php">[ LOGOUT ]</a>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">// MEMBERS //</div>
      <ul class="slot-list">
        <?php for ($i = 0; $i < MAX_TEAM_SIZE; $i++): $m = $members[$i] ?? null; ?>
          <li class="<?= $m ? '' : 'empty' ?>">
            <span class="slot-n">[<?= $i + 1 ?>]</span>
            <?= $m ? h($m['username']) . ((int)$m['id'] === (int)$user['id'] ? ' (you)' : '') : '[ EMPTY ]' ?>
          </li>
        <?php endfor; ?>
      </ul>
      <div class="team-stats">
        <div><span class="pts-label">TEAM SCORE</span><div class="pts-value"><?= (int)$team['points'] ?></div></div>
        <div><span class="pts-label">FLAGS CAPTURED</span><div class="pts-value"><?= count($solved) ?></div></div>
        <?php if ($fb && (int)$fb['team_id'] === (int)$user['team_id']): ?>
          <div><span class="pts-label">EVENT</span><div class="fb-badge">🩸 FIRST BLOOD</div></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">// INVITE CODE //</div>
      <?php if ($full): ?>
        <p class="sub">Team is full. The invite code is hidden.</p>
      <?php else: ?>
        <div class="invite-code" id="invite-code"><?= h($team['invite_code']) ?></div>
        <p class="sub">Share this code with up to <?= MAX_TEAM_SIZE - count($members) ?> more teammate<?= MAX_TEAM_SIZE - count($members) === 1 ? '' : 's' ?>. They join from their own account.</p>
      <?php endif; ?>
    </div>
  </main>

  <div class="footer-bar">[ TEAM <?= h($team['name']) ?> ] &nbsp;|&nbsp; <?= count($solved) ?> FLAGS CAPTURED &nbsp;|&nbsp; <?= (int)$team['points'] ?> PTS</div>

  <script src="matrix.js"></script>
</body>
</html>
