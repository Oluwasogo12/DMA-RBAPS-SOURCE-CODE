<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$db = getDB();

// ── Ensure is_admin column exists ─────────────────────────────────────────
try {
    $db->query("SELECT is_admin FROM users LIMIT 1");
} catch (Exception $e) {
    $db->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
}

// ── Ensure admin_secret table exists ──────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS admin_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    secret_key VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Check if any admin_key exists, if not create a default one
$keyCount = $db->query("SELECT COUNT(*) FROM admin_keys")->fetchColumn();
if (!$keyCount) {
    // Default secret key — admin MUST change this
    $db->exec("INSERT INTO admin_keys (secret_key) VALUES ('rbaps-admin-2024')");
}

// ── If already logged in as admin, go to dashboard ────────────────────────
if (isLoggedIn()) {
    $uid = $_SESSION['user_id'];
    $isAdmin = $db->prepare("SELECT is_admin FROM users WHERE id=?");
    $isAdmin->execute([$uid]);
    $row = $isAdmin->fetch();
    if ($row && $row['is_admin']) {
        header('Location: dashboard.php');
        exit;
    }
}

$mode  = $_GET['mode'] ?? 'login'; // 'login' or 'register'
$error = '';
$success = '';

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADMIN LOGIN ────────────────────────────────────────────────────────
    if ($action === 'login') {
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$identity || !$password) {
            $error = 'Please enter your credentials.';
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE (email=? OR username=?) AND is_admin=1 LIMIT 1");
            $stmt->execute([$identity, $identity]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user']     = $user;
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid admin credentials.';
            }
        }
        $mode = 'login';
    }

    // ── ADMIN REGISTER ─────────────────────────────────────────────────────
    if ($action === 'register') {
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm'] ?? '';
        $secretKey = trim($_POST['secret_key'] ?? '');

        if (!$username || !$email || !$password || !$secretKey) {
            $error = 'All fields are required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            // Verify secret key
            $keyStmt = $db->prepare("SELECT id FROM admin_keys WHERE secret_key=?");
            $keyStmt->execute([$secretKey]);
            if (!$keyStmt->fetch()) {
                $error = 'Invalid admin secret key.';
            } else {
                // Check if username/email taken
                $exists = $db->prepare("SELECT id FROM users WHERE email=? OR username=?");
                $exists->execute([$email, $username]);
                if ($exists->fetch()) {
                    $error = 'That username or email is already taken.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins  = $db->prepare("INSERT INTO users (username,email,password_hash,exam_target,is_admin,created_at) VALUES (?,?,?,'both',1,NOW())");
                    $ins->execute([$username, $email, $hash]);
                    $userId = $db->lastInsertId();
                    session_regenerate_id(true);
                    $_SESSION['user_id']  = $userId;
                    $_SESSION['username'] = $username;
                    $_SESSION['user']     = ['id'=>$userId,'username'=>$username,'email'=>$email,'exam_target'=>'both','is_admin'=>1];
                    header('Location: dashboard.php');
                    exit;
                }
            }
        }
        $mode = 'register';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Access — RBAPS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:      #0d0f14;
  --bg2:     #141720;
  --bg3:     #1c2030;
  --border:  rgba(255,255,255,0.08);
  --border2: rgba(255,255,255,0.14);
  --text:    #e8eaf0;
  --text2:   #8b90a8;
  --text3:   #555c78;
  --accent:  #5b6af5;
  --accent2: #7c89ff;
  --red:     #ef4444;
  --green:   #22c55e;
  --gold:    #f59e0b;
}

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
  background-image:
    radial-gradient(ellipse 60% 50% at 20% 20%, rgba(91,106,245,0.07) 0%, transparent 60%),
    radial-gradient(ellipse 50% 40% at 80% 80%, rgba(91,106,245,0.05) 0%, transparent 55%);
}

.container {
  width: 100%;
  max-width: 420px;
}

/* Back link */
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--text3);
  text-decoration: none;
  margin-bottom: 1.5rem;
  transition: color .15s;
}
.back-link:hover { color: var(--text2); }

/* Header */
.header {
  text-align: center;
  margin-bottom: 2rem;
}

.shield-icon {
  width: 52px; height: 52px;
  background: rgba(91,106,245,0.15);
  border: 1px solid rgba(91,106,245,0.3);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: var(--accent2);
  margin: 0 auto 1rem;
}

.header h1 {
  font-size: 22px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 6px;
}

.header p {
  font-size: 14px;
  color: var(--text3);
}

/* Tab switcher */
.tabs {
  display: flex;
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 4px;
  margin-bottom: 1.5rem;
}

.tab-btn {
  flex: 1;
  padding: 8px;
  border: none;
  border-radius: 7px;
  font-size: 13.5px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: all .15s;
  background: none;
  color: var(--text3);
  text-decoration: none;
  text-align: center;
  display: block;
}

.tab-btn.active {
  background: var(--bg3);
  color: var(--text);
  box-shadow: 0 1px 4px rgba(0,0,0,0.4);
}

/* Card */
.card {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 1.75rem;
}

/* Alert */
.alert {
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13.5px;
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  gap: 8px;
}
.alert-error {
  background: rgba(239,68,68,0.1);
  border: 1px solid rgba(239,68,68,0.25);
  color: #fca5a5;
}
.alert-success {
  background: rgba(34,197,94,0.1);
  border: 1px solid rgba(34,197,94,0.25);
  color: #86efac;
}

/* Form */
.form-group { margin-bottom: 1rem; }

label {
  display: block;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text2);
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

input {
  width: 100%;
  background: var(--bg3);
  border: 1px solid var(--border);
  border-radius: 9px;
  padding: 10px 13px;
  font-size: 14px;
  font-family: inherit;
  color: var(--text);
  outline: none;
  transition: border-color .15s;
}

input:focus { border-color: var(--accent); }
input::placeholder { color: var(--text3); }

input.secret { font-family: 'DM Mono', monospace; letter-spacing: 0.05em; }

.btn {
  width: 100%;
  padding: 11px;
  border-radius: 9px;
  font-size: 14.5px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  border: none;
  margin-top: .25rem;
  transition: opacity .15s, transform .1s;
}

.btn:active { transform: scale(0.98); }

.btn-primary {
  background: var(--accent);
  color: #fff;
}

.btn-primary:hover { opacity: .88; }

/* Secret key hint */
.hint {
  font-size: 12px;
  color: var(--text3);
  margin-top: 5px;
  line-height: 1.5;
}

.hint code {
  font-family: 'DM Mono', monospace;
  background: var(--bg3);
  padding: 1px 5px;
  border-radius: 4px;
  color: var(--gold);
  font-size: 11px;
}

/* Divider */
.divider {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 1.25rem 0;
  color: var(--text3);
  font-size: 12px;
}
.divider::before, .divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

/* Footer note */
.footer-note {
  text-align: center;
  font-size: 12px;
  color: var(--text3);
  margin-top: 1.25rem;
  line-height: 1.6;
}
</style>
</head>
<body>
<div class="container">

  <a href="../index.php" class="back-link">
    <i class="fa-solid fa-arrow-left" style="font-size:11px"></i> Back to Home
  </a>

  <div class="header">
    <div class="shield-icon"><i class="fa-solid fa-shield-halved"></i></div>
    <h1>Admin Access</h1>
    <p>Restricted to authorised administrators only</p>
  </div>

  <!-- Tab switcher -->
  <div class="tabs">
    <a href="?mode=login"    class="tab-btn <?= $mode==='login'    ? 'active' : '' ?>">
      <i class="fa-solid fa-right-to-bracket" style="margin-right:5px;font-size:12px"></i>Login
    </a>
    <a href="?mode=register" class="tab-btn <?= $mode==='register' ? 'active' : '' ?>">
      <i class="fa-solid fa-user-plus" style="margin-right:5px;font-size:12px"></i>Register
    </a>
  </div>

  <div class="card">

    <?php if ($error): ?>
    <div class="alert alert-error">
      <i class="fa-solid fa-circle-exclamation" style="font-size:13px"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
      <i class="fa-solid fa-circle-check" style="font-size:13px"></i>
      <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- ── LOGIN FORM ── -->
    <?php if ($mode === 'login'): ?>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="login">

      <div class="form-group">
        <label>Username or Email</label>
        <input type="text" name="identity" placeholder="admin username or email"
               value="<?= htmlspecialchars($_POST['identity'] ?? '') ?>" required autofocus>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="your password" required>
      </div>

      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-right-to-bracket" style="margin-right:6px"></i>Sign In as Admin
      </button>
    </form>

    <div class="footer-note">
      Don't have an admin account?
      <a href="?mode=register" style="color:var(--accent2);text-decoration:none">Register here</a>
      — you'll need the admin secret key.
    </div>

    <!-- ── REGISTER FORM ── -->
    <?php else: ?>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="register">

      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" placeholder="e.g. superadmin"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="admin@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Minimum 6 characters" required>
      </div>

      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm" placeholder="Repeat password" required>
      </div>

      <div class="divider">Admin verification</div>

      <div class="form-group">
        <label>Admin Secret Key</label>
        <input type="password" name="secret_key" class="secret" placeholder="Enter the admin secret key" required>
        <div class="hint">
          The default key is <code>rbaps-admin-2024</code>. Change it in your database
          (<code>admin_keys</code> table) after first use for security.
        </div>
      </div>

      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-user-shield" style="margin-right:6px"></i>Create Admin Account
      </button>
    </form>

    <div class="footer-note">
      Already have an admin account?
      <a href="?mode=login" style="color:var(--accent2);text-decoration:none">Sign in here</a>
    </div>
    <?php endif; ?>

  </div><!-- .card -->
</div>
</body>
</html>
