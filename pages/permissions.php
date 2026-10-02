<?php
$page_title = 'Permission Rules'; $active = 'permissions';
require_once __DIR__ . '/../includes/header.php';
require_perm('rules.manage');

$defs = perm_defs();
$roles = ['HR', 'user'];   // admin is always super-user, its rules are locked

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (post('act') === 'save') {
        foreach (array_keys($defs) as $key) {
            foreach ($roles as $role) {
                $allowed = isset($_POST['p'][$role][$key]) ? 1 : 0;
                q('INSERT INTO role_permissions (role, perm_key, allowed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)', [$role, $key, $allowed]);
            }
        }
        log_activity('permissions_save', 'role_permissions', 0, 'Rules updated by admin');
        flash('Permission rules saved. They apply immediately to every HR and User account.');
        redirect(BASE_URL . '/pages/permissions.php');
    }
    if (post('act') === 'reset') {
        q('DELETE FROM role_permissions');
        log_activity('permissions_reset', 'role_permissions', 0, 'Rules reset to defaults');
        flash('Rules reset to the built-in defaults.', 'warn');
        redirect(BASE_URL . '/pages/permissions.php');
    }
}

$rows = perm_rows();
$uCount = [];
foreach (['HR', 'user'] as $r) $uCount[$r] = (int) scalar('SELECT COUNT(*) FROM users WHERE role = ?', [$r]);
?>
<form method="post">
  <?= csrf_field() ?><input type="hidden" name="act" value="save">
  <div class="card">
    <div class="card-head">
      <h2>Rules per role</h2>
      <span class="pill">Administrator always has full access</span>
      <span class="pill"><?= (int) $uCount['HR'] ?> HR account(s)</span>
      <span class="pill"><?= (int) $uCount['user'] ?> User account(s)</span>
      <button class="btn btn-primary btn-sm" type="submit">Save rules</button>
    </div>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Permission</th><th>Administrator</th><th>HR Officer</th><th>User</th></tr></thead>
      <tbody>
      <?php foreach ($defs as $key => $meta): ?>
        <tr>
          <td><b><?= e($meta[0]) ?></b><br><code style="font-size:11px"><?= e($key) ?></code></td>
          <td><span class="badge b-approved">Always allowed</span></td>
          <?php foreach ($roles as $role): $cur = array_key_exists($key, $rows[$role] ?? []) ? (int) $rows[$role][$key] : (int) ($meta[$role] ?? 0); ?>
            <td><label class="check"><input type="checkbox" name="p[<?= e($role) ?>][<?= e($key) ?>]" value="1"<?= $cur === 1 ? ' checked' : '' ?>> <span>allow</span></label></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <div class="row" style="margin-top:14px">
      <button class="btn btn-primary" type="submit">Save rules</button>
      <button class="btn btn-ghost" name="act" value="reset" onclick="return confirm('Reset all HR and User rules to the built-in defaults?')">Reset to defaults</button>
    </div>
  </div>
</form>

<div class="card">
  <div class="card-head"><h3>What each rule controls</h3></div>
  <dl class="kv">
    <dt>employees.manage</dt><dd>Add / edit / delete records in the employee master database</dd>
    <dt>master.manage</dt><dd>Companies, departments and professions lists</dd>
    <dt>requests.view_all</dt><dd>See every request — when off, a user only sees their own</dd>
    <dt>requests.approve</dt><dd>Approve or reject requests (status becomes Approved / Rejected)</dd>
    <dt>requests.delete</dt><dd>Delete requests permanently and cancel any request</dd>
    <dt>forms.manage</dt><dd>Add / edit / delete request form types and their fields</dd>
    <dt>reports.view</dt><dd>Open the reports page and export CSV</dd>
    <dt>users.manage / rules.manage / settings.manage</dt><dd>Administrator-only by default (users, permission rules, system settings)</dd>
  </dl>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
