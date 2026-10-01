<?php
/** Print view of a stored request document (offer letter, salary certificate, any request). */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/docs.php';
require_login();
require_perm('requests.print');

$id = (int) get('id');
$ctx = document_context($id);
if (!$ctx) { http_response_code(404); die('Request not found.'); }
$r = $ctx['r'];
if ((int) $r['created_by'] !== (int) current_user()['id'] && !can('requests.view_all')) { require_perm('requests.view_all'); }
$embed = (string) get('embed') === '1';
log_activity('request_print', 'requests', $id, (string) $r['ref_no']);
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($ctx['ft']['name']) ?> — <?= e($r['ref_no']) ?></title>
<link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css?v=<?= APP_VERSION ?>">
<style><?= doc_css() ?>
body{background:#eef1f5;padding:18px}
/* On-screen the sheet is shown at true A4 size with the same blank strips that
   printing reserves, so the preview equals the printed page. */
@media screen{
  .doc{width:210mm;min-height:297mm;margin:0 auto;box-sizing:border-box;
       padding:<?= DOC_TOP_MM ?>mm <?= DOC_SIDE_MM ?>mm <?= DOC_BOTTOM_MM ?>mm}
}
@media print{body{background:#fff;padding:0}.no-print{display:none!important}}
</style>
</head>
<body>
<div class="no-print" style="max-width:900px;margin:0 auto 14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
  <a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>/pages/request_view.php?id=<?= $id ?>">&larr; Back to request</a>
  <button class="btn btn-dark btn-sm" onclick="window.print()">🖨 Print</button>
  <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/pdf.php?id=<?= $id ?>">⤓ Download PDF</a>
  <span class="pill"><?= e($r['ref_no']) ?> · <?= e((string) $r['status']) ?></span>
</div>
<?= $ctx['html'] ?>
<script>
  // Space to print, Enter/Backspace to go back — convenience for fast office work
  document.addEventListener('keydown', function (ev) {
    if (ev.target.tagName === 'INPUT' || ev.target.tagName === 'TEXTAREA') return;
    if (ev.key === ' ') { ev.preventDefault(); window.print(); }
  });
  <?php if (!$embed): ?>if (location.search.indexOf('autoprint=1') > -1) window.print();<?php endif; ?>
</script>
<!-- ===== RED THUNDER PRINT STAMP - DO NOT REMOVE ===== -->
<div class="rt-stamp" data-rt="1" style="position:fixed;left:0;right:0;bottom:0;text-align:center;font-size:8pt;color:#ff252b;letter-spacing:.04em;z-index:2147483647;pointer-events:none;font-family:system-ui,sans-serif">created by red thunder E.G</div>
<div class="rt-watermark" style="position:fixed;left:0;right:0;bottom:0;text-align:center;font-size:8pt;color:#ff252b;z-index:2147483647;pointer-events:none">created by red thunder E.G</div>
<script>
(function(){var s=document.querySelector('.rt-stamp')||document.querySelector('.rt-watermark');if(!s){var n=document.createElement('div');n.className='rt-stamp';n.textContent='created by red thunder E.G';document.body.appendChild(n);}})();
</script>
</body></html>
