<?php session_start(); if (!isset($_SESSION['points'])) { $_SESSION['points'] = 0; $_SESSION['solved'] = []; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>H4G :: CTF Platform</title>
  <link rel="stylesheet" href="styles.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet"/>
</head>
<body>
  <canvas id="matrix"></canvas>

  <div class="hud-top">
    <div class="logo">H4G_CTF<span class="blink">_</span></div>
    <div class="points-display">
      <span class="pts-label">SCORE</span>
      <span class="pts-value" id="points"><?= $_SESSION['points'] ?></span>
    </div>
  </div>

  <div class="scanline"></div>

  <main class="home">
    <div class="terminal-header">
      <p class="glitch" data-text="// SELECT YOUR DOMAIN //">// SELECT YOUR DOMAIN //</p>
      <p class="sub">Choose a category to begin your infiltration.</p>
    </div>

    <div class="category-grid">
      <a href="category.php?cat=cryptography" class="cat-card crypto">
        <div class="cat-icon">🔐</div>
        <div class="cat-name">CRYPTOGRAPHY</div>
        <div class="cat-desc">Ciphers. Codes. Secrets encoded in plain sight.</div>
        <div class="cat-count">4 Challenges</div>
        <div class="corner tl"></div><div class="corner tr"></div>
        <div class="corner bl"></div><div class="corner br"></div>
      </a>
      <a href="category.php?cat=forensics" class="cat-card forensics">
        <div class="cat-icon">🔍</div>
        <div class="cat-name">FORENSICS</div>
        <div class="cat-desc">Dig into files. Recover the hidden. Leave no trace.</div>
        <div class="cat-count">Coming Soon</div>
        <div class="corner tl"></div><div class="corner tr"></div>
        <div class="corner bl"></div><div class="corner br"></div>
      </a>
      <a href="category.php?cat=osint" class="cat-card osint">
        <div class="cat-icon">🌐</div>
        <div class="cat-name">OSINT</div>
        <div class="cat-desc">Open sources. Public data. Find what they left behind.</div>
        <div class="cat-count">Coming Soon</div>
        <div class="corner tl"></div><div class="corner tr"></div>
        <div class="corner bl"></div><div class="corner br"></div>
      </a>
    </div>
  </main>

  <div class="footer-bar">[ SYSTEM ONLINE ] &nbsp;|&nbsp; <?= count($_SESSION['solved']) ?> FLAGS CAPTURED &nbsp;|&nbsp; <?= $_SESSION['points'] ?> PTS</div>

  <script src="matrix.js"></script>
</body>
</html>
