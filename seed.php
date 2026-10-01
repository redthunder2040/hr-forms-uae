<?php
/**
 * Seeds reference data, request forms and the employee master database.
 * Run automatically by run.php, or on its own:  php seed.php
 * Safe to run twice - existing records are never duplicated.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function seed_companies(): void
{
    $defaults = [
        ['Company 1', 'CMP1', 'United Arab Emirates'],
        ['Company 2', 'CMP2', 'United Arab Emirates'],
    ];
    foreach ($defaults as $c) {
        if (!one('SELECT id FROM companies WHERE name = ?', [$c[0]])) {
            q('INSERT INTO companies (name, short_name, address, active, created_at) VALUES (?,?,?,1,?)', [$c[0], $c[1], $c[2], now()]);
        }
    }
    // Fallback: allow matching the short names used in the source spreadsheet
    foreach ([['Company 1', 'CMP1'], ['Company 2', 'CMP2']] as $s) {
        if (!one('SELECT id FROM companies WHERE short_name = ?', [$s[1]])) {
            q('INSERT INTO companies (name, short_name, active, created_at) VALUES (?,?,1,?)', [$s[0], $s[1], now()]);
        }
    }
}

function seed_departments(): void
{
    foreach ([['Labour', 'LAB'], ['Staff', 'STF'], ['Administration', 'ADM'], ['Accounts', 'ACC'], ['Stores', 'STR'], ['Workshop', 'WSH']] as $d) {
        if (!one('SELECT id FROM departments WHERE name = ?', [$d[0]])) {
            q('INSERT INTO departments (name, code, active) VALUES (?,?,1)', $d);
        }
    }
}

function seed_professions(): void
{
    foreach (['Helper', 'Engineer', 'Driver', 'DC', 'Electrician', 'Plumber', 'Carpenter', 'Mason', 'Steel Fixer', 'Foreman', 'Supervisor', 'Storekeeper', 'Accountant', 'Secretary', 'HR Officer', 'Safety Officer', 'Cleaner', 'Watchman', 'Operator', 'Welder'] as $p) {
        if (!one('SELECT id FROM professions WHERE name = ?', [$p])) q('INSERT INTO professions (name) VALUES (?)', [$p]);
    }
}

function seed_forms(): void
{
    $forms = [
        ['Emergency leave request', 'emergency_leave', 'ELR', 'Emergency / unpaid leave application', 10, [
            ['employee',       'Employee',                'employee', 1, 10, ''],
            ['company',        'Company',                 'company', 1, 20, ''],
            ['department',     'Department',              'department', 0, 30, ''],
            ['leave_type',     'Type of emergency leave',  'select', 1, 40, "Emergency\nUnpaid\nSick\nCasual"],
            ['from_date',      'From date',                'date', 1, 50, ''],
            ['to_date',        'To date',                  'date', 1, 60, ''],
            ['total_days',     'Number of days',           'number', 0, 70, 'Calculated automatically from the dates'],
            ['reason',         'Reason / justification',   'textarea', 1, 80, ''],
            ['contact_during_leave', 'Contact number during leave', 'text', 0, 90, ''],
            ['handover_to',    'Work handed over to',      'text', 0, 100, ''],
        ]],
        ['Leave salary request', 'leave_salary', 'LSR', 'Payment of salary for the leave period', 20, [
            ['employee',       'Employee',              'employee', 1, 10, ''],
            ['company',        'Company',               'company', 1, 20, ''],
            ['leave_type',     'Type of leave',         'select', 1, 30, "Annual leave\nEmergency leave\nFinal settlement"],
            ['leave_from',     'Leave from',            'date', 1, 40, ''],
            ['leave_to',       'Leave to',              'date', 1, 50, ''],
            ['leave_days',     'Leave days',            'number', 0, 60, ''],
            ['basic_salary',   'Basic salary (AED)',    'money', 1, 70, 'Filled from the employee master database'],
            ['allowance',      'Allowance (AED)',       'money', 0, 80, ''],
            ['leave_salary_amount', 'Leave salary requested (AED)', 'money', 1, 90, ''],
            ['payment_mode',   'Payment mode',          'select', 0, 100, "Cash\nBank transfer\nCheque"],
            ['bank_iban',      'Bank / IBAN',           'text', 0, 110, ''],
            ['ticket_request', 'Air ticket required',   'select', 0, 120, "Yes\nNo"],
            ['destination',    'Destination country',   'text', 0, 130, ''],
            ['remarks',        'Justification',         'textarea', 0, 140, ''],
        ]],
        ['Vacation request', 'vacation', 'VAC', 'Annual vacation / leave application', 30, [
            ['employee',   'Employee',          'employee', 1, 10, ''],
            ['company',    'Company',           'company', 1, 20, ''],
            ['vacation_type', 'Vacation type',  'select', 1, 30, "Annual\nUnpaid\nCompassionate\nMaternity\nHajj"],
            ['from_date',  'From date',         'date', 1, 40, ''],
            ['to_date',    'To date',           'date', 1, 50, ''],
            ['total_days', 'Number of days',    'number', 0, 60, ''],
            ['last_leave_date', 'Date of last vacation', 'date', 0, 70, ''],
            ['air_ticket', 'Air ticket',        'select', 0, 80, "Company paid\nEmployee paid\nNot required"],
            ['ticket_cost', 'Air ticket cost (AED)', 'money', 0, 90, ''],
            ['destination', 'Destination',      'text', 0, 100, ''],
            ['address_abroad', 'Address abroad', 'textarea', 0, 110, ''],
            ['handover_to', 'Work handed over to', 'text', 0, 120, ''],
        ]],
        ['Salary increment request', 'salary_increment', 'SIR', 'Increment / salary review request', 40, [
            ['employee',          'Employee',            'employee', 1, 10, ''],
            ['company',           'Company',             'company', 1, 20, ''],
            ['current_basic',     'Current basic (AED)', 'money', 1, 30, ''],
            ['current_allowance', 'Current allowance (AED)', 'money', 0, 40, ''],
            ['current_total',     'Current total (AED)', 'money', 0, 50, ''],
            ['increment_amount',  'Increment amount (AED)', 'money', 1, 60, ''],
            ['increment_percent', 'Increment %',         'number', 0, 70, ''],
            ['new_total',         'New total salary (AED)', 'money', 0, 80, ''],
            ['effective_date',    'Effective from',      'date', 1, 90, ''],
            ['justification',     'Justification / performance note', 'textarea', 1, 100, ''],
        ]],
        ['Advance request', 'advance', 'ADV', 'Salary advance / loan request', 50, [
            ['employee',       'Employee',           'employee', 1, 10, ''],
            ['company',        'Company',            'company', 1, 20, ''],
            ['advance_amount', 'Advance amount (AED)', 'money', 1, 30, ''],
            ['reason',         'Reason',             'textarea', 1, 40, ''],
            ['repayment_months', 'Repayment period (months)', 'number', 1, 50, ''],
            ['monthly_deduction', 'Monthly deduction (AED)', 'money', 0, 60, ''],
            ['start_month',    'Deduction starts',   'text', 0, 70, 'e.g. November 2026'],
            ['request_date',   'Required by',        'date', 0, 80, ''],
        ]],
        ['Cancellation letter request', 'cancellation_letter', 'CLR', 'Visa / labour cancellation letter', 60, [
            ['employee',        'Employee',             'employee', 1, 10, ''],
            ['company',         'Company',              'company', 1, 20, ''],
            ['passport_no',     'Passport number',      'text', 1, 30, ''],
            ['nationality',     'Nationality',          'text', 0, 40, ''],
            ['cancellation_type', 'Type of cancellation', 'select', 1, 50, "Resignation\nAbsconding\nContract completion\nTermination\nEmployee request"],
            ['last_working_day', 'Last working day',    'date', 1, 60, ''],
            ['notice_period',   'Notice period served', 'text', 0, 70, '30 days as per UAE Labour Law'],
            ['salary_cleared',  'Final salary settled', 'select', 0, 80, "Yes\nNo"],
            ['reason',          'Reason / remarks',     'textarea', 1, 90, ''],
        ]],
        ['Offer letter', 'offer_letter', 'OFL', 'Employment offer (uses the standard offer letter template)', 70, [
            ['candidate_name',    'Candidate name',        'text', 1, 10, ''],
            ['employee',          'Existing employee (if any)', 'employee', 0, 20, 'Leave empty for an external candidate'],
            ['position',          'Position / title',      'text', 1, 30, ''],
            ['company',           'Company',               'company', 1, 40, ''],
            ['start_date',        'Start date',            'date', 1, 50, ''],
            ['termination_rules', 'Termination rules',     'textarea', 0, 60, 'Default: 30 days advance notice from the date of joining'],
            ['total_salary',      'Total salary (AED) per month', 'money', 1, 70, ''],
            ['benefits',          'Other benefits',        'textarea', 0, 80, 'Default: as per the UAE Labour Law'],
            ['ref_number',        'Letter / number reference', 'text', 0, 90, ''],
            ['accept_date',       'Acceptance date',       'date', 0, 100, ''],
            ['signature',         'Prepared / signed by',  'text', 0, 110, ''],
        ]],
        ['Salary certificate', 'salary_certificate', 'SLC', 'To-whom-it-may-concern salary certificate', 80, [
            ['employee',    'Employee',            'employee', 1, 10, ''],
            ['company',     'Company',             'company', 1, 20, ''],
            ['employee_name', 'Name as printed',   'text', 1, 30, ''],
            ['employee_name_ar', 'Name in Arabic', 'text', 0, 40, ''],
            ['nationality', 'Nationality',         'text', 1, 50, ''],
            ['passport_no', 'Passport number',     'text', 1, 60, ''],
            ['department',  'Department',          'text', 1, 70, ''],
            ['join_date',   'Employed since',      'date', 1, 80, ''],
            ['position',    'Position / designation', 'text', 1, 90, ''],
            ['gross_salary', 'Gross monthly salary (AED)', 'money', 1, 100, ''],
            ['to_whom',     'Addressed to',        'text', 0, 110, 'Default: To Whom It May Concern'],
            ['purpose',     'Purpose of certificate', 'text', 0, 120, 'Bank, embassy, visa, etc.'],
        ]],
    ];

    foreach ($forms as $f) {
        [$name, $slug, $prefix, $desc, $order, $fields] = $f;
        $row = one('SELECT id FROM form_types WHERE slug = ?', [$slug]);
        if (!$row) {
            q('INSERT INTO form_types (name, slug, ref_prefix, description, sort_order, active, created_at) VALUES (?,?,?,?,?,1,?)',
              [$name, $slug, $prefix, $desc, $order, now()]);
            $ftid = (int) db()->lastInsertId();
        } else {
            $ftid = (int) $row['id'];
        }
        foreach ($fields as $ff) {
            $key      = (string) $ff[0];
            $label    = (string) $ff[1];
            $type     = (string) $ff[2];
            $required = (int) $ff[3];
            $sort     = (int) $ff[4];
            /* for dropdowns element 5 holds the option list, element 6 the help text;
               for all other types element 5 is the help text */
            if ($type === 'select') { $options = (string) ($ff[5] ?? ''); $help = (string) ($ff[6] ?? ''); }
            else { $options = ''; $help = (string) ($ff[5] ?? ''); }
            if (!one('SELECT id FROM form_fields WHERE form_type_id = ? AND field_key = ?', [$ftid, $key])) {
                q('INSERT INTO form_fields (form_type_id, field_key, label, field_type, options, is_required, help_text, sort_order, active)
                   VALUES (?,?,?,?,?,?,?,?,1)',
                  [$ftid, $key, $label, $type, $options, $required, $help, $sort]);
            }
        }
    }
}

function seed_settings(): void
{
    $defaults = [
        'org_name'          => 'HR',
        'signatory_name'    => 'Authorised Signatory',
        'signatory_title'   => 'Deputy General Manager',
        'offer_termination' => '30 days advance notice from the date of joining.',
        'offer_benefits'    => 'As per the UAE Labour Law',
        'offer_number'      => '114587545463',
        'doc_footer_note'   => '',
    ];
    foreach ($defaults as $k => $v) { if (setting($k, null) === null) set_setting($k, $v); }
}

function seed_users(): void
{
    $users = [
        ['admin', 'System Administrator', 'admin@example.com',  'admin', 'Admin@123',   1],
        ['hr',    'HR Officer',           'hr@example.com',     'HR',    'Hr@12345',    1],
        ['user',  'Employee Self Service','user@example.com',   'user',  'User@12345',  0],
    ];
    foreach ($users as $u) {
        if (!one('SELECT id FROM users WHERE username = ?', [$u[0]])) {
            q('INSERT INTO users (username, full_name, email, role, all_companies, active, password_hash, created_at)
               VALUES (?,?,?,?,?,?,?,?)',
              [$u[0], $u[1], $u[2], $u[3], (int) $u[5], 1, password_hash((string) $u[4], PASSWORD_DEFAULT), now()]);
        }
    }
    /* grant the default HR permissions explicitly so the rules page shows them */
    $defs = perm_defs();
    foreach (['HR', 'user'] as $role) {
        foreach ($defs as $key => $meta) {
            if (!one('SELECT id FROM role_permissions WHERE role = ? AND perm_key = ?', [$role, $key])) {
                q('INSERT INTO role_permissions (role, perm_key, allowed) VALUES (?,?,?)', [$role, $key, (int) ($meta[$role] ?? 0)]);
            }
        }
    }
}

/** Import the employee master database from data/employees.json (or the uploaded spreadsheet). */
function seed_employees(): int
{
    $file = __DIR__ . '/data/employees.json';
    if (!is_file($file)) return 0;
    $rows = json_decode((string) file_get_contents($file), true);
    if (!is_array($rows)) return 0;

    $companyMap = [];
    foreach (all('SELECT id, name, short_name FROM companies') as $c) {
        $companyMap[strtoupper((string) $c['name'])] = (int) $c['id'];
        $companyMap[strtoupper((string) $c['short_name'])] = (int) $c['id'];
    }
    $deptMap = [];
    foreach (all('SELECT id, name FROM departments') as $d) $deptMap[strtolower((string) $d['name'])] = (int) $d['id'];

    $imported = 0;
    foreach ($rows as $r) {
        $code = trim((string) ($r['code'] ?? ''));
        $name = trim((string) ($r['name'] ?? ''));
        if ($code === '' || $name === '') continue;
        if (one('SELECT id FROM employees WHERE code = ?', [$code])) continue;

        $cname = strtoupper(trim((string) ($r['company'] ?? '')));
        $cid = $companyMap[$cname] ?? (($companyMap['CMP1'] ?? null) ?: ($companyMap['CMP2'] ?? null));
        if (!$cid) $cid = (int) (scalar('SELECT id FROM companies ORDER BY id LIMIT 1') ?: 0) ?: null;

        $dname = strtolower(trim((string) ($r['dept'] ?? '')));
        $did = $deptMap[$dname] ?? null;
        if (!$did && $dname !== '') {
            q('INSERT INTO departments (name, code, active) VALUES (?,?,1)', [ucfirst($dname), strtoupper(substr($dname, 0, 3))]);
            $did = (int) db()->lastInsertId();
            $deptMap[$dname] = $did;
        }
        $prof = trim((string) ($r['prof'] ?? ''));
        if ($prof !== '' && !one('SELECT id FROM professions WHERE name = ?', [$prof])) q('INSERT INTO professions (name) VALUES (?)', [$prof]);

        $basic = (float) ($r['basic'] ?? 0);
        $allow = (float) ($r['allow'] ?? 0);
        $total = (float) ($r['total'] ?? ($basic + $allow));
        q('INSERT INTO employees (code,name,company_id,department_id,profession,sponsor,joining_date,basic,allowance,total,active,created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,1,?)',
          [$code, $name, $cid, $did, $prof, trim((string) ($r['sponsor'] ?? '')), ($r['join'] ?: null), $basic, $allow, $total, now()]);
        $imported++;
    }
    return $imported;
}

/* ---------------- run ---------------- */
if (PHP_SAPI === 'cli' || (isset($_GET['go']) && $_GET['go'] === '1') || defined('SEED_INCLUDED')) {
    seed_companies();
    seed_departments();
    seed_professions();
    seed_forms();
    seed_settings();
    seed_users();
    $n = seed_employees();
    if (PHP_SAPI === 'cli') {
        echo "Companies: " . scalar('SELECT COUNT(*) FROM companies') . "\n";
        echo "Departments: " . scalar('SELECT COUNT(*) FROM departments') . "\n";
        echo "Form types: " . scalar('SELECT COUNT(*) FROM form_types') . " | fields: " . scalar('SELECT COUNT(*) FROM form_fields') . "\n";
        echo "Users: " . scalar('SELECT COUNT(*) FROM users') . "\n";
        echo "Employees imported now: $n | total employees: " . scalar('SELECT COUNT(*) FROM employees') . "\n";
    }
}
