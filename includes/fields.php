<?php
/** Dynamic form fields engine + employee picker + request filters. */
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';

function field_types(): array
{
    return ['text' => 'Text', 'textarea' => 'Long text', 'number' => 'Number', 'money' => 'Amount (AED)',
            'date' => 'Date', 'select' => 'Dropdown', 'employee' => 'Employee picker',
            'company' => 'Company picker', 'department' => 'Department picker'];
}

function form_type_by_id(int $id): ?array { return one('SELECT * FROM form_types WHERE id = ?', [$id]); }
function form_type_by_slug(string $slug): ?array { return one('SELECT * FROM form_types WHERE slug = ? LIMIT 1', [$slug]); }
function fields_of(int $ftid): array     { return all('SELECT * FROM form_fields WHERE form_type_id = ? AND active = 1 ORDER BY sort_order, id', [$ftid]); }
function all_fields_of(int $ftid): array { return all('SELECT * FROM form_fields WHERE form_type_id = ? ORDER BY sort_order, id', [$ftid]); }

function companies(): array { return all('SELECT * FROM companies ORDER BY active DESC, name'); }
function active_companies(): array { return all('SELECT * FROM companies WHERE active = 1 ORDER BY id'); }
function departments(): array { return all('SELECT * FROM departments ORDER BY active DESC, name'); }
function professions(): array { return all('SELECT * FROM professions ORDER BY name'); }

/** The company ids this user is restricted to (empty array = all companies). */
function scope_company_ids(): array
{
    $u = current_user();
    if (!$u) return [];
    if (!empty($u['all_companies']) || can('requests.view_all')) return [];
    return $u['company_id'] ? [(int) $u['company_id']] : [];
}

function employee_list(?int $companyId = null): array
{
    $sql = 'SELECT e.id, e.code, e.name, e.name_ar, e.company_id, e.department_id, e.profession, e.basic, e.allowance, e.total, e.joining_date, e.passport_no, e.nationality, e.designation, c.name AS company_name, d.name AS dep_name
            FROM employees e LEFT JOIN companies c ON c.id = e.company_id LEFT JOIN departments d ON d.id = e.department_id WHERE e.active = 1';
    $p = [];
    $scope = scope_company_ids();
    if ($companyId) { $sql .= ' AND e.company_id = ?'; $p[] = $companyId; }
    elseif ($scope) { $sql .= ' AND e.company_id IN (' . implode(',', array_fill(0, count($scope), '?')) . ')'; $p = array_merge($p, $scope); }
    $sql .= ' ORDER BY CAST(e.code AS UNSIGNED), e.name';
    return all($sql, $p);
}

function my_employee(): ?array
{
    $u = current_user();
    return ($u && $u['employee_id']) ? one('SELECT * FROM employees WHERE id = ?', [(int) $u['employee_id']]) : null;
}

function option_list(array $f): array
{
    $raw = (string) ($f['options'] ?? '');
    if ($raw === '') return [];
    if (str_starts_with(trim($raw), '[')) { $a = json_decode($raw, true); return is_array($a) ? $a : []; }
    return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw))));
}

/* ------------------------------------------------------------------ */
/* employee picker: works without JS, improves with JS                */
/* ------------------------------------------------------------------ */
function employee_select(string $name, int $selected = 0, bool $required = false, ?int $companyId = null): string
{
    static $n = 0;
    $n++;
    $sid  = 'emp_' . $n . '_' . preg_replace('/\W+/', '_', $name);
    $emps = employee_list($companyId);
    $h  = '<div class="emp-wrap">';
    $h .= '<input type="text" class="emp-filter" autocomplete="off" placeholder="Filter employees by name or code…" oninput="filterEmp(this)">';
    $h .= '<select id="' . $sid . '" name="' . e($name) . '"' . ($required ? ' required' : '') . '>';
    $h .= '<option value="">— select employee —</option>';
    foreach ($emps as $emp) {
        $lbl = $emp['code'] . ' - ' . $emp['name'] . ($emp['profession'] ? ' (' . $emp['profession'] . ')' : '');
        $h .= '<option value="' . (int) $emp['id'] . '"'
            . ' data-search="' . e(strtolower($emp['code'] . ' ' . $emp['name'] . ' ' . $emp['profession'])) . '"'
            . ' data-basic="' . e((string) $emp['basic']) . '" data-allowance="' . e((string) $emp['allowance']) . '"'
            . ' data-total="' . e((string) $emp['total']) . '" data-profession="' . e((string) $emp['profession']) . '"'
            . ' data-department="' . e((string) ($emp['dep_name'] ?? '')) . '" data-passport="' . e((string) $emp['passport_no']) . '"'
            . ' data-nationality="' . e((string) $emp['nationality']) . '" data-code="' . e((string) $emp['code']) . '"'
            . ($selected === (int) $emp['id'] ? ' selected' : '') . '>' . e($lbl) . '</option>';
    }
    return $h . '</select></div>';
}

/* ------------------------------------------------------------------ */
/* render one field                                                    */
/* ------------------------------------------------------------------ */
function render_field(array $f, $value = null): string
{
    $name = 'f_' . $f['field_key'];
    $id   = 'fl_' . $f['field_key'];
    $req  = ((int) ($f['is_required'] ?? 0) === 1) ? ' required' : '';
    $h  = '<div class="field"><label for="' . $id . '" class="' . ($req ? 'req' : '') . '">' . e($f['label']) . '</label>';
    $v  = is_array($value) ? '' : (string) ($value ?? '');
    switch ($f['field_type']) {
        case 'textarea':
            $h .= '<textarea id="' . $id . '" name="' . e($name) . '"' . $req . '>' . e($v) . '</textarea>';
            break;
        case 'select':
            $opts = option_list($f);
            $h .= '<select id="' . $id . '" name="' . e($name) . '"' . $req . '><option value="">— select —</option>';
            foreach ($opts as $o) { $h .= '<option value="' . e($o) . '"' . ((string) $o === $v ? ' selected' : '') . '>' . e($o) . '</option>'; }
            $h .= '</select>';
            break;
        case 'money':
        case 'number':
            $h .= '<input type="number" step="' . ($f['field_type'] === 'money' ? '0.01' : 'any') . '" min="0" id="' . $id . '" name="' . e($name) . '" value="' . e($v) . '" data-key="' . e($f['field_key']) . '"' . $req . '>';
            break;
        case 'date':
            $h .= '<input type="date" id="' . $id . '" name="' . e($name) . '" value="' . e($v) . '" data-key="' . e($f['field_key']) . '"' . $req . '>';
            break;
        case 'employee':
            $h .= employee_select($name, (int) $v, $req === ' required');
            break;
        case 'company':
            $h .= '<select id="' . $id . '" name="' . e($name) . '"' . $req . '><option value="">— select —</option>';
            foreach (active_companies() as $c) { $h .= '<option value="' . (int) $c['id'] . '"' . ((string) $c['id'] === $v ? ' selected' : '') . '>' . e($c['name']) . '</option>'; }
            $h .= '</select>';
            break;
        case 'department':
            $h .= '<select id="' . $id . '" name="' . e($name) . '"' . $req . '><option value="">— select —</option>';
            foreach (departments() as $d) { if ((int) $d['active'] !== 1) continue; $h .= '<option value="' . (int) $d['id'] . '"' . ((string) $d['id'] === $v ? ' selected' : '') . '>' . e($d['name']) . '</option>'; }
            $h .= '</select>';
            break;
        default:
            $h .= '<input type="text" id="' . $id . '" name="' . e($name) . '" value="' . e($v) . '" data-key="' . e($f['field_key']) . '"' . $req . '>';
    }
    if (!empty($f['help_text'])) $h .= '<small>' . e($f['help_text']) . '</small>';
    return $h . '</div>';
}

function render_fields(array $fields, array $vals = []): string
{
    $h = '';
    foreach ($fields as $f) { $h .= render_field($f, $vals[$f['field_key']] ?? ''); }
    return $h;
}

/** Office-use block appended to every request (fixed, always available). */
function render_office_fields(array $r = []): string
{
    $h  = '<div class="field"><label for="office_ref">Office reference</label><input type="text" id="office_ref" name="office_ref" value="' . e($r['office_ref'] ?? '') . '"></div>';
    $h .= '<div class="field"><label for="approved_salary">Approved / certified amount (AED)</label><input type="number" step="0.01" min="0" id="approved_salary" name="approved_salary" value="' . e($r['approved_salary'] ?? '') . '"></div>';
    $h .= '<div class="field"><label for="payment_date">Payment / effective date</label><input type="date" id="payment_date" name="payment_date" value="' . e($r['payment_date'] ?? '') . '"></div>';
    $h .= '<div class="field"><label for="remarks">Remarks</label><textarea id="remarks" name="remarks">' . e($r['remarks'] ?? '') . '</textarea></div>';
    return $h;
}

/** Collect submitted dynamic values. */
function collect_fields(array $fields, array $src): array
{
    $data = [];
    $empId = 0;
    foreach ($fields as $f) {
        $key  = $f['field_key'];
        $name = 'f_' . $key;
        $v    = $src[$name] ?? '';
        if (is_array($v)) $v = implode(', ', $v);
        $v = trim((string) $v);
        if ($v === '') continue;
        if (\mb_strlen($v) > 5000) $v = \mb_substr($v, 0, 5000);
        $data[$key] = $v;
        if ($f['field_type'] === 'employee' && ctype_digit($v)) $empId = (int) $v;
    }
    return [$data, $empId];
}

/** Total salary: explicit field, else the sum of money fields. */
function total_salary_from(array $fields, array $data): float
{
    if (isset($data['total_salary']) && is_numeric($data['total_salary'])) return (float) $data['total_salary'];
    $sum = 0.0;
    foreach ($fields as $f) {
        if ($f['field_type'] === 'money' && isset($data[$f['field_key']]) && is_numeric($data[$f['field_key']])
            && !in_array($f['field_key'], ['advance_amount', 'monthly_deduction', 'leave_salary_amount', 'advance_deduction', 'air_ticket_cost', 'increment_amount'], true)) {
            $sum += (float) $data[$f['field_key']];
        }
    }
    return $sum;
}

function missing_required(array $fields, array $data): array
{
    $miss = [];
    foreach ($fields as $f) {
        if ((int) $f['is_required'] === 1 && (string) ($data[$f['field_key']] ?? '') === '') $miss[] = $f['label'];
    }
    return $miss;
}

/** Shared request filter builder used by the register and the reports page. */
function build_request_filter(array $f, ?string $forUser = null): array
{
    $w = ['1 = 1']; $p = [];
    if (!can('requests.view_all')) { $w[] = 'r.created_by = ?'; $p[] = (int) (current_user()['id'] ?? 0); }
    $scope = scope_company_ids();
    if ($scope) { $w[] = 'r.company_id IN (' . implode(',', array_fill(0, count($scope), '?')) . ')'; $p = array_merge($p, $scope); }
    if (!empty($f['company_id'])) { $w[] = 'r.company_id = ?'; $p[] = (int) $f['company_id']; }
    if (!empty($f['form_type_id'])) { $w[] = 'r.form_type_id = ?'; $p[] = (int) $f['form_type_id']; }
    if (!empty($f['status'])) { $w[] = 'r.status = ?'; $p[] = $f['status']; }
    if (!empty($f['employee_id'])) { $w[] = 'r.employee_id = ?'; $p[] = (int) $f['employee_id']; }
    if (!empty($f['from'])) { $w[] = 'DATE(r.created_at) >= ?'; $p[] = $f['from']; }
    if (!empty($f['to'])) { $w[] = 'DATE(r.created_at) <= ?'; $p[] = $f['to']; }
    if (!empty($f['q'])) {
        $w[] = '(r.ref_no LIKE ? OR r.request_no LIKE ? OR e.name LIKE ? OR e.code LIKE ? OR r.subject LIKE ?)';
        $like = '%' . trim((string) $f['q']) . '%';
        array_push($p, $like, $like, $like, $like, $like);
    }
    return [implode(' AND ', $w), $p];
}

function upload_attachment(string $fieldName = 'attachment'): ?string
{
    if (empty($_FILES[$fieldName]['name']) || ($_FILES[$fieldName]['error'] ?? 1) !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$fieldName];
    if ($f['size'] > 5 * 1024 * 1024) return null;
    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx'], true)) return null;
    $new = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);
    return move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $new) ? $new : null;
}

function status_badge(string $s): string
{
    $map = ['Waiting' => 'b-waiting', 'Approved' => 'b-approved', 'Rejected' => 'b-rejected', 'Cancelled' => 'b-cancelled'];
    return '<span class="badge ' . ($map[$s] ?? 'b-info') . '">' . e($s) . '</span>';
}
