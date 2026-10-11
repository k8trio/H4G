<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['points'])) { $_SESSION['points'] = 0; $_SESSION['solved'] = []; }

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

if (in_array($problem, $_SESSION['solved'])) {
  echo json_encode(['correct' => false, 'message' => '[ WARN ] Already solved. No duplicate points.']);
  exit;
}

$correct_answer = $all_problems[$cat][$problem]['answer'];
$pts            = $all_problems[$cat][$problem]['points'];

if ($flag === $correct_answer) {
  $_SESSION['points'] += $pts;
  $_SESSION['solved'][] = $problem;
  echo json_encode([
    'correct' => true,
    'message' => '[ ACCESS GRANTED ] +' . $pts . ' pts. Flag captured!',
    'total'   => $_SESSION['points'],
  ]);
} else {
  echo json_encode(['correct' => false, 'message' => '[ ACCESS DENIED ] Wrong flag. Try again.']);
}
