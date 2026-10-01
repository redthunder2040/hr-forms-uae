<?php
/** Database layer + small helpers (PDO / MySQL, prepared statements only). */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '+04:00'");
        } catch (PDOException $ex) {
            http_response_code(500);
            echo '<div style="font-family:system-ui;padding:24px;max-width:720px;margin:40px auto;border:1px solid #f3c2c2;background:#fff5f5">'
               . '<h2 style="color:#b91c1c;margin:0 0 8px">Database connection failed</h2>'
               . '<p style="color:#444">' . htmlspecialchars($ex->getMessage()) . '</p>'
               . '<p style="color:#666;font-size:14px">1) Import <b>install.sql</b> in phpMyAdmin. '
               . '2) Check DB_NAME / DB_USER / DB_PASS in <b>config.php</b>.</p></div>';
            exit;
        }
    }
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement { $st = db()->prepare($sql); $st->execute($p); return $st; }
function all(string $sql, array $p = []): array   { return q($sql, $p)->fetchAll(); }
function one(string $sql, array $p = []): ?array  { $r = q($sql, $p)->fetch(); return $r === false ? null : $r; }
function scalar(string $sql, array $p = [])        { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r === false ? null : $r[0]; }

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function post(string $k, $d = '') { return $_POST[$k] ?? $d; }
function get(string $k, $d = '')  { return $_GET[$k] ?? $d; }
function ipost(string $k, int $d = 0): int { return (int) ($_POST[$k] ?? $d); }

function money($v): string { return number_format((float) $v, 2); }
function fdate(?string $d): string { if (!$d || $d === '0000-00-00') return '-'; $t = strtotime($d); return $t ? date('d/m/Y', $t) : '-'; }
function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }

/* ---------------- session / csrf / flash ---------------- */
function csrf_token(): string { if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); } return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_verify(): void {
    $t = (string) ($_POST['csrf'] ?? '');
    if ($t === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $t)) {
        http_response_code(419); die('Security token expired. Please go back and submit the form again.');
    }
}
function flash(?string $m = null, string $t = 'success'): void {
    if ($m !== null) { $_SESSION['flash'][] = ['m' => $m, 't' => $t]; }
}
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function redirect(string $path): void { header('Location: ' . $path); exit; }

/* ---------------- settings ---------------- */
function setting(string $k, $d = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try { foreach (all('SELECT skey, svalue FROM settings') as $r) { $cache[$r['skey']] = $r['svalue']; } } catch (Throwable $e) {}
    }
    return array_key_exists($k, $cache) && $cache[$k] !== '' ? $cache[$k] : $d;
}
function set_setting(string $k, string $v): void {
    q('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
}

/* ---------------- reference numbers ---------------- */
function next_ref(string $prefix): string
{
    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';
    $last = scalar('SELECT ref_no FROM requests WHERE ref_no LIKE ? ORDER BY id DESC LIMIT 1', [$like]);
    $n = 1;
    if ($last) { $parts = explode('-', (string) $last); $n = ((int) end($parts)) + 1; }
    return sprintf('%s-%s-%04d', $prefix, $year, $n);
}
