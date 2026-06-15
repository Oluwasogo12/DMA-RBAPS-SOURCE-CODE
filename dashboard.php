<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$pageTitle = 'Dashboard — RBAPS';
$user = currentUser();
$db   = getDB();

// Ensure performance & sessions tables exist
$db->exec("CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject_name VARCHAR(60),
    category VARCHAR(8),
    year VARCHAR(8),
    total_q INT DEFAULT 0,
    correct INT DEFAULT 0,
    score_pct DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS user_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject_name VARCHAR(60),
    mastery_score DECIMAL(5,2) DEFAULT 0,
    total_attempted INT DEFAULT 0,
    total_correct INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_subj (user_id, subject_name)
)");

$uid = $_SESSION['user_id'];

// Stats
$totalSessions = $db->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id=?");
$totalSessions->execute([$uid]);
$sessCount = $totalSessions->fetchColumn() ?: 0;

$totalQ = $db->prepare("SELECT SUM(total_q) FROM user_sessions WHERE user_id=?");
$totalQ->execute([$uid]);
$qCount = $totalQ->fetchColumn() ?: 0;

$avgScore = $db->prepare("SELECT AVG(score_pct) FROM user_sessions WHERE user_id=?");
$avgScore->execute([$uid]);
$avg = round($avgScore->fetchColumn() ?: 0, 1);

// Subject mastery
$mastery = $db->prepare("SELECT subject_name, mastery_score FROM user_performance WHERE user_id=? ORDER BY mastery_score DESC");
$mastery->execute([$uid]);
$masteryRows = $mastery->fetchAll();

// Recent sessions
$recent = $db->prepare("SELECT * FROM user_sessions WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
$recent->execute([$uid]);
$recentRows = $recent->fetchAll();

// Total questions in DB
$dbQ = $db->query("SELECT COUNT(*) FROM questions")->fetchColumn();

include 'includes/header.php';
?>

<div class="section">
  <?php if(isset($_GET['welcome'])): ?>
  <div class="alert alert-success" style="margin-bottom:1.5rem">
    🎉 Welcome to RBAPS! Start practising to track your progress.
  </div>
  <?php endif; ?>

  <div class="dashboard-welcome-banner" style="position:relative; overflow:hidden; border-radius:24px; padding:3rem; margin-bottom:2.5rem; background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(124, 58, 237, 0.03) 100%); border:1px solid rgba(79, 70, 229, 0.15); box-shadow: 0 10px 40px -10px rgba(79, 70, 229, 0.1);">
    <div style="position:absolute; top:-50px; right:-50px; width:200px; height:200px; background:radial-gradient(circle, rgba(79,70,229,0.15) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>
    <div style="position:absolute; bottom:-30px; left:-30px; width:150px; height:150px; background:radial-gradient(circle, rgba(0,200,150,0.1) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>
    
    <div style="position:relative; z-index:1; display:flex; flex-direction:column; gap:0.5rem;">
      <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.25rem;">
        <span class="badge" style="background:rgba(79,70,229,0.1); color:var(--accent); font-weight:700; letter-spacing:0.05em; padding:0.4rem 0.8rem; border-radius:8px;">STUDENT PORTAL</span>
        <span style="font-size:0.85rem; color:var(--text3); font-family:'JetBrains Mono', monospace;"><i class="fa-solid fa-clock"></i> <?= date('l, d M') ?></span>
      </div>
      <h2 class="welcome-heading" style="font-family:'DM Serif Display', serif; line-height:1.1; color:var(--text); letter-spacing:-0.02em; margin:0;">
        Welcome back! 👋
      </h2>
      <p style="color:var(--text2); font-size:1.05rem; max-width:600px; margin-top:0.5rem; line-height:1.6;">
        Track your mastery progress, review recent practice sessions, and tackle your recommended weak topics.
      </p>
    </div>
  </div>

  <?php if($sessCount == 0): ?>
  <div class="card" style="text-align:center; padding: 4rem 2rem; margin-bottom: 2rem; border-color:rgba(79,70,229,0.3); background:linear-gradient(180deg, var(--card), rgba(79,70,229,0.03))">
    <div style="font-size:3.5rem; color:var(--accent); margin-bottom:1rem;"><i class="fa-solid fa-rocket"></i></div>
    <h3 style="font-size:1.75rem; margin-bottom:0.75rem; color:var(--text);">Welcome to RBAPS!</h3>
    <p style="color:var(--text2); margin-bottom:2rem; font-size:1.1rem; max-width:600px; margin-left:auto; margin-right:auto;">
      You haven't taken any quizzes yet. Click below to start your first Adaptive Practice session and the system will begin tracking your mastery!
    </p>
    <a href="practice.php" class="btn btn-primary" style="display:inline-flex; font-size:1.1rem; padding: 0.8rem 2rem;">
      Start First Practice <i class="fa-solid fa-arrow-right" style="margin-left:0.5rem"></i>
    </a>
  </div>
  <?php endif; ?>

  <!-- Hero Stat Cards -->
  <div class="hero-stats" style="margin-bottom:2rem">
    <div class="hero-stat-card" style="--accent:#c9a84c;--accent2:#f7e08a;--glow:rgba(201,168,76,0.18)">
      <div class="hsc-bg-glow"></div>
      <div class="hsc-inner">
        <div class="hsc-top">
          <div class="hsc-icon"><i class="fa-solid fa-book-open"></i></div>
          <div class="hsc-trend"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="hsc-val"><?= number_format($sessCount) ?></div>
        <div class="hsc-lbl">Practice Sessions</div>
        <div class="hsc-bar"><div class="hsc-bar-fill" style="width:<?= min(100, $sessCount * 10) ?>%"></div></div>
      </div>
    </div>
    <div class="hero-stat-card" style="--accent:#4f8ef7;--accent2:#a0c4ff;--glow:rgba(79,142,247,0.18)">
      <div class="hsc-bg-glow"></div>
      <div class="hsc-inner">
        <div class="hsc-top">
          <div class="hsc-icon"><i class="fa-solid fa-circle-question"></i></div>
          <div class="hsc-trend"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="hsc-val"><?= number_format($qCount) ?></div>
        <div class="hsc-lbl">Questions Attempted</div>
        <div class="hsc-bar"><div class="hsc-bar-fill" style="width:<?= min(100, ($dbQ > 0 ? ($qCount / $dbQ) * 100 : 0)) ?>%"></div></div>
      </div>
    </div>
    <div class="hero-stat-card" style="--accent:#00c896;--accent2:#7fffda;--glow:rgba(0,200,150,0.18)">
      <div class="hsc-bg-glow"></div>
      <div class="hsc-inner">
        <div class="hsc-top">
          <div class="hsc-icon"><i class="fa-solid fa-bullseye"></i></div>
          <div class="hsc-trend" style="color:<?= $avg >= 50 ? 'var(--accent)' : '#ff4d6d' ?>">
            <i class="fa-solid fa-<?= $avg >= 50 ? 'arrow-trend-up' : 'arrow-trend-down' ?>"></i>
          </div>
        </div>
        <div class="hsc-val"><?= $avg ?>%</div>
        <div class="hsc-lbl">Average Score</div>
        <div class="hsc-bar"><div class="hsc-bar-fill" style="width:<?= $avg ?>%"></div></div>
      </div>
    </div>
    <div class="hero-stat-card" style="--accent:#7b5cf0;--accent2:#c4b5fd;--glow:rgba(123,92,240,0.18)">
      <div class="hsc-bg-glow"></div>
      <div class="hsc-inner">
        <div class="hsc-top">
          <div class="hsc-icon"><i class="fa-solid fa-database"></i></div>
          <div class="hsc-badge">LIVE</div>
        </div>
        <div class="hsc-val">1K+</div>
        <div class="hsc-lbl">Available Questions</div>
        <div class="hsc-bar"><div class="hsc-bar-fill" style="width:100%"></div></div>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem" class="two-col">
    <!-- Subject Mastery -->
    <div class="card" style="border-radius:20px; border:1px solid var(--slate-alpha-5); box-shadow:0 8px 30px -10px rgba(0,0,0,0.05);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <h3 style="font-size:1.15rem; font-weight:700; margin:0;"><i class="fa-solid fa-chart-pie" style="color:var(--accent); margin-right:8px;"></i> Subject Mastery</h3>
        <a href="mastery_report.php" style="font-size:0.8rem; color:var(--accent); font-weight:600; text-decoration:none;">View All &rarr;</a>
      </div>
      <?php if($masteryRows): ?>
        <div style="display:flex; flex-direction:column; gap:1.25rem;">
        <?php foreach(array_slice($masteryRows, 0, 5) as $row):
          $pct = round($row['mastery_score']);
          $color = $pct >= 80 ? 'var(--green)' : ($pct >= 60 ? 'var(--gold)' : ($pct >= 40 ? 'var(--accent)' : 'var(--red)'));
        ?>
        <div class="mastery-row" style="display:flex; flex-direction:column; gap:0.4rem;">
          <div style="display:flex; justify-content:space-between; align-items:flex-end;">
            <span class="mastery-name" style="font-weight:600; font-size:0.95rem; color:var(--text);"><?= htmlspecialchars($row['subject_name']) ?></span>
            <span class="mastery-pct" style="font-family:'JetBrains Mono', monospace; font-weight:700; font-size:0.9rem; color:<?= $color ?>"><?= $pct ?>%</span>
          </div>
          <div class="mastery-bar-wrap" style="height:6px; background:var(--slate-alpha-4); border-radius:999px; overflow:hidden;">
            <div class="mastery-bar-fill" style="height:100%; width:<?= $pct ?>%; background:<?= $color ?>; border-radius:999px; transition:width 1s ease-out;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:3rem 2rem;color:var(--text3); background:var(--slate-alpha-2); border-radius:12px;">
          <div style="font-size:2.5rem;margin-bottom:1rem; opacity:0.5;"><i class="fa-solid fa-chart-pie"></i></div>
          <p style="font-size:0.9rem; margin-bottom:1.5rem;">No data yet. Start practising to see your mastery!</p>
          <a href="practice.php" class="btn btn-primary btn-sm">Start Practice</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="card" style="display:flex; flex-direction:column; border-radius:20px; border:1px solid var(--slate-alpha-5); box-shadow:0 8px 30px -10px rgba(0,0,0,0.05); background:linear-gradient(180deg, var(--card), rgba(255,255,255,0.01));">
      <h3 style="font-size:1.15rem; margin-bottom:1.5rem; font-weight:700;"><i class="fa-solid fa-bolt" style="color:#f5a623; margin-right:8px;"></i> Quick Actions</h3>
      <div class="action-grid">
        <a href="practice.php" class="action-card" style="--ac-color: #4f8ef7;">
          <div class="ac-icon-wrap"><i class="fa-solid fa-rocket"></i></div>
          <span>Adaptive Practice</span>
        </a>
        <a href="non_adaptive.php" class="action-card" style="--ac-color: #00c896;">
          <div class="ac-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>
          <span>Year-Based Practice</span>
        </a>
        <a href="mastery_report.php" class="action-card" style="--ac-color: #f5c842;">
          <div class="ac-icon-wrap"><i class="fa-solid fa-chart-line"></i></div>
          <span>Mastery Report</span>
        </a>
        <a href="games.php" class="action-card" style="--ac-color: #7b5cf0;">
          <div class="ac-icon-wrap"><i class="fa-solid fa-gamepad"></i></div>
          <span>Brain Games</span>
        </a>
        <?php if($masteryRows): ?>
        <?php
          // Find weakest subject
          $weakest = end($masteryRows);
          reset($masteryRows);
        ?>
        <a href="practice.php?subject=<?= urlencode($weakest['subject_name']) ?>" class="action-card full-width" style="--ac-color: #ff4d6d; background: linear-gradient(135deg, rgba(255, 77, 109, 0.08), rgba(255, 77, 109, 0.02)); border-color:rgba(255,77,109,0.2);">
          <div style="display:flex; align-items:center; gap: 1.25rem;">
            <div class="ac-icon-wrap" style="background:rgba(255,77,109,0.15); width:50px; height:50px; display:flex; align-items:center; justify-content:center; border-radius:12px;">
              <i class="fa-solid fa-triangle-exclamation" style="font-size:1.5rem; margin:0; color:#ff4d6d;"></i>
            </div>
            <div style="text-align:left">
              <span style="display:block; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; color:#ff4d6d; margin-bottom:.2rem">Recommended Focus</span>
              <span style="font-size:1.1rem; font-weight:700; color:var(--text)"><?= htmlspecialchars($weakest['subject_name']) ?> <span style="opacity:0.7; font-weight:500;">(<?= round($weakest['mastery_score']) ?>%)</span></span>
            </div>
          </div>
        </a>
        <?php endif; ?>
      </div>
      <div style="margin-top:auto;padding-top:1.5rem;border-top:1px dashed var(--slate-alpha-6);">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="font-size:.8rem;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:.05em">Target Exam</span>
          <span class="badge" style="background:linear-gradient(135deg, var(--accent), #7c3aed); color:white; font-size:.75rem; font-weight:700; padding:0.4rem 0.8rem; box-shadow:0 4px 10px rgba(79,70,229,0.3);"><?= strtoupper($user['exam_target'] ?? 'BOTH') ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Sessions -->
  <?php if($recentRows): ?>
  <div style="margin-top:2.5rem; margin-bottom: 2rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
      <h3 style="font-size:1.15rem; font-weight:700; margin:0;"><i class="fa-solid fa-clock-rotate-left" style="color:var(--text3); margin-right:8px;"></i> Recent Activity</h3>
    </div>
    <div class="card" style="padding:0; border-radius:16px; overflow:hidden; border:1px solid var(--slate-alpha-5); box-shadow:0 8px 30px -10px rgba(0,0,0,0.05);">
      <div class="table-wrap" style="margin:0; border-radius:0;">
        <table style="margin:0;">
          <thead style="background:var(--slate-alpha-2);">
            <tr>
              <th style="padding:1.2rem 1.5rem;">Subject</th>
              <th>Mode</th>
              <th>Year</th>
              <th>Score</th>
              <th style="text-align:right; padding-right:1.5rem;">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($recentRows as $r):
              $pct = round($r['score_pct']);
              $color = $pct >= 70 ? 'var(--green)' : ($pct >= 50 ? 'var(--gold)' : 'var(--red)');
              $isAdaptive = strtolower($r['category']) === 'adaptive';
            ?>
            <tr style="border-bottom: 1px solid var(--slate-alpha-4); transition:background 0.2s;">
              <td style="padding:1rem 1.5rem;">
                <div style="font-weight:600; color:var(--text); font-size:0.95rem;"><?= htmlspecialchars($r['subject_name']) ?></div>
                <div style="font-size:0.75rem; color:var(--text3); margin-top:0.2rem;"><?= $r['correct'] ?> of <?= $r['total_q'] ?> correct</div>
              </td>
              <td>
                <span class="badge" style="background:<?= $isAdaptive ? 'rgba(79,142,247,0.1)' : 'rgba(245,200,66,0.1)' ?>; color:<?= $isAdaptive ? 'var(--accent)' : 'var(--gold)' ?>;">
                  <i class="fa-solid <?= $isAdaptive ? 'fa-robot' : 'fa-calendar-days' ?>" style="margin-right:4px;"></i> <?= strtoupper($r['category']) ?>
                </span>
              </td>
              <td style="color:var(--text2); font-size:0.9rem; font-family:'JetBrains Mono', monospace;"><?= $r['year'] ? htmlspecialchars($r['year']) : '&mdash;' ?></td>
              <td>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                  <span style="color:<?= $color ?>; font-weight:700; font-family:'JetBrains Mono', monospace; font-size:1rem;"><?= $pct ?>%</span>
                </div>
              </td>
              <td style="color:var(--text3); font-size:0.85rem; text-align:right; padding-right:1.5rem;">
                <?= date('d M Y', strtotime($r['created_at'])) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<style>
@media(max-width:768px){
  .two-col{grid-template-columns:1fr!important}
  .action-grid{grid-template-columns:1fr!important}
  .dashboard-welcome-banner { padding: 1.5rem !important; border-radius: 16px !important; }
  .welcome-heading { font-size: 1.8rem !important; }
}

.welcome-heading {
  font-size: 2.8rem;
}
.welcome-name {
  color: var(--accent);
  background: linear-gradient(135deg, var(--accent), #7c3aed);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  display: inline-block;
}

.action-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.action-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1.5rem 1rem;
  background: var(--bg3);
  border: 1px solid var(--slate-alpha-5);
  border-radius: 16px;
  text-decoration: none;
  color: var(--text2);
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  text-align: center;
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 15px -5px rgba(0,0,0,0.02);
}
.action-card::before {
  content: ''; position: absolute; inset: 0; background: var(--ac-color); opacity: 0; transition: opacity 0.3s ease;
}
.action-card:hover {
  transform: translateY(-5px);
  border-color: color-mix(in srgb, var(--ac-color) 40%, transparent);
  color: var(--text);
  box-shadow: 0 12px 24px -6px color-mix(in srgb, var(--ac-color) 20%, transparent);
  background: var(--card);
}
.action-card:hover::before {
  opacity: 0.04;
}
.ac-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: color-mix(in srgb, var(--ac-color) 12%, transparent);
  margin-bottom: 0.85rem;
  transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), background 0.3s ease;
}
.action-card i {
  font-size: 1.4rem;
  color: var(--ac-color);
  transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.action-card:hover .ac-icon-wrap {
  transform: scale(1.1) rotate(-5deg);
  background: color-mix(in srgb, var(--ac-color) 18%, transparent);
}
.action-card span {
  font-size: 0.85rem;
  font-weight: 700;
  position: relative; z-index: 1;
}
.action-card.full-width {
  grid-column: 1 / -1;
  align-items: flex-start;
  padding: 1.25rem 1.5rem;
}
.action-card.full-width:hover .ac-icon-wrap {
  transform: scale(1.05) translateX(4px);
}
table tbody tr:hover {
  background: var(--slate-alpha-2);
}
</style>
<?php include 'includes/footer.php'; ?>
