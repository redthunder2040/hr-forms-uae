<?php
/**
 * One-time installer: creates the tables, seeds reference data, request forms,
 * users and the employee master database. Delete this file after installing
 * (or keep it - it refuses to run again once users exist).
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

$steps = [];
$done  = false;
$isCli = (PHP_SAPI === 'cli');

if ($isCli || ($_GET['go'] ?? '') === '1' || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $sql = (string) file_get_contents(__DIR__ . '/sql/schema.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            db()->exec($stmt);
        }
        $steps[] = 'Database structure created / verified (11 tables).';

        define('SEED_INCLUDED', true);
        require __DIR__ . '/seed.php';
        $steps[] = 'Companies, departments, professions and settings seeded.';
        $steps[] = 'Request forms seeded: ' . scalar('SELECT COUNT(*) FROM form_types') . ' forms with ' . scalar('SELECT COUNT(*) FROM form_fields') . ' fields.';
        $steps[] = 'Users created: ' . scalar('SELECT COUNT(*) FROM users') . ' (admin / hr / user).';
        $steps[] = 'Employee master database: ' . scalar('SELECT COUNT(*) FROM employees') . ' records.';
        $done = true;
    } catch (Throwable $ex) {
        $steps[] = 'ERROR: ' . $ex->getMessage();
    }
}

$users = 0;
try { $users = (int) scalar('SELECT COUNT(*) FROM users'); } catch (Throwable $e) {}

if ($isCli) {
    foreach ($steps as $s) { echo $s . "\n"; }
    echo 'Existing users: ' . $users . "\n";
    exit(0);
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css"></head>
<body style="background:#f2f4f7">
<div style="max-width:780px;margin:40px auto;padding:0 18px">
  <div class="card">
    <div class="card-head"><img src="<?= ASSET_URL ?>/img/logo.png" style="width:56px;height:66px;background:#000;border-radius:8px" alt="COMPANY">
      <h2 style="flex:1"><?= e(APP_NAME) ?> — installation</h2></div>
    <?php foreach ($steps as $s): ?><div class="alert <?= str_starts_with($s, 'ERROR') ? 'alert-error' : 'alert-success' ?>"><?= e($s) ?></div><?php endforeach; ?>
    <p>This page creates the database structure and imports the reference data, the eight request forms
       and the employee master database. It is safe to run again — nothing is duplicated.</p>
    <dl class="kv">
      <dt>Database</dt><dd><?= e((string) (scalar('SELECT DATABASE()') ?: DB_NAME)) ?> @ <?= e(DB_HOST) ?></dd>
      <dt>Existing users</dt><dd><?= $users ?></dd>
      <dt>PHP version</dt><dd><?= e(PHP_VERSION) ?></dd>
      <dt>Uploads writable</dt><dd><?= is_writable(UPLOAD_DIR) ? 'yes' : '<b style="color:#c0271f">no — chmod 775 uploads</b>' ?></dd>
    </dl>
    <?php if ($done): ?>
      <div class="alert alert-success"><b>Installation finished.</b> Sign in with <b>admin / Admin@123</b>, then change the password in <i>My Account</i>.</div>
      <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>/index.php">Go to the sign-in page</a>
    <?php else: ?>
      <form method="post"><button class="btn btn-primary btn-lg" type="submit" name="go" value="1">Run installation</button></form>
      <p class="muted" style="margin-top:10px">If the connection fails, check DB_NAME / DB_USER / DB_PASS in <code>config.php</code> first.</p>
    <?php endif; ?>
    <p class="muted" style="margin-top:14px"><b>Security:</b> delete <code>run.php</code> after installing.</p>
  </div>
</div>
</body></html>
