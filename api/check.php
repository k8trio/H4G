<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_scoring.php';
header('Content-Type: application/json');

function respond(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['correct' => false, 'message' => '[ ERROR ] POST only.'], 405);

// Team id, name and score always come from the DB user record, never from the request.
$user = require_team(true);
if (!csrf_valid()) respond(['correct' => false, 'message' => '[ ERROR ] Session expired. Refresh the page and try again.'], 403);

$all_problems = load_problems();

$cat     = strtolower(trim((string)($_POST['cat']     ?? '')));
$problem = strtolower(trim((string)($_POST['problem'] ?? '')));
$flag    = trim((string)($_POST['flag'] ?? ''));

if (!isset($all_problems[$cat][$problem])) respond(['correct' => false, 'message' => '[ ERROR ] Unknown problem.']);

$pdo     = db();
$team_id = (int)$user['team_id'];
$base    = (int)$all_problems[$cat][$problem]['points'];

// Rate limit: too many wrong flags from this player recently blocks every submission for a moment.
$st = $pdo->prepare('SELECT COUNT(*) FROM submissions
                     WHERE player_id = ? AND correct = false AND created_at > now() - make_interval(secs => ?)');
$st->execute([$user['id'], WRONG_FLAG_WINDOW]);
if ((int)$st->fetchColumn() >= WRONG_FLAG_LIMIT) {
  respond(['correct' => false, 'message' => '[ RATE LIMITED ] Too many wrong flags. Wait a minute and try again.'], 429);
}

$correct = hash_equals($all_problems[$cat][$problem]['answer'], $flag);

// Audit trail: who tried what and when (the submitted text is never stored).
$pdo->prepare('INSERT INTO submissions (team_id, player_id, problem, correct) VALUES (?, ?, ?, ?)')
    ->execute([$team_id, $user['id'], $problem, $correct ? 'true' : 'false']);

if (!$correct) respond(['correct' => false, 'message' => '[ ACCESS DENIED ] Wrong flag. Try again.']);

try {
  $pdo->beginTransaction();

  // Serialize solves of this challenge so every team gets a distinct solve order.
  $pdo->prepare("SELECT pg_advisory_xact_lock(hashtext('solve:' || ?))")->execute([$problem]);

  $st = $pdo->prepare('SELECT COUNT(*) FROM solves WHERE problem = ?');
  $st->execute([$problem]);
  $rank = (int)$st->fetchColumn() + 1;
  [$points] = award_for($base, $rank);

  $st = $pdo->prepare('INSERT INTO solves (team_id, problem, solved_by, points_awarded, solve_rank)
                       VALUES (?, ?, ?, ?, ?) ON CONFLICT (team_id, problem) DO NOTHING');
  $st->execute([$team_id, $problem, $user['id'], $points, $rank]);

  if ($st->rowCount() !== 1) {
    $st = $pdo->prepare('SELECT points FROM teams WHERE id = ?');
    $st->execute([$team_id]);
    $total = (int)$st->fetchColumn();
    $pdo->commit();
    respond([
      'correct' => true, 'already' => true, 'message' => '[ WARN ] Already solved by your team. +0 pts.',
      'awarded' => 0, 'rank' => null, 'total' => $total, 'first_blood' => false, 'bonus' => 0,
    ]);
  }

  // The event's single First Blood: the one-row table lets exactly one insert ever succeed.
  $st = $pdo->prepare('INSERT INTO event_first_blood (id, team_id, team_name, player_id, problem, awarded)
                       VALUES (1, ?, ?, ?, ?, ?) ON CONFLICT (id) DO NOTHING');
  $st->execute([$team_id, $user['team_name'], $user['id'], $problem, FIRST_BLOOD_BONUS]);
  $first_blood = $st->rowCount() === 1;
  $bonus = $first_blood ? FIRST_BLOOD_BONUS : 0;

  if ($first_blood) {
    $pdo->prepare('UPDATE solves SET points_awarded = points_awarded + ?, first_blood = true WHERE team_id = ? AND problem = ?')
        ->execute([$bonus, $team_id, $problem]);
  }

  $awarded = $points + $bonus;
  $st = $pdo->prepare('UPDATE teams SET points = points + ? WHERE id = ? RETURNING points');
  $st->execute([$awarded, $team_id]);
  $total = (int)$st->fetchColumn();

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('check.php solve failed for team ' . $team_id . ' problem ' . $problem . ': ' . $e->getMessage());
  respond(['correct' => false, 'message' => '[ ERROR ] Could not record the solve. Try again.'], 500);
}

$msg = '[ ACCESS GRANTED ] +' . $points . ' pts (solve #' . $rank . ').';
if ($first_blood) $msg .= ' FIRST BLOOD! +' . $bonus . ' bonus.';

respond([
  'correct'     => true,
  'message'     => $msg . ' Flag captured!',
  'awarded'     => $awarded,
  'rank'        => $rank,
  'total'       => $total,
  'first_blood' => $first_blood,
  'bonus'       => $bonus,
  'team'        => $user['team_name'],
  'challenge'   => $all_problems[$cat][$problem]['title'],
]);
