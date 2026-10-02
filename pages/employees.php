<?php
$page_title = 'Employees (master data)'; $active = 'employees';
require_once __DIR__ . '/../includes/header.php';
require_perm('employees.view');

$canManage = can('employees.manage');
$editId = (int) get('edit');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    csrf_verify();
    $act = (string) post('act');
    if ($act === 'save') {
        $id = ipost('id');
        $f = [
            'code' => trim((string) post('code')), 'name' => trim((string) post('name')), 'name_ar' => trim((string) post('name_ar')),
            'company_id' => ipost('company_id') ?: null, 'department_id' => ipost('department_id') ?: null,
            'profession' => trim((string) post('profession')), 'designation' => trim((string) post('designation')),
            'passport_no' => trim((string) post('passport_no')), 'nationality' => trim((string) post('nationality')),
            'joining_date' => post('joining_date') ?: null, 'basic' => (float) post('basic'), 'allowance' => (float) post('allowance'),
            'active' => ipost('active', 1),
        ];
        $f['total'] = $f['basic'] + $f['allowance'];
        if ($f['code'] === '' || $f['name'] === '') { flash('Employee code and name are required.', 'error'); }
        else {
            try {
                if ($id) {
                    q('UPDATE employees SET code=?,name=?,name_ar=?,company_id=?,department_id=?,profession=?,designation=?,passport_no=?,nationality=?,joining_date=?,basic=?,allowance=?,total=?,active=? WHERE id=?',
                       [$f['code'], $f['name'], $f['name_ar'], $f['company_id'], $f['department_id'], $f['profession'], $f['designation'], $f['passport_no'], $f['nationality'], $f['joining_date'], $f['basic'], $f['allowance'], $f['total'], $f['active'], $id]);
                    log_activity('employee_update', 'employees', $id, $f['name']);
                    flash('Employee updated.');
                } else {
                    q('INSERT INTO employees (code,name,name_ar,company_id,department_id,profession,designation,passport_no,nationality,joining_date,basic,allowance,total,active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                       [$f['code'], $f['name'], $f['name_ar'], $f['company_id'], $f['department_id'], $f['profession'], $f['designation'], $f['passport_no'], $f['nationality'], $f['joining_date'], $f['basic'], $f['allowance'], $f['total'], $f['active']]);
                    log_activity('employee_create', 'employees', (int) db()->lastInsertId(), $f['name']);
                    flash('Employee added.');
                }
            } catch (Throwable $e) { flash('Save failed: duplicate code? ' . $e->getMessage(), 'error'); }
        }
        redirect(BASE_URL . '/pages/employees.php');
    }
    if ($act === 'delete') {
        $id = ipost('id');
        q('DELETE FROM employees WHERE id = ?', [$id]);
        q('UPDATE users SET employee_id = NULL WHERE employee_id = ?', [$id]);
        log_activity('employee_delete', 'employees', $id);
        flash('Employee deleted.');
        redirect(BASE_URL . '/pages/employees.php');
    }
}

/* filters + pagination */
$q = trim((string) get('q')); $co = (int) get('company_id'); $dep = (int) get('department_id'); $prof = trim((string) get('profession'));
$page = max(1, (int) get('page', 1)); $per = 25; $off = ($page - 1) * $per;
$w = ['1=1']; $p = [];
$scope = scope_company_ids();
if ($scope) { $w[] = 'e.company_id IN (' . implode(',', array_fill(0, count($scope), '?')) . ')'; $p = array_merge($p, $scope); }
if ($q !== '') { $w[] = '(e.name LIKE ? OR e.code LIKE ? OR e.passport_no LIKE ? OR e.profession LIKE ?)'; $l = "%$q%"; array_push($p, $l, $l, $l, $l); }
if ($co) { $w[] = 'e.company_id = ?'; $p[] = $co; }
if ($dep) { $w[] = 'e.department_id = ?'; $p[] = $dep; }
if ($prof !== '') { $w[] = 'e.profession = ?'; $p[] = $prof; }
$where = implode(' AND ', $w);
$total = (int) scalar("SELECT COUNT(*) FROM employees e WHERE $where", $p);
$rows  = all("SELECT e.*, c.short_name AS co, d.name AS dep FROM employees e
              LEFT JOIN companies c ON c.id = e.company_id LEFT JOIN departments d ON d.id = e.department_id
              WHERE $where ORDER BY CAST(e.code AS UNSIGNED), e.name LIMIT $per OFFSET $off", $p);
$profList = array_column(all('SELECT DISTINCT profession FROM employees WHERE profession <> "" ORDER BY profession'), 'profession');
$ed = $editId ? one('SELECT * FROM employees WHERE id = ?', [$editId]) : null;

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=hr-employees-' . date('Ymd') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Code', 'Name', 'Company', 'Department', 'Profession', 'Joining date', 'Basic', 'Allowance', 'Total', 'Passport', 'Nationality']);
    $allRows = all("SELECT e.*, c.name AS co, d.name AS dep FROM employees e LEFT JOIN companies c ON c.id=e.company_id LEFT JOIN departments d ON d.id=e.department_id WHERE $where ORDER BY CAST(e.code AS UNSIGNED)", $p);
    foreach ($allRows as $r) fputcsv($out, [$r['code'], $r['name'], $r['co'], $r['dep'], $r['profession'], $r['joining_date'], $r['basic'], $r['allowance'], $r['total'], $r['passport_no'], $r['nationality']]);
    fclose($out); exit;
}
?>
<div class="card">
  <div class="card-head">
    <h2>Employee master database</h2>
    <span class="pill"><?= number_format($total) ?> records</span>
    <a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Export CSV</a>
    <?php if ($canManage): ?><a class="btn btn-primary btn-sm" href="?edit=0#form">＋ Add employee</a><?php endif; ?>
  </div>
  <form class="toolbar" method="get">
    <div class="f" style="flex:1 1 260px"><label>Search</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="name, code, passport…"></div>
    <div class="f"><label>Company</label><select name="company_id"><option value="">All</option>
      <?php foreach (active_companies() as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $co === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="f"><label>Department</label><select name="department_id"><option value="">All</option>
      <?php foreach (departments() as $d): ?><option value="<?= (int) $d['id'] ?>"<?= $dep === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="f"><label>Profession</label><select name="profession"><option value="">All</option>
      <?php foreach ($profList as $pr): ?><option value="<?= e($pr) ?>"<?= $prof === $pr ? ' selected' : '' ?>><?= e($pr) ?></option><?php endforeach; ?>
    </select></div>
    <button class="btn btn-dark" type="submit">Filter</button>
    <a class="btn btn-ghost" href="employees.php">Reset</a>
  </form>

  <div class="table-wrap"><table class="data" id="empTable">
    <thead><tr><th>Code</th><th>Name</th><th>Company</th><th>Dept</th><th>Profession</th><th>Joined</th>
      <th class="num">Basic</th><th class="num">Allowance</th><th class="num">Total</th><th>Status</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= e($r['code']) ?></b></td>
        <td><?= e($r['name']) ?></td>
        <td><?= e($r['co'] ?: '-') ?></td>
        <td><?= e($r['dep'] ?: '-') ?></td>
        <td><?= e($r['profession'] ?: '-') ?></td>
        <td><small><?= fdate($r['joining_date']) ?></small></td>
        <td class="num"><?= money($r['basic']) ?></td>
        <td class="num"><?= money($r['allowance']) ?></td>
        <td class="num"><b><?= money($r['total']) ?></b></td>
        <td><?= (int) $r['active'] === 1 ? '<span class="badge b-approved">Active</span>' : '<span class="badge b-cancelled">Inactive</span>' ?></td>
        <?php if ($canManage): ?><td style="white-space:nowrap">
          <a class="btn btn-sm btn-ghost" href="?edit=<?= (int) $r['id'] ?>#form">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this employee?')">
            <?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="btn btn-sm btn-bad" type="submit">Del</button></form>
        </td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="11" class="empty">No employees match your filter.</td></tr><?php endif; ?>
    </tbody></table></div>

  <?php $pages = (int) ceil($total / $per); if ($pages > 1): ?>
    <div class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="cur"><?= $i ?></span>
        <?php else: ?><a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $i, 'edit' => null]))) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($canManage): ?>
<div class="card" id="form">
  <div class="card-head"><h2><?= $ed ? 'Edit employee: ' . e($ed['name']) : 'Add employee' ?></h2></div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int) ($ed['id'] ?? 0) ?>">
    <div class="grid g3">
      <div class="field"><label class="req">Employee code</label><input type="text" name="code" required value="<?= e($ed['code'] ?? '') ?>" placeholder="0480"></div>
      <div class="field"><label class="req">Full name</label><input type="text" name="name" required value="<?= e($ed['name'] ?? '') ?>"></div>
      <div class="field"><label>Name (Arabic)</label><input type="text" name="name_ar" value="<?= e($ed['name_ar'] ?? '') ?>"></div>
      <div class="field"><label>Company</label><select name="company_id"><option value="">—</option>
        <?php foreach (active_companies() as $c): ?><option value="<?= (int) $c['id'] ?>"<?= ($ed['company_id'] ?? 0) == $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Department</label><select name="department_id"><option value="">—</option>
        <?php foreach (departments() as $d): ?><option value="<?= (int) $d['id'] ?>"<?= ($ed['department_id'] ?? 0) == $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Profession</label><input type="text" name="profession" value="<?= e($ed['profession'] ?? '') ?>" list="profs">
        <datalist id="profs"><?php foreach ($profList as $pr): ?><option value="<?= e($pr) ?>"><?php endforeach; ?></datalist></div>
      <div class="field"><label>Designation (as per visa / contract)</label><input type="text" name="designation" value="<?= e($ed['designation'] ?? '') ?>"></div>
      <div class="field"><label>Passport number</label><input type="text" name="passport_no" value="<?= e($ed['passport_no'] ?? '') ?>"></div>
      <div class="field"><label>Nationality</label><input type="text" name="nationality" value="<?= e($ed['nationality'] ?? '') ?>"></div>
      <div class="field"><label>Joining date</label><input type="date" name="joining_date" value="<?= e($ed['joining_date'] ?? '') ?>"></div>
      <div class="field"><label>Basic salary (AED)</label><input type="number" step="0.01" name="basic" value="<?= e($ed['basic'] ?? '0') ?>"></div>
      <div class="field"><label>Allowance (AED)</label><input type="number" step="0.01" name="allowance" value="<?= e($ed['allowance'] ?? '0') ?>"></div>
      <div class="field"><label>Status</label><select name="active"><option value="1"<?= (int) ($ed['active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) ($ed['active'] ?? 1) === 0 ? ' selected' : '' ?>>Inactive</option></select></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= $ed ? 'Update employee' : 'Save employee' ?></button>
    <?php if ($ed): ?><a class="btn btn-ghost" href="employees.php">Cancel</a><?php endif; ?>
    <small style="margin-left:10px">Total salary is calculated automatically as basic + allowance.</small>
  </form>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
