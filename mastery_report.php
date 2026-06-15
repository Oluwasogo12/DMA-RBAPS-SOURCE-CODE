<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$pageTitle = 'Adaptive Mastery Report — RBAPS';
$db  = getDB();
$uid = $_SESSION['user_id'];

// ── Ensure tables exist ───────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS user_topic_performance (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    subject_name VARCHAR(60), topic VARCHAR(255),
    mastery_score DECIMAL(5,2) DEFAULT 0, total_attempted INT DEFAULT 0,
    total_correct INT DEFAULT 0, consecutive_correct INT DEFAULT 0,
    difficulty_level VARCHAR(10) DEFAULT 'easy',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_topic (user_id, subject_name, topic))");
$db->exec("CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, subject_name VARCHAR(60),
    category VARCHAR(8), year VARCHAR(8), total_q INT DEFAULT 0,
    correct INT DEFAULT 0, score_pct DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS user_performance (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, subject_name VARCHAR(60),
    mastery_score DECIMAL(5,2) DEFAULT 0, total_attempted INT DEFAULT 0,
    total_correct INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_subj (user_id, subject_name))");

// ── Filter by subject (optional) ─────────────────────────────────
$filterSubject = trim($_GET['subject'] ?? '');

// ── Overall stats ─────────────────────────────────────────────────
$statsStmt = $db->prepare("
    SELECT
        SUM(total_attempted) AS total_q,
        SUM(total_correct)   AS total_c,
        COUNT(DISTINCT subject_name) AS subjects_practiced,
        COUNT(DISTINCT topic) AS topics_attempted,
        MAX(last_updated) AS last_active
    FROM user_topic_performance WHERE user_id=?
");
$statsStmt->execute([$uid]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// ── Session count ─────────────────────────────────────────────────
$sessCountStmt = $db->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id=? AND category='adaptive'");
$sessCountStmt->execute([$uid]);
$adaptiveSessions = (int)$sessCountStmt->fetchColumn();

// ── All subjects practiced ─────────────────────────────────────────
$subjStmt = $db->prepare("
    SELECT subject_name, mastery_score, total_attempted, total_correct, last_updated
    FROM user_performance WHERE user_id=? ORDER BY mastery_score ASC
");
$subjStmt->execute([$uid]);
$allSubjects = $subjStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Topic performance ─────────────────────────────────────────────
$topicQuery = "
    SELECT subject_name, topic, mastery_score, total_attempted, total_correct,
           consecutive_correct, difficulty_level, last_updated
    FROM user_topic_performance
    WHERE user_id=?
";
$topicParams = [$uid];
if ($filterSubject) {
    $topicQuery .= " AND subject_name=?";
    $topicParams[] = $filterSubject;
}
$topicQuery .= " ORDER BY mastery_score ASC, total_attempted DESC";

$topicStmt = $db->prepare($topicQuery);
$topicStmt->execute($topicParams);
$allTopics = $topicStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Categorise topics by adaptive thresholds ──────────────────────
// Rule 1: score < 60% → NEEDS FOCUS (remedial)
// score 60–79% → ON TRACK
// score 80–89% → PROFICIENT
// score ≥ 90%  → MASTERED
$critical   = array_filter($allTopics, fn($t) => $t['mastery_score'] < 40 && $t['total_attempted'] >= 3);
$needsWork  = array_filter($allTopics, fn($t) => $t['mastery_score'] >= 40 && $t['mastery_score'] < 60);
$onTrack    = array_filter($allTopics, fn($t) => $t['mastery_score'] >= 60 && $t['mastery_score'] < 80);
$proficient = array_filter($allTopics, fn($t) => $t['mastery_score'] >= 80 && $t['mastery_score'] < 90);
$mastered   = array_filter($allTopics, fn($t) => $t['mastery_score'] >= 90);
$unattempted_critical = array_filter($allTopics, fn($t) => $t['total_attempted'] < 3 && $t['mastery_score'] < 40);

// ── Recent sessions (adaptive only) ─────────────────────────────
$sessQ = "SELECT * FROM user_sessions WHERE user_id=? AND category='adaptive'";
$sessP = [$uid];
if ($filterSubject) { $sessQ .= " AND subject_name=?"; $sessP[] = $filterSubject; }
$sessQ .= " ORDER BY created_at DESC LIMIT 12";
$sessStmt = $db->prepare($sessQ);
$sessStmt->execute($sessP);
$recentSessions = $sessStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Score history for mini chart (last 20 adaptive sessions) ─────
$chartStmt = $db->prepare("
    SELECT score_pct, subject_name, created_at
    FROM user_sessions
    WHERE user_id=? AND category='adaptive'
    ORDER BY created_at ASC LIMIT 20
");
$chartStmt->execute([$uid]);
$chartData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Overall mastery % ─────────────────────────────────────────────
$overallPct = ($stats['total_q'] > 0)
    ? round(($stats['total_c'] / $stats['total_q']) * 100, 1)
    : 0;

// ── Priority study list — top 5 weakest topics with attempts ──────
$priorityTopics = array_slice(
    array_filter($allTopics, fn($t) => $t['total_attempted'] >= 1),
    0, 5
);

// ── Subjects for filter dropdown ──────────────────────────────────
$subjFilterStmt = $db->prepare("SELECT DISTINCT subject_name FROM user_topic_performance WHERE user_id=? ORDER BY subject_name");
$subjFilterStmt->execute([$uid]);
$subjectList = $subjFilterStmt->fetchAll(PDO::FETCH_COLUMN);

// ── Export to CSV ─────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=RBAPS_Mastery_Report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Subject', 'Topic', 'Mastery Score (%)', 'Total Attempted', 'Total Correct', 'Last Updated']);
    foreach ($allTopics as $t) {
        fputcsv($output, [
            $t['subject_name'],
            $t['topic'],
            $t['mastery_score'],
            $t['total_attempted'],
            $t['total_correct'],
            $t['last_updated']
        ]);
    }
    fclose($output);
    exit;
}

include 'includes/header.php';

// Helper: mastery colour
function masteryColor(float $pct): string {
    if ($pct >= 90) return 'var(--green)';
    if ($pct >= 80) return '#4fcb8d';
    if ($pct >= 60) return 'var(--gold)';
    if ($pct >= 40) return 'var(--accent)';
    return 'var(--red)';
}
?>

<div class="section" style="max-width:1060px">

  <!-- ── Page Header ─────────────────────────────────────────────── -->
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
    <div>
      <a href="dashboard.php" style="font-size:.82rem;color:var(--text3);text-decoration:none">← Dashboard</a>
      <h2 style="font-family:'DM Serif Display',serif;font-size:1.75rem;margin-top:.3rem">
        <i class="fa-solid fa-chart-line"></i> Adaptive Mastery Report
      </h2>
      <p style="color:var(--text2);font-size:.9rem;margin-top:.25rem">
        Your performance across all adaptive sessions — topics you need to read more &amp; improve
      </p>
    </div>

    <!-- Subject filter -->
    <form method="GET" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
      <select name="subject" class="form-control" style="width:auto;min-width:170px;font-size:.85rem" onchange="this.form.submit()">
        <option value="">All Subjects</option>
        <?php foreach ($subjectList as $s): ?>
        <option value="<?= htmlspecialchars($s) ?>" <?= $filterSubject===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($filterSubject): ?>
      <a href="mastery_report.php" class="btn btn-ghost btn-sm">✕ Clear</a>
      <?php endif; ?>
      <a href="mastery_report.php?export=csv<?= $filterSubject ? '&subject='.urlencode($filterSubject) : '' ?>" class="btn btn-outline btn-sm" style="border-color:var(--border); color:var(--text)">
        <i class="fa-solid fa-download"></i> Export CSV
      </a>
    </form>
  </div>

  <?php if (!$allTopics): ?>
  <!-- ── Empty state ────────────────────────────────────────────── -->
  <div class="card" style="text-align:center;padding:3rem 2rem">
    <div style="font-size:3.5rem;margin-bottom:1rem"><i class="fa-solid fa-robot" style="color:var(--text3)"></i></div>
    <h3 style="font-family:'DM Serif Display',serif;font-size:1.4rem;margin-bottom:.5rem">No adaptive data yet</h3>
    <p style="color:var(--text2);max-width:420px;margin:0 auto 1.5rem">
      Complete at least one adaptive practice session to generate your personalised mastery report.
    </p>
    <a href="practice.php" class="btn btn-primary"><i class="fa-solid fa-rocket"></i> Start Adaptive Practice</a>
  </div>

  <?php else: ?>

  <!-- ── Summary Metrics ───────────────────────────────────────── -->
  <div class="dashboard-grid" style="margin-bottom:2rem">
    <div class="metric-card">
      <div class="metric-icon"><i class="fa-solid fa-bullseye"></i></div>
      <span class="metric-val" style="color:<?= masteryColor($overallPct) ?>"><?= $overallPct ?>%</span>
      <div class="metric-lbl">Overall Mastery</div>
    </div>
    <div class="metric-card">
      <div class="metric-icon"><i class="fa-solid fa-robot"></i></div>
      <span class="metric-val"><?= $adaptiveSessions ?></span>
      <div class="metric-lbl">Adaptive Sessions</div>
    </div>
    <div class="metric-card">
      <div class="metric-icon"><i class="fa-solid fa-book-open"></i></div>
      <span class="metric-val"><?= $stats['topics_attempted'] ?? 0 ?></span>
      <div class="metric-lbl">Topics Tracked</div>
    </div>
    <div class="metric-card">
      <div class="metric-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <span class="metric-val" style="color:var(--red)"><?= count($critical) + count($needsWork) ?></span>
      <div class="metric-lbl">Topics Below 60%</div>
    </div>
  </div>

  <!-- ── Priority Study Alert ──────────────────────────────────── -->
  <?php if (count($critical) || count($needsWork)): ?>
  <div class="card" style="margin-bottom:1.5rem;border-color:rgba(255,77,109,0.4);background:rgba(255,77,109,0.05)">
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">
      <div style="font-size:1.8rem"><i class="fa-solid fa-thumbtack" style="color:var(--red)"></i></div>
      <div>
        <div style="font-weight:700;font-size:1rem;color:var(--red)">Priority Study List — Read These First</div>
        <div style="font-size:.82rem;color:var(--text2);margin-top:.2rem">
          Topics below the 60% mastery threshold need the most attention — focus your reading and revision here first.
        </div>
      </div>
    </div>

    <?php
    $pList = array_merge(array_values($critical), array_values($needsWork));
    usort($pList, fn($a,$b) => $a['mastery_score'] <=> $b['mastery_score']);
    foreach (array_slice($pList, 0, 8) as $t):
        $pct = round($t['mastery_score']);
    ?>
    <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .75rem;border-radius:10px;background:var(--bg3);margin-bottom:.5rem;flex-wrap:wrap">
      <div style="flex:1;min-width:160px">
        <div style="font-weight:600;font-size:.88rem"><?= htmlspecialchars($t['topic']) ?></div>
        <div style="font-size:.75rem;color:var(--text3);margin-top:.1rem"><?= htmlspecialchars($t['subject_name']) ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">

        <span style="font-size:.75rem;color:var(--text3)"><?= $t['total_correct'] ?>/<?= $t['total_attempted'] ?> correct</span>
        <span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--red);font-size:.95rem;min-width:42px;text-align:right"><?= $pct ?>%</span>
      </div>
      <div style="width:100%;height:5px;background:var(--card2);border-radius:999px;overflow:hidden;margin-top:.3rem">
        <div style="height:100%;width:<?= $pct ?>%;background:var(--red);border-radius:999px"></div>
      </div>
    </div>
    <?php endforeach; ?>

    <a href="practice.php" class="btn btn-primary btn-sm" style="margin-top:.75rem"><i class="fa-solid fa-rocket"></i> Practice Weak Topics Now</a>
  </div>
  <?php endif; ?>

  <!-- ── Two-column layout: subject overview + score trend ─────── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem" class="two-col">

    <!-- Subject Mastery -->
    <div class="card">
      <h3 style="font-size:.95rem;font-weight:700;margin-bottom:1rem">Subject Mastery Overview</h3>
      <?php if ($allSubjects): ?>
        <?php foreach ($allSubjects as $s):
          $sp = round($s['mastery_score']);
          $sc = masteryColor($sp);
          $status = $sp >= 90 ? '<i class="fa-solid fa-trophy"></i> Mastered' : ($sp >= 80 ? '<i class="fa-solid fa-circle-check"></i> Proficient' : ($sp >= 60 ? '<i class="fa-solid fa-chart-line"></i> On Track' : '<i class="fa-solid fa-triangle-exclamation"></i> Needs Work'));
        ?>
        <div style="margin-bottom:.9rem">
          <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:.3rem">
            <div>
              <span style="font-weight:600;font-size:.88rem"><?= htmlspecialchars($s['subject_name']) ?></span>
              <span style="font-size:.7rem;color:var(--text3);margin-left:.4rem"><?= $status ?></span>
            </div>
            <span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:<?= $sc ?>;font-size:.9rem"><?= $sp ?>%</span>
          </div>
          <div style="height:7px;background:var(--card2);border-radius:999px;overflow:hidden">
            <div style="height:100%;width:<?= $sp ?>%;background:<?= $sc ?>;border-radius:999px;transition:width .5s ease"></div>
          </div>
          <div style="font-size:.72rem;color:var(--text3);margin-top:.25rem"><?= $s['total_correct'] ?>/<?= $s['total_attempted'] ?> correct ·
            <a href="mastery_report.php?subject=<?= urlencode($s['subject_name']) ?>" style="color:var(--accent);text-decoration:none">View topics →</a>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color:var(--text3);font-size:.85rem">No subject data yet.</p>
      <?php endif; ?>
    </div>

    <!-- Score Trend Chart -->
    <div class="card">
      <h3 style="font-size:.95rem;font-weight:700;margin-bottom:1rem">Session Score Trend</h3>
      <?php if (count($chartData) >= 2): ?>
      <div style="position:relative;height:160px;margin-bottom:.5rem">
        <canvas id="trendChart" style="width:100%;height:160px"></canvas>
      </div>
      <div style="font-size:.75rem;color:var(--text3);text-align:center">Last <?= count($chartData) ?> adaptive sessions</div>
      <script>
      (function(){
        const pts = <?= json_encode(array_map(fn($r) => [
            'y'   => (float)$r['score_pct'],
            'subj'=> $r['subject_name'],
            'date'=> date('d M', strtotime($r['created_at']))
        ], $chartData)) ?>;

        const canvas = document.getElementById('trendChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const W = canvas.offsetWidth || 340;
        const H = 140;
        canvas.width  = W * window.devicePixelRatio;
        canvas.height = H * window.devicePixelRatio;
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

        const pad = { l:32, r:12, t:10, b:20 };
        const gW = W - pad.l - pad.r;
        const gH = H - pad.t - pad.b;

        const style = getComputedStyle(document.documentElement);
        const green  = style.getPropertyValue('--green').trim()  || '#00c896';
        const gold   = style.getPropertyValue('--gold').trim()   || '#f5c842';
        const red    = style.getPropertyValue('--red').trim()    || '#ff4d6d';
        const accent = style.getPropertyValue('--accent').trim() || '#4f8ef7';
        const text3  = style.getPropertyValue('--text3').trim()  || '#888';
        const border = style.getPropertyValue('--border').trim() || '#2a2a3a';

        // Grid lines at 60% and 80%
        [[60, gold],[80, green]].forEach(([v, col]) => {
          const y = pad.t + gH - (v / 100) * gH;
          ctx.strokeStyle = col + '55';
          ctx.lineWidth = 1;
          ctx.setLineDash([4,4]);
          ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(pad.l + gW, y); ctx.stroke();
          ctx.setLineDash([]);
          ctx.fillStyle = col; ctx.font = '9px sans-serif'; ctx.textAlign = 'left';
          ctx.fillText(v+'%', 2, y+3);
        });

        // Plot line
        ctx.beginPath();
        pts.forEach((p, i) => {
          const x = pad.l + (i / (pts.length - 1)) * gW;
          const y = pad.t + gH - (p.y / 100) * gH;
          i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.strokeStyle = accent;
        ctx.lineWidth   = 2.5;
        ctx.lineJoin    = 'round';
        ctx.stroke();

        // Dots
        pts.forEach((p, i) => {
          const x = pad.l + (i / (pts.length - 1)) * gW;
          const y = pad.t + gH - (p.y / 100) * gH;
          const col = p.y >= 80 ? green : p.y >= 60 ? gold : red;
          ctx.beginPath();
          ctx.arc(x, y, 4, 0, Math.PI*2);
          ctx.fillStyle   = col;
          ctx.strokeStyle = '#111';
          ctx.lineWidth   = 1.5;
          ctx.fill(); ctx.stroke();
        });
      })();
      </script>
      <?php elseif ($recentSessions): ?>
        <div style="text-align:center;padding:1.5rem;color:var(--text3);font-size:.85rem">
          Complete at least 2 sessions for a trend line.
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:1.5rem;color:var(--text3);font-size:.85rem">No sessions yet.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Full Topic Breakdown ───────────────────────────────────── -->
  <div class="card" style="margin-bottom:1.5rem">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.5rem">
      <h3 style="font-size:.95rem;font-weight:700">
        Full Topic Mastery Breakdown
        <?php if ($filterSubject): ?>
          — <em><?= htmlspecialchars($filterSubject) ?></em>
        <?php endif; ?>
      </h3>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;font-size:.78rem">
        <span style="display:flex;align-items:center;gap:.3rem"><span style="width:10px;height:10px;border-radius:50%;background:var(--red);display:inline-block"></span>Critical &lt;40%</span>
        <span style="display:flex;align-items:center;gap:.3rem"><span style="width:10px;height:10px;border-radius:50%;background:var(--accent);display:inline-block"></span>Needs Work 40–59%</span>
        <span style="display:flex;align-items:center;gap:.3rem"><span style="width:10px;height:10px;border-radius:50%;background:var(--gold);display:inline-block"></span>On Track 60–79%</span>
        <span style="display:flex;align-items:center;gap:.3rem"><span style="width:10px;height:10px;border-radius:50%;background:var(--green);display:inline-block"></span>Proficient ≥80%</span>
      </div>
    </div>

    <!-- Tabs -->
    <div id="tabBar" style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap">
      <?php
      $tabs = [
        'all'        => ['label'=>'All Topics', 'count'=> count($allTopics)],
        'critical'   => ['label'=>'Critical',    'count'=> count($critical)],
        'needs_work' => ['label'=>'Needs Work',   'count'=> count($needsWork)],
        'on_track'   => ['label'=>'On Track',     'count'=> count($onTrack)],
        'proficient' => ['label'=>'Proficient',   'count'=> count($proficient)],
        'mastered'   => ['label'=>'Mastered',     'count'=> count($mastered)],
      ];
      foreach ($tabs as $key => $tab):
      ?>
      <button class="tab-btn <?= $key==='all'?'tab-active':'' ?>" data-tab="<?= $key ?>" onclick="switchTab('<?= $key ?>')">
        <?= $tab['label'] ?> <span style="font-size:.75rem;opacity:.7">(<?= $tab['count'] ?>)</span>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Topic list groups -->
    <?php
    $groups = [
        'critical'   => array_values($critical),
        'needs_work' => array_values($needsWork),
        'on_track'   => array_values($onTrack),
        'proficient' => array_values($proficient),
        'mastered'   => array_values($mastered),
    ];
    $allSorted = $allTopics; // already sorted by mastery_score ASC
    ?>

    <div id="tab-all">
      <?php if ($allSorted): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Topic</th>
              <?php if (!$filterSubject): ?><th>Subject</th><?php endif; ?>
              <th>Mastery</th>
              <th>Correct</th>

              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($allSorted as $t):
            $p   = round($t['mastery_score']);
            $col = masteryColor($p);
            $statusText = $p >= 90 ? '<i class="fa-solid fa-trophy"></i> Mastered' : ($p >= 80 ? '<i class="fa-solid fa-circle-check"></i> Proficient' : ($p >= 60 ? '<i class="fa-solid fa-chart-line"></i> On Track' : ($p >= 40 ? '<i class="fa-solid fa-triangle-exclamation"></i> Needs Work' : '<i class="fa-solid fa-circle-xmark"></i> Critical')));
          ?>
          <tr>
            <td style="max-width:280px">
              <div style="font-weight:600;font-size:.86rem"><?= htmlspecialchars($t['topic']) ?></div>
              <div style="height:4px;background:var(--card2);border-radius:999px;margin-top:.3rem;overflow:hidden;width:120px">
                <div style="height:100%;width:<?= $p ?>%;background:<?= $col ?>;border-radius:999px"></div>
              </div>
            </td>
            <?php if (!$filterSubject): ?>
            <td style="font-size:.8rem;color:var(--text3)"><?= htmlspecialchars($t['subject_name']) ?></td>
            <?php endif; ?>
            <td><span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:<?= $col ?>"><?= $p ?>%</span></td>
            <td style="font-size:.82rem"><?= $t['total_correct'] ?> / <?= $t['total_attempted'] ?></td>

            <td style="font-size:.8rem"><?= $statusText ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <p style="color:var(--text3);text-align:center;padding:2rem;font-size:.88rem">No topics found.</p>
      <?php endif; ?>
    </div>

    <?php foreach ($groups as $key => $rows): ?>
    <div id="tab-<?= $key ?>" style="display:none">
      <?php if ($rows): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Topic</th>
              <?php if (!$filterSubject): ?><th>Subject</th><?php endif; ?>
              <th>Mastery</th>
              <th>Correct</th>

              <th style="font-size:.8rem">Last Active</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $t):
            $p   = round($t['mastery_score']);
            $col = masteryColor($p);
          ?>
          <tr>
            <td style="max-width:280px">
              <div style="font-weight:600;font-size:.86rem"><?= htmlspecialchars($t['topic']) ?></div>
              <div style="height:4px;background:var(--card2);border-radius:999px;margin-top:.3rem;overflow:hidden;width:120px">
                <div style="height:100%;width:<?= $p ?>%;background:<?= $col ?>;border-radius:999px"></div>
              </div>
            </td>
            <?php if (!$filterSubject): ?>
            <td style="font-size:.8rem;color:var(--text3)"><?= htmlspecialchars($t['subject_name']) ?></td>
            <?php endif; ?>
            <td><span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:<?= $col ?>"><?= $p ?>%</span></td>
            <td style="font-size:.82rem"><?= $t['total_correct'] ?> / <?= $t['total_attempted'] ?></td>

            <td style="font-size:.78rem;color:var(--text3)"><?= date('d M Y', strtotime($t['last_updated'])) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:2.5rem;color:var(--text3)">
        <div style="font-size:2rem;margin-bottom:.5rem">✨</div>
        <p style="font-size:.88rem">No topics in this category yet.</p>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Adaptive Rules Key (admin/guest only) ──────────────────── -->
  <?php if(false): // Hidden from students — rules are internal logic ?>
  <div class="card" style="margin-bottom:1.5rem">
    <h3 style="font-size:.95rem;font-weight:700;margin-bottom:1rem"><i class="fa-solid fa-robot"></i> How Adaptive Scoring Works</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.75rem">
      <?php
      $rules = [
          ['badge-red',    'Rule 1 — Remedial Focus',          'Topics below 60% are served more questions to bring you up to threshold.'],
          ['badge-blue',   'Rule 2 — Progressive Challenge',   '3 consecutive correct answers advances difficulty: Easy → Medium → Hard.'],
          ['badge-green',  'Rule 3 — Topic Rotation',          'Once above 60%, sessions rotate to reinforce and maintain mastery.'],
          ['badge-gold',   'Rule 4 — Prerequisite Validation', 'Numbered topics must be unlocked in order — earlier topics must pass 60% first.'],
          ['',             'Rule 7 — Instant Feedback',        'Wrong answers show the concept missed so you know exactly what to read.'],
      ];
      foreach ($rules as [$cls, $name, $desc]):
      ?>
      <div style="padding:.75rem;border-radius:10px;background:var(--bg3);border:1px solid var(--border)">
        <div class="badge <?= $cls ?>" style="margin-bottom:.5rem;font-size:.72rem"><?= $name ?></div>
        <p style="font-size:.8rem;color:var(--text2);line-height:1.5;margin:0"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:1rem;padding:.75rem;border-radius:10px;background:rgba(79,142,247,0.07);border:1px solid rgba(79,142,247,.2);font-size:.82rem;color:var(--text2)">
      <strong>60% Mastery Threshold:</strong> The adaptive engine targets ≥60% on every topic before moving on. Topics below this line are your study priority — focus reading &amp; revision on those first.
    </div>
  </div>
  <?php endif; // end hidden rules card ?>

  <!-- ── Recent Adaptive Sessions ──────────────────────────────── -->
  <?php if ($recentSessions): ?>
  <div class="card" style="margin-bottom:1.5rem">
    <h3 style="font-size:.95rem;font-weight:700;margin-bottom:1rem">Recent Adaptive Sessions</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Subject</th><th>Questions</th><th>Score</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recentSessions as $r):
          $sp  = round($r['score_pct']);
          $col = $sp >= 70 ? 'var(--green)' : ($sp >= 50 ? 'var(--gold)' : 'var(--red)');
        ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($r['subject_name']) ?></strong>
            <span class="badge badge-green" style="font-size:.68rem;margin-left:.4rem">Adaptive</span>
          </td>
          <td><?= $r['correct'] ?> / <?= $r['total_q'] ?></td>
          <td><span style="font-weight:700;color:<?= $col ?>"><?= $sp ?>%</span></td>
          <td style="color:var(--text3);font-size:.82rem"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── CTA ───────────────────────────────────────────────────── -->
  <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem">
    <a href="practice.php" class="btn btn-primary"><i class="fa-solid fa-rocket"></i> Continue Adaptive Practice</a>
    <a href="dashboard.php" class="btn btn-ghost">← Dashboard</a>
    <?php if (count($critical) || count($needsWork)):
      // Pick weakest subject
      usort($allSubjects, fn($a,$b) => $a['mastery_score'] <=> $b['mastery_score']);
      $weakSubj = $allSubjects[0]['subject_name'] ?? '';
      if ($weakSubj):
    ?>
    <a href="practice.php?subject=<?= urlencode($weakSubj) ?>" class="btn btn-outline" style="border-color:var(--red);color:var(--red)">
      <i class="fa-solid fa-triangle-exclamation"></i> Focus: <?= htmlspecialchars($weakSubj) ?>
    </a>
    <?php endif; endif; ?>
  </div>

  <?php endif; // end has data ?>
</div>

<style>
.tab-btn {
  padding:.4rem .85rem;border-radius:8px;border:1px solid var(--border);
  background:var(--bg3);color:var(--text2);font-size:.82rem;cursor:pointer;transition:all .15s;
}
.tab-btn:hover { background:var(--card2); color:var(--text); }
.tab-active { background:var(--accent)!important; color:#fff!important; border-color:var(--accent)!important; }
@media(max-width:700px){.two-col{grid-template-columns:1fr!important}}
</style>

<script>
function switchTab(key) {
  // Hide all tab panes
  ['all','critical','needs_work','on_track','proficient','mastered'].forEach(k => {
    const el = document.getElementById('tab-' + k);
    if (el) el.style.display = 'none';
  });
  // Show selected
  const target = document.getElementById('tab-' + key);
  if (target) target.style.display = 'block';
  // Update button styles
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.toggle('tab-active', btn.dataset.tab === key);
  });
}
</script>

<?php include 'includes/footer.php'; ?>
