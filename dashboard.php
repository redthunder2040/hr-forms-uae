<?php
$page_title = 'Dashboard'; $active = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$scope = can('requests.view_all') ? '' : ' AND r.created_by = ' . (int) $__u['id'];
$cnt = [
    'waiting'  => (int) scalar("SELECT COUNT(*) FROM requests r WHERE r.status = 'Waiting'" . $scope),
    'approved' => (int) scalar("SELECT COUNT(*) FROM requests r WHERE r.status = 'Approved'" . $scope),
    'rejected' => (int) scalar("SELECT COUNT(*) FROM requests r WHERE r.status = 'Rejected'" . $scope),
    'total'    => (int) scalar('SELECT COUNT(*) FROM requests r WHERE 1=1' . $scope),
];
$empActive = (int) scalar('SELECT COUNT(*) FROM employees WHERE active = 1');
$headcount = all('SELECT c.name, c.short_name, COUNT(e.id) AS n, SUM(e.total) AS payroll FROM companies c LEFT JOIN employees e ON e.company_id = c.id AND e.active = 1 WHERE c.active = 1 GROUP BY c.id ORDER BY c.id');
$byType = all('SELECT f.name, COUNT(r.id) AS n FROM form_types f LEFT JOIN requests r ON r.form_type_id = f.id AND MONTH(r.created_at) = MONTH(CURDATE()) AND YEAR(r.created_at) = YEAR(CURDATE()) GROUP BY f.id ORDER BY n DESC LIMIT 10');
$recent = all('SELECT r.*, f.name AS form_name, e.name AS emp_name, e.code AS emp_code, c.short_name AS co
               FROM requests r LEFT JOIN form_types f ON f.id = r.form_type_id
               LEFT JOIN employees e ON e.id = r.employee_id LEFT JOIN companies c ON c.id = r.company_id
               WHERE 1=1' . $scope . ' ORDER BY r.id DESC LIMIT 8');
?>
<div class="grid g4" style="margin-bottom:18px">
  <div class="stat"><div class="k">Total requests</div><div class="v"><?= $cnt['total'] ?></div></div>
  <div class="stat warn"><div class="k">Waiting approval</div><div class="v"><?= $cnt['waiting'] ?></div></div>
  <div class="stat ok"><div class="k">Approved</div><div class="v"><?= $cnt['approved'] ?></div></div>
  <div class="stat bad"><div class="k">Rejected</div><div class="v"><?= $cnt['rejected'] ?></div></div>
</div>

<div class="grid g2">
  <div class="card">
    <div class="card-head"><h2>Headcount &amp; payroll</h2><span class="pill"><?= number_format($empActive) ?> active employees</span></div>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Company</th><th class="num">Employees</th><th class="num">Monthly payroll (AED)</th></tr></thead>
      <tbody>
      <?php foreach ($headcount as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td class="num"><?= number_format((int) $r['n']) ?></td><td class="num"><?= money((float) $r['payroll']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Requests this month</h2><a class="btn btn-sm btn-ghost" href="<?= BASE_URL ?>/pages/reports.php">Reports</a></div>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Form</th><th class="num">Requests</th></tr></thead>
      <tbody>
      <?php $any = false; foreach ($byType as $r): if ((int) $r['n'] === 0) continue; $any = true; ?>
        <tr><td><?= e($r['name']) ?></td><td class="num"><?= (int) $r['n'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$any): ?><tr><td colspan="2" class="empty">No requests created this month yet.</td></tr><?php endif; ?>
      </tbody></table></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>Latest requests</h2>
    <?php if (can('requests.create')): ?><a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/pages/request_new.php">＋ New request</a><?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>/pages/requests.php">Full register</a></div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Ref</th><th>Form</th><th>Employee</th><th>Company</th><th class="num">Salary</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php if (!$recent): ?><tr><td colspan="8" class="empty">No requests yet. Use “New request” to create the first one.</td></tr><?php endif; ?>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><b><?= e($r['ref_no']) ?></b></td>
        <td><?= e($r['form_name']) ?></td>
        <td><?= e($r['emp_name'] ?: ($r['subject'] ?? '-')) ?><?= $r['emp_code'] ? ' <small>' . e($r['emp_code']) . '</small>' : '' ?></td>
        <td><?= e($r['co'] ?: '-') ?></td>
        <td class="num"><?= money((float) $r['total_salary']) ?></td>
        <td><?= status_badge((string) $r['status']) ?></td>
        <td><small><?= fdate($r['created_at']) ?></small></td>
        <td><a class="btn btn-sm btn-ghost" href="<?= BASE_URL ?>/pages/request_view.php?id=<?= (int) $r['id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
