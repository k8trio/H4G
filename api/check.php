<?php
require_once __DIR__ . '/_auth.php';
header('Content-Type: application/json');

$user = require_login(true);

$all_problems = require __DIR__ . '/_problems.php';

$cat     = strtolower(trim($_POST['cat']    ?? ''));
$problem = strtolower(trim($_POST['problem'] ?? ''));
$flag    = trim($_POST['flag'] ?? '');

if (!isset($all_problems[$cat][$problem])) {
  echo json_encode(['correct' => false, 'message' => '[ ERROR ] Unknown problem.']);
  exit;
}

$correct_answer = $all_problems[$cat][$problem]['answer'];
$pts            = $all_problems[$cat][$problem]['points'];

if (!hash_equals($correct_answer, $flag)) {
  echo json_encode(['correct' => false, 'message' => '[ ACCESS DENIED ] Wrong flag. Try again.']);
  exit;
}

// One atomic query: record the solve (once per player) and add the points only if it was new.
$st = db()->prepare(
  'WITH ins AS (
     INSERT INTO solves (player_id, problem) VALUES (?, ?) ON CONFLICT DO NOTHING RETURNING 1
   )
   UPDATE players SET points = points + ? WHERE id = ? AND EXISTS (SELECT 1 FROM ins) RETURNING points'
);
$st->execute([$user['id'], $problem, $pts, $user['id']]);
$total = $st->fetchColumn();

if ($total === false) {
  echo json_encode(['correct' => false, 'message' => '[ WARN ] Already solved. No duplicate points.']);
  exit;
}

echo json_encode([
  'correct' => true,
  'message' => '[ ACCESS GRANTED ] +' . $pts . ' pts. Flag captured!',
  'total'   => (int)$total,
]);