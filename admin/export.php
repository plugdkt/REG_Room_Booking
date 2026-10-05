<?php
/**
 * ส่งออกเอกสารตามตัวกรองเดียวกับหน้าผู้ดูแล เป็น CSV (UTF-8 BOM เปิดใน Excel ได้ภาษาไทยถูกต้อง)
 * เลือกแบบฟอร์มเดียว → ได้คอลัมน์ครบทุกช่องของฟอร์มนั้น / ทุกแบบฟอร์ม → คอลัมน์สรุป
 */
require __DIR__ . '/../inc/bootstrap.php';

require_admin();

$filters = admin_filters();
[$rows] = sub_search($filters, 10000, 0);
$form = $filters['form'] !== '' ? form_get($filters['form']) : null;
$fields = $form ? array_filter(form_fields($form), fn($f) => $f['type'] !== 'fixed') : [];

$header = ['เลขอ้างอิง', 'สร้างเมื่อ', 'UP Account', 'สถานะ', 'ส่ง DMS เมื่อ', 'DMS รับเมื่อ'];
$header = array_merge($header, $form ? array_column($fields, 'label') : ['แบบฟอร์ม', 'ผู้ขอ', 'รายละเอียด']);

/** กันสูตร Excel (CSV injection): ค่าที่ขึ้นต้นด้วย = + - @ ให้เป็นข้อความ */
function csv_cell(string $v): string
{
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

$name = 'eform_' . ($form['code'] ?? 'all') . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $header);
foreach ($rows as $s) {
    $line = [$s['ref'], $s['created_at'], $s['user_login'], sub_status($s)[0], (string)$s['dms_sent_at'], (string)$s['dms_fetched_at']];
    if ($form) {
        foreach ($fields as $name => $f) $line[] = field_display($f, (string)($s['data'][$name] ?? ''));
    } else {
        $line[] = $s['form']['short'] ?? $s['form_code'];
        $line[] = $s['data']['fullname'] ?? '';
        $line[] = $s['form'] ? sub_summary($s) : '';
    }
    fputcsv($out, array_map(fn($v) => csv_cell((string)$v), $line));
}
fclose($out);
