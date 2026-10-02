<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/fields.php';
require_login();
$__u      = current_user();
$page_title = $page_title ?? 'Dashboard';
$active     = $active ?? '';
$__flashes  = flashes();
if (!function_exists('nav_item')) {
    function nav_item(string $href, string $label, string $key, string $active, ?string $perm = null, string $icon = ''): void
    {
        if ($perm !== null && !can($perm)) return;
        $cls = $key === $active ? 'active' : '';
        echo '<a class="nav-item ' . $cls . '" href="' . BASE_URL . $href . '"><span class="ni">' . $icon . '</span><span>' . e($label) . '</span></a>';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> | <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= ASSET_URL ?>/img/logo.png">
<link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css?v=<?= APP_VERSION ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img src="<?= ASSET_URL ?>/img/logo.png" alt="Company logo" class="brand-logo">
      <div class="brand-txt"><strong>COMPANY</strong><span>HR Management</span></div>
    </div>
    <nav class="nav">
      <div class="nav-cap">Main</div>
      <?php nav_item('/dashboard.php', 'Dashboard', 'dashboard', $active, null, '▦'); ?>

      <div class="nav-cap">People</div>
      <?php nav_item('/pages/employees.php', 'Employees', 'employees', $active, 'employees.view', '👥'); ?>
      <?php nav_item('/pages/master.php', 'Companies &amp; Departments', 'master', $active, 'master.manage', '🏢'); ?>

      <div class="nav-cap">Requests</div>
      <?php nav_item('/pages/request_new.php', 'New Request', 'request_new', $active, 'requests.create', '＋'); ?>
      <?php nav_item('/pages/requests.php', 'Request Register', 'requests', $active, null, '📋'); ?>
      <?php nav_item('/pages/reports.php', 'Reports', 'reports', $active, 'reports.view', '📊'); ?>

      <div class="nav-cap">Administration</div>
      <?php nav_item('/pages/forms.php', 'Form Builder', 'forms', $active, 'forms.manage', '🧩'); ?>
      <?php nav_item('/pages/users.php', 'Users', 'users', $active, 'users.manage', '🔑'); ?>
      <?php nav_item('/pages/permissions.php', 'Permission Rules', 'permissions', $active, 'rules.manage', '⚙'); ?>
      <?php nav_item('/pages/settings.php', 'Settings', 'settings', $active, 'settings.manage', '🛠'); ?>
      <?php nav_item('/pages/profile.php', 'My Account', 'profile', $active, null, '👤'); ?>
    </nav>
    <div class="side-foot">
      <img src="<?= ASSET_URL ?>/img/logo.png" alt="" class="foot-logo">
      <small>v<?= APP_VERSION ?> &middot; <?= e(APP_ORG) ?></small>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="burger" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <h1 class="page-title"><?= e($page_title) ?></h1>
      <div class="userbox">
        <span class="role-badge role-<?= e(strtolower((string) $__u['role'])) ?>"><?= e(ROLES[$__u['role']] ?? $__u['role']) ?></span>
        <span class="uname"><?= e($__u['full_name'] ?: $__u['username']) ?></span>
        <a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>/logout.php">Sign out</a>
      </div>
    </header>
    <main class="content">
      <?php foreach ($__flashes as $f): ?>
        <div class="alert alert-<?= e($f['t']) ?>"><?= e($f['f']['m']) ?? '' ?></div>
      <?php endforeach; ?>
      <!-- RED THUNDER STAMP HEADER (re-stamped at footer too) -->
      <?php echo '<div class="rt-stamp" style="position:fixed;left:0;right:0;bottom:0;text-align:center;font-size:8.5px;color:#ff252b;letter-spacing:.04em;z-index:2147483647;pointer-events:none">created by red thunder E.G</div>'; ?>
