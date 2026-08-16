<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];

$syId    = intval($_GET['sy'] ?? 0);
$subject = trim($_GET['subject'] ?? '');

// ── Ensure supporting tables ──────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, subject_name VARCHAR(60),
    category VARCHAR(8), year VARCHAR(8),
    total_q INT DEFAULT 0, correct INT DEFAULT 0, score_pct DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS user_performance (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, subject_name VARCHAR(60),
    mastery_score DECIMAL(5,2) DEFAULT 0, total_attempted INT DEFAULT 0,
    total_correct INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_subj (user_id, subject_name))");
$db->exec("CREATE TABLE IF NOT EXISTS user_topic_performance (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, subject_name VARCHAR(60),
    topic VARCHAR(255), mastery_score DECIMAL(5,2) DEFAULT 0,
    total_attempted INT DEFAULT 0, total_correct INT DEFAULT 0,
    consecutive_correct INT DEFAULT 0, difficulty_level VARCHAR(10) DEFAULT 'easy',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_topic (user_id, subject_name, topic))");
$db->exec("CREATE TABLE IF NOT EXISTS user_answers (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, session_id INT,
    question_id INT NOT NULL, chosen VARCHAR(2), is_correct TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

// ── Topic table map (same as adaptive.php) ───────────────────────────────
$topicTableMap = [
    'Chemistry'            => ['mapping_chemistry',       'new'],
    'Physics'              => ['mapping_physics',         'new'],
    'Mathematics'          => ['mapping_mathematics',     'new'],
    'Biology'              => ['mapping_biology',         'new'],
    'English'              => ['mapping_english',         'new'],
    'Civic Education'      => ['mapping_civic',           'new'],
    'civic'                => ['mapping_civic',           'new'], // Legacy fallback
    'Economics'            => ['mapping_economics',       'new'],
    'Government'           => ['mapping_government',      'new'], // Updated to modern format
    'History'              => ['mapping_history',         'new'], // Updated to modern format
    'ICT'                  => ['mapping_ict',             'new'], // Updated to modern format
    'Geography'            => ['mapping_geography',       'new'], // New mapping table
    'Commerce'             => ['mapping_commerce',        'new'], // New mapping table
    'Financial accounting' => ['mapping_financial_accounting', 'new'],
    'Technical Drawing'    => null,
];

// ── Resolve year / subject info ───────────────────────────────────────────
if (!$syId) { header('Location: non_adaptive.php'); exit; }

$stmt = $db->prepare("SELECT sy.*, sn.name AS subject_name
    FROM subjectyear sy JOIN subjectname sn ON sn.id = sy.subjectnamrid WHERE sy.id = ?");
$stmt->execute([$syId]);
$syInfo = $stmt->fetch();

if (!$syInfo) { header('Location: non_adaptive.php'); exit; }
$subject  = $syInfo['subject_name'];
$catLabel = strtoupper($syInfo['category'] ?? '');
$yearLabel = $syInfo['year'] ?? '';

// ── Load questions in original order ─────────────────────────────────────
$stmt = $db->prepare("
    SELECT q.*, sy.year, sy.category
    FROM questions q
    JOIN subjectyear sy ON sy.id = q.subjectyear_id
    WHERE q.subjectyear_id = ? AND q.correct_option IS NOT NULL
    ORDER BY q.id
");
$stmt->execute([$syId]);
$questions = $stmt->fetchAll();

$sysSettings = getSystemSettings();
$hide2024 = $sysSettings['hide_2024'] ?? '0';
$only2024 = $sysSettings['only_2024'] ?? '0';

$filteredQuestions = [];
foreach ($questions as $q) {
    $qYear = trim($q['year']);
    if ($hide2024 === '1' && $qYear === '2024') {
        continue;
    }
    if ($only2024 === '1' && $qYear !== '2024') {
        continue;
    }
    $filteredQuestions[] = $q;
}
$questions = $filteredQuestions;
if (!$questions) {
    header('Location: non_adaptive.php?subject=' . urlencode($subject) . '&noq=1');
    exit;
}

// ── Resolve topic table ───────────────────────────────────────────────────
$topicTableEntry   = $topicTableMap[$subject] ?? false;
$topicTable        = null;
$isNewMappingTable = false;

if ($topicTableEntry !== null && $topicTableEntry !== false) {
    [$candidateTable, $tableStyle] = $topicTableEntry;
    try {
        $db->query("SELECT 1 FROM `$candidateTable` LIMIT 1");
        $topicTable        = $candidateTable;
        $isNewMappingTable = ($tableStyle === 'new');
    } catch (Exception $e) {
        $subjectSlug = strtolower(preg_replace('/\s+/', '_', $subject));
        $altTable    = ($tableStyle === 'new')
            ? $subjectSlug . '_topic_mapping'
            : 'mapping_' . $subjectSlug;
        try {
            $db->query("SELECT 1 FROM `$altTable` LIMIT 1");
            $topicTable        = $altTable;
            $isNewMappingTable = ($tableStyle !== 'new');
        } catch (Exception $e2) { $topicTable = null; }
    }
}

if ($topicTable && !$isNewMappingTable) {
    try {
        $colCheck = $db->query("SHOW COLUMNS FROM `$topicTable` LIKE 'best_topic_name'");
        if ($colCheck->rowCount() > 0) $isNewMappingTable = true;
    } catch (Exception $e) {}
}

// ── Build topic map for all questions ────────────────────────────────────
$qIds     = array_column($questions, 'id');
$topicMap = [];
if ($qIds && $topicTable) {
    $placeholders = implode(',', array_fill(0, count($qIds), '?'));
    $topicCol = $isNewMappingTable
        ? "COALESCE(NULLIF(TRIM(best_topic_name),''), 'General')"
        : "COALESCE(NULLIF(TRIM(best_subtopic),''), NULLIF(TRIM(best_topic),''), 'General')";
    $tStmt = $db->prepare("SELECT question_id, $topicCol AS topic
        FROM `$topicTable` WHERE question_id IN ($placeholders)");
    $tStmt->execute($qIds);
    foreach ($tStmt->fetchAll() as $r) $topicMap[$r['question_id']] = $r['topic'];
}
foreach ($questions as $q) {
    if (empty($topicMap[$q['id']])) $topicMap[$q['id']] = $subject;
}

// ── Load stored mastery for weak-topic panel seed ─────────────────────────
$masteryStmt = $db->prepare("
    SELECT topic, mastery_score, total_attempted, total_correct
    FROM user_topic_performance
    WHERE user_id = ? AND subject_name = ?
    ORDER BY mastery_score ASC
    LIMIT 20
");
$masteryStmt->execute([$uid, $subject]);
$storedMastery = $masteryStmt->fetchAll();

$pageTitle = "Non-Adaptive: $subject $yearLabel — RBAPS";
$currentPage = 'non_adaptive';
include 'includes/header.php';
?>

<div class="quiz-container">

  <!-- Header row -->
  <div class="quiz-header">
    <a href="non_adaptive.php?subject=<?= urlencode($subject) ?>" class="btn btn-ghost btn-sm">← Back</a>
    <div class="quiz-progress"><div class="quiz-progress-fill" id="progressFill" style="width:0%"></div></div>
    <div style="display:flex;align-items:center;gap:.75rem">
      <div id="quizTimer" class="badge" style="background:rgba(255,77,109,0.1);color:var(--red);font-size:.9rem;font-weight:700;font-family:'JetBrains Mono',monospace;">
        <i class="fa-solid fa-stopwatch"></i> <span id="timeDisplay">00:00</span>
      </div>
      <span class="quiz-counter" id="qCounter">1 / <?= count($questions) ?></span>
      <button onclick="confirmEnd()" class="btn btn-danger btn-sm" id="endBtn">⏹ End Quiz</button>
    </div>
  </div>

  <!-- Meta badges -->
  <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap">
    <span class="badge badge-blue"><?= htmlspecialchars($subject) ?></span>
    <?php if ($catLabel): ?><span class="badge badge-purple"><?= $catLabel ?></span><?php endif; ?>
    <?php if ($yearLabel): ?><span class="badge badge-gold"><?= $yearLabel ?></span><?php endif; ?>
    <span class="badge" style="background:rgba(245,200,66,0.12);color:var(--gold)">
      <i class="fa-solid fa-calendar-days"></i> Year-Based Mode
    </span>
    <span class="badge" style="background:rgba(255,255,255,0.05);color:var(--text3)" id="sessionScore">Score: 0 / 0</span>
  </div>

  <!-- Question card -->
  <div class="question-card" id="questionCard">
    <div class="question-text" id="questionText">Loading…</div>
    <ul class="options-list" id="optionsList"></ul>
    <div class="feedback-panel" id="feedbackPanel" style="display:none"></div>
  </div>

  <!-- Action row -->
  <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem">
    <span id="questionNum" style="font-size:.8rem;color:var(--text3)"></span>
    <button class="btn btn-primary" id="nextBtn" style="display:none" onclick="nextQuestion()">
      Next Question →
    </button>
  </div>

  <!-- Results screen -->
  <div id="resultsScreen" style="display:none">
    <div class="card results-card">
      <div class="score-ring" id="scoreRing">
        <span class="score-text" id="scorePct">0%</span>
      </div>
      <h2 style="font-family:'DM Serif Display',serif;font-size:1.8rem;margin-bottom:.5rem" id="resultsTitle">Session Complete!</h2>
      <p style="color:var(--text2);margin-bottom:1.5rem" id="resultsSubtitle"></p>

      <!-- Weak topic panel — the key feature of this page -->
      <div id="weakTopicPanel" style="margin-bottom:2rem;text-align:left"></div>

      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
        <a href="non_adaptive.php?subject=<?= urlencode($subject) ?>" class="btn btn-primary">
          <i class="fa-solid fa-calendar-days"></i> Try Another Year
        </a>
        <a href="adaptive.php?subject=<?= urlencode($subject) ?>" class="btn btn-outline">
          <i class="fa-solid fa-robot"></i> Drill Weak Topics (Adaptive)
        </a>
        <a href="dashboard.php" class="btn btn-ghost">Dashboard</a>
      </div>

      <div id="reviewList" style="margin-top:2rem;text-align:left"></div>
    </div>
  </div>

</div>

<script>
// ── PHP data ────────────────────────────────────────────────────────────────
const QUESTIONS     = <?= json_encode($questions, JSON_HEX_TAG) ?>;
const TOPIC_MAP     = <?= json_encode($topicMap,   JSON_HEX_TAG) ?>;
const SUBJECT       = <?= json_encode($subject) ?>;
const STORED_MASTERY = <?= json_encode($storedMastery, JSON_HEX_TAG) ?>;  // prior mastery rows

// ── State ────────────────────────────────────────────────────────────────────
let current   = 0;
let correct   = 0;
let attempted = 0;
let answered  = false;
const results = [];
const topicStats = {};   // topic → { correct, attempted } — this session only

// ── Timer Logic ──────────────────────────────────────────────────────────────
let timeLeft = QUESTIONS.length * 60; // 1 minute per question
let timerInterval = null;

function formatTime(seconds) {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
}

function updateTimer() {
  const tDisp = document.getElementById('timeDisplay');
  if (tDisp) tDisp.textContent = formatTime(timeLeft);
  
  if (timeLeft <= 0) {
    clearInterval(timerInterval);
    alert("Time is up! Your session will now end.");
    endSession();
  } else {
    timeLeft--;
  }
}

// ── DOM ──────────────────────────────────────────────────────────────────────
const qText    = document.getElementById('questionText');
const optsList = document.getElementById('optionsList');
const feedPanel= document.getElementById('feedbackPanel');
const nextBtn  = document.getElementById('nextBtn');
const progFill = document.getElementById('progressFill');
const qCounter = document.getElementById('qCounter');
const sessScore= document.getElementById('sessionScore');
const qNum     = document.getElementById('questionNum');

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Confirm end ──────────────────────────────────────────────────────────────
function confirmEnd() {
  if (attempted === 0 || confirm('End the quiz now? Your progress will be saved.')) {
    endSession();
  }
}

// ── Render question ──────────────────────────────────────────────────────────
function renderQuestion() {
  if (current >= QUESTIONS.length) { endSession(); return; }
  answered = false;
  feedPanel.style.display = 'none';
  feedPanel.className = 'feedback-panel';
  nextBtn.style.display = 'none';
  optsList.innerHTML = '';

  const q = QUESTIONS[current];
  progFill.style.width = (current / QUESTIONS.length * 100) + '%';
  qCounter.textContent = (current + 1) + ' / ' + QUESTIONS.length;
  qNum.textContent = 'Question ' + (current + 1) + '  ·  Topic: ' + (TOPIC_MAP[q.id] || SUBJECT);
  sessScore.textContent = 'Score: ' + correct + ' / ' + attempted;

  qText.textContent = q.question;

  const opts = [
    { key: 'A', text: q.option_a },
    { key: 'B', text: q.option_b },
    { key: 'C', text: q.option_c },
    { key: 'D', text: q.option_d },
  ].filter(o => o.text && o.text.trim());

  opts.forEach(opt => {
    const li = document.createElement('li');
    li.className = 'option-item';
    li.dataset.key = opt.key;
    li.innerHTML = `<span class="option-label">${opt.key}</span>
                    <span class="option-text">${escHtml(opt.text)}</span>`;
    li.addEventListener('click', () => selectOption(opt.key, q));
    optsList.appendChild(li);
  });
}

// ── Select answer ────────────────────────────────────────────────────────────
function selectOption(chosen, q) {
  if (answered) return;
  answered = true;
  attempted++;

  const isCorrect = chosen === q.correct_option;
  if (isCorrect) correct++;

  const topic = TOPIC_MAP[q.id] || SUBJECT;
  if (!topicStats[topic]) topicStats[topic] = { correct: 0, attempted: 0 };
  topicStats[topic].attempted++;
  if (isCorrect) topicStats[topic].correct++;

  document.querySelectorAll('.option-item').forEach(li => {
    li.classList.add('disabled');
    if (li.dataset.key === chosen)
      li.classList.add(isCorrect ? 'correct' : 'wrong');
    if (li.dataset.key === q.correct_option && !isCorrect)
      li.classList.add('correct');
  });

  const correctText = q['option_' + q.correct_option.toLowerCase()] || '';
  feedPanel.className = 'feedback-panel show ' + (isCorrect ? 'correct-fb' : 'wrong-fb');

  const conceptNote = !isCorrect
    ? `<div style="margin-top:.6rem;padding:.5rem .75rem;border-radius:8px;background:rgba(255,255,255,0.04);font-size:.82rem;color:var(--text2)">
         <strong><i class="fa-solid fa-lightbulb" style="color:var(--gold)"></i> Concept missed:</strong> <em>${escHtml(topic)}</em><br>
         Review this topic — it will appear in your weakness report.
       </div>`
    : '';

  feedPanel.innerHTML = isCorrect
    ? `<div class="feedback-title"><i class="fa-solid fa-circle-check" style="color:var(--green)"></i> Correct!</div>
       <div class="feedback-explanation">The answer is <strong>${escHtml(q.correct_option)}: ${escHtml(correctText)}</strong>.</div>`
    : `<div class="feedback-title"><i class="fa-solid fa-circle-xmark" style="color:var(--red)"></i> Incorrect</div>
       <div class="feedback-explanation">The correct answer is <strong>${escHtml(q.correct_option)}: ${escHtml(correctText)}</strong>.</div>
       ${conceptNote}`;

  feedPanel.style.display = 'block';
  nextBtn.style.display = 'inline-flex';
  sessScore.textContent = 'Score: ' + correct + ' / ' + attempted;
  results.push({ q, chosen, isCorrect, topic });

  fetch('api/answer.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ question_id: q.id, chosen, is_correct: isCorrect ? 1 : 0, subject: SUBJECT })
  }).catch(() => {});
}

// ── Next question ────────────────────────────────────────────────────────────
function nextQuestion() {
  if (!answered) return;
  current++;
  renderQuestion();
}

// ── End session ──────────────────────────────────────────────────────────────
function endSession() {
  if (timerInterval) clearInterval(timerInterval);
  const endBtn = document.getElementById('endBtn');
  if (endBtn) endBtn.style.display = 'none';
  document.getElementById('quizTimer').style.display = 'none';
  document.getElementById('questionCard').style.display = 'none';
  document.querySelector('.quiz-header').style.display = 'none';
  document.querySelectorAll('.quiz-container > div').forEach(el => {
    if (el.id !== 'resultsScreen') el.style.display = 'none';
  });

  const pct  = attempted > 0 ? Math.round(correct / attempted * 100) : 0;
  const ring = document.getElementById('scoreRing');
  ring.style.setProperty('--pct', pct + '%');
  ring.querySelector('.score-text').textContent = pct + '%';

  const title = document.getElementById('resultsTitle');
  const sub   = document.getElementById('resultsSubtitle');
  if      (pct >= 80) title.innerHTML = '<i class="fa-solid fa-star" style="color:var(--gold)"></i> Excellent Work!';
  else if (pct >= 60) title.innerHTML = '<i class="fa-solid fa-thumbs-up" style="color:var(--green)"></i> Good Effort!';
  else if (pct >= 40) title.innerHTML = '<i class="fa-solid fa-book-open" style="color:var(--accent)"></i> Keep Practising';
  else                title.innerHTML = '<i class="fa-solid fa-fist-raised" style="color:var(--red)"></i> Don\'t Give Up!';
  sub.textContent = `You got ${correct} out of ${attempted} correct — ${pct}% — ${SUBJECT} ${<?= json_encode($catLabel) ?>} <?= htmlspecialchars($yearLabel) ?>`;

  // ── Build weak topic panel ──────────────────────────────────────────────
  buildWeakTopicPanel(pct);

  document.getElementById('resultsScreen').style.display = 'block';

  // Save session
  fetch('api/save_session.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      subject: SUBJECT, correct, total_q: attempted, score_pct: pct,
      category: <?= json_encode($catLabel) ?>,
      year: <?= json_encode($yearLabel) ?>
    })
  }).catch(() => {});

  // Save per-topic mastery
  const masteryPayload = Object.entries(topicStats).map(([topic, s]) => ({
    topic,
    score:       (s.correct / s.attempted) * 100,
    attempted:   s.attempted,
    correct:     s.correct,
    consecutive: 0,
    difficulty:  'easy'
  }));
  if (masteryPayload.length) {
    fetch('api/save_topic_mastery.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ subject: SUBJECT, mastery: masteryPayload })
    }).catch(() => {});
  }

  // Question review list
  const rev = document.getElementById('reviewList');
  if (results.length) {
    rev.innerHTML = '<h3 style="font-size:.95rem;font-weight:700;margin-bottom:1rem"><i class="fa-solid fa-list-check"></i> Question Review</h3>';
    results.forEach((r, i) => {
      const ans = r.q['option_' + r.q.correct_option.toLowerCase()] || r.q.correct_option;
      rev.innerHTML += `<div style="padding:.75rem 1rem;border-radius:10px;margin-bottom:.6rem;
        background:${r.isCorrect ? 'rgba(0,200,150,0.06)' : 'rgba(255,77,109,0.06)'};
        border:1px solid ${r.isCorrect ? 'rgba(0,200,150,0.2)' : 'rgba(255,77,109,0.15)'}">
        <div style="font-size:.75rem;color:var(--text3);margin-bottom:.2rem">Topic: ${escHtml(r.topic)}</div>
        <div style="font-size:.85rem;font-weight:600;margin-bottom:.3rem">
          ${r.isCorrect
            ? '<i class="fa-solid fa-circle-check" style="color:var(--green)"></i>'
            : '<i class="fa-solid fa-circle-xmark" style="color:var(--red)"></i>'}
          Q${i + 1}: ${escHtml(r.q.question.slice(0, 120))}${r.q.question.length > 120 ? '…' : ''}
        </div>
        ${!r.isCorrect ? `<div style="font-size:.8rem;color:var(--green)">✓ Correct: ${escHtml(ans)}</div>` : ''}
      </div>`;
    });
  }
}

/**
 * Build the weak topic weakness report panel.
 *
 * Priority 1 — Topics failed THIS session (< 60% this session)
 * Priority 2 — Topics with stored mastery < 60% from past sessions
 *              that weren't even tested today (still need attention)
 */
function buildWeakTopicPanel(sessionPct) {
  const el = document.getElementById('weakTopicPanel');

  // This-session topic mastery
  const sessionTopics = Object.entries(topicStats).map(([topic, s]) => ({
    topic,
    score:     Math.round((s.correct / s.attempted) * 100),
    correct:   s.correct,
    attempted: s.attempted,
    source:    'session'
  }));

  // Stored mastery — only keep topics not practised today
  const testedTopics = new Set(Object.keys(topicStats));
  const storedWeak   = STORED_MASTERY
    .filter(r => !testedTopics.has(r.topic) && parseFloat(r.mastery_score) < 60)
    .map(r => ({
      topic:     r.topic,
      score:     Math.round(parseFloat(r.mastery_score)),
      correct:   r.total_correct,
      attempted: r.total_attempted,
      source:    'history'
    }));

  const weak   = sessionTopics.filter(t => t.score < 60).sort((a, b) => a.score - b.score);
  const medium = sessionTopics.filter(t => t.score >= 60 && t.score < 80);
  const strong = sessionTopics.filter(t => t.score >= 80);

  let html = '';

  // ── SESSION weaknesses ────────────────────────────────────────────────────
  if (weak.length) {
    html += `<div style="margin-bottom:1.1rem;padding:1rem 1.1rem;border-radius:14px;
        background:rgba(255,77,109,0.08);border:1px solid rgba(255,77,109,0.25)">
      <div style="font-weight:700;font-size:.9rem;color:var(--red);margin-bottom:.6rem">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Topics to Focus On — This Session (below 60%)
      </div>
      <div style="font-size:.78rem;color:var(--text2);margin-bottom:.75rem">
        You scored below 60% on these topics in today's year-based session:
      </div>
      <div style="display:flex;flex-direction:column;gap:.4rem">
        ${weak.map(t => topicRow(t)).join('')}
      </div>
    </div>`;
  }

  // ── HISTORICAL weaknesses (topics not practised today) ───────────────────
  if (storedWeak.length) {
    html += `<div style="margin-bottom:1.1rem;padding:1rem 1.1rem;border-radius:14px;
        background:rgba(245,200,66,0.07);border:1px solid rgba(245,200,66,0.22)">
      <div style="font-weight:700;font-size:.9rem;color:var(--gold);margin-bottom:.6rem">
        <i class="fa-solid fa-clock-rotate-left"></i>
        Unaddressed Weak Topics — From Previous Sessions
      </div>
      <div style="font-size:.78rem;color:var(--text2);margin-bottom:.75rem">
        These topics were not tested today but still need work based on your history:
      </div>
      <div style="display:flex;flex-direction:column;gap:.4rem">
        ${storedWeak.slice(0, 8).map(t => topicRow(t, true)).join('')}
      </div>
    </div>`;
  }

  // ── Adaptive drill CTA ────────────────────────────────────────────────────
  if (weak.length > 0 || storedWeak.length > 0) {
    html += `<div style="margin-bottom:1.1rem;padding:.85rem 1rem;border-radius:12px;
        background:rgba(79,142,247,0.07);border:1px solid rgba(79,142,247,0.22)">
      <div style="font-weight:700;font-size:.88rem;color:var(--accent);margin-bottom:.4rem">
        <i class="fa-solid fa-robot"></i> Drill Your Weaknesses with Adaptive Mode
      </div>
      <div style="font-size:.82rem;color:var(--text2)">
        The adaptive engine will automatically prioritise your weak topics,
        adjust difficulty, and pull questions from all years — not just one.
      </div>
      <a href="adaptive.php?subject=<?= urlencode($subject) ?>"
         class="btn btn-primary btn-sm" style="margin-top:.75rem;display:inline-flex">
        <i class="fa-solid fa-rocket"></i> Start Adaptive Mode
      </a>
    </div>`;
  }

  // ── Full topic breakdown grid ─────────────────────────────────────────────
  if (sessionTopics.length) {
    html += `<h3 style="font-size:.9rem;font-weight:700;margin-bottom:.65rem">
      <i class="fa-solid fa-chart-bar"></i> Topic Breakdown — This Session
    </h3>`;
    html += `<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.55rem">`;
    [...sessionTopics].sort((a, b) => b.score - a.score).forEach(t => {
      const color = t.score >= 80 ? 'var(--green)' : t.score >= 60 ? 'var(--gold)' : t.score >= 40 ? 'var(--accent)' : 'var(--red)';
      const badge = t.score >= 60
        ? '<i class="fa-solid fa-circle-check" style="color:var(--green)"></i>'
        : '<i class="fa-solid fa-triangle-exclamation" style="color:var(--red)"></i>';
      const shortT = t.topic.length > 32 ? t.topic.slice(0, 32) + '…' : t.topic;
      html += `<div style="padding:.6rem .8rem;border-radius:10px;background:var(--bg3);border:1px solid var(--border)">
        <div style="font-size:.8rem;font-weight:600;margin-bottom:.25rem" title="${escHtml(t.topic)}">${badge} ${escHtml(shortT)}</div>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="font-family:'JetBrains Mono',monospace;color:${color};font-weight:700;font-size:.9rem">${t.score}%</span>
          <span style="font-size:.72rem;color:var(--text3)">${t.correct}/${t.attempted}</span>
        </div>
        <div style="height:3px;background:var(--card2);border-radius:999px;margin-top:.35rem;overflow:hidden">
          <div style="height:100%;width:${t.score}%;background:${color};border-radius:999px"></div>
        </div>
      </div>`;
    });
    html += '</div>';
  }

  el.innerHTML = html || '';
}

function topicRow(t, historical = false) {
  const color = t.score < 30 ? 'var(--red)' : t.score < 50 ? '#f5a623' : 'var(--gold)';
  const histBadge = historical
    ? `<span style="font-size:.68rem;background:rgba(245,200,66,0.15);color:var(--gold);border-radius:4px;padding:1px 5px;margin-left:.35rem">past</span>`
    : '';
  return `<div style="display:flex;align-items:center;gap:.75rem;padding:.45rem .75rem;
      border-radius:8px;background:rgba(255,255,255,0.04)">
    <span style="font-size:.8rem;font-weight:700;color:${color};min-width:38px">${t.score}%</span>
    <div style="flex:1">
      <div style="font-size:.82rem;font-weight:600;color:var(--text1)">${escHtml(t.topic)}${histBadge}</div>
      <div style="height:3px;background:var(--card2);border-radius:999px;margin-top:3px;overflow:hidden">
        <div style="height:100%;width:${t.score}%;background:${color};border-radius:999px"></div>
      </div>
    </div>
    <span style="font-size:.72rem;color:var(--text3)">${t.correct}/${t.attempted}</span>
  </div>`;
}

// ── Start ────────────────────────────────────────────────────────────────────
renderQuestion();
timerInterval = setInterval(updateTimer, 1000);
updateTimer();
</script>

<?php include 'includes/footer.php'; ?>
