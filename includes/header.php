<?php
$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $_SESSION['username'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$userInitial = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'RBAPS — Dynamic Mastery Assessment' ?></title>
  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='%23c9a84c'/><stop offset='1' stop-color='%23f5dfa0'/></linearGradient></defs><rect width='100' height='100' rx='22' fill='%230d0f14'/><rect width='100' height='100' rx='22' fill='none' stroke='url(%23g)' stroke-width='4'/><text y='.88em' x='50%' font-size='58' text-anchor='middle' fill='url(%23g)' font-family='Georgia,serif' font-weight='bold'>R</text></svg>">
  <meta name="theme-color" content="#ffffff">
  <script>
    const savedTheme = localStorage.getItem('rbaps_theme');
    if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  </script>
</head>
<body>
<div class="nav-overlay" id="nav-overlay"></div>
<nav class="navbar" role="navigation" aria-label="Main navigation">
  <a href="index.php" class="nav-brand" style="text-decoration:none" aria-label="RBAPS Home">
    <div class="logo-icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></div>
    <span>RBAPS</span>
  </a>

  <div class="nav-links" id="nav-links" role="menubar">
    <button class="nav-close-btn" id="nav-close-btn" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
    <a href="index.php" class="<?= $currentPage==='index'?'active':'' ?>" role="menuitem">Home</a>
    <?php if($isLoggedIn): ?>
    <a href="dashboard.php" class="<?= $currentPage==='dashboard'?'active':'' ?>" role="menuitem">Dashboard</a>
    <a href="practice.php" class="<?= $currentPage==='practice'?'active':'' ?>" role="menuitem">Practice</a>
    <a href="non_adaptive.php" class="<?= ($currentPage??'')==='non_adaptive'?'active':'' ?>" role="menuitem">Year Practice</a>
    <a href="syllabus.php" class="<?= $currentPage==='syllabus'?'active':'' ?>" role="menuitem">Syllabus</a>
    <a href="mastery_report.php" class="<?= $currentPage==='mastery_report'?'active':'' ?>" role="menuitem">
      <i class="fa-solid fa-chart-line" aria-hidden="true"></i> Mastery
    </a>
    <a href="games.php" class="<?= $currentPage==='games'?'active':'' ?>" role="menuitem">
      <i class="fa-solid fa-gamepad" aria-hidden="true"></i> Games
    </a>
    <a href="profile.php" class="<?= $currentPage==='profile'?'active':'' ?>" role="menuitem">
      <i class="fa-solid fa-user-gear" aria-hidden="true"></i> Profile
    </a>
    <?php endif; ?>
  </div>

  <div class="nav-right">
    <?php if($isLoggedIn): ?>
      <a href="profile.php" style="text-decoration:none">
        <div class="nav-user-chip" style="cursor:pointer">
          <div class="nav-user-avatar" aria-hidden="true"><?= $userInitial ?></div>
          <span class="nav-user-name"><?= htmlspecialchars($userName) ?></span>
        </div>
      </a>
      <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-outline btn-sm">Login</a>
      <a href="register.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-rocket" aria-hidden="true"></i> Sign Up</a>
    <?php endif; ?>

    <button type="button" id="theme-toggle" class="btn btn-ghost btn-sm" aria-label="Toggle dark mode" style="padding: 0.38rem 0.5rem;">
      <i class="fa-solid fa-moon"></i>
    </button>
    <button class="nav-hamburger" id="nav-hamburger" aria-label="Toggle menu" aria-expanded="false" aria-controls="nav-links">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>
