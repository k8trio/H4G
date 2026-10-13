<?php
// Scoring config + challenge loading. Tune the constants here before the event.

const FULL_SCORE_TEAMS  = 3;   // first N teams on a challenge get full points
const DECAY_STEP_PCT    = 10;  // after that, lose ceil(P*10%) per place
const FLOOR_PCT         = 50;  // never below floor(P*50%)
const FIRST_BLOOD_BONUS = 50;  // flat bonus, paid once for the whole event
const MAX_TEAM_SIZE     = 4;

const WRONG_FLAG_LIMIT   = 5;   // wrong flags per player...
const WRONG_FLAG_WINDOW  = 60;  // ...per this many seconds
const BAD_INVITE_LIMIT   = 10;  // invalid invite codes per player...
const BAD_INVITE_WINDOW  = 600; // ...per this many seconds
const LOGIN_FAIL_LIMIT   = 10;  // failed logins per username + IP...
const LOGIN_FAIL_WINDOW  = 900; // ...per this many seconds

// Returns [points, base]: the decayed points for the n-th team to solve, and the original base P.
// Integer math only: in floats, ceil(30 * 0.10) is 4, not 3.
function award_for(int $base, int $n): array {
  if ($n <= FULL_SCORE_TEAMS) return [$base, $base];
  $step  = intdiv($base * DECAY_STEP_PCT + 99, 100); // ceil(P * 10%)
  $floor = intdiv($base * FLOOR_PCT, 100);           // floor(P * 50%)
  return [max($floor, $base - $step * ($n - FULL_SCORE_TEAMS)), $base];
}

// What the next team to solve would earn (First Blood bonus excluded).
function current_value(int $base, int $solved_count): int {
  return award_for($base, $solved_count + 1)[0];
}

// Loads _problems.php once. Problem keys must be unique across categories because solves store only the key.
function load_problems(): array {
  static $all = null;
  if ($all !== null) return $all;
  $all = require __DIR__ . '/_problems.php';
  $seen = [];
  foreach ($all as $cat => $problems) {
    foreach ($problems as $key => $p) {
      if (isset($seen[$key])) throw new RuntimeException("Duplicate problem key '$key' in '$cat' and '{$seen[$key]}'.");
      $seen[$key] = $cat;
    }
  }
  return $all;
}

// Finds a problem by key in any category: [cat, problem] or null.
function find_problem(string $key): ?array {
  foreach (load_problems() as $cat => $problems) {
    if (isset($problems[$key])) return [$cat, $problems[$key]];
  }
  return null;
}
