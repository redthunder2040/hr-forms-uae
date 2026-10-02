<?php
$page_title = 'Request Reports'; $active = 'reports';
require_once __DIR__ . '/../includes/header.php';
require_perm('reports.view');

$f = [
    'q' => trim((string) get('q')), 'status' => (string) get('status'), 'form_type_id' => (int) get('form_type_id'),
    'company_id' => (int) get('company_id'), 'from' => (string) get('from'), 'to' => (string) get('to'), 'employee_id' => 0,
];
[$where, $params] = build_request_filter($f);

if (get('export') === 'csv') {
    $rows = all("SELECT r.ref_no, ft.name AS form, r.status, c.name AS company, e.code, e.name AS employee, r.subject,
                        r.total_salary, r.office_ref, r.approved_salary, r.payment_date, r.created_by_name, r.approved_by_name, r.created_at, r.approved_at
                 FROM requests r LEFT JOIN form_types ft ON ft.id = r.form_type_id LEFT JOIN employees e ON e.id = r.employee_id
                 LEFT JOIN companies c ON c.id = r.company_id WHERE $where ORDER BY r.id DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=hr-requests-' . date('Ymd-His') . '.csv');
    $o = fopen('php://output', 'w');
    fputcsv($o, ['Reference', 'Form', 'Status', 'Company', 'Emp code', 'Employee', 'Subject', 'Total salary', 'Office ref', 'Approved amount', 'Payment date', 'Created by', 'Action by', 'Created', 'Decided']);
    foreach ($rows as $r) fputcsv($o, array_values($r));
    fclose($o); exit;
}

$byStatus  = all("SELECT r.status, COUNT(*) n, COALESCE(SUM(r.total_salary),0) amount FROM requests r LEFT JOIN employees e ON e.id = r.employee_id WHERE $where GROUP BY r.status", $params);
$byForm    = all("SELECT ft.name, r.status, COUNT(*) n FROM requests r LEFT JOIN form_types ft ON ft.id=r.form_type_id LEFT JOIN employees e ON e.id=r.employee_id WHERE $where GROUP BY ft.name, r.status ORDER BY ft.name", $params);
$byCompany = all("SELECT c.name, COUNT(*) n, COALESCE(SUM(r.total_salary),0) amount FROM requests r LEFT JOIN companies c ON c.id=r.company_id LEFT JOIN employees e ON e.id=r.employee_id WHERE $where GROUP BY c.name ORDER BY n DESC", $params);
$byMonth   = all("SELECT DATE_FORMAT(r.created_at,'%Y-%m') ym, COUNT(*) n FROM requests r LEFT JOIN employees e ON e.id=r.employee_id WHERE $where GROUP BY ym ORDER BY ym DESC LIMIT 12", $params);
$detail    = all("SELECT r.*, ft.name AS form_name, e.name AS emp_name, e.code AS emp_code, c.short_name AS co
                  FROM requests r LEFT JOIN form_types ft ON ft.id=r.form_type_id LEFT JOIN employees e ON e.id=r.employee_id
                  LEFT JOIN companies c ON c.id=r.company_id WHERE $where ORDER BY r.id DESC LIMIT 300", $params);
$statusMap = [];
foreach ($byStatus as $s) $statusMap[$s['status']] = $s;
$forms = all('SELECT id, name FROM form_types ORDER BY sort_order, id');
?>
<div class="card">
  <div class="card-head"><h2>Request reports</h2>
    <span class="pill">Status: Waiting / Approved / Rejected / Cancelled</span>
    <a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Export CSV</a>
  </div>
  <form class="toolbar" method="get">
    <div class="f" style="flex:1 1 200px"><label>Search</label><input type="text" name="q" value="<?= e($f['q']) ?>"></div>
    <div class="f"><label>Form</label><select name="form_type_id"><option value="">All</option>
      <?php foreach ($forms as $x): ?><option value="<?= (int) $x['id'] ?>"<?= $f['form_type_id'] === (int) $x['id'] ? ' selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label>Status</label><select name="status"><option value="">All</option>
      <?php foreach (['Waiting', 'Approved', 'Rejected', 'Cancelled'] as $s): ?><option value="<?= $s ?>"<?= $f['status'] === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <div class="f"><label>Company</label><select name="company_id"><option value="">All</option>
      <?php foreach (active_companies() as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $f['company_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['short_name'] ?: $c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label>From</label><input type="date" name="from" value="<?= e($f['from']) ?>"></div>
    <div class="f"><label>To</label><input type="date" name="to" value="<?= e($f['to']) ?>"></div>
    <button class="btn btn-dark">Run report</button>
    <a class="btn btn-ghost" href="reports.php">Reset</a>
  </form>
</div>

<div class="grid g4" style="margin-bottom:18px">
  <?php
  $cards = [['Waiting', '', 'warn'], ['Approved', 'ok', 'ok'], ['Rejected', 'bad', 'bad'], ['Cancelled', '', '']];
  foreach ($cards as $c): $d = $statusMap[$c[0]] ?? ['n' => 0, 'amount' => 0]; ?>
    <div class="stat <?= $c[2] ?>"><div class="k"><?= $c[0] ?></div><div class="v"><?= number_format((int) $d['n']) ?></div>
      <small>AED <?= money((float) $d['amount']) ?> requested</small></div>
  <?php endforeach; ?>
</div>

<div class="grid g2">
  <div class="card">
    <div class="card-head"><h3>By form type</h3></div>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Form</th><th>Status</th><th class="num">Count</th></tr></thead>
      <tbody><?php foreach ($byForm as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td><?= status_badge((string) $r['status']) ?></td><td class="num"><?= (int) $r['n'] ?></td></tr>
      <?php endforeach; ?><?php if (!$byForm): ?><tr><td colspan="3" class="empty">No data.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="card">
    <div class="card-head"><h3>By company</h3></div>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Company</th><th class="num">Requests</th><th class="num">Amount (AED)</th></tr></thead>
      <tbody><?php foreach ($byCompany as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td class="num"><?= (int) $r['n'] ?></td><td class="num"><?= money((float) $r['amount']) ?></td></tr>
      <?php endforeach; ?><?php if (!$byCompany): ?><tr><td colspan="3" class="empty">No data.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="card">
    <div class="card-head"><h3>Last 12 months volume</h3></div>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Month</th><th class="num">Requests</th></tr></thead>
      <tbody><?php foreach ($byMonth as $r): ?><tr><td><?= e($r['ym']) ?></td><td class="num"><?= (int) $r['n'] ?></td></tr><?php endforeach; ?>
      <?php if (!$byMonth): ?><tr><td colspan="2" class="empty">No data.</td></tr><?php endif; ?></tbody></table></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3>Detailed register (latest 300)</h3></div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Ref</th><th>Form</th><th>Employee</th><th>Company</th><th class="num">Salary</th><th>Status</th><th>Created by</th><th>Date</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($detail as $r): ?>
      <tr><td><b><?= e($r['ref_no']) ?></b></td><td><?= e($r['form_name']) ?></td>
        <td><?= e($r['emp_name'] ?: $r['subject']) ?> <?= $r['emp_code'] ? '<small>' . e($r['emp_code']) . '</small>' : '' ?></td>
        <td><small><?= e($r['co']) ?></small></td><td class="num"><?= money((float) $r['total_salary']) ?></td>
        <td><?= status_badge((string) $r['status']) ?></td><td><small><?= e($r['created_by_name']) ?></small></td>
        <td><small><?= fdate($r['created_at']) ?></small></td>
        <td><a class="btn btn-sm btn-ghost" href="request_view.php?id=<?= (int) $r['id'] ?>">Open</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$detail): ?><tr><td colspan="9" class="empty">No requests match this filter.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
