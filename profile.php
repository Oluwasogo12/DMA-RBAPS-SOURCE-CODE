<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$pageTitle = 'Profile Settings — RBAPS';
$currentPage = 'profile';
$db = getDB();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch current user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $new_pass = $_POST['new_password'] ?? '';
    
    if (!$username) {
        $error = 'Username cannot be empty.';
    } else {
        // Check if username/email/phone is taken by someone else
        $check = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ? OR phone = ?) AND id != ?");
        $check->execute([$username, $email ?: 'N/A', $phone ?: 'N/A', $user_id]);
        if ($check->fetch()) {
            $error = 'Username, email, or phone is already taken by another account.';
        } else {
            // Update details
            if (!empty($new_pass)) {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $update = $db->prepare("UPDATE users SET username=?, email=?, phone=?, password_hash=? WHERE id=?");
                $update->execute([$username, $email, $phone, $hash, $user_id]);
            } else {
                $update = $db->prepare("UPDATE users SET username=?, email=?, phone=? WHERE id=?");
                $update->execute([$username, $email, $phone, $user_id]);
            }
            
            // Update session vars
            $_SESSION['username'] = $username;
            $_SESSION['user']['username'] = $username;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;
            
            $success = 'Profile updated successfully!';
            
            // Re-fetch to update view
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    }
}

include 'includes/header.php';
?>

<div class="section" style="max-width:600px; margin: 2rem auto; padding: 2rem 1.5rem; background:var(--card); border-radius:var(--radius); border:1px solid var(--border-soft); box-shadow:var(--shadow)">
  <div class="section-header" style="text-align:center; margin-bottom: 2rem;">
    <h2><i class="fa-solid fa-user-gear" style="color:var(--accent); margin-right:0.5rem"></i> Profile Settings</h2>
    <p>Update your account details and password</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom: 1.5rem">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  
  <?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label class="form-label">Username <span style="color:var(--red)">*</span></label>
      <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
    </div>
    
    <div class="form-group">
      <label class="form-label">Email Address</label>
      <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="example@email.com">
    </div>
    
    <div class="form-group">
      <label class="form-label">Phone Number</label>
      <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="08012345678">
    </div>

    <hr style="border:0; border-top:1px solid var(--border-soft); margin: 2rem 0">
    <h3 style="font-size:1.1rem; margin-bottom:1rem; color:var(--text)">Security</h3>

    <div class="form-group">
      <label class="form-label">New Password</label>
      <input type="password" class="form-control" name="new_password" placeholder="Leave blank to keep current password">
      <small style="color:var(--text3); font-size:0.8rem; margin-top:0.4rem; display:block;">Only fill this if you want to change your password.</small>
    </div>

    <button type="submit" class="btn btn-primary w-full" style="margin-top: 1.5rem">Save Changes</button>
  </form>
</div>

<?php include 'includes/footer.php'; ?>
