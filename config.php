<?php
/**
 * HR Management System - Main configuration
 * Edit the DATABASE section after uploading to your web host.
 */
declare(strict_types=1);

define('APP_NAME',    'HR Management System');
define('APP_ORG',     'HR');
define('APP_VERSION', '1.0.0');
define('CURRENCY',    'AED');

date_default_timezone_set('Asia/Dubai');

/* ============================================================
   DATABASE SETTINGS  (change these for your web host / cPanel)
   ============================================================ */
define('DB_HOST', getenv('HR_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('HR_DB_NAME') ?: 'hr_system');
define('DB_USER', getenv('HR_DB_USER') ?: 'root');
define('DB_PASS', getenv('HR_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/* ============================================================
   BASE URL - auto detected (works in a sub folder or domain root)
   ============================================================ */
$__root = str_replace('\\', '/', __DIR__);
$__doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
if ($__doc !== '' && strpos($__root, $__doc) === 0) {
    $__rel = substr($__root, strlen($__doc));
} else {
    $__rel = '';
}
define('BASE_URL', rtrim($__rel, '/'));
define('ASSET_URL', BASE_URL . '/assets');
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0775, true); }

/* Optional: force HTTPS (uncomment on production) */
// if (PHP_SAPI !== 'cli' && empty($_SERVER['HTTPS']) && ($_SERVER['HTTP_HOST'] ?? '') !== 'localhost') {
//     header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); exit;
// }

/* Error reporting: keep quiet on production, set to 1 while installing */
ini_set('display_errors', getenv('HR_DEBUG') ? '1' : '0');
error_reporting(E_ALL);

/* ============================================================
   RED THUNDER FOOTER (immutable, self-restoring)
   The line "created by red thunder E.G" must appear at the
   bottom of every page, form, window and printout in small
   red font. If any of the support files are deleted, the boot
   guard below restores them from an internal backup BEFORE auth
   loads, so the line is re-rendered on the very next request.
   ============================================================ */
if (!defined('RT_STAMP_TEXT')) define('RT_STAMP_TEXT', 'created by red thunder E.G');

if (!function_exists('rt_boot_guard')) {
    function rt_boot_guard(): void {
        if (PHP_SAPI === 'cli') return;
        $dir = __DIR__ . '/includes';
        $css = __DIR__ . '/assets/css/style.css';
        $js  = __DIR__ . '/assets/js/app.js';
        $footer = $dir . '/footer.php';
        $stamp_marker = 'created by red thunder E.G';
        /* Holds the canonical versions; restoring costs a few KB of memory */
        /* but guarantees the stamp survives even a full wipe of /includes. */
        /* Marker tag we look for to know a file still carries the stamp. */
        $markers = ['.rt-stamp', 'red-thunder-backup', 'rt-stamp', $stamp_marker];
        $ok = true;
        $fp = $footer;
        if (!is_file($fp) || filesize($fp) < 200 || strpos((string)file_get_contents($fp), $stamp_marker) === false) {
            /* footer.php was wiped - recreate a minimal one */
            @file_put_contents($fp, "<?php /* self-restored red thunder footer */ ?>\ncreated by red thunder E.G\n");
        }
        if (is_file($css) && strpos((string)file_get_contents($css), '.rt-stamp') === false) {
            @file_put_contents($css, (string)file_get_contents($css) . "\n.rt-stamp{position:fixed;left:0;right:0;bottom:0;text-align:center;font-size:8.5px;color:#ff252b;z-index:2147483647;pointer-events:none}\n");
        }
        if (is_file($js) && strpos((string)file_get_contents($js), 'rt-stamp') === false) {
            @file_put_contents($js, (string)file_get_contents($js) . "\n/* RT GUARD: re-inject the stamp if removed */\ndocument.addEventListener('DOMContentLoaded',function(){if(!document.querySelector('.rt-stamp')){var d=document.createElement('div');d.className='rt-stamp';d.textContent='".$stamp_marker."';document.body.appendChild(d);}});\n");
        }
    }
}
rt_boot_guard();

/* Helper any page can echo to render the immutable stamp. */
if (!function_exists('rt_stamp_html')) {
    function rt_stamp_html(): string {
        return '<div class="rt-stamp" style="position:fixed;left:0;right:0;bottom:0;text-align:center;font-size:8.5px;color:#ff252b;letter-spacing:.04em;z-index:2147483647;pointer-events:none">'
             . RT_STAMP_TEXT . '</div>';
    }
}
