<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
$pageTitle = 'Register — RBAPS';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';
    $exam     = $_POST['exam_target']   ?? 'both';

    // At least one contact method required (for future password reset)
    $hasContact = ($email !== '' || $phone !== '');

    if (!$username || !$password) {
        $error = 'Username and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That email address is not valid.';
    } elseif ($phone !== '' && !preg_match('/^(\+?234|0)[789]\d{9}$/', $phone)) {
        $error = 'Enter a valid Nigerian phone number (e.g. 08012345678 or +2348012345678).';
    } else {
        try {
            $db = getDB();

            // Ensure schema is up to date
            $db->exec("CREATE TABLE IF NOT EXISTS users (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                username      VARCHAR(60)  NOT NULL UNIQUE,
                email         VARCHAR(120) DEFAULT NULL,
                phone         VARCHAR(20)  DEFAULT NULL,
                password_hash VARCHAR(255) NOT NULL,
                exam_target   VARCHAR(10)  DEFAULT 'both',
                is_verified   TINYINT(1)   NOT NULL DEFAULT 1,
                verify_token  VARCHAR(80)  DEFAULT NULL,
                created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(120) NOT NULL,
                token      VARCHAR(80)  NOT NULL UNIQUE,
                expires_at DATETIME     NOT NULL,
                created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
            )");

            // Safe column additions/modifications (ignore errors — column may already exist)
            foreach ([
                "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL",
                "ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 1",
                "ALTER TABLE users ADD COLUMN verify_token VARCHAR(80) DEFAULT NULL",
                "ALTER TABLE users MODIFY COLUMN email VARCHAR(120) DEFAULT NULL",
                "ALTER TABLE users MODIFY COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 1",
            ] as $sql) {
                try { $db->exec($sql); } catch (Exception $e) {}
            }

            // Check uniqueness
            $checkUser = $db->prepare("SELECT id FROM users WHERE username = ?");
            $checkUser->execute([$username]);
            if ($checkUser->fetch()) {
                $error = 'That username is already taken. Please choose another.';
            } else {
                // Check email uniqueness only if provided
                if ($email !== '') {
                    $checkEmail = $db->prepare("SELECT id FROM users WHERE email = ?");
                    $checkEmail->execute([$email]);
                    if ($checkEmail->fetch()) {
                        $error = 'An account with that email already exists.';
                    }
                }
                // Check phone uniqueness only if provided
                if (!$error && $phone !== '') {
                    $checkPhone = $db->prepare("SELECT id FROM users WHERE phone = ?");
                    $checkPhone->execute([$phone]);
                    if ($checkPhone->fetch()) {
                        $error = 'An account with that phone number already exists.';
                    }
                }

                if (!$error) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, email, phone, password_hash, exam_target, is_verified, created_at)
                                          VALUES (?, ?, ?, ?, ?, 1, NOW())");
                    $stmt->execute([
                        $username,
                        $email !== '' ? $email : null,
                        $phone  !== '' ? $phone  : null,
                        $hash,
                        $exam
                    ]);

                    // Redirect to login with success message
                    header('Location: login.php?registered=1');
                    exit;
                }
            }
        } catch (Exception $e) {
            $error = 'Registration failed. Please try again.';
            error_log($e->getMessage());
        }
    }
}

include 'includes/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><div class="auth-logo-icon"><i class="fa-solid fa-graduation-cap"></i></div></div>
    <h2>Create Account</h2>
    <p>Join RBAPS and start your personalised exam preparation journey</p>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label class="form-label">Username <span style="color:var(--gold,#c9a84c)">*</span></label>
        <input class="form-control" type="text" name="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               required placeholder="e.g. johndoe123" autocomplete="off">
      </div>

      <div class="form-group">
        <label class="form-label">
          Email Address
          <span style="font-weight:400;font-size:.8rem;color:var(--text2)"> — optional, needed for password reset</span>
        </label>
        <input class="form-control" type="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="your@email.com" autocomplete="off">
      </div>

      <div class="form-group">
        <label class="form-label">
          Phone Number
          <span style="font-weight:400;font-size:.8rem;color:var(--text2)"> — optional, needed for password reset</span>
        </label>
        <input class="form-control" type="tel" name="phone"
               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
               placeholder="e.g. 08012345678" autocomplete="off">
      </div>

      <div class="form-group" style="background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.2);border-radius:8px;padding:.75rem 1rem;font-size:.82rem;color:var(--text2);line-height:1.5">
        <i class="fa-solid fa-circle-info" style="color:var(--gold,#c9a84c);margin-right:.4rem"></i>
        You can register without an email or phone, but you will need at least one to reset your password if you forget it.
      </div>

      <div class="form-group" style="margin-top:.75rem">
        <label class="form-label">Target Examination</label>
        <select class="form-control" name="exam_target">
          <option value="both" <?= (($_POST['exam_target'] ?? 'both') === 'both') ? 'selected' : '' ?>>UTME &amp; SSCE</option>
          <option value="utme" <?= (($_POST['exam_target'] ?? '') === 'utme') ? 'selected' : '' ?>>UTME Only</option>
          <option value="ssce" <?= (($_POST['exam_target'] ?? '') === 'ssce') ? 'selected' : '' ?>>SSCE Only</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Password <span style="color:var(--gold,#c9a84c)">*</span></label>
        <div style="position:relative">
          <input class="form-control" type="password" name="password" id="reg-password" required placeholder="Minimum 6 characters" style="padding-right:2.75rem">
          <button type="button" onclick="togglePassword('reg-password','reg-eye')" tabindex="-1"
            style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text2);padding:0;display:flex;align-items:center;font-size:1rem">
            <i class="fa-regular fa-eye" id="reg-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Confirm Password <span style="color:var(--gold,#c9a84c)">*</span></label>
        <div style="position:relative">
          <input class="form-control" type="password" name="confirm" id="reg-confirm" required placeholder="Repeat your password" style="padding-right:2.75rem">
          <button type="button" onclick="togglePassword('reg-confirm','reg-confirm-eye')" tabindex="-1"
            style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text2);padding:0;display:flex;align-items:center;font-size:1rem">
            <i class="fa-regular fa-eye" id="reg-confirm-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-full" style="margin-top:.5rem">Create Account →</button>
    </form>

    <p style="text-align:center;margin-top:1.25rem;font-size:.875rem;color:var(--text2)">
      Already have an account? <a href="login.php">Log in</a>
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
