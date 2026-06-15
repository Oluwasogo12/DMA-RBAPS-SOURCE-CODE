<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];

$syId     = intval($_GET['sy'] ?? 0);
$subject  = trim($_GET['subject'] ?? '');
$adaptive = isset($_GET['adaptive']);

// ── Ensure supporting tables ───────────────────────────────────────────────
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
$db->exec("CREATE TABLE IF NOT EXISTS user_answers (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, session_id INT,
    question_id INT NOT NULL, chosen VARCHAR(2), is_correct TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

// ── Map subject → topic table ──────────────────────────────────────────────
// New-style tables have best_topic_name column; legacy have best_topic/best_subtopic.
$topicTableMap = [
    'Chemistry'           => ['mapping_chemistry',    'new'],
    'Physics'             => ['mapping_physics',      'new'],
    'Mathematics'         => ['mapping_mathematics',  'new'],
    'Biology'             => ['mapping_biology',      'new'],
    'English'             => ['mapping_english',      'new'],
    'civic'               => ['mapping_civic',        'new'],
    'Economics'           => ['mapping_economics',    'new'],
    'Government'          => ['government_topic_mapping', 'legacy'],
    'History'             => ['history_topic_mapping',    'legacy'],
    'ICT'                 => ['ict_topic_mapping',        'legacy'],
    // Subjects with no dedicated mapping table — will fall through to subject-as-topic
    'Geography'           => null,
    'Commerce'            => null,
    'Technical Drawing'   => null,
    'Financial accounting'=> null,
];

// ── Resolve subjectyear info ───────────────────────────────────────────────
$syInfo = null;
if ($syId) {
    $stmt = $db->prepare("SELECT sy.*, sn.name as subject_name
        FROM subjectyear sy JOIN subjectname sn ON sn.id = sy.subjectnamrid WHERE sy.id = ?");
    $stmt->execute([$syId]);
    $syInfo = $stmt->fetch();
    if ($syInfo) $subject = $syInfo['subject_name'];
}
if (!$subject) { header('Location: practice.php'); exit; }

// ── Load questions ─────────────────────────────────────────────────────────
if ($adaptive || !$syId) {
    $stmt = $db->prepare("
        SELECT q.*, sy.year, sy.category
        FROM questions q
        JOIN subjectyear sy ON sy.id = q.subjectyear_id
        JOIN subjectname sn ON sn.id = sy.subjectnamrid
        WHERE sn.name = ? AND q.correct_option IS NOT NULL
        ORDER BY RAND() LIMIT 40");
    $stmt->execute([$subject]);
    $mode = 'adaptive'; $catLabel = 'Adaptive'; $yearLabel = 'Mixed Years';
} else {
    $stmt = $db->prepare("
        SELECT q.*, sy.year, sy.category
        FROM questions q
        JOIN subjectyear sy ON sy.id = q.subjectyear_id
        WHERE q.subjectyear_id = ? AND q.correct_option IS NOT NULL
        ORDER BY q.id");
    $stmt->execute([$syId]);
    $mode     = 'standard';
    $catLabel = strtoupper($syInfo['category'] ?? '');
    $yearLabel = $syInfo['year'] ?? '';
}
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
if (!$questions) { header('Location: practice.php?subject='.urlencode($subject).'&noq=1'); exit; }

// ── Load topic mapping for questions so we can assign topic per question ───
$topicTableEntry   = $topicTableMap[$subject] ?? false; // false = unknown subject
$topicTable        = null;
$isNewMappingTable = false;

if ($topicTableEntry !== null && $topicTableEntry !== false) {
    [$candidateTable, $tableStyle] = $topicTableEntry;
    try {
        $db->query("SELECT 1 FROM `$candidateTable` LIMIT 1");
        $topicTable        = $candidateTable;
        $isNewMappingTable = ($tableStyle === 'new');
    } catch(Exception $e) {
        // Preferred table missing — try auto-detecting alternate name
        $subjectSlug = strtolower(preg_replace('/\s+/', '_', $subject));
        $altTable    = ($tableStyle === 'new')
            ? $subjectSlug . '_topic_mapping'
            : 'mapping_' . $subjectSlug;
        try {
            $db->query("SELECT 1 FROM `$altTable` LIMIT 1");
            $topicTable        = $altTable;
            $isNewMappingTable = ($tableStyle !== 'new'); // flipped
        } catch(Exception $e2) { $topicTable = null; }
    }
}

// Final column-level check in case style was mis-detected
if ($topicTable && !$isNewMappingTable) {
    try {
        $colCheck = $db->query("SHOW COLUMNS FROM `$topicTable` LIKE 'best_topic_name'");
        if ($colCheck->rowCount() > 0) $isNewMappingTable = true;
    } catch(Exception $e) {}
}

$qIds     = array_column($questions, 'id');
$topicMap = []; // question_id → topic string
if ($qIds && $topicTable) {
    $placeholders = implode(',', array_fill(0, count($qIds), '?'));
    $topicCol = $isNewMappingTable
        ? "COALESCE(NULLIF(TRIM(best_topic_name),''), 'General')"
        : "COALESCE(NULLIF(TRIM(best_subtopic),''), NULLIF(TRIM(best_topic),''), 'General')";
    $tStmt = $db->prepare("SELECT question_id, $topicCol AS topic
        FROM `$topicTable` WHERE question_id IN ($placeholders)");
    $tStmt->execute($qIds);
    foreach($tStmt->fetchAll() as $r) $topicMap[$r['question_id']] = $r['topic'];
}
// Fill any un-mapped questions — use subject name so adaptive engine still works
foreach($questions as $q) {
    if (empty($topicMap[$q['id']])) $topicMap[$q['id']] = $subject;
}

$pageTitle = "Practice: $subject — RBAPS";
include 'includes/header.php';
?>

<div class="quiz-container">

  <!-- Header row -->
  <div class="quiz-header">
    <a href="practice.php?subject=<?= urlencode($subject) ?>" class="btn btn-ghost btn-sm">← Back</a>
    <div class="quiz-progress"><div class="quiz-progress-fill" id="progressFill" style="width:0%"></div></div>
    <div style="display:flex;align-items:center;gap:.75rem">
      <span class="quiz-counter" id="qCounter">1 / <?= count($questions) ?></span>
      <button onclick="confirmEnd()" class="btn btn-danger btn-sm" id="endBtn">⏹ End Quiz</button>
    </div>
  </div>

  <!-- Meta badges -->
  <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap">
    <span class="badge badge-blue"><?= htmlspecialchars($subject) ?></span>
    <?php if($catLabel): ?><span class="badge badge-purple"><?= $catLabel ?></span><?php endif; ?>
    <?php if($yearLabel): ?><span class="badge badge-gold"><?= $yearLabel ?></span><?php endif; ?>
    <?php if($adaptive): ?><span class="badge badge-green"><i class="fa-solid fa-robot"></i> Adaptive Mode</span><?php endif; ?>
    <span class="badge" style="background:rgba(255,255,255,0.05);color:var(--text3)" id="sessionScore">Score: 0 / 0</span>
  </div>

  <!-- Rule indicator (hidden from students — internal engine logic) -->
  <div id="ruleIndicator" style="display:none !important;margin-bottom:1rem;padding:.65rem 1rem;border-radius:10px;font-size:.83rem"></div>

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

      <!-- Adaptive study recommendations (post-session) -->
      <div id="studyRecommendations" style="margin-bottom:2rem;text-align:left"></div>

      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
        <a href="practice.php?subject=<?= urlencode($subject) ?>" class="btn btn-primary">Practice Again</a>
        <a href="adaptive.php?subject=<?= urlencode($subject) ?>" class="btn btn-outline"><i class="fa-solid fa-robot"></i> Try Adaptive Mode</a>
        <a href="dashboard.php" class="btn btn-ghost">View Dashboard</a>
      </div>

      <div id="reviewList" style="margin-top:2rem;text-align:left"></div>
    </div>
  </div>

</div>

<script>
// ── Data from PHP ──────────────────────────────────────────────────────────
const QUESTIONS  = <?= json_encode($questions, JSON_HEX_TAG) ?>;
const TOPIC_MAP  = <?= json_encode($topicMap, JSON_HEX_TAG) ?>;   // qId → topic
const SUBJECT    = <?= json_encode($subject) ?>;
const MODE       = <?= json_encode($mode) ?>;

// ── State ──────────────────────────────────────────────────────────────────
let current   = 0;
let correct   = 0;
let attempted = 0;
let answered  = false;
const results = [];

// Per-topic tracking for post-session recommendations (Rule 1-3)
// topicStats[topic] = { correct, attempted }
const topicStats = {};

// Adaptive state
let consecutiveCorrect = 0;

// ── DOM refs ───────────────────────────────────────────────────────────────
const qText     = document.getElementById('questionText');
const optsList  = document.getElementById('optionsList');
const feedPanel = document.getElementById('feedbackPanel');
const nextBtn   = document.getElementById('nextBtn');
const progFill  = document.getElementById('progressFill');
const qCounter  = document.getElementById('qCounter');
const sessScore = document.getElementById('sessionScore');
const qNum      = document.getElementById('questionNum');

// ── Helpers ────────────────────────────────────────────────────────────────
function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showRule(name, msg, color) {
  // Rule indicators are hidden from students — engine runs silently
}

// ── End Quiz (with confirm) ────────────────────────────────────────────────
function confirmEnd() {
  if (attempted === 0 || confirm('End the quiz now? Your progress so far will be saved.')) {
    endSession();
  }
}

// ── Render question ────────────────────────────────────────────────────────
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
    { key:'A', text: q.option_a },
    { key:'B', text: q.option_b },
    { key:'C', text: q.option_c },
    { key:'D', text: q.option_d },
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

// ── Select answer (Rule 7 — Feedback) ─────────────────────────────────────
function selectOption(chosen, q) {
  if (answered) return;
  answered = true;
  attempted++;

  const isCorrect = chosen === q.correct_option;
  if (isCorrect) { correct++; consecutiveCorrect++; }
  else           { consecutiveCorrect = 0; }

  // Track per-topic performance for post-session recommendations
  const topic = TOPIC_MAP[q.id] || SUBJECT;
  if (!topicStats[topic]) topicStats[topic] = { correct: 0, attempted: 0 };
  topicStats[topic].attempted++;
  if (isCorrect) topicStats[topic].correct++;

  // Style options
  document.querySelectorAll('.option-item').forEach(li => {
    li.classList.add('disabled');
    if (li.dataset.key === chosen)
      li.classList.add(isCorrect ? 'correct' : 'wrong');
    if (li.dataset.key === q.correct_option && !isCorrect)
      li.classList.add('correct');
  });

  // Rule 2 — Progressive Challenge notification
  if (consecutiveCorrect === 3) {
    showRule('Rule 2 — Progressive Challenge',
      '3 correct answers in a row! Keep it up — difficulty is advancing.', 'accent');
  }

  // Rule 7 — Feedback Generation
  const correctText = q['option_' + q.correct_option.toLowerCase()] || '';
  feedPanel.className = 'feedback-panel show ' + (isCorrect ? 'correct-fb' : 'wrong-fb');

  const conceptNote = !isCorrect
    ? `<div style="margin-top:.6rem;padding:.5rem .75rem;border-radius:8px;background:rgba(255,255,255,0.04);font-size:.82rem;color:var(--text2)">
         <strong><i class="fa-solid fa-lightbulb" style="color:var(--gold)"></i> Concept missed:</strong> <em>${escHtml(topic)}</em><br>
         Review this topic to strengthen your understanding.
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
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ question_id: q.id, chosen, is_correct: isCorrect ? 1 : 0, subject: SUBJECT })
  }).catch(() => {});
}

// ── Next question ──────────────────────────────────────────────────────────
function nextQuestion() {
  if (!answered) return;
  current++;
  renderQuestion();
}

// ── End session ────────────────────────────────────────────────────────────
function endSession() {
  const endBtn = document.getElementById('endBtn');
  if (endBtn) endBtn.style.display = 'none';
  document.getElementById('questionCard').style.display = 'none';
  document.querySelector('.quiz-header').style.display = 'none';
  document.querySelectorAll('.quiz-container > div').forEach(el => {
    if (el.id !== 'resultsScreen') el.style.display = 'none';
  });

  const pct = attempted > 0 ? Math.round(correct / attempted * 100) : 0;
  const ring = document.getElementById('scoreRing');
  ring.style.setProperty('--pct', pct + '%');
  ring.querySelector('.score-text').textContent = pct + '%';

  const title = document.getElementById('resultsTitle');
  const sub   = document.getElementById('resultsSubtitle');
  if      (pct >= 80) { title.innerHTML = '<i class="fa-solid fa-star" style="color:var(--gold)"></i> Excellent Work!'; }
  else if (pct >= 60) { title.innerHTML = '<i class="fa-solid fa-thumbs-up" style="color:var(--green)"></i> Good Effort!'; }
  else if (pct >= 40) { title.innerHTML = '<i class="fa-solid fa-book-open" style="color:var(--accent)"></i> Keep Practising'; }
  else                { title.innerHTML = '<i class="fa-solid fa-fist-raised" style="color:var(--red)"></i> Don\'t Give Up!'; }
  sub.textContent = `You got ${correct} out of ${attempted} correct — ${pct}% — in ${SUBJECT}`;

  // ── Post-session Adaptive Study Recommendations (Rules 1, 3) ─────────────
  buildStudyRecommendations(pct);

  document.getElementById('resultsScreen').style.display = 'block';

  // Save session
  fetch('api/save_session.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      subject: SUBJECT, correct, total_q: attempted, score_pct: pct,
      category: <?= json_encode($catLabel) ?>,
      year: <?= json_encode($yearLabel) ?>
    })
  }).catch(() => {});

  // Save per-topic mastery to DB so adaptive.php can pick up where we left off
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
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ subject: SUBJECT, mastery: masteryPayload })
    }).catch(() => {});
  }

  // Review list
  const rev = document.getElementById('reviewList');
  if (results.length) {
    rev.innerHTML = '<h3 style="font-size:.95rem;font-weight:700;margin-bottom:1rem"><i class="fa-solid fa-list-check"></i> Question Review</h3>';
    results.forEach((r, i) => {
      const ans = r.q['option_' + r.q.correct_option.toLowerCase()] || r.q.correct_option;
      rev.innerHTML += `<div style="padding:.75rem 1rem;border-radius:10px;margin-bottom:.6rem;
        background:${r.isCorrect?'rgba(0,200,150,0.06)':'rgba(255,77,109,0.06)'};
        border:1px solid ${r.isCorrect?'rgba(0,200,150,0.2)':'rgba(255,77,109,0.15)'}">
        <div style="font-size:.75rem;color:var(--text3);margin-bottom:.2rem">Topic: ${escHtml(r.topic)}</div>
        <div style="font-size:.85rem;font-weight:600;margin-bottom:.3rem">${r.isCorrect?'<i class="fa-solid fa-circle-check" style="color:var(--green)"></i>':'<i class="fa-solid fa-circle-xmark" style="color:var(--red)"></i>'} Q${i+1}: ${escHtml(r.q.question.slice(0,120))}${r.q.question.length>120?'…':''}</div>
        ${!r.isCorrect ? `<div style="font-size:.8rem;color:var(--green)">✓ Correct: ${escHtml(ans)}</div>` : ''}
      </div>`;
    });
  }
}

/**
 * Build the post-session adaptive study recommendations panel.
 * Rule 1 — topics below 60% mastery → flag as priority study
 * Rule 3 — exhausted topics (attempted but still < 60%) → recommend adaptive mode
 */
function buildStudyRecommendations(sessionPct) {
  const el = document.getElementById('studyRecommendations');
  const topicEntries = Object.entries(topicStats);
  if (!topicEntries.length) { el.style.display = 'none'; return; }

  // Calculate mastery per topic
  const topicMastery = topicEntries.map(([topic, s]) => ({
    topic,
    score:    Math.round((s.correct / s.attempted) * 100),
    correct:  s.correct,
    attempted: s.attempted
  })).sort((a, b) => a.score - b.score);

  const weak   = topicMastery.filter(t => t.score < 60);
  const medium = topicMastery.filter(t => t.score >= 60 && t.score < 80);
  const strong = topicMastery.filter(t => t.score >= 80);

  let html = '';

  // Rule 1 — Priority Study (Remedial Focus)
  if (weak.length) {
    html += `<div style="margin-bottom:1rem;padding:.85rem 1rem;border-radius:12px;
      background:rgba(255,77,109,0.08);border:1px solid rgba(255,77,109,0.25)">
      <div style="font-weight:700;font-size:.9rem;color:var(--red);margin-bottom:.5rem">
        <i class="fa-solid fa-thumbtack" style="color:var(--red)"></i> Topics to Focus On (below 60% mastery)
      </div>
      <div style="font-size:.82rem;color:var(--text2);margin-bottom:.65rem">
        Focus on these topics first in your next practice session:
      </div>
      <div style="display:flex;flex-direction:column;gap:.4rem">
        ${weak.map(t => `
          <div style="display:flex;align-items:center;gap:.6rem;padding:.4rem .6rem;border-radius:8px;background:rgba(255,255,255,0.04)">
            <span style="font-size:.8rem;color:var(--red);font-weight:700;min-width:36px">${t.score}%</span>
            <div style="flex:1">
              <div style="font-size:.83rem;font-weight:600">${escHtml(t.topic)}</div>
              <div style="height:3px;background:var(--card2);border-radius:999px;margin-top:3px;overflow:hidden">
                <div style="height:100%;width:${t.score}%;background:var(--red);border-radius:999px"></div>
              </div>
            </div>
            <span style="font-size:.75rem;color:var(--text3)">${t.correct}/${t.attempted}</span>
          </div>`).join('')}
      </div>
    </div>`;
  }

  // Rule 3 — Mastery Reinforcement: topics that need more questions
  if (weak.length > 0) {
    html += `<div style="margin-bottom:1rem;padding:.75rem 1rem;border-radius:12px;
      background:rgba(245,200,66,0.08);border:1px solid rgba(245,200,66,0.25)">
      <div style="font-weight:700;font-size:.88rem;color:var(--gold);margin-bottom:.4rem">
        🔄 Keep Practising — More Questions Available
      </div>
      <div style="font-size:.82rem;color:var(--text2)">
        You've exhausted some questions in weak topics. Switch to
        <a href="adaptive.php?subject=<?= urlencode($subject) ?>" style="color:var(--accent);font-weight:600">
        <i class="fa-solid fa-robot"></i> Adaptive Mode</a> to automatically fetch related questions and drill these areas further.
      </div>
    </div>`;
  }

  // Topic breakdown grid
  html += `<h3 style="font-size:.9rem;font-weight:700;margin-bottom:.65rem"><i class="fa-solid fa-chart-bar"></i> Topic Breakdown</h3>`;
  html += `<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.55rem">`;
  topicMastery.sort((a,b) => b.score - a.score).forEach(t => {
    const color = t.score >= 80 ? 'var(--green)' : t.score >= 60 ? 'var(--gold)' : t.score >= 40 ? 'var(--accent)' : 'var(--red)';
    const badge = t.score >= 60 ? '<i class="fa-solid fa-circle-check" style="color:var(--green)"></i>' : '<i class="fa-solid fa-triangle-exclamation" style="color:var(--red)"></i>';
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

  el.innerHTML = html;
}

// ── Start ──────────────────────────────────────────────────────────────────
renderQuestion();
</script>

<?php include 'includes/footer.php'; ?>
