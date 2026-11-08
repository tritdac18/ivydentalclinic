<?php
/**
 * login.php — Authentication entry point.
 *
 * ── Bootstrap ──────────────────────────────────────────────────────────────
 */
require_once 'config/db.php';
require_once 'config/security.php';
require_once ROOT_PATH . '/controllers/AuthController.php';

setSecurityHeaders();

// ── Handle POST ─────────────────────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = AuthController::handleLogin($_POST);
    // On success, handleLogin() redirects → never reaches here.
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
    }
}

// Pick up errors stored in session (e.g. from a previous POST redirect)
if (isset($_SESSION['login_errors'])) {
    $errors = $_SESSION['login_errors'];
    unset($_SESSION['login_errors']);
}

// ── View variables ───────────────────────────────────────────────────────────
$page_title = 'Login';

// ── Render view ─────────────────────────────────────────────────────────────
include ROOT_PATH . '/views/auth/login.php';
