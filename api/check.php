<?php
require_once __DIR__ . '/_auth.php';
header('Content-Type: application/json');

$user = require_login(true);

$all_problems = [
  'cryptography' => [
    '13 caesar'              => ['answer' => 'ctf{this_1s_th3_fl4g}', 'points' => 15],
    'apol'                   => ['answer' => 'ctf{wozniak}',           'points' => 15],
    'esrever'                => ['answer' => 'ctf{an0_d4w}',           'points' => 25],
    'combination mo ang limit' => ['answer' => 'ctf{w0w_4nG_g4L!nG}', 'points' => 35],
  ],
  'forensics' => [],
  'osint'     => [],
];

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

$pdo = db();
$pdo->beginTransaction();

// The primary key (player_id, problem) makes sure each flag only counts once per player.
$st = $pdo->prepare('INSERT INTO solves (player_id, problem) VALUES (?, ?) ON CONFLICT DO NOTHING');
$st->execute([$user['id'], $problem]);

if ($st->rowCount() === 0) {
  $pdo->rollBack();
  echo json_encode(['correct' => false, 'message' => '[ WARN ] Already solved. No duplicate points.']);
  exit;
}

$st = $pdo->prepare('UPDATE players SET points = points + ? WHERE id = ? RETURNING points');
$st->execute([$pts, $user['id']]);
$total = (int)$st->fetchColumn();
$pdo->commit();

echo json_encode([
  'correct' => true,
  'message' => '[ ACCESS GRANTED ] +' . $pts . ' pts. Flag captured!',
  'total'   => $total,
]);