<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];

// ── Ensure tables ──────────────────────────────────────────────────────────
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

$subject = trim($_GET['subject'] ?? '');
if (!$subject) { header('Location: practice.php'); exit; }

$syId = intval($_GET['sy'] ?? 0);
$targetCategory = null;
$targetYearName = null;

if ($syId) {
    $syStmt = $db->prepare("SELECT year, category FROM subjectyear WHERE id = ?");
    $syStmt->execute([$syId]);
    $syRow = $syStmt->fetch();
    if ($syRow) {
        $targetCategory = $syRow['category'];
        $targetYearName = $syRow['year'];
    }
}

// ── Load existing mastery data for this student/subject ──────────────────
$masteryRows = $db->prepare("SELECT topic, mastery_score, total_attempted, total_correct,
    consecutive_correct, difficulty_level FROM user_topic_performance
    WHERE user_id=? AND subject_name=?");
$masteryRows->execute([$uid, $subject]);
$topicMastery = [];
foreach($masteryRows->fetchAll() as $row) {
    $topicMastery[$row['topic']] = $row;
}

// ── Map subject name → topic mapping table ────────────────────────────────
// New-style tables (have best_topic_name column):
//   mapping_biology, mapping_chemistry, mapping_civic, mapping_economics,
//   mapping_english, mapping_mathematics, mapping_physics
// Legacy-style tables (have best_topic / best_subtopic columns):
//   civic_topic_mapping, english_topic_mapping, government_topic_mapping,
//   history_topic_mapping, ict_topic_mapping, mathematics_topic_mapping,
//   physics_topic_mapping
// Priority: always prefer the new mapping_ tables when available.
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
    // Subjects with no dedicated mapping table yet — will use subject name as topic
    'Financial accounting' => ['mapping_financial_accounting', 'new'],
    'Geography'           => ['mapping_geography',    'new'],
    'Commerce'            => null,
    'Technical Drawing'   => null,
];

$topicTableEntry = $topicTableMap[$subject] ?? false; // false = not in map at all
$topicTable      = null;
$isNewMappingTable = false;

if ($topicTableEntry !== null && $topicTableEntry !== false) {
    [$candidateTable, $tableStyle] = $topicTableEntry;
    try {
        $db->query("SELECT 1 FROM `$candidateTable` LIMIT 1");
        $topicTable        = $candidateTable;
        $isNewMappingTable = ($tableStyle === 'new');
    } catch(Exception $e) {
        // Table doesn't exist in this DB — try the other style as fallback
        $fallbackStyle = ($tableStyle === 'new') ? 'legacy' : 'new';
        // Build a fallback table name by convention
        $subjectSlug = strtolower(preg_replace('/\s+/', '_', $subject));
        $legacyName  = $subjectSlug . '_topic_mapping';
        $newName     = 'mapping_' . $subjectSlug;
        $tryName     = ($fallbackStyle === 'legacy') ? $legacyName : $newName;
        try {
            $db->query("SELECT 1 FROM `$tryName` LIMIT 1");
            $topicTable        = $tryName;
            $isNewMappingTable = ($fallbackStyle === 'new');
        } catch(Exception $e2) { $topicTable = null; }
    }
}

// If we have a table but haven't verified its style yet, double-check columns
if ($topicTable && !$isNewMappingTable) {
    try {
        $colCheck = $db->query("SHOW COLUMNS FROM `$topicTable` LIKE 'best_topic_name'");
        if ($colCheck->rowCount() > 0) $isNewMappingTable = true;
    } catch(Exception $e) {}
}

// ── Build question pool grouped by topic — fetch MORE so queues don't run dry ──
// We fetch ALL available questions (no LIMIT), grouped by topic so each topic
// has a healthy pool. We'll cap the session at MAX_QUESTIONS in JS.
if ($topicTable && $isNewMappingTable) {
    // New mapping_ tables: use best_topic_name column
    $stmt = $db->prepare("
        SELECT q.id, q.question, q.option_a, q.option_b, q.option_c, q.option_d,
               q.correct_option, sy.year, sy.category, sy.id AS sy_id,
               COALESCE(NULLIF(TRIM(tm.best_topic_name),''), 'General') AS topic
        FROM questions q
        JOIN subjectyear sy ON sy.id = q.subjectyear_id
        JOIN subjectname sn ON sn.id = sy.subjectnamrid
        LEFT JOIN `$topicTable` tm ON tm.question_id = q.id
        WHERE sn.name = ? AND q.correct_option IS NOT NULL
        ORDER BY RAND()
        LIMIT 1000
    ");
    $stmt->execute([$subject]);
} elseif ($topicTable) {
    // Legacy tables: use best_subtopic first, then best_topic
    $stmt = $db->prepare("
        SELECT q.id, q.question, q.option_a, q.option_b, q.option_c, q.option_d,
               q.correct_option, sy.year, sy.category, sy.id AS sy_id,
               COALESCE(NULLIF(TRIM(tm.best_subtopic),''), NULLIF(TRIM(tm.best_topic),''), 'General') AS topic
        FROM questions q
        JOIN subjectyear sy ON sy.id = q.subjectyear_id
        JOIN subjectname sn ON sn.id = sy.subjectnamrid
        LEFT JOIN `$topicTable` tm ON tm.question_id = q.id
        WHERE sn.name = ? AND q.correct_option IS NOT NULL
        ORDER BY RAND()
        LIMIT 1000
    ");
    $stmt->execute([$subject]);
} else {
    // No mapping table — fetch questions tagged with subject name as the single topic.
    // Adaptive engine will still work; it just treats the whole subject as one topic.
    $stmt = $db->prepare("
        SELECT q.id, q.question, q.option_a, q.option_b, q.option_c, q.option_d,
               q.correct_option, sy.year, sy.category, sy.id AS sy_id,
               ? AS topic
        FROM questions q
        JOIN subjectyear sy ON sy.id = q.subjectyear_id
        JOIN subjectname sn ON sn.id = sy.subjectnamrid
        WHERE sn.name = ? AND q.correct_option IS NOT NULL
        ORDER BY RAND()
        LIMIT 1000
    ");
    $stmt->execute([$subject, $subject]);
}

$allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sysSettings = getSystemSettings();
$hide2024 = $sysSettings['hide_2024'] ?? '0';
$only2024 = $sysSettings['only_2024'] ?? '0';

$filteredQuestions = [];
foreach ($allQuestions as $q) {
    $qYear = trim($q['year']);
    if ($hide2024 === '1' && $qYear === '2024') {
        continue;
    }
    if ($only2024 === '1' && $qYear !== '2024') {
        continue;
    }
    $filteredQuestions[] = $q;
}
$allQuestions = $filteredQuestions;

// Group by topic
$byTopic = [];
$fallbackByTopic = [];
$flatPool = [];

foreach($allQuestions as $q) {
    $t = $q['topic'] ?: 'General';
    if ($syId) {
        if ($q['sy_id'] == $syId) {
            $byTopic[$t][] = $q;
            $flatPool[] = $q;
        } else if ($q['category'] === $targetCategory) {
            $fallbackByTopic[$t][] = $q;
            $flatPool[] = $q;
        }
    } else {
        $byTopic[$t][] = $q;
        $flatPool[] = $q;
    }
}

$pageTitle = $syId ? "Adaptive Practice: $subject ($targetYearName) — RBAPS" : "Adaptive Practice: $subject — RBAPS";
include 'includes/header.php';
?>

<div class="quiz-container" style="max-width:860px">

  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem">
    <div>
      <a href="practice.php?subject=<?= urlencode($subject) ?>" style="font-size:.85rem;color:var(--text3);text-decoration:none">← Back</a>
      <h2 style="font-family:'Playfair Display',serif;font-size:1.5rem;margin-top:.25rem"><?= htmlspecialchars($subject) ?> <?= $syId ? "(".htmlspecialchars($targetYearName).")" : "" ?> — Adaptive Mode</h2>
      <div style="display:flex;gap:.5rem;margin-top:.4rem;flex-wrap:wrap">
        <span class="badge badge-green"><i class="fa-solid fa-robot"></i> Rule-Based Adaptive</span>
        <span class="badge badge-purple" id="currentTopicBadge">Starting…</span>
      </div>
    </div>
    <div style="display:flex;gap:.75rem;align-items:center">
      <div style="text-align:right">
        <div style="font-size:.75rem;color:var(--text3);font-weight:600">SESSION SCORE</div>
        <div style="font-family:'Playfair Display',serif;font-size:1.6rem;color:var(--gold)" id="liveScore">0 / 0</div>
      </div>
      <button onclick="endQuiz()" class="btn btn-danger btn-sm" id="endBtn">⏹ End Quiz</button>
    </div>
  </div>

  <!-- Progress bar -->
  <div style="height:6px;background:var(--card2);border-radius:999px;overflow:hidden;margin-bottom:1.75rem">
    <div id="progressFill" style="height:100%;width:0%;border-radius:999px;background:linear-gradient(90deg,var(--gold),var(--gold2));transition:width .4s ease"></div>
  </div>

  <!-- Rule indicator (hidden from students — internal engine logic) -->
  <div id="ruleIndicator" style="display:none !important;margin-bottom:1.25rem;font-size:.875rem;padding:.75rem 1rem;border-radius:10px"></div>

  <!-- Question card -->
  <div id="questionCard" class="question-card">
    <div class="question-text" id="questionText">
      <div class="loading-spinner"></div>
    </div>
    <ul class="options-list" id="optionsList"></ul>
    <div class="feedback-panel" id="feedbackPanel" style="display:none"></div>

    <div id="nextRow" style="display:none;margin-top:1.25rem;text-align:right">
      <button class="btn btn-primary" id="nextBtn" onclick="nextQuestion()">
        Next Question →
      </button>
    </div>
  </div>

  <!-- Question meta -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.6rem">
    <span id="qMeta" style="font-size:.8rem;color:var(--text3)"></span>
  </div>

  <!-- Topic Mastery Panel (hidden during session, shown on results) -->
  <div class="card" style="margin-top:2rem;display:none" id="masteryPanel">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
      <h3 style="font-size:.95rem;font-weight:700">Topic Mastery</h3>
      <span style="font-size:.75rem;color:var(--text3)">Session summary</span>
    </div>
    <div id="masteryList"></div>
  </div>

  <!-- Results screen -->
  <div id="resultsScreen" style="display:none">
    <div class="card results-card">
      <div class="score-ring" id="scoreRing" style="--pct:0%">
        <span class="score-text" id="scorePct">0%</span>
      </div>
      <h2 style="font-family:'Playfair Display',serif;font-size:1.8rem;margin-bottom:.5rem" id="resultsTitle">Session Complete!</h2>
      <p style="color:var(--text2);margin-bottom:1.5rem" id="resultsSubtitle"></p>
      <div id="masteryReport" style="text-align:left;margin-bottom:2rem"></div>
      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
        <a href="adaptive.php?subject=<?= urlencode($subject) ?>" class="btn btn-primary">Practice Again</a>
        <a href="dashboard.php" class="btn btn-ghost">View Dashboard</a>
        <a href="subjects.php" class="btn btn-outline">All Subjects</a>
      </div>
      <div id="reviewList" style="margin-top:2rem;text-align:left"></div>
    </div>
  </div>

</div><!-- /quiz-container -->

<script>
// ═══════════════════════════════════════════════════════════════════
//  DATA FROM PHP
// ═══════════════════════════════════════════════════════════════════
const SUBJECT      = <?= json_encode($subject) ?>;
const BY_TOPIC     = <?= json_encode($byTopic, JSON_HEX_TAG) ?>;
const FALLBACK_BY_TOPIC = <?= json_encode($fallbackByTopic, JSON_HEX_TAG) ?>;
const FLAT_POOL    = <?= json_encode($flatPool, JSON_HEX_TAG) ?>;
const INIT_MASTERY = <?= json_encode($topicMastery, JSON_HEX_TAG) ?>;
const MAX_QUESTIONS = 40;

// ═══════════════════════════════════════════════════════════════════
//  STATE
// ═══════════════════════════════════════════════════════════════════
// mastery[topic] = { score, attempted, correct, consecutive, difficulty }
let mastery = {};

// Seed from saved DB history
for (const [topic, m] of Object.entries(INIT_MASTERY)) {
  mastery[topic] = {
    score:       parseFloat(m.mastery_score)     || 0,
    attempted:   parseInt(m.total_attempted)     || 0,
    correct:     parseInt(m.total_correct)       || 0,
    consecutive: parseInt(m.consecutive_correct) || 0,
    difficulty:  m.difficulty_level              || 'easy'
  };
}

// Make sure every topic from BY_TOPIC AND FALLBACK_BY_TOPIC has a mastery entry
const allKnownTopics = new Set([...Object.keys(BY_TOPIC), ...Object.keys(FALLBACK_BY_TOPIC)]);
for (const topic of allKnownTopics) {
  if (!mastery[topic]) {
    mastery[topic] = { score:0, attempted:0, correct:0, consecutive:0, difficulty:'easy' };
  }
}

// Per-topic question queues — cloned so we can drain them without losing BY_TOPIC
let topicQueues = {};
for (const [t, qs] of Object.entries(BY_TOPIC)) {
  topicQueues[t] = shuffle([...qs]);
}
let fallbackQueues = {};
for (const [t, qs] of Object.entries(FALLBACK_BY_TOPIC)) {
  fallbackQueues[t] = shuffle([...qs]);
}

// Flat pool of questions not yet used in this session (for Rule 3 fallback)
const usedQIds = new Set();
let flatFallback = shuffle([...FLAT_POOL]);

let totalAnswered  = 0;
let totalCorrect   = 0;
let answered       = false;   // true after user picks an answer, reset by nextQuestion
let currentQ       = null;
let currentTopic   = null;
let results        = [];
let questionCount  = 0;
let quizEnded      = false;

// ═══════════════════════════════════════════════════════════════════
//  UTILS
// ═══════════════════════════════════════════════════════════════════
function shuffle(arr) {
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showRule(name, msg, color) {
  // Rule indicators are hidden from students — engine runs silently
}

// ═══════════════════════════════════════════════════════════════════
//  ADAPTIVE ENGINE — Rules 1-4
// ═══════════════════════════════════════════════════════════════════

/**
 * Rule 1 — Remedial Focus: IF topic mastery score > 60%, move to another topic.
 *   → We serve questions to topics BELOW 60% first (that's the remedial work).
 *   → Once a topic crosses 60%, the engine stops targeting it and moves on.
 *
 * Rule 3 — Mastery Reinforcement: IF mastery < 60% AND all questions exhausted,
 *   move to the next related topic (served from the flat fallback pool).
 *
 * Rule 4 — Prerequisite Validation: numbered topics must have their
 *   predecessor at ≥ 60% before the engine targets them.
 *
 * Returns the chosen topic name, or null if nothing is available.
 */
function selectNextTopic() {
  const allTopics  = Array.from(allKnownTopics);

  // Topics that still have questions in their own queue or fallback queue
  const withQs = allTopics.filter(t => 
    (topicQueues[t] && topicQueues[t].length > 0) || 
    (fallbackQueues[t] && fallbackQueues[t].length > 0)
  );

  // Topics that are below 60% mastery (need remediation)
  // Unattempted topics start at score=0 → always below 60%
  const needsWork = withQs.filter(t => mastery[t].score < 60);

  if (needsWork.length > 0) {
    // Rule 1 — Remedial Focus: pick the weakest below-60% topic
    needsWork.sort((a, b) => {
      // Prefer attempted topics (meaningful score) over unattempted
      const aAtt = mastery[a].attempted > 0 ? 0 : 1;
      const bAtt = mastery[b].attempted > 0 ? 0 : 1;
      if (aAtt !== bAtt) return aAtt - bAtt;
      return mastery[a].score - mastery[b].score;
    });
    const chosen = applyPrerequisiteRule(needsWork[0], allTopics);
    showRule('Rule 1 — Remedial Focus',
      `Targeting "${chosen}" — mastery ${Math.round(mastery[chosen].score)}% (below 60% threshold).`, 'red');
    return chosen;
  }

  // All remaining topics with questions are >= 60%
  if (withQs.length > 0) {
    // Rule 3 (Mastery Reinforcement variant): pick the lowest above-60%
    withQs.sort((a, b) => mastery[a].score - mastery[b].score);
    const chosen = applyPrerequisiteRule(withQs[0], allTopics);
    showRule('Rule 3 — Mastery Reinforcement',
      `All active topics ≥ 60%. Reinforcing "${chosen}" (${Math.round(mastery[chosen].score)}%).`, 'green');
    return chosen;
  }

  // All topic queues are exhausted — Rule 3 fallback: use flat pool
  // Find any question not yet used in this session
  const fallbackQ = flatFallback.find(q => !usedQIds.has(q.id));
  if (fallbackQ) {
    // Create a synthetic single-question queue under its topic
    const ft = fallbackQ.topic || 'General';
    if (!topicQueues[ft]) topicQueues[ft] = [];
    if (!mastery[ft]) mastery[ft] = { score:0, attempted:0, correct:0, consecutive:0, difficulty:'easy' };
    topicQueues[ft].push(fallbackQ);
    showRule('Rule 3 — Exhausted Topic Fallback',
      `Queue exhausted. Fetching a related question on "${ft}" to continue practice.`, 'gold');
    return ft;
  }

  return null; // truly nothing left
}

/**
 * Rule 4 — Prerequisite Validation:
 * If selected topic has a numeric prefix (e.g. "3. Genetics"), check
 * that topic N-1 has been attempted and has mastery >= 60%.
 * If not, redirect to the prerequisite instead.
 */
function applyPrerequisiteRule(topic, allTopics) {
  const match = topic.match(/^(\d+)[.\-\s]/);
  if (!match) return topic;
  const num = parseInt(match[1]);
  if (num <= 1) return topic;

  const prereqNum = num - 1;
  const prereqTopic = allTopics.find(t => {
    const m2 = t.match(/^(\d+)[.\-\s]/);
    return m2 && parseInt(m2[1]) === prereqNum;
  });

  if (!prereqTopic) return topic;
  const pm = mastery[prereqTopic];
  if (!pm || pm.attempted === 0 || pm.score >= 60) return topic; // prereq fine

  showRule('Rule 4 — Prerequisite Validation',
    `"${prereqTopic}" must reach 60% before "${topic}". Score: ${Math.round(pm.score)}%. Redirecting.`, 'gold');

  // Can we actually serve the prereq?
  const prereqQueueHasQs = (topicQueues[prereqTopic] && topicQueues[prereqTopic].length > 0) || 
                           (fallbackQueues[prereqTopic] && fallbackQueues[prereqTopic].length > 0);
  if (prereqQueueHasQs) return prereqTopic;
  // Prereq queue is also empty — fall through to original topic
  return topic;
}

/**
 * Rule 2 — Progressive Challenge:
 * 3 consecutive correct → advance difficulty (easy→medium→hard).
 * Returns the question to ask for the given topic.
 */
function selectQuestion(topic) {
  const m     = mastery[topic];
  let queue = topicQueues[topic] || [];
  
  if (queue.length === 0 && fallbackQueues[topic] && fallbackQueues[topic].length > 0) {
    topicQueues[topic] = fallbackQueues[topic];
    fallbackQueues[topic] = [];
    queue = topicQueues[topic];
    showRule('Cross-Year Fallback', `Target year exhausted for "${topic}". Fetching from other years.`, 'gold');
  }

  if (queue.length === 0) return null;

  // Advance difficulty if earned
  if (m.consecutive >= 3 && m.difficulty !== 'hard') {
    const next = m.difficulty === 'easy' ? 'medium' : 'hard';
    m.difficulty  = next;
    m.consecutive = 0;
    showRule('Rule 2 — Progressive Challenge',
      `3 correct in a row on "${topic}"! Advancing to <strong>${next.toUpperCase()}</strong>.`, 'accent');
  }

  // Pick a random question from the queue (queue is pre-shuffled)
  const idx  = Math.floor(Math.random() * queue.length);
  const qPick = queue[idx];
  topicQueues[topic] = queue.filter((_, i) => i !== idx);
  usedQIds.add(qPick.id);
  return qPick;
}

// ═══════════════════════════════════════════════════════════════════
//  RENDER QUESTION
// ═══════════════════════════════════════════════════════════════════
function renderQuestion() {
  if (quizEnded) return;

  // No data loaded at all
  if (Object.keys(BY_TOPIC).length === 0 && FLAT_POOL.length === 0) {
    document.getElementById('questionText').innerHTML =
      '<p style="color:var(--red)"><i class="fa-solid fa-triangle-exclamation"></i> No questions found for this subject. ' +
      'Please <a href="practice.php">go back</a> and check the setup.</p>';
    return;
  }

  if (questionCount >= MAX_QUESTIONS) { endQuiz(); return; }

  // Reset per-question UI state
  answered = false;
  document.getElementById('feedbackPanel').style.display = 'none';
  document.getElementById('feedbackPanel').className = 'feedback-panel';
  document.getElementById('nextRow').style.display = 'none';
  document.getElementById('optionsList').innerHTML = '';
  document.getElementById('questionText').textContent = 'Loading…';

  // Pick topic & question
  currentTopic = selectNextTopic();
  if (!currentTopic) { endQuiz(); return; }

  currentQ = selectQuestion(currentTopic);
  if (!currentQ) { endQuiz(); return; }

  questionCount++;

  // ── Update header UI ───────────────────────────────────────────
  const shortTopic = currentTopic.length > 38 ? currentTopic.slice(0, 38) + '…' : currentTopic;
  document.getElementById('currentTopicBadge').innerHTML = '<i class="fa-solid fa-book-open" style="margin-right:4px"></i>' + shortTopic;
  document.getElementById('progressFill').style.width = (questionCount / MAX_QUESTIONS * 100) + '%';
  document.getElementById('liveScore').textContent = totalCorrect + ' / ' + totalAnswered;
  document.getElementById('qMeta').textContent =
    `Question ${questionCount} of ${MAX_QUESTIONS}  ·  Topic: ${shortTopic}`;

  // ── Question text ──────────────────────────────────────────────
  document.getElementById('questionText').textContent = currentQ.question;

  // ── Answer options ─────────────────────────────────────────────
  const opts = [
    { key:'A', text: currentQ.option_a },
    { key:'B', text: currentQ.option_b },
    { key:'C', text: currentQ.option_c },
    { key:'D', text: currentQ.option_d },
  ].filter(o => o.text && o.text.trim() !== '');

  const list = document.getElementById('optionsList');
  list.innerHTML = '';
  opts.forEach(opt => {
    const li = document.createElement('li');
    li.className   = 'option-item';
    li.dataset.key = opt.key;
    li.innerHTML   = `<span class="option-label">${opt.key}</span>
                      <span class="option-text">${escHtml(opt.text)}</span>`;
    li.addEventListener('click', () => choose(opt.key));
    list.appendChild(li);
  });
}

// ═══════════════════════════════════════════════════════════════════
//  CHOOSE ANSWER  (Rule 7 — Feedback Generation)
// ═══════════════════════════════════════════════════════════════════
function choose(chosen) {
  if (answered) return;
  answered = true;

  const isCorrect = chosen === currentQ.correct_option;
  totalAnswered++;
  if (isCorrect) totalCorrect++;

  // Update mastery for this topic
  const m = mastery[currentTopic];
  m.attempted++;
  if (isCorrect) { m.correct++; m.consecutive++; }
  else           { m.consecutive = 0; }
  m.score = (m.correct / m.attempted) * 100;

  // Style options
  document.querySelectorAll('.option-item').forEach(li => {
    li.classList.add('disabled');
    if (li.dataset.key === chosen)
      li.classList.add(isCorrect ? 'correct' : 'wrong');
    if (li.dataset.key === currentQ.correct_option && !isCorrect)
      li.classList.add('correct');
  });

  // ── Rule 7 — Feedback Generation ──────────────────────────────
  const fp          = document.getElementById('feedbackPanel');
  const correctText = currentQ['option_' + currentQ.correct_option.toLowerCase()] || '';

  // Concept explanation lookup (topic-level)
  const topicConceptNote = isCorrect ? '' :
    `<div style="margin-top:.6rem;padding:.5rem .75rem;border-radius:8px;background:rgba(255,255,255,0.04);font-size:.82rem;color:var(--text2)">
       <strong><i class="fa-solid fa-lightbulb" style="color:var(--gold)"></i> Concept missed:</strong> <em>${escHtml(currentTopic)}</em><br>
       This question tests your understanding of <em>${escHtml(currentTopic)}</em>.
       Review this topic and try more questions to build mastery.
     </div>`;

  const streakNote = (isCorrect && m.consecutive >= 3)
    ? `<div style="margin-top:.4rem;font-size:.8rem;color:var(--gold)">🔥 ${m.consecutive} correct in a row!</div>`
    : '';

  const masteryNote = (isCorrect && m.score >= 60 && m.attempted >= 3)
    ? `<div style="margin-top:.4rem;font-size:.8rem;color:var(--green)"><i class="fa-solid fa-chart-line"></i> Topic mastery: ${Math.round(m.score)}% — above 60% threshold! Moving on soon.</div>`
    : '';

  if (isCorrect) {
    fp.className = 'feedback-panel show correct-fb';
    fp.innerHTML = `
      <div class="feedback-title"><i class="fa-solid fa-circle-check" style="color:var(--green)"></i> Correct!</div>
      <div class="feedback-explanation">The answer is <strong>${escHtml(currentQ.correct_option)}: ${escHtml(correctText)}</strong>.</div>
      ${streakNote}${masteryNote}`;
  } else {
    fp.className = 'feedback-panel show wrong-fb';
    fp.innerHTML = `
      <div class="feedback-title"><i class="fa-solid fa-circle-xmark" style="color:var(--red)"></i> Incorrect</div>
      <div class="feedback-explanation">The correct answer is <strong>${escHtml(currentQ.correct_option)}: ${escHtml(correctText)}</strong>.</div>
      ${topicConceptNote}`;
  }
  fp.style.display = 'block';

  // Show Next button
  document.getElementById('nextRow').style.display = 'block';
  document.getElementById('liveScore').textContent = totalCorrect + ' / ' + totalAnswered;

  results.push({ q: currentQ, chosen, isCorrect, topic: currentTopic });
  // Save async
  fetch('api/answer.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      question_id: currentQ.id, chosen, is_correct: isCorrect ? 1 : 0,
      subject: SUBJECT, topic: currentTopic,
      mastery: m.score, consecutive: m.consecutive, difficulty: m.difficulty
    })
  }).catch(() => {});
}

// ── Next button ─────────────────────────────────────────────────────
function nextQuestion() {
  if (!answered) return;                          // must have answered first
  const btn = document.getElementById('nextBtn');
  if (btn) btn.disabled = true;
  renderQuestion();
  if (btn) btn.disabled = false;
}

// ═══════════════════════════════════════════════════════════════════
//  MASTERY PANEL
// ═══════════════════════════════════════════════════════════════════
function renderMastery() {
  const container = document.getElementById('masteryList');
  const topics = Object.keys(mastery).filter(t =>
    mastery[t].attempted > 0 || (BY_TOPIC[t] && BY_TOPIC[t].length > 0));
  if (!topics.length) {
    container.innerHTML = '<p style="color:var(--text3);font-size:.85rem;padding:.5rem 0">Answer your first question to see mastery!</p>';
    return;
  }
  topics.sort((a, b) => mastery[b].score - mastery[a].score);
  container.innerHTML = topics.slice(0, 25).map(t => {
    const m     = mastery[t];
    const pct   = m.attempted > 0 ? Math.round(m.score) : 0;
    const color = pct >= 80 ? 'var(--green)' : pct >= 60 ? 'var(--gold)' : pct >= 40 ? 'var(--cyan)' : 'var(--red)';
    const label = m.attempted > 0 ? (pct >= 60 ? '✓' : '⚠') : '–';
    const shortT = t.length > 45 ? t.slice(0, 45) + '…' : t;
    return `<div class="mastery-row">
      <span class="mastery-name" title="${escHtml(t)}">${escHtml(shortT)}</span>
      <span style="font-size:.7rem;color:${color};width:18px;text-align:center">${label}</span>
      <div class="mastery-bar-wrap">
        <div class="mastery-bar-track">
          <div class="mastery-bar-fill" style="width:${pct}%;background:${color}"></div>
        </div>
      </div>
      <span class="mastery-pct" style="color:${color}">${m.attempted > 0 ? pct + '%' : '—'}</span>
    </div>`;
  }).join('');
}

// ═══════════════════════════════════════════════════════════════════
//  END QUIZ
// ═══════════════════════════════════════════════════════════════════
function endQuiz() {
  if (quizEnded) return;
  quizEnded = true;

  ['questionCard','ruleIndicator','endBtn'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
  document.getElementById('progressFill').parentElement.style.display = 'none';
  document.getElementById('qMeta').parentElement.style.display = 'none';

  const pct = totalAnswered > 0 ? Math.round(totalCorrect / totalAnswered * 100) : 0;
  document.getElementById('scoreRing').style.setProperty('--pct', pct + '%');
  document.getElementById('scorePct').textContent = pct + '%';

  const t = document.getElementById('resultsTitle');
  if      (pct >= 80) { t.innerHTML = '<i class="fa-solid fa-star" style="color:var(--gold)"></i> Excellent Work!'; }
  else if (pct >= 60) { t.innerHTML = '<i class="fa-solid fa-thumbs-up" style="color:var(--green)"></i> Good Effort!'; }
  else if (pct >= 40) { t.innerHTML = '<i class="fa-solid fa-book-open" style="color:var(--cyan)"></i> Keep Practising'; }
  else                { t.innerHTML = '<i class="fa-solid fa-fist-raised" style="color:var(--red)"></i> Don\'t Give Up!'; }

  document.getElementById('resultsSubtitle').textContent =
    `You answered ${totalCorrect} of ${totalAnswered} correctly (${pct}%) — ${SUBJECT}`;

  // Mastery report with study recommendations
  const attempted = Object.entries(mastery).filter(([,m]) => m.attempted > 0);
  if (attempted.length) {
    const weak   = attempted.filter(([,m]) => m.score <  60).sort(([,a],[,b]) => a.score - b.score);
    const strong = attempted.filter(([,m]) => m.score >= 60).sort(([,a],[,b]) => b.score - a.score);

    let html = '<h3 style="font-size:.95rem;font-weight:700;margin-bottom:.75rem"><i class="fa-solid fa-chart-line"></i> Topic Mastery Report</h3>';

    if (weak.length) {
      html += `<div style="margin-bottom:.5rem;padding:.5rem .75rem;border-radius:8px;background:rgba(255,77,109,0.08);border:1px solid rgba(255,77,109,0.2);font-size:.82rem">
        <strong style="color:var(--red)"><i class="fa-solid fa-thumbtack"></i> Priority Study Topics (below 60%)</strong> — Focus on these first in your next session:
        <ul style="margin:.4rem 0 0 1rem;padding:0;color:var(--text2)">
          ${weak.map(([t,m]) => `<li><strong>${escHtml(t)}</strong> — ${Math.round(m.score)}% (${m.correct}/${m.attempted} correct)</li>`).join('')}
        </ul>
      </div>`;
    }

    html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.6rem;margin-top:.75rem">';
    attempted.sort(([,a],[,b]) => b.score - a.score).forEach(([topic, m]) => {
      const p     = Math.round(m.score);
      const color = p >= 80 ? 'var(--green)' : p >= 60 ? 'var(--gold)' : p >= 40 ? 'var(--cyan)' : 'var(--red)';
      const badge = p >= 60 ? '<i class="fa-solid fa-circle-check" style="color:var(--green)"></i> Above threshold' : '<i class="fa-solid fa-triangle-exclamation" style="color:var(--red)"></i> Needs work';
      const shortT = topic.length > 35 ? topic.slice(0, 35) + '…' : topic;
      html += `<div style="padding:.6rem .85rem;border-radius:10px;background:var(--bg3);border:1px solid var(--border)">
        <div style="font-weight:600;font-size:.82rem;margin-bottom:.3rem" title="${escHtml(topic)}">${escHtml(shortT)}</div>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="font-family:'JetBrains Mono',monospace;font-size:.95rem;color:${color};font-weight:700">${p}%</span>
          <span style="font-size:.7rem;color:${color}">${badge}</span>
        </div>
        <div style="height:4px;background:var(--card2);border-radius:999px;margin-top:.4rem;overflow:hidden">
          <div style="height:100%;width:${p}%;background:${color};border-radius:999px"></div>
        </div>
        <div style="font-size:.72rem;color:var(--text3);margin-top:.3rem">${m.correct} / ${m.attempted} correct</div>
      </div>`;
    });
    html += '</div>';
    document.getElementById('masteryReport').innerHTML = html;
  }

  document.getElementById('resultsScreen').style.display = 'block';

  // Reveal the mastery panel below results now that the session is done
  renderMastery();
  const mp = document.getElementById('masteryPanel');
  if (mp) mp.style.display = 'block';

  // Save session
  fetch('api/save_session.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ subject: SUBJECT, correct: totalCorrect,
      total_q: totalAnswered, score_pct: pct, 
      category: <?= json_encode($syId ? $targetCategory : 'adaptive') ?>, 
      year: <?= json_encode($syId ? $targetYearName : 'adaptive') ?> })
  }).catch(() => {});

  // Save topic mastery
  fetch('api/save_topic_mastery.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ subject: SUBJECT,
      mastery: Object.entries(mastery)
        .filter(([,m]) => m.attempted > 0)
        .map(([topic, m]) => ({ topic, ...m })) })
  }).catch(() => {});

  // Question review
  if (results.length) {
    let html = '<h3 style="font-size:.95rem;font-weight:700;margin-bottom:1rem"><i class="fa-solid fa-list-check"></i> Question Review</h3>';
    results.forEach((r, i) => {
      const ans    = r.q['option_' + r.q.correct_option.toLowerCase()] || r.q.correct_option;
      const shortT = r.topic.length > 40 ? r.topic.slice(0, 40) + '…' : r.topic;
      html += `<div style="padding:.7rem 1rem;border-radius:10px;margin-bottom:.5rem;
        background:${r.isCorrect?'rgba(0,200,150,0.05)':'rgba(255,77,109,0.05)'};
        border:1px solid ${r.isCorrect?'rgba(0,200,150,0.2)':'rgba(255,77,109,0.15)'}">
        <div style="font-size:.75rem;color:var(--text3);margin-bottom:.2rem">Topic: ${escHtml(shortT)}</div>
        <div style="font-size:.85rem;font-weight:600;margin-bottom:.25rem">
          ${r.isCorrect?'<i class="fa-solid fa-circle-check" style="color:var(--green)"></i>':'<i class="fa-solid fa-circle-xmark" style="color:var(--red)"></i>'} Q${i+1}: ${escHtml(r.q.question.slice(0, 140))}${r.q.question.length>140?'…':''}
        </div>
        ${!r.isCorrect ? `<div style="font-size:.8rem;color:var(--green)">✓ ${escHtml(ans)}</div>` : ''}
      </div>`;
    });
    document.getElementById('reviewList').innerHTML = html;
  }
}

// ── Kick off ────────────────────────────────────────────────────────
renderQuestion();
</script>

<?php include 'includes/footer.php'; ?>
