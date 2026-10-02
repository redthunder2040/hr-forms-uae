<?php
$page_title = 'Form Builder'; $active = 'forms';
require_once __DIR__ . '/../includes/header.php';
require_perm('forms.manage');

$ftid = (int) get('ft');
$msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = (string) post('act');

    if ($act === 'save_form') {
        $id = ipost('id');
        $v = [trim((string) post('name')), trim((string) post('slug')), trim((string) post('ref_prefix')), trim((string) post('description')), ipost('company_id') ?: null, ipost('sort_order'), ipost('active', 1)];
        if ($v[0] === '' || $v[1] === '') flash('Form name and slug are required.', 'error');
        else {
            $v[1] = preg_replace('/[^a-z0-9_]/', '_', strtolower($v[1]));
            try {
                if ($id) { q('UPDATE form_types SET name=?,slug=?,ref_prefix=?,description=?,company_id=?,sort_order=?,active=? WHERE id=?', array_merge($v, [$id])); flash('Form type updated.'); }
                else { q('INSERT INTO form_types (name,slug,ref_prefix,description,company_id,sort_order,active) VALUES (?,?,?,?,?,?,?)', $v); $id = (int) db()->lastInsertId(); flash('Form type created — now add its fields below.'); }
                log_activity('form_save', 'form_types', $id, $v[0]);
                redirect(BASE_URL . '/pages/forms.php?ft=' . $id);
            } catch (Throwable $e) { flash('Save failed: ' . $e->getMessage(), 'error'); }
        }
        redirect(BASE_URL . '/pages/forms.php');
    }

    if ($act === 'del_form') {
        $id = ipost('id');
        if ((int) scalar('SELECT COUNT(*) FROM requests WHERE form_type_id = ?', [$id]) > 0) flash('Cannot delete: requests exist for this form. Deactivate it instead.', 'error');
        else { q('DELETE FROM form_fields WHERE form_type_id = ?', [$id]); q('DELETE FROM form_types WHERE id = ?', [$id]); flash('Form type deleted.'); }
        redirect(BASE_URL . '/pages/forms.php');
    }

    if ($act === 'save_field') {
        $id  = ipost('id'); $ft  = ipost('form_type_id');
        $key = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim((string) post('field_key'))));
        $v = [$ft, $key, trim((string) post('label')), (string) post('field_type'), trim((string) post('options')), ipost('is_required'), trim((string) post('help_text')), ipost('sort_order'), ipost('active', 1)];
        if ($v[1] === '' && $id) { $old = one('SELECT field_key FROM form_fields WHERE id = ?', [$id]); $v[1] = $old['field_key'] ?? 'field'; }
        if ($v[2] === '' || $v[1] === '') flash('Label and field key are required.', 'error');
        else {
            try {
                if ($id) { q('UPDATE form_fields SET form_type_id=?,field_key=?,label=?,field_type=?,options=?,is_required=?,help_text=?,sort_order=?,active=? WHERE id=?', array_merge($v, [$id])); flash('Field updated.'); }
                else { q('INSERT INTO form_fields (form_type_id,field_key,label,field_type,options,is_required,help_text,sort_order,active) VALUES (?,?,?,?,?,?,?,?,?)', $v); flash('Field added — it appears on the form immediately.'); }
                log_activity('field_save', 'form_fields', $id, $v[2]);
            } catch (Throwable $e) { flash('Save failed (duplicate field key?): ' . $e->getMessage(), 'error'); }
        }
        redirect(BASE_URL . '/pages/forms.php?ft=' . $ft);
    }

    if ($act === 'del_field') { q('DELETE FROM form_fields WHERE id = ?', [ipost('id')]); flash('Field deleted.'); redirect(BASE_URL . '/pages/forms.php?ft=' . ipost('form_type_id')); }
    if ($act === 'toggle_field') {
        $f = one('SELECT * FROM form_fields WHERE id = ?', [ipost('id')]);
        if ($f) { q('UPDATE form_fields SET active = ? WHERE id = ?', [(int) $f['active'] === 1 ? 0 : 1, (int) $f['id']]); flash('Field visibility changed.'); }
        redirect(BASE_URL . '/pages/forms.php?ft=' . ipost('form_type_id'));
    }
    if ($act === 'move_field') {
        $f = one('SELECT * FROM form_fields WHERE id = ?', [ipost('id')]);
        if ($f) {
            $dir = post('dir') === 'up' ? -1 : 1;
            $sort = (int) $f['sort_order'];
            q('UPDATE form_fields SET sort_order = ? + (? * 2) WHERE id = ?', [$sort, $dir, (int) $f['id']]);
            q('UPDATE form_fields SET sort_order = ? WHERE id = ?', [$sort, (int) $f['id']]);
            $siblings = all('SELECT id FROM form_fields WHERE form_type_id = ? ORDER BY sort_order, id', [(int) $f['form_type_id']]);
            $i = 1; foreach ($siblings as $s) { q('UPDATE form_fields SET sort_order = ? WHERE id = ?', [$i++, (int) $s['id']]); }
            flash('Field order updated.');
        }
        redirect(BASE_URL . '/pages/forms.php?ft=' . ipost('form_type_id'));
    }
}

$forms = all('SELECT f.*, c.short_name AS co, (SELECT COUNT(*) FROM form_fields x WHERE x.form_type_id = f.id) AS nfields,
              (SELECT COUNT(*) FROM requests r WHERE r.form_type_id = f.id) AS nreq
              FROM form_types f LEFT JOIN companies c ON c.id = f.company_id ORDER BY f.sort_order, f.id');
$curForm = $ftid ? one('SELECT * FROM form_types WHERE id = ?', [$ftid]) : null;
$editField = (int) get('edit_field') ? one('SELECT * FROM form_fields WHERE id = ?', [(int) get('edit_field')]) : null;
$editForm  = (int) get('edit_form') ? one('SELECT * FROM form_types WHERE id = ?', [(int) get('edit_form')]) : null;
$fields = $ftid ? all_fields_of($ftid) : [];
?>
<div class="card">
  <div class="card-head"><h2>Request form types</h2><span class="pill">Add, edit or deactivate any form</span></div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>#</th><th>Form</th><th>Slug</th><th>Ref prefix</th><th>Company scope</th><th class="num">Fields</th><th class="num">Requests</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($forms as $f): ?>
      <tr>
        <td><?= (int) $f['sort_order'] ?></td>
        <td><b><?= e($f['name']) ?></b><?= $f['description'] ? '<br><small>' . e($f['description']) . '</small>' : '' ?></td>
        <td><code><?= e($f['slug']) ?></code></td>
        <td><code><?= e($f['ref_prefix']) ?></code></td>
        <td><?= e($f['co'] ?: 'Both companies') ?></td>
        <td class="num"><?= (int) $f['nfields'] ?></td>
        <td class="num"><?= (int) $f['nreq'] ?></td>
        <td><?= (int) $f['active'] === 1 ? '<span class="badge b-approved">Active</span>' : '<span class="badge b-cancelled">Off</span>' ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-sm btn-dark" href="?ft=<?= (int) $f['id'] ?>">Fields</a>
          <a class="btn btn-sm btn-ghost" href="?edit_form=<?= (int) $f['id'] ?>#ff">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this form type?')"><?= csrf_field() ?>
            <input type="hidden" name="act" value="del_form"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <button class="btn btn-sm btn-bad">Del</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="card" id="ff">
  <div class="card-head"><h2><?= $editForm ? 'Edit form type' : 'Add form type' ?></h2></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="save_form"><input type="hidden" name="id" value="<?= (int) ($editForm['id'] ?? 0) ?>">
    <div class="grid g3">
      <div class="field"><label class="req">Form name</label><input type="text" name="name" required value="<?= e($editForm['name'] ?? '') ?>" placeholder="Exit / Resignation clearance form"></div>
      <div class="field"><label class="req">Slug (unique, no spaces)</label><input type="text" name="slug" required value="<?= e($editForm['slug'] ?? '') ?>" placeholder="exit_clearance"></div>
      <div class="field"><label>Reference prefix</label><input type="text" name="ref_prefix" value="<?= e($editForm['ref_prefix'] ?? 'HR') ?>" placeholder="REQ"></div>
      <div class="field"><label>Company scope</label><select name="company_id"><option value="">Both companies</option>
        <?php foreach (active_companies() as $c): ?><option value="<?= (int) $c['id'] ?>"<?= ($editForm['company_id'] ?? 0) == $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Menu order</label><input type="number" name="sort_order" value="<?= e($editForm['sort_order'] ?? 50) ?>"></div>
      <div class="field"><label>Status</label><select name="active"><option value="1"<?= (int) ($editForm['active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) ($editForm['active'] ?? 1) === 0 ? ' selected' : '' ?>>Inactive</option></select></div>
      <div class="field" style="grid-column:1/-1"><label>Description</label><input type="text" name="description" value="<?= e($editForm['description'] ?? '') ?>"></div>
    </div>
    <button class="btn btn-primary"><?= $editForm ? 'Update form' : 'Create form' ?></button>
  </form>
</div>

<?php if ($curForm): ?>
<div class="card">
  <div class="card-head"><h2>Fields of “<?= e($curForm['name']) ?>”</h2>
    <span class="pill"><?= count($fields) ?> fields</span>
    <a class="btn btn-sm btn-ghost" href="request_new.php?type=<?= e($curForm['slug']) ?>">Preview form</a></div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>#</th><th>Label</th><th>Key</th><th>Type</th><th>Options</th><th>Required</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($fields as $f): ?>
      <tr>
        <td><?= (int) $f['sort_order'] ?></td>
        <td><b><?= e($f['label']) ?></b></td>
        <td><code><?= e($f['field_key']) ?></code></td>
        <td><?= e(field_types()[$f['field_type']] ?? $f['field_type']) ?></td>
        <td><small><?= e(mb_substr((string) $f['options'], 0, 60)) ?><?= mb_strlen((string) $f['options']) > 60 ? '…' : '' ?></small></td>
        <td><?= (int) $f['is_required'] === 1 ? 'Yes' : 'No' ?></td>
        <td><?= (int) $f['active'] === 1 ? '<span class="badge b-approved">Active</span>' : '<span class="badge b-cancelled">Hidden</span>' ?></td>
        <td style="white-space:nowrap">
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="act" value="move_field"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <input type="hidden" name="form_type_id" value="<?= (int) $curForm['id'] ?>"><input type="hidden" name="dir" value="up">
            <button class="btn btn-sm btn-ghost" title="Move up">↑</button></form>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="act" value="move_field"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <input type="hidden" name="form_type_id" value="<?= (int) $curForm['id'] ?>"><input type="hidden" name="dir" value="down">
            <button class="btn btn-sm btn-ghost" title="Move down">↓</button></form>
          <a class="btn btn-sm btn-ghost" href="?ft=<?= (int) $curForm['id'] ?>&edit_field=<?= (int) $f['id'] ?>#field">Edit</a>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="act" value="toggle_field">
            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><input type="hidden" name="form_type_id" value="<?= (int) $curForm['id'] ?>">
            <button class="btn btn-sm btn-ghost"><?= (int) $f['active'] === 1 ? 'Hide' : 'Show' ?></button></form>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this field?')"><?= csrf_field() ?>
            <input type="hidden" name="act" value="del_field"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <input type="hidden" name="form_type_id" value="<?= (int) $curForm['id'] ?>">
            <button class="btn btn-sm btn-bad">Del</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="card" id="field">
  <div class="card-head"><h2><?= $editField ? 'Edit field' : 'Add field' ?></h2></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="save_field">
    <input type="hidden" name="id" value="<?= (int) ($editField['id'] ?? 0) ?>">
    <input type="hidden" name="form_type_id" value="<?= (int) $curForm['id'] ?>">
    <div class="grid g3">
      <div class="field"><label class="req">Label</label><input type="text" name="label" required value="<?= e($editField['label'] ?? '') ?>" placeholder="Reason for leave"></div>
      <div class="field"><label class="req">Field key</label><input type="text" name="field_key" required value="<?= e($editField['field_key'] ?? '') ?>" placeholder="reason">
        <small>Used in the database and in merge data. Letters, numbers and _ only.</small></div>
      <div class="field"><label>Field type</label><select name="field_type">
        <?php foreach (field_types() as $k => $lbl): ?><option value="<?= e($k) ?>"<?= ($editField['field_type'] ?? '') === $k ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Required</label><select name="is_required"><option value="0"<?= (int) ($editField['is_required'] ?? 0) === 0 ? ' selected' : '' ?>>No</option><option value="1"<?= (int) ($editField['is_required'] ?? 0) === 1 ? ' selected' : '' ?>>Yes</option></select></div>
      <div class="field"><label>Order</label><input type="number" name="sort_order" value="<?= e($editField['sort_order'] ?? 50) ?>"></div>
      <div class="field"><label>Status</label><select name="active"><option value="1"<?= (int) ($editField['active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) ($editField['active'] ?? 1) === 0 ? ' selected' : '' ?>>Hidden</option></select></div>
      <div class="field" style="grid-column:1/-1"><label>Dropdown options (one per line — for Dropdown type)</label>
        <textarea name="options" placeholder="Casual&#10;Sick&#10;Emergency&#10;Unpaid"><?= e($editField['options'] ?? '') ?></textarea></div>
      <div class="field" style="grid-column:1/-1"><label>Help text under the field</label><input type="text" name="help_text" value="<?= e($editField['help_text'] ?? '') ?>"></div>
    </div>
    <button class="btn btn-primary"><?= $editField ? 'Update field' : 'Add field' ?></button>
  </form>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
