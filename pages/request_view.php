<?php
$page_title = 'Request details'; $active = 'requests';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/docs.php';

$id = (int) get('id');
$r  = one('SELECT r.*, ft.name AS form_name, ft.slug AS form_slug, e.name AS emp_name, e.code AS emp_code,
                  c.name AS co_name, c.short_name AS co_short
           FROM requests r LEFT JOIN form_types ft ON ft.id = r.form_type_id
           LEFT JOIN employees e ON e.id = r.employee_id LEFT JOIN companies c ON c.id = r.company_id
           WHERE r.id = ?', [$id]);
if (!$r) { flash('Request not found.', 'error'); redirect(BASE_URL . '/pages/requests.php'); }
$mine = (int) $r['created_by'] === (int) $__u['id'];
if (!$mine && !can('requests.view_all')) require_perm('requests.view_all');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = (string) post('act');
    $note = trim((string) post('note'));
    if ($act === 'office') {
        q('UPDATE requests SET office_ref=?, approved_salary=?, payment_date=?, remarks=? WHERE id=?',
          [trim((string) post('office_ref')), (float) post('approved_salary') ?: null, post('payment_date') ?: null, trim((string) post('remarks')), $id]);
        q('UPDATE requests SET data = ? WHERE id = ?', [json_encode(update_request_data((string) $r['data'], $_POST), JSON_UNESCAPED_UNICODE), $id]);
        log_activity('request_office_update', 'requests', $id, (string) $r['ref_no']);
        flash('Office-use details and field values saved.');
    } elseif ($act === 'approve' || $act === 'reject' || $act === 'cancel') {
        $allowed = ($act === 'cancel') ? ($mine || can('requests.delete')) : can('requests.approve');
        if (!$allowed) { flash('Not permitted.', 'error'); }
        else {
            $st = $act === 'approve' ? 'Approved' : ($act === 'reject' ? 'Rejected' : 'Cancelled');
            q('UPDATE requests SET status=?, approved_by=?, approved_by_name=?, approved_at=?, approval_note=? WHERE id=?',
              [$st, (int) $__u['id'], (string) ($__u['full_name'] ?: $__u['username']), now(), $note, $id]);
            q('INSERT INTO request_status_history (request_id,status,notes,user_id,user_name,created_at) VALUES (?,?,?,?,?,?)',
              [$id, $st, $note, (int) $__u['id'], (string) ($__u['full_name'] ?: $__u['username']), now()]);
            log_activity('request_' . $act, 'requests', $id, (string) $r['ref_no']);
            flash('Status changed to ' . $st . '.');
        }
    }
    redirect(BASE_URL . '/pages/request_view.php?id=' . $id);
}

$ft     = form_type_by_id((int) $r['form_type_id']);
$fields = $ft ? fields_of((int) $ft['id']) : [];
$data   = json_decode((string) $r['data'], true) ?: [];
$emp    = $r['employee_id'] ? one('SELECT * FROM employees WHERE id = ?', [(int) $r['employee_id']]) : null;
$hist   = all('SELECT * FROM request_status_history WHERE request_id = ? ORDER BY id DESC', [$id]);
$ro     = !($mine || can('requests.approve') || can('requests.delete'));

function update_request_data(string $json, array $src): array
{
    $d = json_decode($json, true) ?: [];
    foreach ($src as $k => $v) {
        if (str_starts_with($k, 'f_') && is_string($v)) {
            $d[substr($k, 2)] = trim($v);
        }
    }
    return $d;
}
?>
<div class="grid g2">
  <div class="card">
    <div class="card-head"><h2><?= e($r['form_name']) ?></h2><?= status_badge((string) $r['status']) ?></div>
    <dl class="kv">
      <dt>Reference</dt><dd><b><?= e($r['ref_no']) ?></b></dd>
      <dt>Company</dt><dd><?= e($r['co_name']) ?></dd>
      <dt>Employee</dt><dd><?= e($r['emp_name'] ?: ($data['candidate_name'] ?? '—')) ?><?= $r['emp_code'] ? ' (' . e($r['emp_code']) . ')' : '' ?></dd>
      <?php if ($emp): ?>
        <dt>Profession / Department</dt><dd><?= e($emp['profession']) ?></dd>
        <dt>Joining date</dt><dd><?= fdate($emp['joining_date']) ?></dd>
        <dt>Current salary</dt><dd>AED <?= money((float) $emp['total']) ?> (basic <?= money((float) $emp['basic']) ?> + allowance <?= money((float) $emp['allowance']) ?>)</dd>
        <dt>Passport / Nationality</dt><dd><?= e($emp['passport_no'] ?: '—') ?> · <?= e($emp['nationality'] ?: '—') ?></dd>
      <?php endif; ?>
      <dt>Total salary on request</dt><dd>AED <?= money((float) $r['total_salary']) ?></dd>
      <dt>Created</dt><dd><?= fdate($r['created_at']) ?> by <?= e($r['created_by_name']) ?></dd>
      <dt>Decided</dt><dd><?= $r['approved_at'] ? fdate($r['approved_at']) . ' by ' . e($r['approved_by_name']) : 'Pending' ?></dd>
      <?php if ($r['approval_note']): ?><dt>Decision note</dt><dd><?= e($r['approval_note']) ?></dd><?php endif; ?>
      <?php if ($r['attachment']): ?><dt>Attachment</dt><dd><a class="btn btn-sm btn-ghost" href="<?= UPLOAD_URL ?>/<?= e($r['attachment']) ?>" target="_blank">Open attachment</a></dd><?php endif; ?>
    </dl>
    <div class="row" style="margin-top:16px">
      <a class="btn btn-dark" target="_blank" href="<?= BASE_URL ?>/print.php?id=<?= $id ?>">🖨 Print document</a>
      <a class="btn btn-primary" href="<?= BASE_URL ?>/pdf.php?id=<?= $id ?>">⤓ Download PDF</a>
      <a class="btn btn-ghost" href="requests.php">Back to register</a>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Approval workflow</h3></div>
    <?php if (can('requests.approve')): ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label>Decision note (optional)</label><input type="text" name="note" placeholder="Approved as per policy…"></div>
        <button class="btn btn-ok" name="act" value="approve">✓ Approve request</button>
        <button class="btn btn-warn" name="act" value="reject">✕ Reject request</button>
        <?php if ($mine || can('requests.delete')): ?><button class="btn btn-ghost" name="act" value="cancel">Cancel request</button><?php endif; ?>
      </form>
    <?php else: ?>
      <p class="muted">Your role cannot approve this request. It is waiting for an administrator or HR officer.</p>
      <?php if ($mine): ?>
        <form method="post"><?= csrf_field() ?><button class="btn btn-ghost" name="act" value="cancel">Cancel my request</button></form>
      <?php endif; ?>
    <?php endif; ?>
    <h3 style="margin-top:20px">History</h3>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Status</th><th>By</th><th>Note</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($hist as $h): ?>
        <tr><td><?= status_badge((string) $h['status']) ?></td><td><?= e($h['user_name']) ?></td><td><small><?= e($h['notes']) ?></small></td><td><small><?= fdate($h['created_at']) ?></small></td></tr>
      <?php endforeach; ?>
      <?php if (!$hist): ?><tr><td colspan="4" class="empty">No history yet.</td></tr><?php endif; ?>
      </tbody></table></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3>Submitted values &amp; office use</h3><span class="pill">Editable by HR / admin</span></div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="act" value="office">
    <div class="grid g2">
      <?php foreach ($fields as $f): ?>
        <?php if ($ro): ?>
          <div class="field"><label><?= e($f['label']) ?></label><input type="text" value="<?= e((string) ($data[$f['field_key']] ?? '')) ?>" disabled></div>
        <?php else: ?>
          <?= render_field($f, $data[$f['field_key']] ?? '') ?>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$ro): ?><?= render_office_fields($r) ?><?php endif; ?>
    </div>
    <?php if (!$ro): ?>
      <button class="btn btn-dark" type="submit">Save changes</button>
      <small style="display:block;margin-top:6px">Print or download the PDF again after saving to see the updated document.</small>
    <?php else: ?>
      <p class="muted">Read-only view.</p>
    <?php endif; ?>
  </form>
</div>

<div class="card no-print">
  <div class="card-head"><h3>Document preview</h3><span class="pill">Print / PDF output</span></div>
  <iframe src="<?= BASE_URL ?>/print.php?id=<?= $id ?>&embed=1" style="width:100%;height:780px;border:1px solid var(--line);border-radius:10px;background:#fff"></iframe>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
