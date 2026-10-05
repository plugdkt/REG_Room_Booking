<?php
/**
 * อัปเกรดจากระบบขอใช้ห้องเรียน (ตาราง bookings) เป็นระบบ E-Form (ตาราง submissions)
 *
 *   php tools/migrate_v2.php          ดูว่าจะย้ายกี่รายการ (ไม่เขียนอะไร)
 *   php tools/migrate_v2.php --run    สร้างตาราง submissions แล้วย้ายข้อมูล
 *
 * - รันซ้ำได้: ข้ามเอกสารที่ย้ายไปแล้ว (เทียบด้วย ref)
 * - ref, สถานะ DMS และไฟล์ PDF ใน uploads/dms เหมือนเดิม DMS จึงดึงเอกสารเก่าได้ตามปกติ
 * - ไม่ลบตาราง bookings เดิม (เก็บไว้สำรอง)
 */
if (PHP_SAPI !== 'cli') exit("run from command line\n");
require __DIR__ . '/../inc/bootstrap.php';

$run = in_array('--run', $argv, true);
$pdo = db();

if ($run) {
    $pdo->exec(preg_replace('/^(CREATE DATABASE|USE)\b.*$/m', '', (string)file_get_contents(APP_ROOT . '/sql/schema.sql')));
    echo "ตาราง submissions พร้อมแล้ว\n";
}

if (!$pdo->query("SHOW TABLES LIKE 'bookings'")->fetch()) exit("ไม่มีตาราง bookings เดิม — ไม่มีอะไรต้องย้าย\n");
$hasSubs = (bool)$pdo->query("SHOW TABLES LIKE 'submissions'")->fetch();
$done = $hasSubs ? array_flip($pdo->query('SELECT ref FROM submissions')->fetchAll(PDO::FETCH_COLUMN)) : [];

$codes = ['alc' => 'room_alc', 'classroom' => 'room_classroom'];
$hm = fn($t) => substr((string)$t, 0, 5);
$todo = 0;
$ins = $run ? $pdo->prepare('INSERT INTO submissions (ref, form_code, user_login, data, pdf_generated_at, dms_sent_at, dms_fetched_at, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)') : null;

foreach ($pdo->query('SELECT * FROM bookings ORDER BY id') as $b) {
    if (isset($done[$b['ref']])) continue;
    $todo++;
    $hybrid = $b['room_kind'] === 'hybrid';
    $data = [
        'fullname'       => trim($b['prefix'] . $b['fullname']),
        'faculty'        => $b['faculty'],
        'department'     => $b['department'],
        'position'       => $b['position'],
        'phone'          => $b['phone'],
        'booking_date'   => $b['booking_date'],
        'time_start'     => $hm($b['time_start']),
        'time_end'       => $hm($b['time_end']),
        'purpose'        => $b['purpose'],
        'purpose_detail' => $b['purpose_detail'],
    ];
    if ($b['form_type'] === 'classroom') {
        $data += [
            'room_kind'   => (string)$b['room_kind'],
            'room_name'   => $hybrid ? '' : $b['room_name'],
            'building'    => $hybrid ? '' : $b['building'],
            'hybrid_room' => $hybrid ? $b['room_name'] : '',
            'alt_room'    => $b['alt_room'],
        ];
    }
    echo sprintf("  %s  %-15s %s  %s\n", $b['ref'], $codes[$b['form_type']], $b['created_at'], $data['fullname']);
    if ($run) {
        $ins->execute([$b['ref'], $codes[$b['form_type']], $b['user_login'], sub_json($data),
            $b['pdf_generated_at'], $b['dms_sent_at'], $b['dms_fetched_at'], $b['created_at'], $b['updated_at']]);
    }
}

echo $run ? "ย้ายแล้ว $todo รายการ\n" : "จะย้าย $todo รายการ — รันอีกครั้งพร้อม --run เพื่อย้ายจริง\n";
