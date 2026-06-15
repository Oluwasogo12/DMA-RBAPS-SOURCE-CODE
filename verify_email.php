<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
$pageTitle = 'Verify Email — RBAPS';

$token = trim($_GET['token'] ?? '');
$error = $success = '';

if (!$token) {
    $error = 'Invalid verification link.';
} else {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE verify_token = ? AND is_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $db->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?")
               ->execute([$user['id']]);
            $success = 'Your email has been verified! You can now log in.';
        } else {
            // Maybe already verified
            $already = $db->prepare("SELECT id FROM users WHERE verify_token IS NULL AND is_verified = 1");
            $error = 'This link is invalid or already used. If you\'ve already verified your email, <a href="login.php">log in here</a>.';
        }
    } catch (Exception $e) {
        $error = 'Something went wrong. Please try again.';
    }
}

include 'includes/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><div class="auth-logo-icon"><i class="fa-solid fa-graduation-cap"></i></div></div>
    <h2>Email Verification</h2>

    <?php if ($error): ?>
    <div class="alert alert-error" style="text-align:center">⚠️ <?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success" style="text-align:center">
      <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
    </div>
    <p style="text-align:center;margin-top:1.5rem">
      <a href="login.php" class="btn btn-primary" style="display:inline-block">Log In Now →</a>
    </p>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
