<?php
require_once 'includes/auth.php';

// ── Full session teardown ────────────────────────────────────────────────────
// Clear all session data (including our _init flag) so the next visitor
// who lands with this cookie gets a completely fresh session.
$_SESSION = [];

// Expire the session cookie immediately on the client side
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session on the server
session_destroy();

header('Location: index.php?logout=1');
exit;
