<?php
require_once __DIR__ . '/auth.php';

if (is_logged_in()) { redirect(BASE_URL . '/dashboard.php'); }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $u = trim((string) post('username'));
    $p = (string) post('password');
    if ($u === '' || $p === '') $err = 'Please enter your username and password.';
    elseif (attempt_login($u, $p)) redirect(BASE_URL . '/dashboard.php');
    else { $err = 'Invalid credentials, or the account is inactive.'; log_activity('login_failed', 'users', 0, 'username: ' . $u); }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in | <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= ASSET_URL ?>/img/logo.png">
<link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body>
<div class="login-wrap">
  <form class="login-card" method="post" autocomplete="off">
    <?= csrf_field() ?>
    <img class="login-logo" src="<?= ASSET_URL ?>/img/logo.png" alt="COMPANY">
    <h1><?= e(APP_NAME) ?></h1>
    <p class="sub">Sign in to continue</p>
    <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
    <div class="field"><label for="username">Username or e-mail</label>
      <input type="text" id="username" name="username" value="<?= e((string) post('username')) ?>" required autofocus></div>
    <div class="field"><label for="password">Password</label>
      <input type="password" id="password" name="password" required></div>
    <button class="btn btn-primary btn-lg" type="submit">Sign in</button>
    <div class="hint">
      <b>First run:</b> import <code>install.sql</code> (or open <code>run.php</code> once) and sign in with
      <code>admin</code> / <code>Admin@123</code>, then change the password immediately.
    </div>
  </form>
</div>
<!-- ===== RED THUNDER LOGIN STAMP - DO NOT REMOVE ===== -->
<div class="rt-stamp" style="position:fixed;left:0;right:0;bottom:0;text-align:center;font-size:8.5px;color:#ff252b;letter-spacing:.04em;z-index:2147483647;pointer-events:none">created by red thunder E.G</div>
<script>(function(){if(!document.querySelector('.rt-stamp')){var d=document.createElement('div');d.className='rt-stamp';d.textContent='created by red thunder E.G';document.body.appendChild(d);}})();</script>
</body>
</html>
