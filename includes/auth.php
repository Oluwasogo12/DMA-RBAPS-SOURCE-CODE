<?php
// Harden session before starting
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

// cookie_samesite via ini_set crashes PHP < 7.3; use session_set_cookie_params instead
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(['samesite' => 'Lax', 'httponly' => true]);
} else {
    session_set_cookie_params(0, '/; samesite=Lax', '', false, true);
}

session_start();

// ── Fresh-start guard ────────────────────────────────────────────────────────
// If a session cookie exists from a PREVIOUS user's session (e.g. shared
// device, browser back after logout, or leftover cookie), wipe it completely
// and issue a brand-new session ID so the new visitor always starts clean.
//
// How it works:
//   • Every authenticated session gets a '_user_session' fingerprint stamped
//     with the logged-in user's ID and the time they logged in.
//   • When auth.php runs and finds NO active login (_user_session is absent)
//     but the session array already contains OTHER data (stale leftovers),
//     we destroy everything and regenerate — preventing any bleed-through.
//   • On first ever visit (_init absent) we also regenerate to defeat
//     session-fixation attacks.

if (!isset($_SESSION['_init'])) {
    // Brand-new visitor or post-logout cookie: start completely fresh
    session_regenerate_id(true);
    $_SESSION = [];               // clear any stale data that snuck in
    $_SESSION['_init'] = true;
} elseif (!isset($_SESSION['user_id']) && count($_SESSION) > 1) {
    // Session cookie exists, user is NOT logged in, but session carries data
    // → stale session from a previous user; reset it
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['_init'] = true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}
?>
