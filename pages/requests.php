<?php
$page_title = 'Request Register'; $active = 'requests';
require_once __DIR__ . '/../includes/header.php';

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = (string) post('act');
    $id  = ipost('id');
    $r   = $id ? one('SELECT * FROM requests WHERE id = ?', [$id]) : null;
    if ($r) {
        $mine = (int) $r['created_by'] === (int) $__u['id'];
        if ($act === 'approve' && can('requests.approve')) {
            q('UPDATE requests SET status="Approved", approved_by=?, approved_by_name=?, approved_at=?, approval_note=? WHERE id=?',
              [(int) $__u['id'], (string) ($__u['full_name'] ?: $__u['username']), now(), trim((string) post('note')), $id]);
            q('INSERT INTO request_status_history (request_id,status,notes,user_id,user_name,created_at) VALUES (?,?,?,?,?,?)',
              [$id, 'Approved', trim((string) post('note')), (int) $__u['id'], (string) ($__u['full_name'] ?: $__u['username']), now()]);
            log_activity('request_approve', 'requests', $id, (string) $r['ref_no']);
            flash('Request ' . $r['ref_no'] . ' approved.');
        } elseif ($act === 'reject' && can('requests.approve')) {
            q('UPDATE requests SET status="Rejected", approved_by=?, approved_by_name=?, approved_at=?, approval_note=? WHERE id=?',
              [(int) $__u['id'], (string) ($__u['full_name'] ?: $__u['username']), now(), trim((string) post('note')), $id]);
            q('INSERT INTO request_status_history (request_id,status,notes,user_id,user_name,created_at) VALUES (?,?,?,?,?,?)',
              [$id, 'Rejected', trim((string) post('note')), (int) $__u['id'], (string) ($__u['full_name'] ?: $__u['username']), now()]);
            flash('Request ' . $r['ref_no'] . ' rejected.', 'warn');
        } elseif ($act === 'cancel' && ($mine || can('requests.delete'))) {
            q('UPDATE requests SET status="Cancelled" WHERE id=?', [$id]);
            q('INSERT INTO request_status_history (request_id,status,notes,user_id,user_name,created_at) VALUES (?,?,?,?,?,?)',
              [$id, 'Cancelled', trim((string) post('note')), (int) $__u['id'], (string) ($__u['full_name'] ?: $__u['username']), now()]);
            flash('Request ' . $r['ref_no'] . ' cancelled.', 'warn');
        } elseif ($act === 'delete' && can('requests.delete')) {
            q('DELETE FROM request_status_history WHERE request_id = ?', [$id]);
            q('DELETE FROM requests WHERE id = ?', [$id]);
            log_activity('request_delete', 'requests', $id, (string) $r['ref_no']);
            flash('Request deleted.');
        } else { flash('You are not allowed to perform this action.', 'error'); }
    }
    redirect(BASE_URL . '/pages/requests.php?' . http_build_query(array_diff_key($_GET, ['id' => 1])));
}

/* ---------- list ---------- */
$f = [
    'q' => trim((string) get('q')), 'status' => (string) get('status'), 'form_type_id' => (int) get('form_type_id'),
    'company_id' => (int) get('company_id'), 'from' => (string) get('from'), 'to' => (string) get('to'), 'employee_id' => (int) get('employee_id'),
];
[$where, $params] = build_request_filter($f);
$page = max(1, (int) get('page', 1)); $per = 20; $off = ($page - 1) * $per;
$total = (int) scalar("SELECT COUNT(*) FROM requests r LEFT JOIN employees e ON e.id = r.employee_id WHERE $where", $params);
$rows = all("SELECT r.*, ft.name AS form_name, e.name AS emp_name, e.code AS emp_code, c.short_name AS co, c.name AS co_full
             FROM requests r
             LEFT JOIN form_types ft ON ft.id = r.form_type_id
             LEFT JOIN employees e ON e.id = r.employee_id
             LEFT JOIN companies c ON c.id = r.company_id
             WHERE $where ORDER BY r.id DESC LIMIT $per OFFSET $off", $params);
$forms = all('SELECT id, name FROM form_types ORDER BY sort_order, id');
?>
<div class="card">
  <div class="card-head">
    <h2>Request register</h2>
    <span class="pill"><?= number_format($total) ?> record(s)</span>
    <?php if (can('requests.create')): ?><a class="btn btn-primary btn-sm" href="request_new.php">＋ New request</a><?php endif; ?>
  </div>
  <form class="toolbar" method="get">
    <div class="f" style="flex:1 1 230px"><label>Search</label><input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="ref, employee, code…"></div>
    <div class="f"><label>Form</label><select name="form_type_id"><option value="">All</option>
      <?php foreach ($forms as $x): ?><option value="<?= (int) $x['id'] ?>"<?= $f['form_type_id'] === (int) $x['id'] ? ' selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label>Company</label><select name="company_id"><option value="">All</option>
      <?php foreach (active_companies() as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $f['company_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['short_name'] ?: $c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label>Status</label><select name="status"><option value="">All</option>
      <?php foreach (['Waiting', 'Approved', 'Rejected', 'Cancelled'] as $s): ?><option value="<?= $s ?>"<?= $f['status'] === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <div class="f"><label>From</label><input type="date" name="from" value="<?= e($f['from']) ?>"></div>
    <div class="f"><label>To</label><input type="date" name="to" value="<?= e($f['to']) ?>"></div>
    <button class="btn btn-dark">Filter</button>
    <a class="btn btn-ghost" href="requests.php">Reset</a>
  </form>

  <div class="table-wrap"><table class="data">
    <thead><tr><th>Ref</th><th>Form</th><th>Employee</th><th>Company</th><th class="num">Salary (AED)</th><th>Status</th><th>Created by</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= e($r['ref_no']) ?></b><?= $r['attachment'] ? ' <small title="Attachment">📎</small>' : '' ?></td>
        <td><?= e($r['form_name']) ?></td>
        <td><?= e($r['emp_name'] ?: ($r['subject'] ?: '-')) ?><?= $r['emp_code'] ? '<br><small>' . e($r['emp_code']) . '</small>' : '' ?></td>
        <td><small><?= e($r['co'] ?: $r['co_full']) ?></small></td>
        <td class="num"><?= money((float) $r['total_salary']) ?></td>
        <td><?= status_badge((string) $r['status']) ?><?php if ($r['approved_by_name']): ?><br><small><?= e($r['approved_by_name']) ?> · <?= fdate($r['approved_at']) ?></small><?php endif; ?></td>
        <td><small><?= e($r['created_by_name']) ?></small></td>
        <td><small><?= fdate($r['created_at']) ?></small></td>
        <td style="white-space:nowrap">
          <a class="btn btn-sm btn-dark" href="request_view.php?id=<?= (int) $r['id'] ?>">Open</a>
          <a class="btn btn-sm btn-ghost" target="_blank" href="<?= BASE_URL ?>/print.php?id=<?= (int) $r['id'] ?>">Print</a>
          <a class="btn btn-sm btn-ghost" href="<?= BASE_URL ?>/pdf.php?id=<?= (int) $r['id'] ?>">PDF</a>
          <?php if (can('requests.approve')): ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="act" value="approve"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-sm btn-ok" title="Approve">✓</button></form>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="act" value="reject"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-sm btn-warn" title="Reject">✕</button></form>
          <?php endif; ?>
          <?php if (can('requests.delete')): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this request permanently?')"><?= csrf_field() ?>
              <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-sm btn-bad" title="Delete">🗑</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="empty">No requests found for this filter.</td></tr><?php endif; ?>
    </tbody></table></div>

  <?php $pages = (int) ceil($total / $per); if ($pages > 1): ?>
    <div class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="cur"><?= $i ?></span>
        <?php else: ?><a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
