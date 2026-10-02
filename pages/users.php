<?php
$page_title = 'Users &amp; Access'; $active = 'users';
require_once __DIR__ . '/../includes/header.php';
require_perm('users.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = (string) post('act');
    if ($act === 'save') {
        $id = ipost('id');
        $v  = [trim((string) post('username')), trim((string) post('full_name')), trim((string) post('email')),
               (string) post('role', 'user'), ipost('company_id') ?: null, ipost('all_companies'), ipost('employee_id') ?: null, ipost('active', 1)];
        if (!in_array($v[3], array_keys(ROLES), true)) $v[3] = 'user';
        $pw = (string) post('password');
        if ($v[0] === '' || $v[1] === '') flash('Username and full name are required.', 'error');
        elseif (!$id && $pw === '') flash('A password is required for a new user.', 'error');
        else {
            try {
                if ($id) {
                    q('UPDATE users SET username=?,full_name=?,email=?,role=?,company_id=?,all_companies=?,employee_id=?,active=? WHERE id=?', array_merge($v, [$id]));
                    if ($pw !== '') q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
                    flash('User updated.' . ($pw !== '' ? ' Password changed.' : ''));
                } else {
                    q('INSERT INTO users (username,full_name,email,role,company_id,all_companies,employee_id,active,password_hash,created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?)', array_merge($v, [password_hash($pw, PASSWORD_DEFAULT), now()]));
                    flash('User created.');
                }
                log_activity('user_save', 'users', $id, $v[0]);
            } catch (Throwable $e) { flash('Save failed (duplicate username?): ' . $e->getMessage(), 'error'); }
        }
        redirect(BASE_URL . '/pages/users.php');
    }
    if ($act === 'delete') {
        $id = ipost('id');
        if ($id === (int) $__u['id']) flash('You cannot delete your own account.', 'error');
        elseif ((int) scalar("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1") <= 1 && (string) scalar('SELECT role FROM users WHERE id = ?', [$id]) === 'admin') {
            flash('At least one active administrator must remain.', 'error');
        } else { q('DELETE FROM users WHERE id = ?', [$id]); log_activity('user_delete', 'users', $id); flash('User deleted.'); }
        redirect(BASE_URL . '/pages/users.php');
    }
    if ($act === 'toggle') {
        $id = ipost('id');
        $row = one('SELECT * FROM users WHERE id = ?', [$id]);
        if ($row) {
            if ((int) $row['active'] === 1 && (string) $row['role'] === 'admin' && (int) scalar("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1") <= 1) {
                flash('You cannot deactivate the last active administrator.', 'error');
            } else {
                q('UPDATE users SET active = ? WHERE id = ?', [(int) $row['active'] === 1 ? 0 : 1, $id]);
                flash('User status changed.');
            }
        }
        redirect(BASE_URL . '/pages/users.php');
    }
}

$edit = (int) get('edit') ? one('SELECT * FROM users WHERE id = ?', [(int) get('edit')]) : null;
$rows = all('SELECT u.*, c.short_name AS co, e.name AS emp_name FROM users u
             LEFT JOIN companies c ON c.id = u.company_id LEFT JOIN employees e ON e.id = u.employee_id
             ORDER BY FIELD(u.role, \'admin\', \'HR\', \'user\'), u.username');
?>
<div class="card">
  <div class="card-head"><h2>System users</h2><span class="pill"><?= count($rows) ?> account(s)</span>
    <a class="btn btn-primary btn-sm" href="?edit=0#uf">＋ Add user</a></div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Username</th><th>Full name</th><th>E-mail</th><th>Role</th><th>Company scope</th><th>Linked employee</th><th>Last login</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr>
        <td><b><?= e($u['username']) ?></b></td>
        <td><?= e($u['full_name']) ?></td>
        <td><small><?= e($u['email']) ?></small></td>
        <td><span class="role-badge role-<?= e(strtolower($u['role'])) ?>"><?= e(ROLES[$u['role']] ?? $u['role']) ?></span></td>
        <td><small><?= (int) $u['all_companies'] === 1 ? 'Both companies' : e($u['co'] ?: '—') ?></small></td>
        <td><small><?= e($u['emp_name'] ?: '—') ?></small></td>
        <td><small><?= $u['last_login'] ? fdate($u['last_login']) : 'never' ?></small></td>
        <td><?= (int) $u['active'] === 1 ? '<span class="badge b-approved">Active</span>' : '<span class="badge b-cancelled">Disabled</span>' ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-sm btn-ghost" href="?edit=<?= (int) $u['id'] ?>#uf">Edit</a>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <button class="btn btn-sm btn-ghost"><?= (int) $u['active'] === 1 ? 'Disable' : 'Enable' ?></button></form>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this user?')"><?= csrf_field() ?>
            <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <button class="btn btn-sm btn-bad">Del</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <p class="muted" style="margin-top:12px">Roles: <b>Administrator</b> = full control (users, rules, all forms, delete).
     <b>HR</b> = create/approve requests, manage employees and master data (no user or rule management).
     <b>User</b> = create own requests, print/download them, view the employee directory.</p>
</div>

<div class="card" id="uf">
  <div class="card-head"><h2><?= $edit ? 'Edit user: ' . e($edit['username']) : 'Add user' ?></h2></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="grid g3">
      <div class="field"><label class="req">Username</label><input type="text" name="username" required value="<?= e($edit['username'] ?? '') ?>"></div>
      <div class="field"><label class="req">Full name</label><input type="text" name="full_name" required value="<?= e($edit['full_name'] ?? '') ?>"></div>
      <div class="field"><label>E-mail</label><input type="text" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
      <div class="field"><label>Role</label><select name="role">
        <?php foreach (ROLES as $k => $lbl): ?><option value="<?= e($k) ?>"<?= ($edit['role'] ?? 'user') === $k ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Company scope</label><select name="company_id"><option value="">—</option>
        <?php foreach (companies() as $c): ?><option value="<?= (int) $c['id'] ?>"<?= ($edit['company_id'] ?? 0) == $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        <small>Used when “both companies” is off.</small></div>
      <div class="field"><label>Access to both companies</label><select name="all_companies">
        <option value="1"<?= (int) ($edit['all_companies'] ?? 1) === 1 ? ' selected' : '' ?>>Yes — both companies</option>
        <option value="0"<?= (int) ($edit['all_companies'] ?? 1) === 0 ? ' selected' : '' ?>>No — only the company above</option></select></div>
      <div class="field"><label>Linked employee (for self-service)</label><select name="employee_id"><option value="">—</option>
        <?php foreach (all('SELECT id, code, name FROM employees WHERE active = 1 ORDER BY CAST(code AS UNSIGNED)') as $emp): ?>
          <option value="<?= (int) $emp['id'] ?>"<?= ($edit['employee_id'] ?? 0) == $emp['id'] ? ' selected' : '' ?>><?= e($emp['code'] . ' - ' . $emp['name']) ?></option>
        <?php endforeach; ?></select></div>
      <div class="field"><label><?= $edit ? 'New password (leave blank to keep)' : 'Password' ?><?= $edit ? '' : ' *' ?></label><input type="text" name="password" placeholder="Minimum 8 characters"></div>
      <div class="field"><label>Status</label><select name="active"><option value="1"<?= (int) ($edit['active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) ($edit['active'] ?? 1) === 0 ? ' selected' : '' ?>>Disabled</option></select></div>
    </div>
    <button class="btn btn-primary"><?= $edit ? 'Update user' : 'Create user' ?></button>
    <?php if ($edit): ?><a class="btn btn-ghost" href="users.php">Cancel</a><?php endif; ?>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
