<?php
require_once __DIR__ . '/includes/auth.php';

// Remove all session data, then destroy the session
$_SESSION = [];
session_destroy();

// Delete the session cookie in the browser
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

header('Location: /login.php');
exit;