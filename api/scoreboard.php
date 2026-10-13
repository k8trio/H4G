<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_scoring.php';
$user = require_login();
$pdo = db();

// Tie-break: equal scores rank by who reached that score first (earliest last solve), then by name.
// Solo players have their own one-member team; they are ranked with the individuals, not here.
$teams = $pdo->query("SELECT t.id, t.name, t.points, COUNT(s.problem) AS solves,
                             to_char(MAX(s.solved_at) AT TIME ZONE 'Asia/Manila', 'Mon DD HH24:MI:SS') AS last_solve_txt
                      FROM teams t LEFT JOIN solves s ON s.team_id = t.id
                      WHERE NOT t.is_solo
                      GROUP BY t.id
                      ORDER BY t.points DESC, MAX(s.solved_at) ASC NULLS LAST, lower(t.name) ASC, t.id ASC")->fetchAll();

// Every player, scored by the flags they personally captured (First Blood bonus goes to the solver).
$players = $pdo->query("SELECT p.id, p.username, t.name AS team_name, t.is_solo,
                               COALESCE(SUM(s.points_awarded), 0) AS points, COUNT(s.problem) AS solves,
                               to_char(MAX(s.solved_at) AT TIME ZONE 'Asia/Manila', 'Mon DD HH24:MI:SS') AS last_solve_txt
                        FROM players p
                        JOIN teams t ON t.id = p.team_id
                        LEFT JOIN solves s ON s.solved_by = p.id
                        GROUP BY p.id, t.id
                        ORDER BY COALESCE(SUM(s.points_awarded), 0) DESC, MAX(s.solved_at) ASC NULLS LAST, lower(p.username) ASC, p.id ASC")->fetchAll();

$log = $pdo->query("SELECT s.problem, s.solved_by, p.username, t.name AS team_name, t.is_solo, s.points_awarded, s.solve_rank, s.first_blood,
                           to_char(s.solved_at AT TIME ZONE 'Asia/Manila', 'Mon DD HH24:MI:SS') AS solved_txt
                    FROM solves s
                    JOIN teams t ON t.id = s.team_id
                    LEFT JOIN players p ON p.id = s.solved_by
                    ORDER BY s.solved_at DESC
                    LIMIT 100")->fetchAll();

$fb = event_first_blood();
$fb_problem = $fb ? find_problem($fb['problem']) : null;
// "player / TEAM" (just "player" for a solo player); the stored team name if the player is gone.
$fb_by = '';
if ($fb) {
  $fb_player = $pdo->query('SELECT p.username FROM event_first_blood f JOIN players p ON p.id = f.player_id WHERE f.id = 1')->fetchColumn();
  $solo = str_ends_with($fb['team_name'], ' [SOLO]');
  $fb_by = $fb_player ? $fb_player . ($solo ? '' : ' / ' . $fb['team_name']) : $fb['team_name'];
}

function team_label(array $r): string {
  return $r['is_solo'] ? 'SOLO' : h($r['team_name']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>PSU :: SCOREBOARD</title>
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
      <p class="glitch" data-text="// RANKINGS //">// RANKINGS //</p>
      <?php if ($fb): ?>
        <p class="fb-status">🩸 FIRST BLOOD: <?= h($fb_by) ?> ON <?= h($fb_problem ? $fb_problem[1]['title'] : $fb['problem']) ?> (+<?= (int)$fb['awarded'] ?>)</p>
      <?php else: ?>
        <p class="fb-status">FIRST BLOOD STILL AVAILABLE: +<?= FIRST_BLOOD_BONUS ?> PTS</p>
      <?php endif; ?>
      <div class="nav-links">
        <a href="#teams">[ TEAMS ]</a>
        <a href="#individuals">[ INDIVIDUALS ]</a>
        <a href="#solves">[ SOLVE LOG ]</a>
      </div>
    </div>

    <div class="panel" id="teams">
      <div class="panel-title">// TEAM RANKINGS //</div>
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

    <div class="panel" id="individuals">
      <div class="panel-title">// INDIVIDUAL RANKINGS //</div>
      <p class="sub">Points from the flags each player captured personally. Solo players and team members alike.</p>
      <?php if (!$players): ?>
        <p class="sub">No players yet.</p>
      <?php else: ?>
        <div class="table-scroll">
          <table class="score-table">
            <thead>
              <tr><th>#</th><th>PLAYER</th><th>TEAM</th><th class="num">SCORE</th><th class="num">SOLVES</th><th>LAST SOLVE (PHT)</th></tr>
            </thead>
            <tbody>
              <?php foreach ($players as $i => $p): ?>
                <tr class="<?= (int)$p['id'] === (int)$user['id'] ? 'mine' : '' ?>">
                  <td><?= $i + 1 ?></td>
                  <td><?= h($p['username']) ?></td>
                  <td><?= team_label($p) ?></td>
                  <td class="num"><?= (int)$p['points'] ?></td>
                  <td class="num"><?= (int)$p['solves'] ?></td>
                  <td><?= h($p['last_solve_txt'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel" id="solves">
      <div class="panel-title">// SOLVE LOG //</div>
      <?php if (!$log): ?>
        <p class="sub">No flags captured yet.</p>
      <?php else: ?>
        <div class="table-scroll">
          <table class="score-table">
            <thead>
              <tr><th>TIME (PHT)</th><th>CHALLENGE</th><th>SOLVED BY</th><th>TEAM</th><th class="num">SOLVE #</th><th class="num">PTS</th></tr>
            </thead>
            <tbody>
              <?php foreach ($log as $s): $prob = find_problem($s['problem']); ?>
                <tr class="<?= (int)$s['solved_by'] === (int)$user['id'] ? 'mine' : '' ?>">
                  <td><?= h($s['solved_txt']) ?></td>
                  <td><?= h($prob ? $prob[1]['title'] : $s['problem']) ?></td>
                  <td><?= h($s['username'] ?? '[ deleted ]') ?></td>
                  <td><?= team_label($s) ?></td>
                  <td class="num"><?= (int)$s['solve_rank'] ?></td>
                  <td class="num"><?= (int)$s['points_awarded'] ?><?= $s['first_blood'] ? ' 🩸' : '' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <div class="footer-bar">[ SCOREBOARD ] &nbsp;|&nbsp; <?= count($teams) ?> TEAMS &nbsp;|&nbsp; <?= count($players) ?> PLAYERS</div>

  <script src="matrix.js"></script>
</body>
</html>
