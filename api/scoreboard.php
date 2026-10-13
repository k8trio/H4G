<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_scoring.php';
$user = require_login();

// Tie-break: equal scores rank by who reached that score first (earliest last solve), then by name.
$teams = db()->query("SELECT t.id, t.name, t.points, COUNT(s.problem) AS solves,
                             MAX(s.solved_at) AS last_solve,
                             to_char(MAX(s.solved_at) AT TIME ZONE 'Asia/Manila', 'Mon DD HH24:MI:SS') AS last_solve_txt
                      FROM teams t LEFT JOIN solves s ON s.team_id = t.id
                      GROUP BY t.id
                      ORDER BY t.points DESC, MAX(s.solved_at) ASC NULLS LAST, lower(t.name) ASC, t.id ASC")->fetchAll();
$fb = event_first_blood();
$fb_problem = $fb ? find_problem($fb['problem']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>H4G :: SCOREBOARD</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body>
  <canvas id="matrix"></canvas>
  <div class="scanline"></div>

  <div class="hud-top">
    <a href="index.php" class="back-btn">← BACK</a>
    <div class="logo">SCOREBOARD</div>
    <?php if ($user['team_id'] !== null): ?>
      <?= hud_stats($user) ?>
    <?php else: ?>
      <a href="team-setup.php" class="back-btn">JOIN A TEAM</a>
    <?php endif; ?>
  </div>

  <main>
    <div class="terminal-header">
      <p class="glitch" data-text="// TEAM RANKINGS //">// TEAM RANKINGS //</p>
      <?php if ($fb): ?>
        <p class="fb-status">🩸 FIRST BLOOD: <?= h($fb['team_name']) ?> ON <?= h($fb_problem ? $fb_problem[1]['title'] : $fb['problem']) ?> (+<?= (int)$fb['awarded'] ?>)</p>
      <?php else: ?>
        <p class="fb-status">FIRST BLOOD STILL AVAILABLE: +<?= FIRST_BLOOD_BONUS ?> PTS</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <?php if (!$teams): ?>
        <p class="sub">No teams yet.</p>
      <?php else: ?>
        <div class="table-scroll">
          <table class="score-table">
            <thead>
              <tr><th>#</th><th>TEAM</th><th class="num">SCORE</th><th class="num">SOLVES</th><th>LAST SOLVE (PHT)</th><th>FIRST BLOOD</th></tr>
            </thead>
            <tbody>
              <?php foreach ($teams as $i => $t): ?>
                <tr class="<?= (int)$t['id'] === (int)$user['team_id'] ? 'mine' : '' ?>">
                  <td><?= $i + 1 ?></td>
                  <td><?= h($t['name']) ?></td>
                  <td class="num"><?= (int)$t['points'] ?></td>
                  <td class="num"><?= (int)$t['solves'] ?></td>
                  <td><?= h($t['last_solve_txt'] ?? '-') ?></td>
                  <td><?= ($fb && (int)$fb['team_id'] === (int)$t['id']) ? '<span class="fb-badge">🩸 FIRST BLOOD</span>' : '' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <div class="footer-bar">[ SCOREBOARD ] &nbsp;|&nbsp; <?= count($teams) ?> TEAMS</div>

  <script src="matrix.js"></script>
</body>
</html>
