<?php
$page_title = 'New Request'; $active = 'request_new';
require_once __DIR__ . '/../includes/header.php';
require_perm('requests.create');

$slug   = (string) get('type', '');
$ftList = all('SELECT * FROM form_types WHERE active = 1 ORDER BY sort_order, id');
$ft     = $slug !== '' ? form_type_by_slug($slug) : ($ftList[0] ?? null);
$me     = my_employee();
$forceOwn = ($me && (user_role() === 'user' || !can('employees.view')));   // simple users always submit for themselves
$adminOrHr = in_array((string) $__u['role'], ['admin', 'HR'], true);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ftid = ipost('form_type_id');
    $ft   = $ftid ? form_type_by_id($ftid) : null;
    if (!$ft) { $errors[] = 'Please choose a valid request form.'; }
    else {
        $fields = fields_of((int) $ft['id']);
        $src = $_POST;
        if ($forceOwn) { $src['f_employee'] = (string) $me['id']; }
        [$data, $empId] = collect_fields($fields, $src);
        $miss = missing_required($fields, $data);
        if ($miss) $errors[] = 'Please complete the required field(s): ' . implode(', ', $miss);
        if ($forceOwn && !$empId) $empId = (int) $me['id'];

        /* company = employee's company, else the form's company, else the selected one */
        $companyId = 0;
        if ($empId) { $companyId = (int) (scalar('SELECT company_id FROM employees WHERE id = ?', [$empId]) ?: 0); }
        if (!$companyId) $companyId = (int) ($ft['company_id'] ?: 0);
        if (!$companyId) { $c = active_companies()[0] ?? null; $companyId = (int) ($c['id'] ?? 0); }
        if (!$companyId) $errors[] = 'No company is configured yet — please add a company in Companies & Departments.';

        if (!$errors) {
            $refNo = next_ref((string) ($ft['ref_prefix'] ?: 'HR'));
            $total = total_salary_from($fields, $data);
            $subject = '';
            foreach (['candidate_name', 'employee_name', 'destination', 'reason'] as $k) { if (!empty($data[$k])) { $subject = (string) $data[$k]; break; } }
            if ($subject === '' && $empId) $subject = (string) (scalar('SELECT name FROM employees WHERE id = ?', [$empId]) ?: '');
            $att = upload_attachment('attachment');
            q('INSERT INTO requests (ref_no, form_type_id, company_id, employee_id, subject, data, status, total_salary, attachment,
                 office_ref, approved_salary, payment_date, remarks, created_by, created_by_name, created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [$refNo, (int) $ft['id'], $companyId, $empId ?: null, $subject, json_encode($data, JSON_UNESCAPED_UNICODE), 'Waiting',
               $total, $att, trim((string) post('office_ref')), (float) post('approved_salary') ?: null, post('payment_date') ?: null,
               trim((string) post('remarks')), (int) $__u['id'], (string) ($__u['full_name'] ?: $__u['username']), now()]);
            $newId = (int) db()->lastInsertId();
            q('INSERT INTO request_status_history (request_id, status, notes, user_id, user_name, created_at) VALUES (?,?,?,?,?,?)',
              [$newId, 'Waiting', 'Request created', (int) $__u['id'], (string) ($__u['full_name'] ?: $__u['username']), now()]);
            log_activity('request_create', 'requests', $newId, $refNo);
            flash('Request ' . $refNo . ' created and stored. Status: Waiting approval.');
            redirect(BASE_URL . '/pages/request_view.php?id=' . $newId);
        }
    }
}

$fields = ($ft && !$errors) ? fields_of((int) $ft['id']) : ($ft ? fields_of((int) $ft['id']) : []);
?>
<div class="card">
  <div class="card-head"><h2>Choose request form</h2><span class="pill"><?= count($ftList) ?> active forms</span></div>
  <div class="tabs">
    <?php foreach ($ftList as $f): ?>
      <a class="tab <?= ($ft && (int) $ft['id'] === (int) $f['id']) ? 'active' : '' ?>" href="?type=<?= e($f['slug']) ?>"><?= e($f['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$ftList): ?><div class="alert alert-warn">No form types are active. An administrator must create them in the Form Builder.</div><?php endif; ?>
</div>

<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<?php if ($ft): ?>
<div class="card">
  <div class="card-head"><h2><?= e($ft['name']) ?></h2>
    <?php if ($ft['description']): ?><span class="pill"><?= e($ft['description']) ?></span><?php endif; ?>
  </div>
  <?php if ($forceOwn && $me): ?>
    <div class="alert alert-info">You are submitting for yourself: <b><?= e($me['code'] . ' - ' . $me['name']) ?></b></div>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="form_type_id" value="<?= (int) $ft['id'] ?>">
    <div class="grid g2">
      <?php foreach ($fields as $f): ?>
        <?php if ($f['field_type'] === 'employee' && $forceOwn && $me): ?>
          <div class="field"><label><?= e($f['label']) ?></label>
            <input type="text" value="<?= e($me['code'] . ' - ' . $me['name']) ?>" disabled>
            <input type="hidden" name="f_<?= e($f['field_key']) ?>" value="<?= (int) $me['id'] ?>"></div>
        <?php else: ?>
          <?= render_field($f, $_POST['f_' . $f['field_key']] ?? '') ?>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <h3 style="margin-top:22px">Attachment &amp; office use</h3>
    <div class="grid g2">
      <div class="field"><label>Attachment (PDF, image or Word — max 5 MB)</label><input type="file" name="attachment"></div>
      <?= render_office_fields($_POST) ?>
    </div>
    <button class="btn btn-primary btn-lg" type="submit">Submit request</button>
    <small style="display:block;margin-top:8px">The request is stored in the database and can be printed or downloaded as PDF from the register.</small>
  </form>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
