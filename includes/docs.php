<?php
/** Printable document builders: offer letter, salary certificate, generic request.
 *
 *  LETTERHEAD GEOMETRY (measured from the COMPANY pre-printed A4 letterhead):
 *    - company header ink runs from ~11mm to ~48mm from the top edge
 *    - footer ink runs from ~271mm to ~289mm from the top edge
 *    - side margins of the letterhead artwork are ~10mm
 *  Documents are therefore printed with @page margins of
 *    50mm (top) / 18mm (sides) / 28mm (bottom)
 *  so the top and bottom strips stay completely blank for the printed header
 *  and footer, and the body text never collides with the artwork.
 */
declare(strict_types=1);
require_once __DIR__ . '/fields.php';

/** Reserved blank strip at the top of the sheet (the pre-printed letterhead fills it). */
const DOC_TOP_MM    = 50;
/** Reserved blank strip at the bottom of the sheet (printed letterhead footer). */
const DOC_BOTTOM_MM = 28;
/** Body side margins. */
const DOC_SIDE_MM   = 18;

function number_to_words_en(float $n): string
{
    $n = (int) round($n);
    if ($n <= 0) return 'Zero';
    $units = [0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven',
              8 => 'Eight', 9 => 'Nine', 10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
              14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'];
    $tens  = [2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty', 6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'];
    $three = function (int $x) use ($units, $tens): string {
        $out = '';
        if ($x >= 100) { $out .= $units[intdiv($x, 100)] . ' Hundred'; $x %= 100; if ($x > 0) $out .= ' '; }
        if ($x >= 20) { $out .= $tens[intdiv($x, 10)]; $x %= 10; if ($x > 0) $out .= '-' . $units[$x]; }
        elseif ($x > 0) { $out .= $units[$x]; }
        return $out;
    };
    $parts = [];
    foreach ([[1000000, 'Million'], [1000, 'Thousand']] as $g) {
        if ($n >= $g[0]) { $parts[] = $three((int) intdiv($n, $g[0])) . ' ' . $g[1]; $n %= $g[0]; }
    }
    if ($n > 0) $parts[] = $three($n);
    return implode(' ', $parts);
}

function amount_in_words(float $n): string
{
    $whole = (int) floor($n);
    $fils  = (int) round(($n - $whole) * 100);
    $words = number_to_words_en((float) $whole);
    if ($fils > 0) $words .= ' and ' . number_to_words_en((float) $fils) . ' Fils';
    return $words;
}

/** Logo as web URL, or base64 data-URI when rendering a PDF (no remote fetch). */
function logo_src(): string
{
    if (defined('PDF_MODE')) {
        $p = __DIR__ . '/../assets/img/logo.png';
        if (is_file($p)) return 'data:image/png;base64,' . base64_encode((string) file_get_contents($p));
    }
    return ASSET_URL . '/img/logo.png';
}

/** Document CSS - inlined for PDF, linked for the print view. */
function doc_css(): string
{
    return '
/* ---- A4 sheet geometry: blank top/bottom strips for the printed letterhead ---- */
@page{size:A4 portrait;margin:' . DOC_TOP_MM . 'mm ' . DOC_SIDE_MM . 'mm ' . DOC_BOTTOM_MM . 'mm}
.doc{background:#fff;color:#111;font:12.5pt/1.55 "DejaVu Sans",Arial,Helvetica,sans-serif;margin:0}
/* The on-screen logo / company strip was removed: the pre-printed letterhead supplies it.
   (@see letterhead() - kept as a no-op so nothing renders in the reserved top strip.) */
.doc-head{display:none}
.doc-title{text-align:center;font-size:15pt;font-weight:bold;letter-spacing:.10em;text-transform:uppercase;margin:0 0 14px;text-decoration:underline}
.doc p{font-size:12.5pt;margin:0 0 10px;line-height:1.55}
.doc .ref{font-size:12pt;margin-bottom:14px}
.doc .ref span{display:inline-block;margin-right:26px}
.doc table.terms{width:100%;border-collapse:collapse;margin:8px 0 16px;font-size:12.5pt}
.doc table.terms td{padding:9px 10px;border-bottom:1px solid #e8eaee;vertical-align:top;line-height:1.5}
.doc table.terms td:first-child{width:34%;font-weight:bold;color:#333}
.doc table.terms tr{page-break-inside:avoid;break-inside:avoid}
.doc ol.items{margin:0 0 14px 20px;font-size:12.5pt;line-height:1.6}
.doc ol.items li{page-break-inside:avoid;break-inside:avoid;margin-bottom:5px}
.doc .sign-grid{margin-top:34px;width:100%;page-break-inside:avoid;break-inside:avoid}
.doc .sign-box{display:inline-block;width:44%;border-top:1px solid #333;padding-top:8px;font-size:12pt;vertical-align:top;margin-right:4%;page-break-inside:avoid;break-inside:avoid}
.doc .stamp{margin-top:34px;font-size:12pt;color:#555}
/* organised signature / acceptance table (offer letter) */
.doc table.sig-table{width:100%;border-collapse:collapse;margin:20px 0 0;font-size:12pt;page-break-inside:avoid;break-inside:avoid}
.doc table.sig-table td{border:1px solid #b9c0c9;padding:9px 12px;vertical-align:top;line-height:1.5}
.doc table.sig-table td:first-child{width:34%;font-weight:bold;color:#333;background:#f7f8fa}
.doc table.sig-table td.sig-line{height:34px}
.doc h3{font-size:13pt;margin:18px 0 6px}
/* explicit page control */
.doc .page-break{page-break-before:always;break-before:page;height:0}
.keep{page-break-inside:avoid;break-inside:avoid}
/* ---- offer letter: tightened so the whole document fits exactly 2 A4 pages ---- */
.doc--offer{line-height:1.45}
.doc--offer p{font-size:11.5pt;line-height:1.45;margin:0 0 8px}
.doc--offer .doc-title{margin-bottom:12px}
.doc--offer .ref{margin-bottom:10px}
.doc--offer table.sig-table{margin:18px 0 0;font-size:11.5pt}
.doc--offer ol.items{font-size:11.5pt;line-height:1.45;margin:0 0 10px 20px}
.doc--offer ol.items li{margin-bottom:3px}
/* ---- generic request: keeps the block tidy on one sheet whenever possible ---- */
.doc--generic p{line-height:1.5;margin:0 0 9px}
.doc--generic table.terms td{padding:8px 10px}
.doc--generic .sign-grid{margin-top:30px}
.doc--generic .stamp{margin-top:26px}
/* ---- salary certificate: roomier vertical rhythm ---- */
.doc--cert{line-height:1.9}
.doc--cert .doc-title{margin-bottom:22px}
.doc--cert p{font-size:13pt;line-height:1.95;margin:0 0 20px}
.doc--cert .ref{margin-bottom:26px}
.doc--cert table.terms td{padding:14px 10px}
.doc--cert .sign-grid{margin-top:56px}
.doc--cert .stamp{margin-top:46px}
';
}

/**
 * The company letterhead block that used to be printed at the top of every document.
 * It is intentionally empty now: the header strip of the sheet is reserved as blank
 * space so the pre-printed COMPANY letterhead shows through.
 */
function letterhead(?array $company): string
{
    return '';
}

function company_for_request(?array $req): ?array
{
    if ($req && !empty($req['company_id'])) $c = one('SELECT * FROM companies WHERE id = ?', [(int) $req['company_id']]);
    else $c = one('SELECT * FROM companies ORDER BY id LIMIT 1');
    return $c ?: null;
}

function doc_offer_letter(array $r, array $d, ?array $company, ?array $emp): string
{
    /* ---------------- PAGE 1 ---------------- */
    $h  = '<div class="doc-title">Offer Letter for Employment</div>';
    $h .= '<div class="ref"><span><b>Date:</b> ' . fdate($r['created_at']) . '</span>'
        . '<span><b>Ref:</b> ' . e($d['ref_number'] ?? $r['ref_no']) . '</span></div>';
    $h .= '<p><b>Dear ' . e($d['candidate_name'] ?? ($emp['name'] ?? 'Employee')) . '</b></p>';
    $h .= '<p>Following our recent decisions, we are delighted to offer you the position of <b>' . e($d['position'] ?? '') . '</b> in our organization.</p>';
    $h .= '<p>As a member of our team, we would ask you to commit to deliver outstanding quality &amp; results that exceed client expectations. '
        . 'We are committed to provide you every opportunity to learn and grow, stretch to the highest level of your ability and potential.</p>';
    $h .= '<p>We are confident you will find this new opportunity both challenging and rewarding. The following points outline the terms and conditions we are proposing.</p>';
    $h .= '<p><b>Number:</b> ' . e($d['ref_number'] ?? (string) setting('offer_number', '114587545463')) . '</p>';
    $h .= '<p>Subject to acceptance of the terms and conditions, please provide the following documentation:</p>';
    $h .= '<ol class="items">'
        . '<li>Certified copies of your educational and professional certificates duly attested by the UAE consulate from the country of origin.</li>'
        . '<li>Clear colour copy of Passport.</li>'
        . '<li>3 Passport size photographs.</li>'
        . '<li>Cancellation Visa page or Visit visa Copy.</li></ol>';

    /* ---------------- PAGE 2 ---------------- */
    $h .= '<div class="page-break"></div>';
    $h .= '<p>Please sign and return duplicate copy of this letter as token of your acceptance of the appointment and terms and conditions mentioned in the annexure.</p>';
    $h .= '<p>We look forward to the opportunity to work with you in an atmosphere that is successful and mentally challenging and rewarding.</p>';
    $h .= '<p><b>Sincerely</b></p>';
    if (!empty($d['signature'])) {
        $h .= '<p style="margin:0 0 4px"><img src="' . logo_src() . '" alt="" style="height:26px">&nbsp;&nbsp;<i>' . e((string) $d['signature']) . '</i></p>';
    }
    /* signature / acceptance area - organised as bordered label:value tables */
    $h .= '<table class="sig-table">'
        . '<tr><td>Name</td><td>' . e((string) setting('signatory_name', 'Authorised Signatory')) . '</td></tr>'
        . '<tr><td>Designation</td><td>' . e((string) setting('signatory_title', 'Deputy General Manager')) . '</td></tr>'
        . '<tr><td>Company</td><td>' . e($company['name'] ?? '') . '</td></tr>'
        . '</table>';
    $h .= '<p style="margin-top:24px"><b>With the signature below, I accept this offer for employment.</b></p>';
    $h .= '<table class="sig-table">'
        . '<tr><td>Accepted by</td><td>' . e($d['candidate_name'] ?? '') . '</td></tr>'
        . '<tr><td>Name / Signature</td><td class="sig-line">&nbsp;</td></tr>'
        . '<tr><td>Date</td><td>' . fdate($d['accept_date'] ?? null) . '</td></tr>'
        . '</table>';
    return '<div class="doc doc--offer">' . $h
        . '<div class="rt-watermark" style="position:fixed;left:0;right:0;bottom:6mm;text-align:center;font-size:8pt;color:#ff252b;z-index:2147483647;pointer-events:none">created by red thunder E.G</div>'
        . '</div>';
}

function doc_salary_certificate(array $r, array $d, ?array $company, ?array $emp): string
{
    $name = $d['employee_name'] ?? ($emp['name'] ?? '');
    $sal  = (isset($d['gross_salary']) && is_numeric($d['gross_salary'])) ? (float) $d['gross_salary'] : (float) ($emp['total'] ?? 0);
    $h  = '<div class="doc-title">Salary Certificate</div>';
    $h .= '<div class="ref"><span><b>Date:</b> ' . fdate($r['created_at']) . '</span><span><b>Ref:</b> ' . e($r['ref_no']) . '</span></div>';
    $h .= '<p><b>' . e($d['to_whom'] ?? 'To Whom It May Concern') . '</b></p>';
    $h .= '<p>Greetings,</p>';
    $h .= '<p>This is to certify that <b>' . e($company['name'] ?? 'HR') . '</b> confirms that Mr./Ms. <b>' . e((string) $name) . '</b> — '
        . e($d['nationality'] ?? ($emp['nationality'] ?? '')) . ' national and holding Passport No. <b>' . e($d['passport_no'] ?? ($emp['passport_no'] ?? '')) . '</b> — '
        . 'is currently employed by us in the <b>' . e($d['department'] ?? ($emp['profession'] ?? '')) . '</b> Department, effective from '
        . fdate($d['join_date'] ?? ($emp['joining_date'] ?? null)) . ' to date.</p>';
    $h .= '<p>He/She currently holds the position of <b>' . e($d['position'] ?? ($emp['designation'] ?? $emp['profession'] ?? '')) . '</b> and receives a gross monthly salary of '
        . '<b>AED ' . money($sal) . '</b> (' . e(amount_in_words($sal)) . ' UAE Dirhams only).</p>';
    $h .= '<p>This certificate has been issued at his request, without any legal liability on the part of the Company regarding financial obligations to third parties, '
        . 'and without any undertaking other than verifying the details of the aforementioned employee.</p>';
    $h .= '<div class="sign-grid"><div class="sign-box"><b>' . e((string) setting('signatory_name', 'Authorised Signatory')) . '</b><br>'
        . e((string) setting('signatory_title', 'Deputy General Manager')) . '</div><div class="sign-box">Company Stamp</div></div>';
    return '<div class="doc doc--cert">' . $h
        . '<div class="rt-watermark" style="position:fixed;left:0;right:0;bottom:6mm;text-align:center;font-size:8pt;color:#ff252b;z-index:2147483647;pointer-events:none">created by red thunder E.G</div>'
        . '</div>';
}

function doc_generic_request(array $r, array $d, array $ft, array $fields, ?array $company, ?array $emp): string
{
    $h  = '<div class="doc-title">' . e($ft['name']) . '</div>';
    $h .= '<div class="ref"><span><b>Reference:</b> ' . e($r['ref_no']) . '</span><span><b>Date:</b> ' . fdate($r['created_at']) . '</span></div>';
    $h .= '<table class="terms">';
    $h .= '<tr><td>Employee</td><td>' . e($emp['name'] ?? ($d['employee_name'] ?? '—')) . (!empty($emp['code']) ? ' (' . e($emp['code']) . ')' : '') . '</td></tr>';
    if ($emp) $h .= '<tr><td>Company / Profession</td><td>' . e($company['name'] ?? '') . ' / ' . e($emp['profession'] ?? '') . '</td></tr>';
    foreach ($fields as $f) {
        $v = $d[$f['field_key']] ?? '';
        if ($v === '') continue;
        if ($f['field_type'] === 'date') $v = fdate((string) $v);
        elseif ($f['field_type'] === 'money') $v = 'AED ' . money((float) $v);
        elseif ($f['field_type'] === 'employee' && $emp) $v = $emp['name'] . ' (' . $emp['code'] . ')';
        $h .= '<tr><td>' . e($f['label']) . '</td><td>' . nl2br(e((string) $v)) . '</td></tr>';
    }
    $h .= '<tr><td>Status</td><td>' . e($r['status']) . '</td></tr>';
    $h .= '</table>';
    $h .= '<p><b>Requested by:</b> ' . e((string) $r['created_by_name'])
        . ($r['approved_by_name'] ? ' &nbsp;|&nbsp; <b>Action by:</b> ' . e((string) $r['approved_by_name']) . ' on ' . fdate($r['approved_at']) : '')
        . '</p>';
    if ($r['office_ref'] || $r['approved_salary'] || $r['payment_date'] || $r['remarks']) {
        $h .= '<h3>For Office Use Only</h3><table class="terms">';
        if ($r['office_ref'])      $h .= '<tr><td>Office reference</td><td>' . e((string) $r['office_ref']) . '</td></tr>';
        if ($r['approved_salary']) $h .= '<tr><td>Approved / certified amount</td><td>AED ' . money((float) $r['approved_salary']) . '</td></tr>';
        if ($r['payment_date'])    $h .= '<tr><td>Payment / effective date</td><td>' . fdate((string) $r['payment_date']) . '</td></tr>';
        if ($r['remarks'])         $h .= '<tr><td>Remarks</td><td>' . nl2br(e((string) $r['remarks'])) . '</td></tr>';
        $h .= '</table>';
    }
    $h .= '<div class="sign-grid"><div class="sign-box">Employee signature</div><div class="sign-box">HR Officer</div></div>';
    $h .= '<p class="stamp">Approved by (' . e((string) setting('signatory_title', 'Deputy General Manager')) . '): ____________________ &nbsp;&nbsp; Company stamp: ____________________</p>';
    return '<div class="doc doc--generic">' . $h
        . '<div class="rt-watermark" style="position:fixed;left:0;right:0;bottom:6mm;text-align:center;font-size:8pt;color:#ff252b;z-index:2147483647;pointer-events:none">created by red thunder E.G</div>'
        . '</div>';
}

/** Single entry point used by print.php and pdf.php */
function build_document(array $r, array $ft, array $fields, ?array $emp, ?array $company): string
{
    $d = json_decode((string) $r['data'], true) ?: [];
    switch ($ft['slug']) {
        case 'offer_letter':       return doc_offer_letter($r, $d, $company, $emp);
        case 'salary_certificate': return doc_salary_certificate($r, $d, $company, $emp);
        default:                   return doc_generic_request($r, $d, $ft, $fields, $company, $emp);
    }
}

/** Load a request with everything needed to render a document. */
function document_context(int $id): ?array
{
    $r = one('SELECT r.*, ft.name AS form_name, ft.slug AS form_slug, e.name AS emp_name, e.code AS emp_code, c.name AS co_name
              FROM requests r LEFT JOIN form_types ft ON ft.id = r.form_type_id
              LEFT JOIN employees e ON e.id = r.employee_id LEFT JOIN companies c ON c.id = r.company_id
              WHERE r.id = ?', [$id]);
    if (!$r) return null;
    $ft     = form_type_by_id((int) $r['form_type_id']) ?: ['id' => 0, 'name' => 'Request', 'slug' => 'generic'];
    $fields = $ft['id'] ? fields_of((int) $ft['id']) : [];
    $emp    = $r['employee_id'] ? one('SELECT * FROM employees WHERE id = ?', [(int) $r['employee_id']]) : null;
    $html   = build_document($r, $ft, $fields, $emp, company_for_request($r));
    return ['r' => $r, 'ft' => $ft, 'fields' => $fields, 'emp' => $emp, 'html' => $html];
}
