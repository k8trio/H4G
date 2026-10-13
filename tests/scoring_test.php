<?php
// Unit test for the scoring formula. No database needed: php tests/scoring_test.php
require __DIR__ . '/../api/_scoring.php';

$fails = 0;
function expect($label, $got, $want) {
  global $fails;
  if ($got === $want) { echo "  [PASS] $label = $got\n"; return; }
  echo "  [FAIL] $label = $got, expected $want\n"; $fails++;
}

// PRD section 3.2 example: P = 30 (step 3, floor 15).
$want = [1 => 30, 2 => 30, 3 => 30, 4 => 27, 5 => 24, 6 => 21, 7 => 18, 8 => 15, 9 => 15, 50 => 15];
foreach ($want as $n => $pts) expect("P=30 n=$n", award_for(30, $n)[0], $pts);

// Small and odd bases: step = ceil(10%), floor = floor(50%).
expect('P=5 n=4',    award_for(5, 4)[0], 4);
expect('P=5 n=6',    award_for(5, 6)[0], 2);
expect('P=5 n=99',   award_for(5, 99)[0], 2);
expect('P=15 n=4',   award_for(15, 4)[0], 13);   // step ceil(1.5) = 2
expect('P=15 n=9',   award_for(15, 9)[0], 7);    // floor(7.5) = 7
expect('P=100 n=4',  award_for(100, 4)[0], 90);
expect('P=100 n=20', award_for(100, 20)[0], 50);
expect('P=35 n=5',   award_for(35, 5)[0], 27);   // step ceil(3.5) = 4
expect('current_value(30, 0)', current_value(30, 0), 30);
expect('current_value(30, 3)', current_value(30, 3), 27);

// Every challenge in _problems.php: monotonic, never below the floor, keys unique (load_problems throws otherwise).
foreach (load_problems() as $cat => $problems) {
  foreach ($problems as $key => $p) {
    $prev = PHP_INT_MAX;
    for ($n = 1; $n <= 30; $n++) {
      $v = award_for((int)$p['points'], $n)[0];
      if ($v > $prev || $v < intdiv((int)$p['points'] * FLOOR_PCT, 100)) { echo "  [FAIL] $key n=$n -> $v\n"; $fails++; }
      $prev = $v;
    }
  }
}
echo "  [PASS] all challenges decay monotonically and respect the floor\n";

echo $fails ? "$fails FAILED\n" : "ALL SCORING TESTS PASSED\n";
exit($fails ? 1 : 0);
