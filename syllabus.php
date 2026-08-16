<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$pageTitle = 'Syllabus Notes — RBAPS';
$db = getDB();

$selectedSubject = $_GET['subject'] ?? '';
$selectedTopic   = $_GET['topic'] ?? '';

$allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// ── Load topics and notes ──────────────────────────────────────────────────
$topics    = [];
$notes     = [];
$materials = [];

if ($selectedSubject === 'Biology' && in_array('biology_syllabus_comprehensive', $allTables)) {
    // Biology uses its dedicated comprehensive table
    $tRes = $db->query("SELECT DISTINCT topic FROM biology_syllabus_comprehensive ORDER BY id")->fetchAll();
    foreach ($tRes as $r) $topics[] = $r['topic'];

    if ($selectedTopic) {
        $stmt = $db->prepare("SELECT * FROM biology_syllabus_comprehensive WHERE topic=? ORDER BY id");
        $stmt->execute([$selectedTopic]);
        $notes = $stmt->fetchAll();
    }

} elseif ($selectedSubject && in_array('syllabus', $allTables)) {
    // All other subjects — resolve subject name → numeric id
    $sidStmt = $db->prepare("SELECT id FROM subjectname WHERE name=? LIMIT 1");
    $sidStmt->execute([$selectedSubject]);
    $subjectRow = $sidStmt->fetch();
    $subjectId  = $subjectRow ? (string)$subjectRow['id'] : null;

    if ($subjectId) {
        // Topics from syllabus table
        $sylTopics = $db->prepare("SELECT DISTINCT topic FROM syllabus WHERE subjectid=? ORDER BY id LIMIT 200");
        $sylTopics->execute([$subjectId]);
        $topics = $sylTopics->fetchAll(PDO::FETCH_COLUMN);

        // Merge any topics that exist only in materials
        try {
            $matTopics = $db->prepare("SELECT DISTINCT topic FROM materials WHERE subject_id=? ORDER BY id");
            $matTopics->execute([(int)$subjectId]);
            foreach ($matTopics->fetchAll(PDO::FETCH_COLUMN) as $mt) {
                if (!in_array($mt, $topics)) $topics[] = $mt;
            }
        } catch (Exception $e) {}

        if ($selectedTopic) {
            // Syllabus notes for this topic
            $stmt = $db->prepare("SELECT * FROM syllabus WHERE subjectid=? AND topic=? ORDER BY id LIMIT 100");
            $stmt->execute([$subjectId, $selectedTopic]);
            $notes = $stmt->fetchAll();

            // Materials for this topic — THIS was the missing query
            try {
                $mstmt = $db->prepare("SELECT * FROM materials WHERE subject_id=? AND topic=? ORDER BY id");
                $mstmt->execute([(int)$subjectId, $selectedTopic]);
                $materials = $mstmt->fetchAll();
            } catch (Exception $e) {}
        }
    }
}

// Subjects list for nav
$subjects = $db->query("SELECT name FROM subjectname ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<!-- ── Mobile navigation (hidden on desktop via CSS) ──────────────────────── -->
<div class="syllabus-mobile-nav">
  <div>
    <label for="mobile-subject-select">Subject</label>
    <select id="mobile-subject-select">
      <option value="">— Select a subject —</option>
      <?php foreach($subjects as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= $selectedSubject===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($selectedSubject && $topics): ?>
  <div>
    <label for="mobile-topic-select">Topic</label>
    <select id="mobile-topic-select">
      <option value="">— Select a topic —</option>
      <?php foreach($topics as $t): ?>
      <option value="<?= htmlspecialchars($t) ?>" <?= $selectedTopic===$t?'selected':'' ?>><?= htmlspecialchars(strlen($t)>60?substr($t,0,60).'…':$t) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:260px 1fr;gap:0;min-height:calc(100vh - 64px)" class="syllabus-layout">

  <!-- Sidebar -->
  <div class="syllabus-sidebar" style="background:var(--bg2);border-right:1px solid var(--border);padding:1.5rem 1rem;overflow-y:auto">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:.75rem">Subjects</div>
    <?php foreach($subjects as $s): ?>
    <a href="syllabus.php?subject=<?= urlencode($s) ?>"
       style="display:block;padding:.5rem .75rem;border-radius:8px;font-size:.875rem;margin-bottom:2px;
              color:<?= $selectedSubject===$s?'var(--text)':'var(--text2)' ?>;
              background:<?= $selectedSubject===$s?'var(--card)':'transparent' ?>;
              text-decoration:none;transition:all .15s"
       onmouseover="this.style.background='var(--card)'" onmouseout="this.style.background='<?= $selectedSubject===$s?'var(--card)':'transparent' ?>'">
      <?= htmlspecialchars($s) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Main content -->
  <div class="syllabus-main-content" style="padding:2rem;overflow-y:auto">

    <?php if (!$selectedSubject): ?>
      <div style="text-align:center;padding:4rem 2rem">
        <div style="font-size:3rem;margin-bottom:1rem"><i class="fa-solid fa-book-open" style="color:var(--text3)"></i></div>
        <h2 style="font-family:'DM Serif Display',serif;font-size:1.8rem;margin-bottom:.75rem">Syllabus & Study Notes</h2>
        <p style="color:var(--text2);margin-bottom:2rem">Select a subject from the left panel to explore topics and detailed notes.</p>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
          <?php foreach(['Biology','Chemistry','Physics','Mathematics','English'] as $s): ?>
          <a href="syllabus.php?subject=<?= urlencode($s) ?>" class="btn btn-ghost"><?= $s ?></a>
          <?php endforeach; ?>
        </div>
      </div>

    <?php elseif ($selectedSubject): ?>

      <div class="syllabus-subject-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem">
        <div>
          <h2 style="font-family:'DM Serif Display',serif;font-size:1.5rem"><?= htmlspecialchars($selectedSubject) ?> Notes</h2>
          <p style="color:var(--text2);font-size:.875rem;margin-top:.25rem"><?= count($topics) ?> topics available</p>
        </div>
        <a href="practice.php?subject=<?= urlencode($selectedSubject) ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-rocket"></i> Practice <?= htmlspecialchars($selectedSubject) ?></a>
      </div>

      <?php if ($topics): ?>
      <!-- Topic pills -->
      <div class="topic-pill-wrap" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem">
        <?php foreach($topics as $t): ?>
        <a href="syllabus.php?subject=<?= urlencode($selectedSubject) ?>&topic=<?= urlencode($t) ?>"
           class="filter-btn <?= $selectedTopic===$t?'active':'' ?>">
          <?= htmlspecialchars(strlen($t)>55 ? substr($t,0,55).'…' : $t) ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($selectedTopic): ?>

        <?php
        $hasMaterials = !empty($materials);
        $hasSyllabus  = !empty($notes);
        ?>

        <?php if (!$hasMaterials && !$hasSyllabus): ?>
          <div class="alert alert-info">
            <i class="fa-solid fa-book"></i> No notes found for <strong><?= htmlspecialchars($selectedTopic) ?></strong> yet.
            <br><br>
            <a href="practice.php?subject=<?= urlencode($selectedSubject) ?>" class="btn btn-primary btn-sm">Practice <?= htmlspecialchars($selectedSubject) ?> questions instead</a>
          </div>

        <?php else: ?>

          <?php if ($hasMaterials): ?>
          <!-- Rich materials — detailed study notes from `materials` table -->
          <?php if ($hasSyllabus): ?>
          <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--accent);margin-bottom:.75rem">
            <i class="fa-solid fa-star"></i> Detailed Study Notes
          </div>
          <?php endif; ?>

          <?php foreach($materials as $mat):
            $subTopic = $mat['sub_topic'] ?? '';
            $noteText = $mat['note']      ?? '';
            $keywords = $mat['keywords']  ?? '';
            $heading  = $subTopic ?: ($mat['topic'] ?? '');
            $noteHtml = (strpos($noteText, '<') !== false)
                        ? $noteText
                        : nl2br(htmlspecialchars($noteText));
          ?>
          <div class="card" style="margin-bottom:1.25rem">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:<?= $noteHtml ? '.75rem' : '0' ?>"><?= htmlspecialchars($heading) ?></h3>
            <?php if ($noteHtml): ?>
            <div style="font-size:.9rem;line-height:1.75;color:var(--text2)"><?= $noteHtml ?></div>
            <?php endif; ?>
            <?php if ($keywords): ?>
            <div style="margin-top:1rem;padding-top:.75rem;border-top:1px solid var(--border)">
              <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);margin-bottom:.5rem">Key Terms</div>
              <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                <?php foreach(array_slice(explode(',', $keywords), 0, 20) as $kw): ?>
                <?php $kw = trim($kw); if (!$kw) continue; ?>
                <span style="background:rgba(79,142,247,0.08);border:1px solid rgba(79,142,247,0.15);color:var(--accent);padding:.2rem .55rem;border-radius:6px;font-size:.72rem"><?= htmlspecialchars($kw) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endif; // hasMaterials ?>

          <?php if ($hasSyllabus): ?>
          <!-- Syllabus overview notes from `syllabus` table -->
          <?php if ($hasMaterials): ?>
          <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:.75rem;margin-top:.5rem">
            <i class="fa-solid fa-list"></i> Syllabus Overview
          </div>
          <?php endif; ?>

          <?php foreach($notes as $note):
            $subTopic = $note['subtopic'] ?? $note['sub_topic'] ?? null;
            $topic    = $note['topic']    ?? '';
            $noteText = $note['notes']    ?? $note['note']     ?? '';
            $keywords = $note['keywords'] ?? '';
            $noteHtml = (strpos($noteText, '<') !== false)
                        ? $noteText
                        : nl2br(htmlspecialchars($noteText));
            $heading  = ($subTopic !== null && $subTopic !== '') ? $subTopic : $topic;
          ?>
          <div class="card" style="margin-bottom:1.25rem">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:<?= $noteHtml ? '.75rem' : '0' ?>"><?= htmlspecialchars($heading) ?></h3>
            <?php if($subTopic && $topic && $subTopic !== $topic): ?>
            <span class="badge badge-blue" style="margin-bottom:.75rem;display:inline-block"><?= htmlspecialchars($topic) ?></span>
            <?php endif; ?>
            <?php if($noteHtml): ?>
            <div style="font-size:.9rem;line-height:1.75;color:var(--text2)"><?= $noteHtml ?></div>
            <?php endif; ?>
            <?php if($keywords): ?>
            <div style="margin-top:1rem;padding-top:.75rem;border-top:1px solid var(--border)">
              <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);margin-bottom:.5rem">Key Terms</div>
              <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                <?php foreach(array_slice(explode(',', $keywords), 0, 16) as $kw): ?>
                <?php $kw = trim($kw); if(!$kw) continue; ?>
                <span style="background:rgba(79,142,247,0.08);border:1px solid rgba(79,142,247,0.15);color:var(--accent);padding:.2rem .55rem;border-radius:6px;font-size:.72rem"><?= htmlspecialchars($kw) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endif; // hasSyllabus ?>

        <?php endif; // has content ?>

      <?php elseif (!$topics): ?>
        <div class="alert alert-info">Syllabus data for <strong><?= htmlspecialchars($selectedSubject) ?></strong> is not yet available. <a href="practice.php?subject=<?= urlencode($selectedSubject) ?>">Practice questions</a> instead.</div>

      <?php else: ?>
        <!-- Topic not yet selected -->
        <div style="text-align:center;padding:3rem 2rem;color:var(--text2)">
          <i class="fa-solid fa-hand-pointer" style="font-size:2rem;margin-bottom:1rem;display:block"></i>
          Select a topic above to view detailed study notes.
        </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<style>
/* ── Mobile-first responsive syllabus ─────────────────────────────────────── */

/* Mobile subject/topic selector bar — hidden on desktop */
.syllabus-mobile-nav {
  display: none;
  padding: .75rem 1rem;
  background: var(--bg2);
  border-bottom: 1px solid var(--border);
  gap: .5rem;
  flex-direction: column;
}
.syllabus-mobile-nav select {
  width: 100%;
  padding: .6rem .75rem;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--text);
  font-size: .9rem;
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right .75rem center;
  padding-right: 2.25rem;
  cursor: pointer;
}
.syllabus-mobile-nav label {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--text3);
  margin-bottom: .2rem;
}

@media (max-width: 700px) {
  /* Hide desktop sidebar, show mobile nav */
  .syllabus-sidebar { display: none !important; }
  .syllabus-layout  { grid-template-columns: 1fr !important; }
  .syllabus-mobile-nav { display: flex; }

  /* Main content padding */
  .syllabus-main-content { padding: 1rem !important; }

  /* Heading */
  .syllabus-main-content h2 { font-size: 1.2rem !important; }

  /* Topic pills */
  .topic-pill-wrap .filter-btn {
    font-size: .75rem;
    padding: .3rem .6rem;
  }

  /* Cards */
  .syllabus-main-content .card { padding: 1rem !important; }
  .syllabus-main-content .card h3 { font-size: .95rem !important; }

  /* Practice button — full width */
  .syllabus-subject-header { flex-direction: column !important; align-items: flex-start !important; }
  .syllabus-subject-header .btn-sm { width: 100%; text-align: center; }
}

@media (max-width: 400px) {
  .syllabus-mobile-nav   { padding: .5rem .75rem; }
  .syllabus-main-content { padding: .75rem !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var subSel = document.getElementById('mobile-subject-select');
  var topSel = document.getElementById('mobile-topic-select');
  if (subSel) {
    subSel.addEventListener('change', function () {
      if (this.value) window.location = 'syllabus.php?subject=' + encodeURIComponent(this.value);
    });
  }
  if (topSel) {
    topSel.addEventListener('change', function () {
      var subj = subSel ? subSel.value : '';
      if (this.value && subj)
        window.location = 'syllabus.php?subject=' + encodeURIComponent(subj) + '&topic=' + encodeURIComponent(this.value);
    });
  }
});
</script>

<?php include 'includes/footer.php'; ?>
