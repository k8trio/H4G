<?php
session_start();
if (!isset($_SESSION['points'])) { $_SESSION['points'] = 0; $_SESSION['solved'] = []; }

$cat = strtolower($_GET['cat'] ?? '');

$all_problems = [
  'cryptography' => [
    '13 caesar'              => ['title' => '13 Caesar',              'desc' => 'Caesar never thought 13 steps ahead.', 'points' => 15,  'answer' => 'ctf{this_1s_th3_fl4g}'],
    'apol'                   => ['title' => 'apol',                   'desc' => 'A name behind the machine.', 'points' => 15,  'answer' => 'ctf{wozniak}'],
    'esrever'                => ['title' => 'esrever',                'desc' => 'Read between the lines — backwards.', 'points' => 25,  'answer' => 'ctf{an0_d4w}'],
    'combination mo ang limit' => ['title' => 'COMBINATION MO ANG LIMIT', 'desc' => 'The combinations are endless. Or are they?', 'points' => 35,  'answer' => 'ctf{w0w_4nG_g4L!nG}'],
  ],
  'forensics' => [],
  'osint'     => [],
];

$labels = ['cryptography' => 'CRYPTOGRAPHY', 'forensics' => 'FORENSICS', 'osint' => 'OSINT'];

if (!array_key_exists($cat, $all_problems)) {
  header('Location: index.php'); exit;
}

$problems = $all_problems[$cat];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>H4G :: <?= htmlspecialchars(strtoupper($cat)) ?></title>
  <link rel="stylesheet" href="styles.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet"/>
</head>
<body>
  <canvas id="matrix"></canvas>
  <div class="scanline"></div>

  <div class="hud-top">
    <a href="index.php" class="back-btn">← BACK</a>
    <div class="logo"><?= htmlspecialchars($labels[$cat]) ?></div>
    <div class="points-display">
      <span class="pts-label">SCORE</span>
      <span class="pts-value" id="points"><?= $_SESSION['points'] ?></span>
    </div>
  </div>

  <main class="challenges-page">
    <?php if (empty($problems)): ?>
      <div class="coming-soon">
        <p class="glitch" data-text="[ COMING SOON ]">[ COMING SOON ]</p>
        <p class="sub">This sector is still being breached. Check back later.</p>
      </div>
    <?php else: ?>
      <div class="prob-grid">
        <?php foreach ($problems as $key => $p):
          $solved = in_array($key, $_SESSION['solved']);
        ?>
        <div class="prob-card <?= $solved ? 'solved' : '' ?>" onclick="<?= $solved ? '' : "openModal('" . addslashes($key) . "')" ?>">
          <div class="prob-pts"><?= $p['points'] ?> PTS</div>
          <div class="prob-title"><?= htmlspecialchars($p['title']) ?></div>
          <div class="prob-desc"><?= htmlspecialchars($p['desc']) ?></div>
          <?php if ($solved): ?>
            <div class="solved-badge">✔ SOLVED</div>
          <?php else: ?>
            <div class="prob-cta">[ SUBMIT FLAG ]</div>
          <?php endif; ?>
          <div class="corner tl"></div><div class="corner tr"></div>
          <div class="corner bl"></div><div class="corner br"></div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <!-- Modal -->
  <div class="modal-overlay" id="modalOverlay" onclick="closeModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
      <div class="modal-title" id="modalTitle"></div>
      <div class="modal-desc" id="modalDesc"></div>
      <div class="flag-input-wrap">
        <span class="prompt">root@ctf:~$</span>
        <input type="text" id="flagInput" placeholder="ctf{...}" autocomplete="off" spellcheck="false"/>
      </div>
      <div id="modalMsg" class="modal-msg"></div>
      <div class="modal-actions">
        <button onclick="submitFlag()">SUBMIT</button>
        <button class="btn-cancel" onclick="closeModal()">CANCEL</button>
      </div>
      <div class="corner tl"></div><div class="corner tr"></div>
      <div class="corner bl"></div><div class="corner br"></div>
    </div>
  </div>

  <div class="footer-bar">[ <?= htmlspecialchars(strtoupper($cat)) ?> ] &nbsp;|&nbsp; <?= count($_SESSION['solved']) ?> FLAGS CAPTURED &nbsp;|&nbsp; <?= $_SESSION['points'] ?> PTS</div>

  <script src="matrix.js"></script>
  <script>
    let currentKey = null;

    function openModal(key) {
      currentKey = key;
      const data = <?= json_encode($problems) ?>;
      document.getElementById('modalTitle').innerText = data[key].title;
      document.getElementById('modalDesc').innerText  = data[key].desc;
      document.getElementById('flagInput').value = '';
      document.getElementById('modalMsg').innerText = '';
      document.getElementById('modalMsg').className = 'modal-msg';
      document.getElementById('modalOverlay').classList.add('active');
      setTimeout(() => document.getElementById('flagInput').focus(), 100);
    }

    function closeModal() {
      document.getElementById('modalOverlay').classList.remove('active');
      currentKey = null;
    }

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeModal();
      if (e.key === 'Enter' && currentKey) submitFlag();
    });

    function submitFlag() {
      const flag = document.getElementById('flagInput').value.trim();
      const msg  = document.getElementById('modalMsg');
      if (!flag) { msg.innerText = 'Enter a flag first.'; return; }

      fetch('check.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `problem=${encodeURIComponent(currentKey)}&flag=${encodeURIComponent(flag)}&cat=${encodeURIComponent('<?= $cat ?>')}`
      })
      .then(r => r.json())
      .then(data => {
        msg.className = 'modal-msg ' + (data.correct ? 'correct' : 'wrong');
        msg.innerText = data.message;
        if (data.correct) {
          document.getElementById('points').innerText = data.total;
          setTimeout(() => {
            closeModal();
            location.reload();
          }, 1500);
        }
      });
    }
  </script>
</body>
</html>
