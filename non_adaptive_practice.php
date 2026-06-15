<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$pageTitle = 'Non-Adaptive Practice — RBAPS';
$db  = getDB();
$uid = $_SESSION['user_id'];

$selectedSubject = $_GET['subject'] ?? '';

// Ensure tables exist
$db->exec("CREATE TABLE IF NOT EXISTS user_topic_performance (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    subject_name VARCHAR(60), topic VARCHAR(255),
    mastery_score DECIMAL(5,2) DEFAULT 0, total_attempted INT DEFAULT 0,
    total_correct INT DEFAULT 0, consecutive_correct INT DEFAULT 0,
    difficulty_level VARCHAR(10) DEFAULT 'easy',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_topic (user_id, subject_name, topic))");

// All subjects
$subjects = $db->query("SELECT id, name FROM subjectname ORDER BY name")->fetchAll();

// Available years for selected subject
$years = [];
if ($selectedSubject) {
    $stmt = $db->prepare("
        SELECT sy.id, sy.year, sy.category, COUNT(q.id) as q_count
        FROM subjectyear sy
        JOIN subjectname sn ON sn.id = sy.subjectnamrid
        LEFT JOIN questions q ON q.subjectyear_id = sy.id AND q.correct_option IS NOT NULL
        WHERE sn.name = ?
        GROUP BY sy.id, sy.year, sy.category
        HAVING q_count > 0
        ORDER BY sy.category, sy.year DESC
    ");
    $stmt->execute([$selectedSubject]);
    $years = $stmt->fetchAll();
    
    $sysSettings = getSystemSettings();
    $hide2024 = $sysSettings['hide_2024'] ?? '0';
    $only2024 = $sysSettings['only_2024'] ?? '0';

    $filteredYears = [];
    foreach ($years as $y) {
        $yYear = trim($y['year']);
        if ($hide2024 === '1' && $yYear === '2024') {
            continue;
        }
        if ($only2024 === '1' && $yYear !== '2024') {
            continue;
        }
        $filteredYears[] = $y;
    }
    $years = $filteredYears;
}

// Fetch weak topics for selected subject (score < 60%)
$weakTopics = [];
$mediumTopics = [];
if ($selectedSubject) {
    $stmt = $db->prepare("
        SELECT topic, mastery_score, total_attempted, total_correct
        FROM user_topic_performance
        WHERE user_id = ? AND subject_name = ?
        ORDER BY mastery_score ASC
    ");
    $stmt->execute([$uid, $selectedSubject]);
    $allTopicPerf = $stmt->fetchAll();

    foreach ($allTopicPerf as $t) {
        if ($t['mastery_score'] < 60) {
            $weakTopics[] = $t;
        } elseif ($t['mastery_score'] < 80) {
            $mediumTopics[] = $t;
        }
    }
}

// Count total sessions for non-adaptive (category != 'adaptive')
$totalSessions = 0;
if ($selectedSubject) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id=? AND subject_name=? AND category != 'adaptive'");
    $stmt->execute([$uid, $selectedSubject]);
    $totalSessions = (int)$stmt->fetchColumn();
}

include 'includes/header.php';
?>

<style>
/* ── Non-Adaptive Practice Page ─────────────────────────────────── */
.nap-hero {
    background: linear-gradient(135deg, rgba(78,201,176,0.08) 0%, rgba(167,139,250,0.06) 100%);
    border: 1px solid rgba(78,201,176,0.18);
    border-radius: var(--radius);
    padding: 1.5rem 2rem;
    margin-bottom: 1.75rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
}
.nap-hero-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(78,201,176,0.15), rgba(167,139,250,0.12));
    border: 1px solid rgba(78,201,176,0.25);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
    flex-shrink: 0;
}
.nap-hero-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.35rem;
    font-weight: 700;
    margin-bottom: .25rem;
    background: linear-gradient(135deg, var(--cyan), var(--lavender));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.nap-hero-desc {
    font-size: .875rem;
    color: var(--text2);
    line-height: 1.55;
}

/* Year grid */
.year-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: .65rem;
    margin-top: .75rem;
}
.year-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: .3rem;
    padding: .85rem 1rem;
    border-radius: var(--radius-sm);
    background: var(--bg3);
    border: 1px solid var(--border-soft);
    cursor: pointer;
    text-decoration: none;
    color: var(--text);
    transition: border-color var(--t-base) var(--ease-out),
                background var(--t-base) var(--ease-out),
                transform var(--t-base) var(--ease-spring);
}
.year-card:hover {
    border-color: var(--border-hover);
    background: var(--bg4);
    transform: translateY(-2px);
    text-decoration: none;
    color: var(--text);
}
.year-card .yc-year {
    font-size: 1.05rem;
    font-weight: 700;
    font-family: 'DM Mono', monospace;
    color: var(--gold2);
}
.year-card .yc-count {
    font-size: .75rem;
    color: var(--text3);
}
.year-card .yc-badge {
    font-size: .68rem;
    padding: .15rem .5rem;
    border-radius: var(--radius-pill);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-top: .1rem;
}
.yc-badge.utme { background: rgba(79,142,247,0.14); color: #6fa0f8; }
.yc-badge.ssce { background: rgba(61,214,140,0.12); color: var(--green); }

/* Weak topics panel */
.weak-panel {
    border-radius: var(--radius);
    border: 1px solid rgba(240,85,112,0.22);
    background: rgba(240,85,112,0.05);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
}
.weak-panel-header {
    display: flex;
    align-items: center;
    gap: .65rem;
    margin-bottom: .9rem;
}
.weak-panel-title {
    font-weight: 700;
    font-size: .95rem;
    color: var(--red);
}
.weak-panel-subtitle {
    font-size: .8rem;
    color: var(--text2);
    margin-bottom: .9rem;
    line-height: 1.5;
}

.topic-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .5rem .65rem;
    border-radius: var(--radius-xs);
    background: rgba(255,255,255,0.03);
    margin-bottom: .4rem;
}
.topic-row:last-child { margin-bottom: 0; }
.tr-score {
    font-family: 'DM Mono', monospace;
    font-size: .85rem;
    font-weight: 700;
    min-width: 38px;
    text-align: right;
}
.tr-name {
    flex: 1;
    font-size: .83rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tr-bar-wrap {
    width: 80px;
    height: 4px;
    background: var(--card2);
    border-radius: 999px;
    overflow: hidden;
    flex-shrink: 0;
}
.tr-bar-fill {
    height: 100%;
    border-radius: 999px;
}
.tr-fraction {
    font-size: .7rem;
    color: var(--text3);
    min-width: 32px;
    text-align: right;
    flex-shrink: 0;
}

.medium-panel {
    border-radius: var(--radius);
    border: 1px solid rgba(245,200,66,0.20);
    background: rgba(245,200,66,0.05);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
}
.medium-panel-title {
    font-weight: 700;
    font-size: .9rem;
    color: var(--gold);
    margin-bottom: .75rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}

.no-topics-note {
    font-size: .82rem;
    color: var(--text3);
    font-style: italic;
    padding: .4rem 0;
}

/* Category divider */
.cat-divider {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin: 1.25rem 0 .75rem;
}
.cat-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-soft);
}

/* Diff from practice.php: subtle cyan/lavender tint on the CTA link at bottom */
.nap-practice-link {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    font-size: .85rem;
    color: var(--cyan);
    text-decoration: none;
    padding: .35rem .75rem;
    border-radius: var(--radius-xs);
    border: 1px solid rgba(78,201,176,0.2);
    background: rgba(78,201,176,0.05);
    transition: all var(--t-base);
}
.nap-practice-link:hover {
    background: rgba(78,201,176,0.10);
    border-color: rgba(78,201,176,0.35);
    color: var(--cyan);
    text-decoration: none;
}

.section-label {
    font-size: .75rem;
    color: var(--text3);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
}
</style>

<div class="section" style="max-width:860px">

  <!-- Hero banner -->
  <div class="nap-hero">
    <div class="nap-hero-icon">📅</div>
    <div>
      <div class="nap-hero-title">Non-Adaptive Practice</div>
      <div class="nap-hero-desc">
        Attempt past exam questions by year — UTME or SSCE. After each session, you'll see which topics you still need to work on based on your performance history.
      </div>
    </div>
  </div>

  <!-- Step 1 — Subject -->
  <div class="card" style="margin-bottom:1.5rem">
    <h3 style="font-size:.95rem;font-weight:700;margin-bottom:1.1rem">Step 1 — Choose Subject</h3>
    <form method="GET" action="non_adaptive_practice.php">
      <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label">Subject</label>
          <select class="form-control" name="subject" onchange="this.form.submit()">
            <option value="">— Select a subject —</option>
            <?php foreach($subjects as $s): ?>
            <option value="<?= htmlspecialchars($s['name']) ?>" <?= $selectedSubject===$s['name']?'selected':'' ?>>
              <?= htmlspecialchars($s['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-ghost" style="margin-bottom:0">Go</button>
      </div>
    </form>
  </div>

  <?php if ($selectedSubject && $years): ?>

  <!-- ── Weak Topics Panel ─────────────────────────────────────── -->
  <?php if ($weakTopics): ?>
  <div class="weak-panel">
    <div class="weak-panel-header">
      <i class="fa-solid fa-triangle-exclamation" style="color:var(--red)"></i>
      <span class="weak-panel-title">Topics That Need Your Attention</span>
      <span class="badge" style="background:rgba(240,85,112,0.15);color:var(--red);margin-left:auto"><?= count($weakTopics) ?> topic<?= count($weakTopics)!==1?'s':'' ?></span>
    </div>
    <div class="weak-panel-subtitle">
      Based on your practice history in <strong><?= htmlspecialchars($selectedSubject) ?></strong>, these topics scored below 60% mastery. Pick a year below and focus on questions from these areas.
    </div>
    <?php foreach ($weakTopics as $t):
      $color = $t['mastery_score'] < 40 ? 'var(--red)' : 'var(--gold)';
    ?>
    <div class="topic-row">
      <span class="tr-score" style="color:<?= $color ?>"><?= round($t['mastery_score']) ?>%</span>
      <span class="tr-name" title="<?= htmlspecialchars($t['topic']) ?>"><?= htmlspecialchars($t['topic']) ?></span>
      <div class="tr-bar-wrap">
        <div class="tr-bar-fill" style="width:<?= round($t['mastery_score']) ?>%;background:<?= $color ?>"></div>
      </div>
      <span class="tr-fraction"><?= $t['total_correct'] ?>/<?= $t['total_attempted'] ?></span>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:.9rem;font-size:.8rem;color:var(--text3)">
      <i class="fa-solid fa-lightbulb" style="color:var(--gold);margin-right:.35rem"></i>
      When attempting questions from any year below, pay extra attention to these topics. Your score per topic is updated at the end of every session.
    </div>
  </div>
  <?php elseif ($mediumTopics): ?>
  <!-- Medium topics (60-80%) -->
  <div class="medium-panel">
    <div class="medium-panel-title">
      <i class="fa-solid fa-chart-line"></i>
      Topics to Reinforce (60–79% mastery)
    </div>
    <?php foreach (array_slice($mediumTopics, 0, 5) as $t): ?>
    <div class="topic-row">
      <span class="tr-score" style="color:var(--gold)"><?= round($t['mastery_score']) ?>%</span>
      <span class="tr-name" title="<?= htmlspecialchars($t['topic']) ?>"><?= htmlspecialchars($t['topic']) ?></span>
      <div class="tr-bar-wrap">
        <div class="tr-bar-fill" style="width:<?= round($t['mastery_score']) ?>%;background:var(--gold)"></div>
      </div>
      <span class="tr-fraction"><?= $t['total_correct'] ?>/<?= $t['total_attempted'] ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <?php if ($totalSessions > 0): ?>
  <div class="card" style="margin-bottom:1.25rem;border-color:rgba(61,214,140,0.2);background:rgba(61,214,140,0.04)">
    <div style="display:flex;align-items:center;gap:.75rem">
      <i class="fa-solid fa-circle-check" style="color:var(--green);font-size:1.3rem"></i>
      <div>
        <div style="font-weight:700;font-size:.9rem;color:var(--green)">All Topics at 80%+ Mastery</div>
        <div style="font-size:.8rem;color:var(--text2)">Great work on <?= htmlspecialchars($selectedSubject) ?>! Try more years to keep sharp, or switch to Adaptive Mode for a challenge.</div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="card" style="margin-bottom:1.25rem;border-color:rgba(78,201,176,0.15);background:rgba(78,201,176,0.04)">
    <div style="display:flex;align-items:center;gap:.75rem">
      <i class="fa-solid fa-circle-info" style="color:var(--cyan);font-size:1.2rem"></i>
      <div>
        <div style="font-weight:700;font-size:.88rem">No topic history yet for <?= htmlspecialchars($selectedSubject) ?></div>
        <div style="font-size:.8rem;color:var(--text2)">Complete a practice session to see your weak topics appear here automatically.</div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- ── Step 2 — Year Picker ───────────────────────────────────── -->
  <div class="card">
    <h3 style="font-size:.95rem;font-weight:700;margin-bottom:.4rem">
      Step 2 — Pick a Year for <em><?= htmlspecialchars($selectedSubject) ?></em>
    </h3>
    <p style="font-size:.82rem;color:var(--text2);margin-bottom:.25rem">
      Each year's questions are fixed — attempt them in order, exactly as they appeared in the exam.
    </p>

    <?php
    $utmeYears = array_filter($years, fn($y) => $y['category']==='utme');
    $ssceYears = array_filter($years, fn($y) => $y['category']==='ssce');
    ?>

    <?php if ($utmeYears): ?>
    <div class="cat-divider">
      <span class="badge badge-blue">UTME</span>
    </div>
    <div class="year-grid">
      <?php foreach ($utmeYears as $y): ?>
      <a href="quiz.php?sy=<?= $y['id'] ?>&subject=<?= urlencode($selectedSubject) ?>" class="year-card">
        <span class="yc-year"><?= htmlspecialchars($y['year']) ?></span>
        <span class="yc-count"><?= $y['q_count'] ?> questions</span>
        <span class="yc-badge utme">UTME</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($ssceYears): ?>
    <div class="cat-divider">
      <span class="badge badge-green">SSCE / WAEC</span>
    </div>
    <div class="year-grid">
      <?php foreach ($ssceYears as $y): ?>
      <a href="quiz.php?sy=<?= $y['id'] ?>&subject=<?= urlencode($selectedSubject) ?>" class="year-card">
        <span class="yc-year"><?= htmlspecialchars($y['year']) ?></span>
        <span class="yc-count"><?= $y['q_count'] ?> questions</span>
        <span class="yc-badge ssce">SSCE</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Bottom links -->
  <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.25rem;align-items:center">
    <a href="practice.php?subject=<?= urlencode($selectedSubject) ?>" class="nap-practice-link">
      <i class="fa-solid fa-shuffle"></i> Standard Practice
    </a>
    <a href="adaptive.php?subject=<?= urlencode($selectedSubject) ?>" class="nap-practice-link" style="color:var(--lavender);border-color:rgba(167,139,250,0.2);background:rgba(167,139,250,0.05)">
      <i class="fa-solid fa-robot"></i> Adaptive Mode
    </a>
    <?php if ($weakTopics): ?>
    <a href="mastery_report.php?subject=<?= urlencode($selectedSubject) ?>" class="nap-practice-link" style="color:var(--gold);border-color:rgba(201,168,76,0.2);background:rgba(201,168,76,0.05)">
      <i class="fa-solid fa-chart-bar"></i> Full Mastery Report
    </a>
    <?php endif; ?>
  </div>

  <?php elseif ($selectedSubject): ?>
  <div class="alert alert-info">No questions found for this subject yet.</div>
  <?php endif; ?>

  <!-- Popular subjects quick access -->
  <?php if (!$selectedSubject): ?>
  <div style="margin-top:2rem">
    <div class="section-label" style="margin-bottom:1rem">Popular Subjects</div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <?php
      $popular = ['Chemistry','Physics','Mathematics','Biology','English','Economics','Government'];
      foreach ($popular as $p):
      ?>
      <a href="non_adaptive_practice.php?subject=<?= urlencode($p) ?>" class="btn btn-ghost btn-sm"><?= $p ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
