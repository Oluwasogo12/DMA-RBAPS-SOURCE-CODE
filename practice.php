<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$pageTitle = 'Practice — RBAPS';
$db = getDB();

$selectedSubject = $_GET['subject'] ?? '';

// Get all subjects
$subjects = $db->query("SELECT id, name FROM subjectname ORDER BY name")->fetchAll();

// If subject selected, get available years
$years = [];
if($selectedSubject) {
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

include 'includes/header.php';
?>
<div class="section" style="max-width:800px">
  <div class="section-header">
    <h2>Start Practice Session</h2>
    <p>Select a subject, examination type, and year to begin</p>
  </div>

  <div class="card" style="margin-bottom:1.5rem">
    <h3 style="font-size:1rem;margin-bottom:1.25rem;font-weight:700">Step 1 — Choose Subject</h3>
    <form method="GET" action="practice.php">
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

  <?php if($selectedSubject && $years): ?>
  <div class="card">
    <h3 style="font-size:1rem;margin-bottom:1.25rem;font-weight:700">
      Step 2 — Choose Exam Type &amp; Year for <em><?= htmlspecialchars($selectedSubject) ?></em>
    </h3>

    <?php
    $utmeYears = array_filter($years, fn($y) => $y['category']==='utme');
    $ssceYears = array_filter($years, fn($y) => $y['category']==='ssce');
    ?>

    <?php if($utmeYears): ?>
    <div style="margin-bottom:1.5rem">
      <div class="badge badge-blue" style="margin-bottom:.75rem">UTME</div>
      <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label">Select Year</label>
          <select class="form-control" id="utme-year-select" onchange="goToYear(this, '<?= urlencode($selectedSubject) ?>')">
            <option value="">— Choose a year —</option>
            <?php foreach($utmeYears as $y): ?>
            <option value="<?= $y['id'] ?>" data-count="<?= $y['q_count'] ?>">
              📅 <?= $y['year'] ?> — <?= $y['q_count'] ?> questions
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="button" class="btn btn-primary" onclick="goToYearBtn('utme-year-select', '<?= urlencode($selectedSubject) ?>')" style="margin-bottom:0">Start →</button>
      </div>
    </div>
    <?php endif; ?>

    <?php if($ssceYears): ?>
    <div>
      <div class="badge badge-green" style="margin-bottom:.75rem">SSCE</div>
      <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label">Select Year</label>
          <select class="form-control" id="ssce-year-select" onchange="goToYear(this, '<?= urlencode($selectedSubject) ?>')">
            <option value="">— Choose a year —</option>
            <?php foreach($ssceYears as $y): ?>
            <option value="<?= $y['id'] ?>" data-count="<?= $y['q_count'] ?>">
              📅 <?= $y['year'] ?> — <?= $y['q_count'] ?> questions
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="button" class="btn btn-primary" onclick="goToYearBtn('ssce-year-select', '<?= urlencode($selectedSubject) ?>')" style="margin-bottom:0">Start →</button>
      </div>
    </div>
    <?php endif; ?>

    <script>
    function goToYear(select, subject) {
      // Only navigate immediately if a real value was chosen
      if (select.value) {
        window.location.href = 'adaptive.php?sy=' + select.value + '&subject=' + subject;
      }
    }
    function goToYearBtn(selectId, subject) {
      var val = document.getElementById(selectId).value;
      if (val) {
        window.location.href = 'adaptive.php?sy=' + val + '&subject=' + subject;
      } else {
        alert('Please select a year first.');
      }
    }
    </script>
  </div>

  <!-- Adaptive Mode Card -->
  <div class="card" style="margin-top:1.25rem;background:linear-gradient(135deg,rgba(79,142,247,0.08),rgba(123,92,240,0.08));border-color:rgba(79,142,247,0.3)">
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
      <div style="font-size:2.5rem">🤖</div>
      <div style="flex:1;min-width:200px">
        <div style="font-weight:700;font-size:1.05rem;margin-bottom:.35rem">Adaptive Mode — <em><?= htmlspecialchars($selectedSubject) ?></em></div>
        <div style="font-size:.875rem;color:var(--text2);line-height:1.55">Topic-level mastery tracking with 5 adaptive rules: Remedial Focus, Progressive Challenge, Topic Rotation, Prerequisite Validation, and Instant Feedback.</div>

      </div>
      <a href="adaptive.php?subject=<?= urlencode($selectedSubject) ?>"
         class="btn btn-primary btn-lg" style="flex-shrink:0"><i class="fa-solid fa-rocket"></i> Start Adaptive</a>
    </div>
  </div>

  <?php elseif($selectedSubject): ?>
  <div class="alert alert-info">No questions found for this subject yet.</div>
  <?php endif; ?>

  <!-- Popular subjects quick access -->
  <?php if(!$selectedSubject): ?>
  <div style="margin-top:2rem">
    <div style="font-size:.8rem;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:1rem">Popular Subjects</div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <?php
      $popular = ['Chemistry','Physics','Mathematics','Biology','English','Economics','Government'];
      foreach($popular as $p):
      ?>
      <a href="practice.php?subject=<?= urlencode($p) ?>" class="btn btn-ghost btn-sm"><?= $p ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
