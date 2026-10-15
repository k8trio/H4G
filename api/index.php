<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_scoring.php';
$user = require_team();
$solved_count = count(solved_map((int)$user['team_id']));
$P = load_problems();
$fb = event_first_blood();
$fb_problem = $fb ? find_problem($fb['problem']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>PSU :: CTF Platform</title>
  <link rel="stylesheet" href="styles.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet"/>
</head>
<body>
  <canvas id="matrix"></canvas>

  <div class="hud-top">
    <div class="logo"><img class="logo-img" src="/assets/psu_logo_96.gif" alt="Pangasinan State University logo" width="44" height="44"/>PSU_CTF<span class="blink">_</span></div>
    <?= hud_stats($user) ?>
  </div>

  <div class="scanline"></div>

  <main class="home">
    <div class="terminal-header">
      <p class="glitch" data-text="// SELECT YOUR DOMAIN //">// SELECT YOUR DOMAIN //</p>
      <p class="sub">Choose a category to begin your infiltration.</p>
      <div class="nav-links">
        <a href="team.php">[ TEAM LOBBY ]</a>
        <a href="scoreboard.php">[ SCOREBOARD ]</a>
        <a href="logout.php">[ LOGOUT ]</a>
      </div>
      <?php if ($fb): ?>
        <p class="fb-status">FIRST BLOOD CLAIMED BY <?= h($fb['team_name']) ?> ON <?= h($fb_problem ? $fb_problem[1]['title'] : $fb['problem']) ?></p>
      <?php else: ?>
        <p class="fb-status">FIRST BLOOD STILL AVAILABLE: +<?= FIRST_BLOOD_BONUS ?> PTS TO THE FIRST SOLVE OF THE EVENT</p>
      <?php endif; ?>
    </div>

    <div class="category-grid">
      <a href="category.php?cat=cryptography" class="cat-card crypto">

        <div class="cat-name">CRYPTOGRAPHY</div>
        <div class="cat-desc">Ciphers. Codes. Secrets encoded in plain sight.</div>
        <div class="cat-count"><?= count($P['cryptography']) ?> Challenges</div>
        <div class="corner tl"></div><div class="corner tr"></div>
        <div class="corner bl"></div><div class="corner br"></div>
      </a>
      <a href="category.php?cat=forensics" class="cat-card forensics">

        <div class="cat-name">FORENSICS</div>
        <div class="cat-desc">Dig into files. Recover the hidden. Leave no trace.</div>
        <div class="cat-count"><?= count($P['forensics']) ?> Challenges</div>
        <div class="corner tl"></div><div class="corner tr"></div>
        <div class="corner bl"></div><div class="corner br"></div>
      </a>
      <a href="category.php?cat=osint" class="cat-card osint">

        <div class="cat-name">OSINT</div>
        <div class="cat-desc">Open sources. Public data. Find what they left behind.</div>
        <div class="cat-count"><?= count($P['osint']) ?> Challenges</div>
        <div class="corner tl"></div><div class="corner tr"></div>
        <div class="corner bl"></div><div class="corner br"></div>
      </a>

      <a href="category.php?cat=steganography" class="cat-card stego">

        <div class="cat-name">STEGANOGRAPHY</div>
        <div class="cat-desc">Messages hidden inside images, audio, and more.</div>
        <div class="cat-count"><?= count($P['steganography']) ?> Challenges</div>
        <div class="corner tl"></div><div class="corner tr"></div>
        <div class="corner bl"></div><div class="corner br"></div>
      </a>
    </div>
  </main>

  <div class="footer-bar">[ SYSTEM ONLINE ] &nbsp;|&nbsp; <?= $user['is_solo'] ? 'SOLO ' . h($user['username']) : 'TEAM ' . h($user['team_name']) ?> &nbsp;|&nbsp; <?= $solved_count ?> FLAGS CAPTURED &nbsp;|&nbsp; <?= (int)$user['team_points'] ?> PTS</div>

  <script src="matrix.js"></script>
</body>
</html>