<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_scoring.php';
$user = require_team();
$csrf = csrf_token();
$solved = solved_map((int)$user['team_id']);
$counts = solve_counts();

$cat = is_string($_GET['cat'] ?? null) ? strtolower($_GET['cat']) : '';

$all_problems = load_problems();

$labels = ['cryptography' => 'CRYPTOGRAPHY', 'forensics' => 'FORENSICS', 'osint' => 'OSINT'];

if (!array_key_exists($cat, $all_problems)) {
  header('Location: index.php'); exit;
}

$problems = $all_problems[$cat];
$cat_solved = count(array_intersect_key($solved, $problems));

// Card data for the modal. Answers are never included.
$modal_data = array_map(fn($p) => [
  'title' => $p['title'], 'desc' => $p['desc'],
  'files' => array_map(fn($f) => ['name' => basename($f), 'url' => '/' . implode('/', array_map('rawurlencode', explode('/', $f)))], $p['files'] ?? []),
], $problems);
$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="csrf-token" content="<?= h($csrf) ?>"/>
  <title>H4G :: <?= h(strtoupper($cat)) ?></title>
  <link rel="stylesheet" href="styles.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet"/>
</head>
<body>
  <canvas id="matrix"></canvas>
  <div class="scanline"></div>

  <div class="hud-top">
    <a href="index.php" class="back-btn">← BACK</a>
    <div class="logo"><?= h($labels[$cat] ?? strtoupper($cat)) ?></div>
    <?= hud_stats($user) ?>
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
          $s = $solved[$key] ?? null;
          $n = $counts[$key] ?? 0;
        ?>
        <div class="prob-card<?= $s ? ' solved' : '' ?><?= ($s && $s['first_blood']) ? ' first-blood' : '' ?>"<?= $s ? '' : ' data-key="' . h($key) . '"' ?>>
          <?php if ($s): ?>
            <div class="prob-pts">+<?= (int)$s['points_awarded'] ?> PTS</div>
          <?php else: ?>
            <div class="prob-pts">CURRENT VALUE: <?= current_value((int)$p['points'], $n) ?> PTS</div>
          <?php endif; ?>
          <div class="prob-title"><?= h($p['title']) ?></div>
          <div class="prob-desc"><?= h($p['desc']) ?></div>
          <div class="prob-meta"><?= $n ?> TEAM<?= $n === 1 ? '' : 'S' ?> SOLVED &nbsp;·&nbsp; BASE <?= (int)$p['points'] ?></div>
          <?php if ($s): ?>
            <div class="solved-badge">
              ✔ SOLVED (#<?= (int)$s['solve_rank'] ?>)
              <span class="solved-by">Solved by <?= h($s['solved_by'] ?? '[ deleted ]') ?></span>
              <?php if ($s['first_blood']): ?><span class="fb-badge">🩸 FIRST BLOOD</span><?php endif; ?>
            </div>
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
      <div id="modalFiles" style="margin:10px 0;"></div>
      <div class="flag-input-wrap">
        <span class="prompt">root@ctf:~$</span>
        <input type="text" id="flagInput" placeholder="<?= $cat === 'cryptography' ? 'ctf{...}' : 'FLAG{...}' ?>" autocomplete="off" spellcheck="false"/>
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

  <!-- First Blood overlay (only shown to the solver) -->
  <div class="fb-overlay" id="fbOverlay" role="alertdialog" aria-labelledby="fbTitle">
    <button type="button" class="fb-mute" id="fbMute">[ SOUND: ON ]</button>
    <div class="fb-box">
      <div class="fb-title" id="fbTitle">FIRST BLOOD</div>
      <div class="fb-line">TEAM: <span id="fbTeam"></span></div>
      <div class="fb-line">CHALLENGE: <span id="fbChallenge"></span></div>
      <div class="fb-bonus">+<span id="fbBonus"></span> PTS BONUS</div>
      <div class="fb-hint">[ CLICK ANYWHERE TO CONTINUE ]</div>
      <div class="corner tl"></div><div class="corner tr"></div>
      <div class="corner bl"></div><div class="corner br"></div>
    </div>
  </div>

  <div class="footer-bar">[ <?= h(strtoupper($cat)) ?> ] &nbsp;|&nbsp; <?= $cat_solved ?> / <?= count($problems) ?> FLAGS CAPTURED &nbsp;|&nbsp; <span id="footerPts"><?= (int)$user['team_points'] ?></span> PTS</div>

  <script src="matrix.js"></script>
  <script>
    const DATA = <?= json_encode($modal_data, $json_flags) ?>;
    const CAT  = <?= json_encode($cat, $json_flags) ?>;
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    let currentKey = null;

    document.querySelectorAll('.prob-card[data-key]').forEach(card => {
      card.addEventListener('click', () => openModal(card.dataset.key));
    });

    function openModal(key) {
      if (!DATA[key]) return;
      currentKey = key;
      document.getElementById('modalTitle').innerText = DATA[key].title;
      document.getElementById('modalDesc').innerText  = DATA[key].desc;
      const fw = document.getElementById('modalFiles');
      fw.innerHTML = '';
      (DATA[key].files || []).forEach(f => {
        const a = document.createElement('a');
        a.href = f.url; a.download = f.name; a.textContent = '⬇ ' + f.name;
        a.style.cssText = 'display:block;color:var(--green);margin:6px 0;word-break:break-all;';
        fw.appendChild(a);
      });
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
      if (fbOpen) { if (e.key === 'Escape' || e.key === 'Enter') closeFirstBlood(); return; }
      if (e.key === 'Escape') closeModal();
      if (e.key === 'Enter' && currentKey) submitFlag();
    });

    // ---- First Blood sound -------------------------------------------------
    // Drop the file at /assets/first-blood.mp3. If it is missing, play() rejects and we ignore it.
    let fbAudio = null, fbPrime = Promise.resolve(), fbOpen = false, fbTimer = null;
    let fbMuted = false;
    try { fbMuted = localStorage.getItem('h4g_fb_muted') === '1'; } catch (e) {}

    // Called inside the SUBMIT click/Enter handler: browsers only allow audio started from a user
    // gesture, so play it muted now and pause it, then the real play() after the fetch is allowed.
    function primeAudio() {
      try {
        if (!fbAudio) { fbAudio = new Audio('/assets/first-blood.mp3'); fbAudio.preload = 'auto'; }
        fbAudio.muted = true;
        fbPrime = Promise.resolve(fbAudio.play())
          .then(() => { fbAudio.pause(); fbAudio.currentTime = 0; })
          .catch(() => {})
          .finally(() => { if (fbAudio) fbAudio.muted = false; });
      } catch (e) { fbPrime = Promise.resolve(); }
    }

    function playFirstBloodSound() {
      if (!fbAudio || fbMuted) return;
      fbPrime.then(() => {
        try {
          fbAudio.muted = fbMuted;
          fbAudio.currentTime = 0;
          Promise.resolve(fbAudio.play()).catch(() => {});
        } catch (e) {}
      });
    }

    function renderMute() {
      document.getElementById('fbMute').innerText = fbMuted ? '[ SOUND: OFF ]' : '[ SOUND: ON ]';
    }
    renderMute();

    document.getElementById('fbMute').addEventListener('click', e => {
      e.stopPropagation();
      fbMuted = !fbMuted;
      try { localStorage.setItem('h4g_fb_muted', fbMuted ? '1' : '0'); } catch (err) {}
      if (fbAudio) { fbAudio.muted = fbMuted; if (fbMuted) fbAudio.pause(); }
      renderMute();
    });

    function showFirstBlood(data) {
      document.getElementById('fbTeam').textContent      = data.team || '';
      document.getElementById('fbChallenge').textContent = data.challenge || '';
      document.getElementById('fbBonus').textContent     = String(data.bonus || 0);
      document.getElementById('fbOverlay').classList.add('active');
      fbOpen = true;
      playFirstBloodSound();
      fbTimer = setTimeout(closeFirstBlood, 6000);
    }

    function closeFirstBlood() {
      if (!fbOpen) return;
      fbOpen = false;
      clearTimeout(fbTimer);
      document.getElementById('fbOverlay').classList.remove('active');
      if (fbAudio) { try { fbAudio.pause(); } catch (e) {} }
      location.reload();
    }
    document.getElementById('fbOverlay').addEventListener('click', closeFirstBlood);

    // ---- Flag submission ---------------------------------------------------
    let busy = false;
    function submitFlag() {
      if (busy || !currentKey) return;
      const flag = document.getElementById('flagInput').value.trim();
      const msg  = document.getElementById('modalMsg');
      if (!flag) { msg.className = 'modal-msg wrong'; msg.innerText = 'Enter a flag first.'; return; }

      primeAudio();
      busy = true;
      msg.className = 'modal-msg';
      msg.innerText = '[ CHECKING... ]';

      const body = new URLSearchParams({ problem: currentKey, flag: flag, cat: CAT, csrf: CSRF });
      fetch('check.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        credentials: 'same-origin',
        body: body.toString()
      })
      .then(r => r.text().then(t => {
        try { return JSON.parse(t); }
        catch (e) { throw new Error('Server error (' + r.status + '). Check the Vercel runtime logs.'); }
      }))
      .then(data => {
        msg.className = 'modal-msg ' + (data.correct ? 'correct' : 'wrong');
        msg.innerText = data.message;
        if (data.correct) {
          if (typeof data.total === 'number') {
            document.getElementById('points').innerText = data.total;
            document.getElementById('footerPts').innerText = data.total;
          }
          if (data.first_blood) {
            closeModal();
            showFirstBlood(data);
          } else {
            setTimeout(() => { closeModal(); location.reload(); }, 1500);
          }
        }
      })
      .catch(err => {
        msg.className = 'modal-msg wrong';
        msg.innerText = '[ ERROR ] ' + err.message;
      })
      .finally(() => { busy = false; });
    }
  </script>
</body>
</html>
