<?php
// Session handling and small helper functions used by every page.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Is someone logged in?
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Put this at the top of any page that needs login.
// If nobody is logged in, send them to the login page.
function require_login() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

// Safely print text inside HTML (prevents XSS).
function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}