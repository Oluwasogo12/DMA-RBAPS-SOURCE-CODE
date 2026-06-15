<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$pageTitle  = 'Non-Adaptive Practice — RBAPS';
$currentPage = 'non_adaptive';
$db = getDB();
$uid = $_SESSION['user_id'];

$selectedSubject = $_GET['subject'] ?? '';

// All subjects
$subjects = $db->query("SELECT id, name FROM subjectname ORDER BY name")->fetchAll();

// Years for selected subject
$years = [];
if ($selectedSubject) {
    $stmt = $db->prepare("
        SELECT sy.id, sy.year, sy.category, COUNT(q.id) AS q_count
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

// Load weak topics for this user+subject (mastery < 60%)
$weakTopics = [];
if ($selectedSubject) {
    $wStmt = $db->prepare("
        SELECT topic, mastery_score, total_attempted, total_correct
        FROM user_topic_performance
        WHERE user_id = ? AND subject_name = ? AND mastery_score < 60
        ORDER BY mastery_score ASC
        LIMIT 10
    ");
    $wStmt->execute([$uid, $selectedSubject]);
    $weakTopics = $wStmt->fetchAll();
}

include 'includes/header.php';
?>

<div class="section" style="max-width:860px">

  <div class="section-header">
    <h2><i class="fa-solid fa-calendar-days" style="color:var(--gold)"></i> Non-Adaptive Practice</h2>
    <p>Attempt real past questions by examination year — questions appear in original order</p>
  </div>

  <!-- Mode comparison banner -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.75rem">
    <div style="padding:.85rem 1rem;border-radius:12px;background:rgba(245,200,66,0.08);border:1px solid rgba(245,200,66,0.25)">
      <div style="font-weight:700;font-size:.85rem;color:var(--gold);margin-bottom:.3rem">
        <i class="fa-solid fa-calendar-days"></i> This Mode — Year-Based
      </div>
      <div style="font-size:.78rem;color:var(--text2);line-height:1.5">
        Work through an entire exam year in the original question order. See your topic weakness panel after each session.
      </div>
    </div>
    <div style="padding:.85rem 1rem;border-radius:12px;background:rgba(79,142,247,0.06);border:1px solid rgba(79,142,247,0.18)">
      <div style="font-weight:700;font-size:.85rem;color:var(--accent);margin-bottom:.3rem">
        <i class="fa-solid fa-robot"></i> Adaptive Mode
      </div>
      <div style="font-size:.78rem;color:var(--text2);line-height:1.5">
        Engine picks questions by topic mastery, adjusts difficulty, and prioritises your weakest areas automatically.
        <a href="practice.php" style="color:var(--accent);font-weight:600">Switch →</a>
      </div>
    </div>
  </div>

  <!-- Step 1: Subject -->
  <div class="card" style="margin-bottom:1.25rem">
    <h3 style="font-size:1rem;margin-bottom:1.25rem;font-weight:700">Step 1 — Choose Subject</h3>
    <form method="GET" action="non_adaptive.php">
      <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label">Subject</label>
          <select class="form-control" name="subject" onchange="this.form.submit()">
            <option value="">— Select a subject —</option>
            <?php foreach ($subjects as $s): ?>
            <option value="<?= htmlspecialchars($s['name']) ?>"
                    <?= $selectedSubject === $s['name'] ? 'selected' : '' ?>>
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

    <!-- Weak Topics Alert (shown when data exists) -->
    <?php if ($weakTopics): ?>
    <div style="margin-bottom:1.25rem;padding:1rem 1.1rem;border-radius:14px;
        background:rgba(255,77,109,0.07);border:1px solid rgba(255,77,109,0.25)">
      <div style="font-weight:700;font-size:.9rem;color:var(--red);margin-bottom:.65rem">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Topics Needing Attention — <em><?= htmlspecialchars($selectedSubject) ?></em>
      </div>
      <div style="font-size:.78rem;color:var(--text2);margin-bottom:.75rem">
        Based on your previous sessions, focus on these topics when choosing a year to practice:
      </div>
      <div style="display:flex;flex-direction:column;gap:.4rem">
        <?php foreach ($weakTopics as $wt):
          $pct   = round($wt['mastery_score']);
          $color = $pct < 30 ? 'var(--red)' : ($pct < 50 ? '#f5a623' : 'var(--gold)');
        ?>
        <div style="display:flex;align-items:center;gap:.75rem;padding:.45rem .75rem;
            border-radius:8px;background:rgba(255,255,255,0.04)">
          <span style="font-size:.8rem;font-weight:700;color:<?= $color ?>;min-width:38px"><?= $pct ?>%</span>
          <div style="flex:1">
            <div style="font-size:.82rem;font-weight:600;color:var(--text1)"><?= htmlspecialchars($wt['topic']) ?></div>
            <div style="height:3px;background:var(--card2);border-radius:999px;margin-top:3px;overflow:hidden">
              <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:999px"></div>
            </div>
          </div>
          <span style="font-size:.72rem;color:var(--text3)"><?= $wt['total_correct'] ?>/<?= $wt['total_attempted'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:.85rem;font-size:.78rem;color:var(--text3)">
        <i class="fa-solid fa-lightbulb" style="color:var(--gold)"></i>
        Tip: pick a year below and keep an eye on these topics. Your weakness report updates after every session.
      </div>
    </div>
    <?php endif; ?>

    <!-- Step 2: Year -->
    <div class="card">
      <h3 style="font-size:1rem;margin-bottom:1.25rem;font-weight:700">
        Step 2 — Choose Exam Type &amp; Year for <em><?= htmlspecialchars($selectedSubject) ?></em>
      </h3>

      <?php
      $utmeYears = array_filter($years, fn($y) => $y['category'] === 'utme');
      $ssceYears = array_filter($years, fn($y) => $y['category'] === 'ssce');
      ?>

      <?php if ($utmeYears): ?>
      <div style="margin-bottom:1.5rem">
        <div class="badge badge-blue" style="margin-bottom:.75rem">UTME</div>
        <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end">
          <div class="form-group" style="margin:0">
            <label class="form-label">Select Year</label>
            <select class="form-control" id="utme-year-select"
                    onchange="previewCount(this,'utme-count')">
              <option value="">— Choose a year —</option>
              <?php foreach ($utmeYears as $y): ?>
              <option value="<?= $y['id'] ?>" data-count="<?= $y['q_count'] ?>">
                📅 <?= $y['year'] ?> — <?= $y['q_count'] ?> questions
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="button" class="btn btn-primary"
                  onclick="startQuiz('utme-year-select','<?= urlencode($selectedSubject) ?>')"
                  style="margin-bottom:0">Start →</button>
        </div>
        <div id="utme-count" style="font-size:.78rem;color:var(--text3);margin-top:.5rem;min-height:1rem"></div>
      </div>
      <?php endif; ?>

      <?php if ($ssceYears): ?>
      <div>
        <div class="badge badge-green" style="margin-bottom:.75rem">SSCE</div>
        <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end">
          <div class="form-group" style="margin:0">
            <label class="form-label">Select Year</label>
            <select class="form-control" id="ssce-year-select"
                    onchange="previewCount(this,'ssce-count')">
              <option value="">— Choose a year —</option>
              <?php foreach ($ssceYears as $y): ?>
              <option value="<?= $y['id'] ?>" data-count="<?= $y['q_count'] ?>">
                📅 <?= $y['year'] ?> — <?= $y['q_count'] ?> questions
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="button" class="btn btn-primary"
                  onclick="startQuiz('ssce-year-select','<?= urlencode($selectedSubject) ?>')"
                  style="margin-bottom:0">Start →</button>
        </div>
        <div id="ssce-count" style="font-size:.78rem;color:var(--text3);margin-top:.5rem;min-height:1rem"></div>
      </div>
      <?php endif; ?>
    </div>

  <?php elseif ($selectedSubject): ?>
    <div class="alert alert-info">No questions found for this subject yet.</div>
  <?php endif; ?>

  <!-- Popular subjects quick access -->
  <?php if (!$selectedSubject): ?>
  <div style="margin-top:2rem">
    <div style="font-size:.8rem;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:1rem">Popular Subjects</div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <?php
      $popular = ['Chemistry','Physics','Mathematics','Biology','English','Economics','Government'];
      foreach ($popular as $p):
      ?>
      <a href="non_adaptive.php?subject=<?= urlencode($p) ?>" class="btn btn-ghost btn-sm"><?= $p ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
function previewCount(select, countId) {
  const opt = select.options[select.selectedIndex];
  const el  = document.getElementById(countId);
  if (!select.value) { el.textContent = ''; return; }
  const n = opt.dataset.count;
  el.textContent = `ℹ️ This session will contain ${n} question${n>1?'s':''}.`;
}

function startQuiz(selectId, subject) {
  const val = document.getElementById(selectId).value;
  if (!val) { alert('Please select a year first.'); return; }
  window.location.href = 'non_adaptive_quiz.php?sy=' + val + '&subject=' + subject;
}
</script>

<?php include 'includes/footer.php'; ?>
