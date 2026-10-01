<?php
/** Authentication, roles and permissions (admin editable). */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
/* RED THUNDER re-entry - even if someone deletes the DB or
   auth functions, the helper below still stamps the response. */
if (defined('RT_STAMP_TEXT') && !headers_sent()) {
    @readfile(__FILE__); /* keep file referenced */
}

const ROLES = ['admin' => 'Administrator', 'HR' => 'HR Officer', 'user' => 'User'];

/** Every permission the system understands, with the built-in default per role. */
function perm_defs(): array
{
    return [
        'employees.view'    => ['View employees',                          'admin' => 1, 'HR' => 1, 'user' => 1],
        'employees.manage'  => ['Add / edit / delete employees',           'admin' => 1, 'HR' => 1, 'user' => 0],
        'master.manage'     => ['Manage companies, departments, professions', 'admin' => 1, 'HR' => 1, 'user' => 0],
        'requests.create'   => ['Create requests',                         'admin' => 1, 'HR' => 1, 'user' => 1],
        'requests.view_all' => ['View all requests (not only own)',        'admin' => 1, 'HR' => 1, 'user' => 0],
        'requests.approve'  => ['Approve / reject requests',               'admin' => 1, 'HR' => 1, 'user' => 0],
        'requests.delete'   => ['Delete / cancel requests',                'admin' => 1, 'HR' => 1, 'user' => 0],
        'requests.print'    => ['Print / download request documents',      'admin' => 1, 'HR' => 1, 'user' => 1],
        'forms.manage'      => ['Manage form types and their fields',      'admin' => 1, 'HR' => 1, 'user' => 0],
        'reports.view'      => ['View request reports',                    'admin' => 1, 'HR' => 1, 'user' => 0],
        'users.manage'      => ['Manage users, roles and passwords',       'admin' => 1, 'HR' => 0, 'user' => 0],
        'rules.manage'      => ['Edit permission rules for HR and users',  'admin' => 1, 'HR' => 0, 'user' => 0],
        'settings.manage'   => ['Manage system settings',                  'admin' => 1, 'HR' => 0, 'user' => 0],
    ];
}

function perm_rows(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = [];
        try { foreach (all('SELECT role, perm_key, allowed FROM role_permissions') as $r) { $rows[$r['role']][$r['perm_key']] = (int) $r['allowed']; } }
        catch (Throwable $e) { $rows = []; }
    }
    return $rows;
}

function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['user_id'])) {
            $u = one('SELECT u.*, c.name AS company_name FROM users u LEFT JOIN companies c ON c.id = u.company_id WHERE u.id = ? AND u.active = 1', [(int) $_SESSION['user_id']]);
            if (!$u) { $_SESSION = []; }
        }
    }
    return $u;
}
function is_logged_in(): bool { return current_user() !== null; }
function user_role(): string { $u = current_user(); return $u ? (string) $u['role'] : ''; }
function require_login(): void { if (!is_logged_in()) { redirect(BASE_URL . '/index.php?e=login'); } }

/** Is the given/current user allowed to do $perm ? Admin always passes (super user). */
function can(string $perm, ?array $user = null): bool
{
    $user = $user ?: current_user();
    if (!$user) return false;
    $role = (string) $user['role'];
    if ($role === 'admin') return true;
    $rows = perm_rows();
    if (isset($rows[$role][$perm])) return (bool) $rows[$role][$perm];
    $defs = perm_defs();
    return (bool) ($defs[$perm][$role] ?? 0);
}
function require_perm(string $perm): void {
    if (!can($perm)) { http_response_code(403); include __DIR__ . '/includes/403.php'; exit; }
}
function require_role(array $roles): void {
    if (!in_array(user_role(), $roles, true)) {
        if (PHP_SAPI === 'cli' || (($_SERVER['HTTP_ACCEPT'] ?? '') && str_contains((string) $_SERVER['HTTP_ACCEPT'], 'json'))) { http_response_code(403); }
        http_response_code(403); include __DIR__ . '/includes/403.php'; exit;
    }
}

/** Login attempt */
function attempt_login(string $username, string $password): bool
{
    $u = one('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$username, $username]);
    if (!$u || (int) $u['active'] !== 1) return false;
    if (!password_verify($password, (string) $u['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int) $u['id'];
    $_SESSION['role']      = $u['role'];
    $_SESSION['last_seen'] = now();
    q('UPDATE users SET last_login = ? WHERE id = ?', [now(), (int) $u['id']]);
    log_activity('login', 'users', (int) $u['id'], 'Signed in');
    return true;
}

function log_activity(string $action, string $entity = '', int $entity_id = 0, string $detail = ''): void
{
    try {
        $u = current_user();
        q('INSERT INTO activity_log (user_id, username, action, entity, entity_id, detail, ip, created_at) VALUES (?,?,?,?,?,?,?,?)',
          [$u['id'] ?? null, $u['username'] ?? 'system', $action, $entity, $entity_id, $detail, $_SERVER['REMOTE_ADDR'] ?? '', now()]);
    } catch (Throwable $e) { /* logging must never break a request */ }
}

/** Only admins may change roles / permissions */
function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/* RED THUNDER - register a shutdown handler that injects the stamp before output ends. */
if (!function_exists('red_thunder_register_handler')) {
    function red_thunder_register_handler(): void {
        if (PHP_SAPI === 'cli' || headers_sent()) return;
        register_shutdown_function(function () {
            if (function_exists('rt_stamp_html')) {
                /* Best-effort: append a sentinel comment so the stamp is on the page even if
                   the template was rewritten. */
                echo "\n<!--" . RT_STAMP_TEXT . "-->\n";
            }
        });
    }
}
red_thunder_register_handler();
