<?php
/** Download a stored request as a PDF (bundled Dompdf, no Composer needed on the host). */
declare(strict_types=1);
define('PDF_MODE', true);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/docs.php';
require_login();
require_perm('requests.print');

$id  = (int) get('id');
$ctx = document_context($id);
if (!$ctx) { http_response_code(404); die('Request not found.'); }
$r = $ctx['r'];
if ((int) $r['created_by'] !== (int) current_user()['id'] && !can('requests.view_all')) { require_perm('requests.view_all'); }

$html = '<!doctype html><html><head><meta charset="utf-8"><style>' . doc_css()
      . '</style></head><body>' . $ctx['html']
      . '<div class="rt-stamp" style="position:fixed;left:0;right:0;bottom:6mm;text-align:center;font-size:8pt;color:#ff252b;z-index:2147483647">created by red thunder E.G</div>'
      . '</body></html>';

$safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $r['ref_no']) . '.pdf';
$autoload = __DIR__ . '/vendor/autoload_simple.php';

if (!is_file($autoload)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="font-family:system-ui;max-width:640px;margin:60px auto;padding:22px;border:1px solid #f0dcb0;background:#fdf5e4;border-radius:12px">'
       . '<h2 style="margin:0 0 8px">PDF engine not installed</h2>'
       . '<p>The <code>vendor/</code> folder (bundled Dompdf) is missing from this installation. '
       . 'Open the print view and use <b>Print → Save as PDF</b> meanwhile.</p>'
       . '<p><a href="' . BASE_URL . '/print.php?id=' . $id . '">Open print view</a></p></div>';
    exit;
}

try {
    require_once $autoload;
    $options = new Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    // Letterhead-safe geometry: blank strips top (50mm) and bottom (28mm) of every A4 page.
    $options->set('isRemoteEnabled', false);
    $options->set('chroot', __DIR__);
    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    log_activity('request_pdf', 'requests', $id, (string) $r['ref_no']);
    $dompdf->stream($safeName, ['Attachment' => true]);
} catch (Throwable $ex) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="font-family:system-ui;max-width:640px;margin:60px auto;padding:22px;border:1px solid #f5c9c5;background:#fdeceb;border-radius:12px">'
       . '<h2 style="margin:0 0 8px">PDF generation failed</h2><p>' . htmlspecialchars($ex->getMessage()) . '</p>'
       . '<p><a href="' . BASE_URL . '/print.php?id=' . $id . '">Use the print view instead</a></p></div>';
}
