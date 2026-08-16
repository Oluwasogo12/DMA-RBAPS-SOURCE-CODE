<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
$pageTitle = 'RBAPS — Dynamic Mastery Assessment';

// Redirect logged-in users straight to their dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Get some stats
try {
    $db = getDB();
    $totalQ  = (int)$db->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    $totalS  = (int)$db->query("SELECT COUNT(*) FROM subjectname")->fetchColumn();
    $totalSY = (int)$db->query("SELECT COUNT(DISTINCT year) FROM subjectyear")->fetchColumn();
} catch(Exception $e) { $totalQ=0; $totalS=0; $totalSY=0; }

include 'includes/header.php';
?>

<section class="hero" style="position:relative;overflow:hidden;">
  <!-- Abstract Glowing Orbs -->
  <div class="hero-glow shape-1"></div>
  <div class="hero-glow shape-2"></div>
  <div class="hero-glow shape-3"></div>

  <div class="hero-content-wrapper" style="position:relative;z-index:10;max-width:1200px;margin:0 auto;padding:0 1.5rem">
    <?php if(isset($_GET['logout'])): ?>
    <div class="alert alert-success" style="margin-bottom:2rem;max-width:480px;margin-left:auto;margin-right:auto">
      <i class="fa-solid fa-circle-check"></i> You have been logged out successfully.
    </div>
    <?php endif; ?>
    
    <div class="hero-badge"><div class="hero-badge-dot"></div> Nigeria's Smartest Exam Prep Platform</div>
    
    <h1>Master UTME &amp; SSCE<br>with <em>Adaptive</em> Practice</h1>
    <p>Our system tracks your mastery topic-by-topic, dynamically adjusting difficulty and focusing entirely on your weak areas to guarantee exam readiness.</p>
    
    <div class="hero-cta">
      <a href="register.php" class="btn btn-primary btn-lg" style="box-shadow:0 8px 24px rgba(91,106,245,0.4);transform:translateY(-2px)"><i class="fa-solid fa-rocket"></i> Get Started Free</a>
      <a href="login.php" class="btn btn-outline btn-lg" style="background:var(--slate-alpha-3);backdrop-filter:blur(10px)">Login to Account</a>
    </div>

    <!-- Floating Dashboard Mockup -->
    <div class="hero-mockup-container">
      <div class="hero-mockup-card float-anim-1">
        <div class="hmc-header">
          <div class="hmc-icon" style="background:rgba(34,197,94,0.15);color:var(--green)"><i class="fa-solid fa-brain"></i></div>
          <div class="hmc-text">
            <div class="hmc-title">Topic Mastery</div>
            <div class="hmc-sub">Organic Chemistry</div>
          </div>
          <div class="hmc-score" style="color:var(--green)">86%</div>
        </div>
        <div class="hmc-bar-wrap"><div class="hmc-bar-fill" style="width:86%;background:var(--green)"></div></div>
      </div>
      
      <div class="hero-mockup-card float-anim-2" style="right: -20px; top: -30px; animation-delay: 1.5s;">
        <div class="hmc-header" style="margin-bottom:0">
          <div class="hmc-icon" style="background:rgba(245,158,11,0.15);color:var(--gold)"><i class="fa-solid fa-bolt"></i></div>
          <div class="hmc-text">
            <div class="hmc-title">Adaptive Mode Active</div>
            <div class="hmc-sub">Difficulty adjusting...</div>
          </div>
        </div>
      </div>

      <div class="hero-mockup-card float-anim-3" style="left: -15px; bottom: -20px; animation-delay: 0.7s; padding:1rem">
        <div style="display:flex;align-items:center;gap:10px">
          <div class="hmc-icon" style="background:rgba(91,106,245,0.15);color:var(--accent);width:32px;height:32px;font-size:12px"><i class="fa-solid fa-arrow-trend-up"></i></div>
          <div class="hmc-text">
            <div class="hmc-title" style="font-size:0.8rem">Rank Increase</div>
            <div class="hmc-sub" style="color:var(--accent);font-weight:700">+12% this week</div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Hero Stats Bar -->
    <div class="stats-bar glass-panel" style="margin-top:2.5rem;position:relative;z-index:20">
      <div class="stat-item"><span class="val">1K+</span><span class="lbl">Past Questions</span></div>
      <div class="stat-item"><span class="val"><?= $totalS ?></span><span class="lbl">Subjects</span></div>
      <div class="stat-item"><span class="val"><?= $totalSY ?>+</span><span class="lbl">Exam Years</span></div>
      <div class="stat-item"><span class="val">5</span><span class="lbl">Adaptive Rules</span></div>
    </div>
  </div>
</section>

<!-- Infinite Marquee Ticker -->
<div class="ticker-wrap">
  <div class="ticker">
    <div class="ticker-item"><i class="fa-solid fa-check-double"></i> Real-time Analytics</div>
    <div class="ticker-item"><i class="fa-solid fa-bullseye"></i> Targeted Remedial Focus</div>
    <div class="ticker-item"><i class="fa-solid fa-layer-group"></i> Dynamic Difficulty Adjustment</div>
    <div class="ticker-item"><i class="fa-solid fa-chart-line"></i> Persistent Topic Mastery</div>
    <div class="ticker-item"><i class="fa-solid fa-clock-rotate-left"></i> Spaced Repetition</div>
    <!-- Duplicate for seamless scroll -->
    <div class="ticker-item"><i class="fa-solid fa-check-double"></i> Real-time Analytics</div>
    <div class="ticker-item"><i class="fa-solid fa-bullseye"></i> Targeted Remedial Focus</div>
    <div class="ticker-item"><i class="fa-solid fa-layer-group"></i> Dynamic Difficulty Adjustment</div>
    <div class="ticker-item"><i class="fa-solid fa-chart-line"></i> Persistent Topic Mastery</div>
    <div class="ticker-item"><i class="fa-solid fa-clock-rotate-left"></i> Spaced Repetition</div>
  </div>
</div>

<div class="section">
  <div class="section-header reveal">
    <h2>Subjects Available</h2>
    <p>Covering all core UTME and SSCE subjects aligned to official syllabi</p>
  </div>
  <div class="card-grid">
    <?php
    $subjects = [
      ['Mathematics',         'fa-square-root-variable', 'linear-gradient(135deg,#00c896,#0097a7)', 'rgba(0,200,150,0.12)'],
      ['English',             'fa-book-open',      'linear-gradient(135deg,#f5c842,#f09819)', 'rgba(245,200,66,0.12)'],
      ['Biology',             'fa-dna',            'linear-gradient(135deg,#56ab2f,#a8e063)', 'rgba(86,171,47,0.12)'],
      ['Chemistry',           'fa-flask',          'linear-gradient(135deg,#ff6b35,#f7c59f)', 'rgba(255,107,53,0.12)'],
      ['Physics',             'fa-bolt',           'linear-gradient(135deg,#4f8ef7,#7b5cf0)', 'rgba(79,142,247,0.12)'],
    ];
    foreach($subjects as [$name, $icon, $grad, $iconBg]):
    ?>
    <a href="practice.php?subject=<?= urlencode($name) ?>" class="subject-card"
       style="--card-color:<?= $grad ?>;--icon-bg:<?= $iconBg ?>">
      <div class="icon"><i class="fa-solid <?= $icon ?>"></i></div>
      <h3><?= htmlspecialchars($name) ?></h3>
      <p>UTME &amp; SSCE questions</p>
      <div class="meta">
        <span style="display:flex; align-items:center; gap:0.4rem;"><i class="fa-solid fa-book"></i> Past Questions</span>
        <span style="background:var(--icon-bg); color:var(--text); padding:0.25rem 0.6rem; border-radius:6px; font-size:0.65rem;">ADAPTIVE</span>
      </div>
    </a>
    <?php endforeach; ?>
    <!-- 'And so on' Card -->
    <a href="register.php" class="subject-card" style="--card-color:linear-gradient(135deg,var(--slate-alpha-6),var(--slate-alpha-4));--icon-bg:var(--slate-alpha-2)">
      <div class="icon"><i class="fa-solid fa-ellipsis" style="color:var(--text2)"></i></div>
      <h3 style="color:var(--text2)">And so on...</h3>
      <p>Log in to access all subjects</p>
      <div class="meta" style="border-top:none;justify-content:flex-end">
        <span style="color:var(--accent);font-weight:600;font-size:.75rem">View All <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>
  </div>
</div>

<!-- HOW TO USE THE WEBSITE -->
<div class="section" style="padding-top:0">
  <div class="section-header reveal">
    <h2>How to Use RBAPS</h2>
    <p>Accelerate your exam preparation in 3 simple steps</p>
  </div>
  <div class="card-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
    <div class="card reveal" style="text-align:center;padding:2.5rem 2rem;">
      <div style="width:64px;height:64px;border-radius:16px;background:rgba(91,106,245,0.1);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1.5rem;box-shadow:inset 0 1px 0 rgba(255,255,255,0.4)">
        <i class="fa-solid fa-user-plus"></i>
      </div>
      <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:.5rem">1. Create an Account</h3>
      <p style="color:var(--text2);font-size:.9rem;line-height:1.6">Sign up for free and choose your exam target (JAMB, WAEC, or Both). We'll save your data permanently.</p>
    </div>
    
    <div class="card reveal" style="text-align:center;padding:2.5rem 2rem;border-color:var(--slate-alpha-8);transform:translateY(-8px);box-shadow:0 12px 24px rgba(0,0,0,0.05)">
      <div style="width:64px;height:64px;border-radius:16px;background:rgba(0,200,150,0.1);color:var(--green);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1.5rem;box-shadow:inset 0 1px 0 rgba(255,255,255,0.4)">
        <i class="fa-solid fa-crosshairs"></i>
      </div>
      <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:.5rem">2. Choose a Mode</h3>
      <p style="color:var(--text2);font-size:.9rem;line-height:1.6">Select a subject. You can practice standard Past Questions by year, or use our smart Adaptive Mode.</p>
    </div>

    <div class="card reveal" style="text-align:center;padding:2.5rem 2rem;">
      <div style="width:64px;height:64px;border-radius:16px;background:rgba(245,200,66,0.1);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1.5rem;box-shadow:inset 0 1px 0 rgba(255,255,255,0.4)">
        <i class="fa-solid fa-chart-line"></i>
      </div>
      <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:.5rem">3. Track Mastery</h3>
      <p style="color:var(--text2);font-size:.9rem;line-height:1.6">Answer questions, read the instant feedback, and watch your Topic Mastery scores rise on your dashboard!</p>
    </div>
  </div>
</div>

<!-- HOW IT WORKS -->
<div class="section" style="padding-top:0">
  <div class="section-header reveal">
    <h2>How the Adaptive Engine Works</h2>
    <p>Five intelligent rules that personalise your practice in real time</p>
  </div>
  <div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
    <?php
    $rules = [
      ['1','Remedial Focus',
       'If your topic mastery score exceeds 60%, the system moves you on to a new topic — keeping every session focused where you need it most.',
       'fa-triangle-exclamation','rgba(255,77,109,0.1)','var(--red)'],
      ['2','Progressive Challenge',
       'After three consecutive correct answers on a topic at the current difficulty level, you automatically advance — Easy → Medium → Hard.',
       'fa-arrow-trend-up','rgba(79,142,247,0.1)','#4f8ef7'],
      ['3','Mastery Reinforcement',
       'If your mastery score is below 60% and you\'ve exhausted all questions on a topic, the engine moves you to the next topic related to the one you just finished.',
       'fa-rotate','rgba(0,200,150,0.1)','var(--green)'],
      ['4','Prerequisite Validation',
       'Topics with prerequisite topics are locked until all prerequisites have a mastery score of at least 60%, ensuring a solid foundation before advancing.',
       'fa-lock','rgba(245,200,66,0.1)','var(--gold)'],
      ['5','Instant Feedback',
       'After every answer you receive immediate feedback: the correct option, a topic-specific explanation, and — for wrong answers — identification of the underlying concept missed.',
       'fa-lightbulb','rgba(255,165,0,0.1)','orange'],
    ];
    foreach($rules as [$num,$title,$desc,$icon,$bg,$color]):
    ?>
    <div class="card reveal" style="background:<?= $bg ?>;border-color:<?= $color ?>22">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
        <div style="width:36px;height:36px;border-radius:10px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;font-size:1.1rem"><i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>"></i></div>
        <div>
          <div style="font-size:.7rem;color:<?= $color ?>;font-weight:700;text-transform:uppercase;letter-spacing:.06em">Rule <?= $num ?></div>
          <div style="font-weight:700;font-size:.95rem"><?= $title ?></div>
        </div>
      </div>
      <p style="font-size:.875rem;color:var(--text2);line-height:1.6"><?= $desc ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
