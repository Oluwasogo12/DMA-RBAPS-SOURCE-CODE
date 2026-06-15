<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
$pageTitle = 'Reset Password — RBAPS';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$token      = trim($_GET['token'] ?? '');
$error      = $success = '';
$validToken = false;
$tokenIdentifier = '';

if (!$token) {
    $error = 'Invalid or missing reset token.';
} else {
    try {
        $db = getDB();

        // Support both old (email column) and new (identifier column) schema
        try {
            $stmt = $db->prepare("SELECT identifier FROM password_resets WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            $row = $stmt->fetch();
        } catch (Exception $e) {
            // Fallback: old schema used 'email' column
            $stmt = $db->prepare("SELECT email AS identifier FROM password_resets WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            $row = $stmt->fetch();
        }

        if ($row) {
            $validToken      = true;
            $tokenIdentifier = $row['identifier'];
        } else {
            $error = 'This reset link is invalid or has expired. <a href="forgot_password.php">Request a new one</a>.';
        }
    } catch (Exception $e) {
        $error = 'Something went wrong. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Update by email or phone — whichever was used
            $isPhone = preg_match('/^(\+?234|0)[789]\d{9}$/', $tokenIdentifier);
            if ($isPhone) {
                $db->prepare("UPDATE users SET password_hash = ? WHERE phone = ?")->execute([$hash, $tokenIdentifier]);
            } else {
                $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, $tokenIdentifier]);
            }

            // Clean up token
            try {
                $db->prepare("DELETE FROM password_resets WHERE identifier = ?")->execute([$tokenIdentifier]);
            } catch (Exception $e) {
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$tokenIdentifier]);
            }

            $success    = true;
            $validToken = false;
        } catch (Exception $e) {
            $error = 'Failed to update password. Please try again.';
        }
    }
}

include 'includes/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><div class="auth-logo-icon"><i class="fa-solid fa-graduation-cap"></i></div></div>
    <h2>Reset Password</h2>
    <p>Choose a new secure password for your account</p>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Password updated successfully!</div>
    <p style="text-align:center;margin-top:1rem">
      <a href="login.php?reset=1" class="btn btn-primary" style="display:inline-block">Go to Login →</a>
    </p>
    <?php endif; ?>

    <?php if ($validToken): ?>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input class="form-control" type="password" name="password" required placeholder="Minimum 6 characters">
      </div>
      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input class="form-control" type="password" name="confirm" required placeholder="Repeat your new password">
      </div>
      <button type="submit" class="btn btn-primary w-full" style="margin-top:.5rem">Update Password →</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
