<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';
$pageTitle = 'Forgot Password — RBAPS';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if (!$identifier) {
        $error = 'Please enter your email address or phone number.';
    } else {
        try {
            $db = getDB();

            $isPhone = preg_match('/^(\+?234|0)[789]\d{9}$/', $identifier);
            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

            if (!$isPhone && !$isEmail) {
                $error = 'Please enter a valid email address or Nigerian phone number (e.g. 08012345678).';
            } else {
                $user = null;
                $contactMethod = '';

                if ($isPhone) {
                    $stmt = $db->prepare("SELECT id, email, phone, username FROM users WHERE phone = ? LIMIT 1");
                    $stmt->execute([$identifier]);
                    $user = $stmt->fetch();
                    $contactMethod = 'phone';
                } else {
                    $stmt = $db->prepare("SELECT id, email, phone, username FROM users WHERE email = ? LIMIT 1");
                    $stmt->execute([$identifier]);
                    $user = $stmt->fetch();
                    $contactMethod = 'email';
                }

                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                if ($user) {
                    // Create password_resets table if it doesn't exist yet
                    $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
                        id         INT AUTO_INCREMENT PRIMARY KEY,
                        identifier VARCHAR(120) NOT NULL,
                        token      VARCHAR(80)  NOT NULL UNIQUE,
                        expires_at DATETIME     NOT NULL,
                        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
                    )");

                    $db->prepare("DELETE FROM password_resets WHERE identifier = ?")->execute([$identifier]);
                    $db->prepare("INSERT INTO password_resets (identifier, token, expires_at) VALUES (?, ?, ?)")
                       ->execute([$identifier, $token, $expires]);

                    $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                               . dirname($_SERVER['REQUEST_URI']) . '/reset_password.php?token=' . $token;

                    if ($contactMethod === 'email') {
                        sendMail($user['email'], 'Reset your RBAPS password', "
                            <p>Hi <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
                            <p>We received a request to reset your RBAPS password. Click the button below — this link expires in <strong>1 hour</strong>.</p>
                            <p style=\"text-align:center;margin:28px 0\">
                              <a href=\"{$resetLink}\" style=\"background:#c9a84c;color:#0d0f14;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;\">Reset Password</a>
                            </p>
                            <p>If you didn't request this, you can safely ignore this email.</p>
                        ");
                        $success = 'email';
                    } else {
                        // Phone: show the link directly on screen (no SMS service needed)
                        $_SESSION['phone_reset_link']   = $resetLink;
                        $_SESSION['phone_reset_expiry'] = date('h:i A', strtotime($expires));
                        $success = 'phone';
                    }
                } else {
                    $success = 'notfound'; // generic to avoid enumeration
                }
            }
        } catch (Exception $e) {
            $error = 'Something went wrong. Please try again.';
            error_log($e->getMessage());
        }
    }
}

include 'includes/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><div class="auth-logo-icon"><i class="fa-solid fa-graduation-cap"></i></div></div>
    <h2>Forgot Password</h2>
    <p>Enter your registered email or phone number</p>

    <?php if ($error): ?>
    <div class="alert alert-error">? <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success === 'email'): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> A reset link has been sent to your email. Please check your inbox and spam folder.</div>
    <p style="text-align:center;margin-top:1rem;font-size:.875rem;color:var(--text2)">Remembered it? <a href="login.php">Log in</a></p>

    <?php elseif ($success === 'phone'): ?>
    <?php
      $link   = $_SESSION['phone_reset_link']   ?? '';
      $expiry = $_SESSION['phone_reset_expiry'] ?? '';
      unset($_SESSION['phone_reset_link'], $_SESSION['phone_reset_expiry']);
    ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Phone number verified! Click the button below to reset your password (expires at <strong><?= htmlspecialchars($expiry) ?></strong>).</div>
    <p style="text-align:center;margin:1.5rem 0">
      <a href="<?= htmlspecialchars($link) ?>" class="btn btn-primary" style="display:inline-block">
        <i class="fa-solid fa-key"></i> Reset My Password →
      </a>
    </p>
    <p style="font-size:.8rem;color:var(--text2);text-align:center">Keep this page open — this button will expire.</p>

    <?php elseif ($success === 'notfound'): ?>
    <div class="alert alert-error">? No account found with that email or phone number. Please check and try again.</div>
    <div style="margin-top:1rem;padding:.875rem 1rem;background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.2);border-radius:8px;font-size:.82rem;color:var(--text2);line-height:1.6">
      <i class="fa-solid fa-circle-info" style="color:var(--gold,#c9a84c);margin-right:.4rem"></i>
      If you registered without an email or phone, ask your teacher or school administrator to reset your account.
    </div>

    <?php else: ?>
    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label class="form-label">Email Address or Phone Number</label>
        <input class="form-control" type="text" name="identifier" required
               value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
               placeholder="your@email.com  or  08012345678">
        <p style="font-size:.8rem;color:var(--text2);margin-top:.4rem">Enter the email or phone number you used when creating your account.</p>
      </div>
      <button type="submit" class="btn btn-primary w-full" style="margin-top:.5rem">
        <i class="fa-solid fa-paper-plane"></i> Send Reset Link →
      </button>
    </form>

    <div style="margin-top:1.25rem;padding:.875rem 1rem;background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.2);border-radius:8px;font-size:.82rem;color:var(--text2);line-height:1.6">
      <i class="fa-solid fa-circle-info" style="color:var(--gold,#c9a84c);margin-right:.4rem"></i>
      <strong>Registered without an email or phone?</strong> Ask your teacher or school administrator to reset your password for you.
    </div>

    <p style="text-align:center;margin-top:1.25rem;font-size:.875rem;color:var(--text2)">
      Remembered it? <a href="login.php">Log in</a>
    </p>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
