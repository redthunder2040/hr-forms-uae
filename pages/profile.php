<?php
$page_title = 'My Account'; $active = 'profile';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (post('act') === 'details') {
        $fn = trim((string) post('full_name'));
        $em = trim((string) post('email'));
        if ($fn === '') flash('Full name is required.', 'error');
        else { q('UPDATE users SET full_name=?, email=? WHERE id=?', [$fn, $em, (int) $__u['id']]); log_activity('profile_update', 'users', (int) $__u['id']); flash('Your details were updated.'); }
        redirect(BASE_URL . '/pages/profile.php');
    }
    if (post('act') === 'password') {
        $cur = (string) post('current'); $new = (string) post('new'); $rep = (string) post('repeat');
        if (!password_verify($cur, (string) $__u['password_hash'])) flash('The current password is not correct.', 'error');
        elseif (strlen($new) < 8) flash('The new password must be at least 8 characters.', 'error');
        elseif ($new !== $rep) flash('The two new passwords do not match.', 'error');
        else {
            q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), (int) $__u['id']]);
            log_activity('password_change', 'users', (int) $__u['id']);
            flash('Your password has been changed.');
        }
        redirect(BASE_URL . '/pages/profile.php');
    }
}

$emp = my_employee();
$myPerms = [];
foreach (array_keys(perm_defs()) as $k) if (can($k)) $myPerms[] = $k;
$myRequests = (int) scalar('SELECT COUNT(*) FROM requests WHERE created_by = ?', [(int) $__u['id']]);
?>
<div class="grid g2">
  <div class="card">
    <div class="card-head"><h2>My details</h2><span class="role-badge role-<?= e(strtolower((string) $__u['role'])) ?>"><?= e(ROLES[$__u['role']] ?? $__u['role']) ?></span></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="details">
      <div class="field"><label>Username</label><input type="text" value="<?= e($__u['username']) ?>" disabled></div>
      <div class="field"><label class="req">Full name</label><input type="text" name="full_name" required value="<?= e($__u['full_name']) ?>"></div>
      <div class="field"><label>E-mail</label><input type="text" name="email" value="<?= e($__u['email']) ?>"></div>
      <button class="btn btn-primary">Save details</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h2>Change password</h2></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="password">
      <div class="field"><label class="req">Current password</label><input type="password" name="current" required></div>
      <div class="field"><label class="req">New password (min. 8 characters)</label><input type="password" name="new" required></div>
      <div class="field"><label class="req">Repeat new password</label><input type="password" name="repeat" required></div>
      <button class="btn btn-primary">Change password</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Linked employee record</h3></div>
    <?php if ($emp): ?>
      <dl class="kv">
        <dt>Code</dt><dd><?= e($emp['code']) ?></dd>
        <dt>Name</dt><dd><?= e($emp['name']) ?></dd>
        <dt>Profession</dt><dd><?= e($emp['profession']) ?></dd>
        <dt>Joining date</dt><dd><?= fdate($emp['joining_date']) ?></dd>
        <dt>Salary</dt><dd>AED <?= money((float) $emp['total']) ?></dd>
      </dl>
    <?php else: ?><p class="muted">No employee record is linked to this login. An administrator can link one in Users &amp; Access.</p><?php endif; ?>
    <p class="muted" style="margin-top:12px">Requests created by me: <b><?= $myRequests ?></b></p>
  </div>

  <div class="card">
    <div class="card-head"><h3>My permissions</h3></div>
    <div class="row">
      <?php foreach ($myPerms as $k): ?><span class="pill"><?= e($k) ?></span><?php endforeach; ?>
      <?php if (!$myPerms): ?><span class="muted">No permissions assigned.</span><?php endif; ?>
    </div>
    <?php if ((string) $__u['role'] === 'admin'): ?><p class="muted" style="margin-top:10px">Administrators always have every permission.</p><?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
