<?php
$page_title = 'System Settings'; $active = 'settings';
require_once __DIR__ . '/../includes/header.php';
require_perm('settings.manage');

$keys = [
    'org_name'          => ['Organisation name', 'HR'],
    'signatory_name'    => ['Authorised signatory name (printed on documents)', 'Authorised Signatory'],
    'signatory_title'   => ['Authorised signatory title', 'Deputy General Manager'],
    'offer_termination' => ['Offer letter — default termination rule', '30 days advance notice from the date of joining.'],
    'offer_benefits'    => ['Offer letter — default other benefits', 'As per the UAE Labour Law'],
    'offer_number'      => ['Offer letter — default reference number', '114587545463'],
    'doc_footer_note'   => ['Note printed under documents (optional)', ''],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($keys) as $k) { set_setting($k, trim((string) post($k))); }
    log_activity('settings_save', 'settings', 0, 'System settings updated');
    flash('Settings saved. They apply to every new document and to print/PDF output.');
    redirect(BASE_URL . '/pages/settings.php');
}

$dbName = (string) scalar('SELECT DATABASE()');
$tables = all("SELECT TABLE_NAME t, TABLE_ROWS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
$counts = [
    'Employees'        => (int) scalar('SELECT COUNT(*) FROM employees'),
    'Companies'        => (int) scalar('SELECT COUNT(*) FROM companies'),
    'Departments'      => (int) scalar('SELECT COUNT(*) FROM departments'),
    'Request forms'    => (int) scalar('SELECT COUNT(*) FROM form_types'),
    'Form fields'      => (int) scalar('SELECT COUNT(*) FROM form_fields'),
    'Requests stored'  => (int) scalar('SELECT COUNT(*) FROM requests'),
    'Users'            => (int) scalar('SELECT COUNT(*) FROM users'),
];
?>
<div class="grid g2">
  <div class="card">
    <div class="card-head"><h2>Document &amp; organisation settings</h2></div>
    <form method="post"><?= csrf_field() ?>
      <?php foreach ($keys as $k => $meta): ?>
        <div class="field"><label for="<?= e($k) ?>"><?= e($meta[0]) ?></label>
          <?php $val = (string) setting($k, $meta[1]); ?>
          <?php if (mb_strlen($val) > 70): ?>
            <textarea id="<?= e($k) ?>" name="<?= e($k) ?>"><?= e($val) ?></textarea>
          <?php else: ?>
            <input type="text" id="<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e($val) ?>">
          <?php endif; ?>
          <small>Stored under key <code><?= e($k) ?></code></small></div>
      <?php endforeach; ?>
      <button class="btn btn-primary">Save settings</button>
    </form>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h3>System status</h3></div>
      <dl class="kv">
        <dt>Application</dt><dd><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?></dd>
        <dt>Database</dt><dd><?= e($dbName) ?> @ <?= e(DB_HOST) ?></dd>
        <dt>PHP version</dt><dd><?= e(PHP_VERSION) ?></dd>
        <dt>PDF engine</dt><dd><?= is_file(__DIR__ . '/../vendor/autoload_simple.php') ? 'Dompdf bundled — ready' : 'not installed (use Print → Save as PDF)' ?></dd>
        <dt>Uploads folder</dt><dd><?= is_writable(UPLOAD_DIR) ? 'writable' : '<span style="color:#c0271f">not writable</span>' ?></dd>
        <dt>Base URL</dt><dd><code><?= e(BASE_URL === '' ? '/' : BASE_URL) ?></code></dd>
      </dl>
    </div>
    <div class="card">
      <div class="card-head"><h3>Data stored</h3></div>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Table</th><th class="num">Records</th></tr></thead>
        <tbody>
          <?php foreach ($counts as $lbl => $n): ?><tr><td><?= e($lbl) ?></td><td class="num"><?= number_format($n) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <p class="muted" style="margin-top:10px">Row estimates: <?= count($tables) ?> tables found in this database.</p>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
