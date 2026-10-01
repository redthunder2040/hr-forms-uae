<?php
require_once __DIR__ . '/auth.php';
if (is_logged_in()) log_activity('logout', 'users', (int) current_user()['id'], 'Signed out');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) ($p['secure'] ?? false), (bool) ($p['httponly'] ?? true));
}
session_destroy();
redirect(BASE_URL . '/index.php');
