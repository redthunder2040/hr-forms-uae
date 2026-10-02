<?php
$page_title = 'Companies &amp; Departments'; $active = 'master';
require_once __DIR__ . '/../includes/header.php';
require_perm('master.manage');

$tab = (string) get('tab', 'companies');
$tabs = ['companies' => 'Companies', 'departments' => 'Departments', 'professions' => 'Professions'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = (string) post('act');
    if ($act === 'save_company') {
        $id = ipost('id');
        $v = [trim((string) post('name')), trim((string) post('short_name')), trim((string) post('address')), trim((string) post('phone')), trim((string) post('email')), trim((string) post('trn')), ipost('active', 1)];
        if ($v[0] === '') flash('Company name is required.', 'error');
        else {
            if ($id) { q('UPDATE companies SET name=?,short_name=?,address=?,phone=?,email=?,trn=?,active=? WHERE id=?', array_merge($v, [$id])); flash('Company updated.'); }
            else { q('INSERT INTO companies (name,short_name,address,phone,email,trn,active) VALUES (?,?,?,?,?,?,?)', $v); flash('Company added.'); }
            log_activity('company_save', 'companies', $id, $v[0]);
        }
        redirect(BASE_URL . '/pages/master.php?tab=companies');
    }
    if ($act === 'del_company') {
        $id = ipost('id');
        if ((int) scalar('SELECT COUNT(*) FROM employees WHERE company_id = ?', [$id]) > 0) flash('Cannot delete: employees are linked to this company.', 'error');
        else { q('DELETE FROM companies WHERE id = ?', [$id]); log_activity('company_delete', 'companies', $id); flash('Company deleted.'); }
        redirect(BASE_URL . '/pages/master.php?tab=companies');
    }
    if ($act === 'save_department') {
        $id = ipost('id');
        $v = [trim((string) post('name')), trim((string) post('code')), ipost('active', 1)];
        if ($v[0] === '') flash('Department name is required.', 'error');
        else {
            if ($id) { q('UPDATE departments SET name=?,code=?,active=? WHERE id=?', array_merge($v, [$id])); flash('Department updated.'); }
            else { q('INSERT INTO departments (name,code,active) VALUES (?,?,?)', $v); flash('Department added.'); }
            log_activity('department_save', 'departments', $id, $v[0]);
        }
        redirect(BASE_URL . '/pages/master.php?tab=departments');
    }
    if ($act === 'del_department') {
        $id = ipost('id');
        if ((int) scalar('SELECT COUNT(*) FROM employees WHERE department_id = ?', [$id]) > 0) flash('Cannot delete: employees are linked to this department.', 'error');
        else { q('DELETE FROM departments WHERE id = ?', [$id]); flash('Department deleted.'); }
        redirect(BASE_URL . '/pages/master.php?tab=departments');
    }
    if ($act === 'save_profession') {
        $id = ipost('id');
        $name = trim((string) post('name'));
        if ($name === '') flash('Profession name is required.', 'error');
        else {
            if ($id) { q('UPDATE professions SET name=? WHERE id=?', [$name, $id]); flash('Profession updated.'); }
            else { try { q('INSERT INTO professions (name) VALUES (?)', [$name]); flash('Profession added.'); } catch (Throwable $e) { flash('Already exists.', 'error'); } }
        }
        redirect(BASE_URL . '/pages/master.php?tab=professions');
    }
    if ($act === 'del_profession') { q('DELETE FROM professions WHERE id = ?', [ipost('id')]); redirect(BASE_URL . '/pages/master.php?tab=professions'); }
}

$editCompany = (int) get('edit_company') ? one('SELECT * FROM companies WHERE id = ?', [(int) get('edit_company')]) : null;
$editDept    = (int) get('edit_department') ? one('SELECT * FROM departments WHERE id = ?', [(int) get('edit_department')]) : null;
$counts = [];
foreach (companies() as $c) $counts[$c['id']] = (int) scalar('SELECT COUNT(*) FROM employees WHERE company_id = ?', [$c['id']]);
$deptCounts = [];
foreach (departments() as $d) $deptCounts[$d['id']] = (int) scalar('SELECT COUNT(*) FROM employees WHERE department_id = ?', [$d['id']]);
?>
<div class="tabs">
  <?php foreach ($tabs as $k => $lbl): ?>
    <a class="tab <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'companies'): ?>
<div class="card">
  <div class="card-head"><h2>Companies</h2><span class="pill">Used on every form and on printed documents</span></div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Legal name (as printed)</th><th>Short</th><th>Address / contact</th><th>TRN</th><th class="num">Employees</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach (companies() as $c): ?>
      <tr><td><b><?= e($c['name']) ?></b></td><td><?= e($c['short_name']) ?></td>
        <td><small><?= e(trim(implode(' · ', array_filter([$c['address'], $c['phone'], $c['email']])))) ?: '-' ?></small></td>
        <td><small><?= e($c['trn'] ?: '-') ?></small></td>
        <td class="num"><?= number_format($counts[$c['id']] ?? 0) ?></td>
        <td><?= (int) $c['active'] === 1 ? '<span class="badge b-approved">Active</span>' : '<span class="badge b-cancelled">Inactive</span>' ?></td>
        <td style="white-space:nowrap"><a class="btn btn-sm btn-ghost" href="?tab=companies&edit_company=<?= (int) $c['id'] ?>#cc">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete company?')"><?= csrf_field() ?>
            <input type="hidden" name="act" value="del_company"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn btn-sm btn-bad">Del</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<div class="card" id="cc">
  <div class="card-head"><h2><?= $editCompany ? 'Edit company' : 'Add company' ?></h2></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="save_company"><input type="hidden" name="id" value="<?= (int) ($editCompany['id'] ?? 0) ?>">
    <div class="grid g3">
      <div class="field" style="grid-column:1/-1"><label class="req">Legal name (exactly as it must be printed)</label><input type="text" name="name" required value="<?= e($editCompany['name'] ?? '') ?>" placeholder="Company 2"></div>
      <div class="field"><label>Short name</label><input type="text" name="short_name" value="<?= e($editCompany['short_name'] ?? '') ?>"></div>
      <div class="field"><label>Telephone</label><input type="text" name="phone" value="<?= e($editCompany['phone'] ?? '') ?>"></div>
      <div class="field"><label>E-mail</label><input type="text" name="email" value="<?= e($editCompany['email'] ?? '') ?>"></div>
      <div class="field"><label>TRN</label><input type="text" name="trn" value="<?= e($editCompany['trn'] ?? '') ?>"></div>
      <div class="field" style="grid-column:1/-1"><label>Address</label><input type="text" name="address" value="<?= e($editCompany['address'] ?? '') ?>"></div>
      <div class="field"><label>Status</label><select name="active"><option value="1"<?= (int) ($editCompany['active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) ($editCompany['active'] ?? 1) === 0 ? ' selected' : '' ?>>Inactive</option></select></div>
    </div>
    <button class="btn btn-primary"><?= $editCompany ? 'Update company' : 'Add company' ?></button></form>
</div>

<?php elseif ($tab === 'departments'): ?>
<div class="card">
  <div class="card-head"><h2>Departments</h2><span class="pill">Used by the employee picker and leave/request forms</span></div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Department</th><th>Code</th><th class="num">Employees</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach (departments() as $d): ?>
      <tr><td><b><?= e($d['name']) ?></b></td><td><?= e($d['code']) ?></td><td class="num"><?= number_format($deptCounts[$d['id']] ?? 0) ?></td>
        <td><?= (int) $d['active'] === 1 ? '<span class="badge b-approved">Active</span>' : '<span class="badge b-cancelled">Inactive</span>' ?></td>
        <td style="white-space:nowrap"><a class="btn btn-sm btn-ghost" href="?tab=departments&edit_department=<?= (int) $d['id'] ?>#dd">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete department?')"><?= csrf_field() ?>
            <input type="hidden" name="act" value="del_department"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <button class="btn btn-sm btn-bad">Del</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<div class="card" id="dd">
  <div class="card-head"><h2><?= $editDept ? 'Edit department' : 'Add department' ?></h2></div>
  <form class="row" method="post"><?= csrf_field() ?><input type="hidden" name="act" value="save_department"><input type="hidden" name="id" value="<?= (int) ($editDept['id'] ?? 0) ?>">
    <div class="field" style="flex:1 1 260px"><label class="req">Name</label><input type="text" name="name" required value="<?= e($editDept['name'] ?? '') ?>"></div>
    <div class="field" style="flex:0 1 160px"><label>Code</label><input type="text" name="code" value="<?= e($editDept['code'] ?? '') ?>"></div>
    <div class="field" style="flex:0 1 160px"><label>Status</label><select name="active"><option value="1">Active</option><option value="0"<?= (int) ($editDept['active'] ?? 1) === 0 ? ' selected' : '' ?>>Inactive</option></select></div>
    <div class="field"><button class="btn btn-primary"><?= $editDept ? 'Update' : 'Add' ?></button></div>
  </form>
</div>

<?php else: ?>
<div class="card">
  <div class="card-head"><h2>Professions / designations</h2></div>
  <div class="row">
    <?php foreach (professions() as $p): ?>
      <span class="pill" style="display:inline-flex;gap:8px;align-items:center"><?= e($p['name']) ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Remove?')"><?= csrf_field() ?>
          <input type="hidden" name="act" value="del_profession"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="btn btn-sm" style="padding:0 6px;background:none;color:#c0271f">×</button></form></span>
    <?php endforeach; ?>
  </div>
  <form class="row" method="post" style="margin-top:16px"><?= csrf_field() ?><input type="hidden" name="act" value="save_profession">
    <div class="field" style="flex:1 1 280px"><label>New profession</label><input type="text" name="name" required placeholder="Electrician"></div>
    <div class="field"><button class="btn btn-primary">Add</button></div>
  </form>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
