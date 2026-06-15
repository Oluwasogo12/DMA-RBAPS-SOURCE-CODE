<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
$pageTitle = 'Login — RBAPS';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$identity || !$password) {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $db = getDB();
            // Allow login by username, email, or phone
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1");
            $stmt->execute([$identity, $identity, $identity]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID on privilege elevation (login)
                // to prevent session fixation
                session_regenerate_id(true);
                $_SESSION = [];                        // wipe any pre-login data
                $_SESSION['_init']          = true;
                $_SESSION['user_id']        = $user['id'];
                $_SESSION['username']       = $user['username'];
                $_SESSION['user']           = $user;
                $_SESSION['_user_session']  = [       // fingerprint for stale-session guard
                    'uid'        => $user['id'],
                    'logged_in'  => time(),
                ];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Incorrect username or password. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Login failed. Please make sure the database is set up.';
        }
    }
}

include 'includes/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><div class="auth-logo-icon"><i class="fa-solid fa-graduation-cap"></i></div></div>
    <h2>Welcome Back</h2>
    <p>Log in to continue your exam preparation</p>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['registered'])): ?>
    <div class="alert alert-success">
      <i class="fa-solid fa-circle-check"></i>
      Account created! You can log in immediately — no email verification needed.
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['reset'])): ?>
    <div class="alert alert-success">
      <i class="fa-solid fa-circle-check"></i>
      Password reset successfully. You can now log in with your new password.
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Username, Email or Phone</label>
        <input class="form-control" type="text" name="identity" required
               placeholder="username, email or phone number"
               value="<?= htmlspecialchars($_POST['identity'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
          <span>Password</span>
          <a href="forgot_password.php" style="font-weight:400;font-size:.8rem;color:var(--gold,#c9a84c);text-decoration:none">Forgot password?</a>
        </label>
        <div style="position:relative">
          <input class="form-control" type="password" name="password" id="login-password" required placeholder="your password" style="padding-right:2.75rem">
          <button type="button" onclick="togglePassword('login-password','login-eye')" tabindex="-1"
            style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text2);padding:0;display:flex;align-items:center;font-size:1rem">
            <i class="fa-regular fa-eye" id="login-eye"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-full" style="margin-top:.5rem">Log In →</button>
    </form>

    <p style="text-align:center;margin-top:1.25rem;font-size:.875rem;color:var(--text2)">
      Don't have an account? <a href="register.php">Sign up free</a>
    </p>
  </div>
</div>
<script>
function togglePassword(inputId, iconId) {
  var input = document.getElementById(inputId);
  var icon  = document.getElementById(iconId);
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}
</script>
<?php include 'includes/footer.php'; ?>
