<?php
declare(strict_types=1);

const FORM_TYPES = [
    'alc' => [
        'title'    => 'แบบฟอร์มการขออนุมัติใช้ห้อง Active Learning Classroom',
        'short'    => 'ห้อง Active Learning Classroom',
        'purposes' => [
            'activity' => 'จัดกิจกรรม',
            'project'  => 'โครงการ',
            'seminar'  => 'สัมมนา',
            'other'    => 'อื่น',
        ],
    ],
    'classroom' => [
        'title'    => 'แบบฟอร์มการขออนุมัติใช้ห้องเรียน/ ห้องเรียน Hybrid Classroom',
        'short'    => 'ห้องเรียน / ห้องเรียน Hybrid Classroom',
        'purposes' => [
            'tutorial' => 'จัดสอนเสริมในรายวิชา',
            'exam'     => 'จัดสอบ',
            'activity' => 'จัดกิจกรรม/โครงการ',
            'other'    => 'อื่นๆ',
        ],
    ],
];

const PREFIXES = ['นาย', 'นาง', 'นางสาว'];

const BOOKING_FIELDS = ['prefix', 'fullname', 'faculty', 'department', 'phone', 'position',
    'room_kind', 'room_name', 'building', 'alt_room',
    'booking_date', 'time_start', 'time_end', 'purpose', 'purpose_detail'];

function booking_find(string $ref): ?array
{
    $st = db()->prepare('SELECT * FROM bookings WHERE ref = ?');
    $st->execute([$ref]);
    return $st->fetch() ?: null;
}

/** หาเอกสารที่ผู้ใช้เป็นเจ้าของ ไม่เจอ = 404 */
function booking_for_user(string $ref, array $user): array
{
    $b = booking_find($ref);
    if (!$b || $b['user_login'] !== $user['login']) {
        http_response_code(404);
        exit('ไม่พบเอกสาร');
    }
    return $b;
}

function booking_list(string $login): array
{
    $st = db()->prepare('SELECT * FROM bookings WHERE user_login = ? ORDER BY created_at DESC');
    $st->execute([$login]);
    return $st->fetchAll();
}

function booking_locked(array $b): bool
{
    return $b['dms_fetched_at'] !== null;
}

function booking_status(array $b): array
{
    if ($b['dms_fetched_at']) return ['ส่งเข้า DMS แล้ว', 'sent'];
    if ($b['dms_sent_at'])    return ['กำลังส่งเข้า DMS', 'pending'];
    return ['ฉบับร่าง', 'draft'];
}

/**
 * ตรวจและทำความสะอาดข้อมูลจากฟอร์ม
 * @return array{0: array, 1: array<string,string>} [ข้อมูล, ข้อผิดพลาดรายช่อง]
 */
function booking_validate(string $type, array $in): array
{
    $d = [];
    foreach (BOOKING_FIELDS as $f) {
        $d[$f] = trim((string)($in[$f] ?? ''));
    }
    $e = [];
    // ชื่อจาก SSO มีคำนำหน้าอยู่แล้ว จึงไม่แยกช่องคำนำหน้า และสังกัดกำหนดตายตัวจาก config
    $d['prefix']  = '';
    $d['faculty'] = (string)config('faculty', 'คณะวิทยาศาสตร์การแพทย์');
    $required = ['fullname' => 'ชื่อ-สกุล', 'phone' => 'เบอร์โทร', 'position' => 'ตำแหน่ง'];
    foreach ($required as $f => $label) {
        if ($d[$f] === '') $e[$f] = "กรุณากรอก{$label}";
    }

    if ($type === 'classroom') {
        if (!in_array($d['room_kind'], ['classroom', 'hybrid'], true)) {
            $e['room_kind'] = 'กรุณาเลือกประเภทห้อง';
        } elseif ($d['room_name'] === '') {
            $e['room_name'] = 'กรุณาระบุห้อง';
        }
        if ($d['room_kind'] === 'hybrid') $d['building'] = '';
    } else {
        $d['room_kind'] = null;
        $d['room_name'] = $d['building'] = $d['alt_room'] = '';
    }

    $date = DateTime::createFromFormat('!Y-m-d', $d['booking_date']);
    if (!$date || $date->format('Y-m-d') !== $d['booking_date']) {
        $e['booking_date'] = 'กรุณาเลือกวันที่';
    } elseif ($msg = booking_date_too_soon($d['booking_date'])) {
        $e['booking_date'] = $msg;
    }
    foreach (['time_start', 'time_end'] as $f) {
        // ฟอร์มส่งมาเป็นชั่วโมง/นาทีแยกกัน (เลือกแบบ 24 ชั่วโมง ไม่มี AM/PM)
        if (isset($in[$f . '_h'], $in[$f . '_m']) && $in[$f . '_h'] !== '' && $in[$f . '_m'] !== '') {
            $d[$f] = sprintf('%02d:%02d', (int)$in[$f . '_h'], (int)$in[$f . '_m']);
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $d[$f])) $e[$f] = 'กรุณาเลือกเวลา';
    }
    if (!isset($e['time_start'], $e['time_end']) && $d['time_end'] <= $d['time_start']) {
        $e['time_end'] = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม';
    }

    if (!isset(FORM_TYPES[$type]['purposes'][$d['purpose']])) {
        $e['purpose'] = 'กรุณาเลือกวัตถุประสงค์';
    } elseif ($d['purpose_detail'] === '') {
        $e['purpose_detail'] = 'กรุณาระบุรายละเอียดวัตถุประสงค์';
    }

    foreach ($d as $f => $v) {
        if (is_string($v) && mb_strlen($v) > 200 && $f !== 'purpose_detail') $e[$f] = 'ข้อความยาวเกินไป';
    }
    if (mb_strlen($d['purpose_detail']) > 500) $e['purpose_detail'] = 'ข้อความยาวเกินไป';

    return [$d, $e];
}

/**
 * ตรวจเงื่อนไข "การจองห้องจะต้องดำเนินการก่อน N วันทำการ" นับจากวันนี้
 * ใช้ทั้งตอนบันทึกและตอนกดส่งเข้า DMS (เผื่อบันทึกร่างไว้นานแล้วค่อยส่ง)
 * @return string|null ข้อความแจ้งเตือน หรือ null ถ้าผ่านเงื่อนไข
 */
function booking_date_too_soon(string $bookingDate): ?string
{
    $days = (int)config('min_working_days', 3);
    $min = earliest_booking_date($days);
    if ($bookingDate >= $min) return null;
    return "การจองห้องต้องดำเนินการก่อน $days วันทำการ — วันนี้จองได้ตั้งแต่ " . thai_date($min, true) . ' เป็นต้นไป';
}

function booking_create(string $type, array $user, array $d): string
{
    $cols = array_merge(['ref', 'form_type', 'user_login'], BOOKING_FIELDS);
    $sql = 'INSERT INTO bookings (' . implode(',', $cols) . ') VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')';
    $values = array_map(fn($f) => $d[$f], BOOKING_FIELDS);
    for ($try = 0; ; $try++) {
        // DMS ใช้ ref เป็นตัวเลข (เหมือนระบบขอรถ) จึงสุ่มเลข 9 หลัก — อยู่ในช่วง int 32 บิต และเดาเลขของคนอื่นไม่ได้
        $ref = (string)random_int(100000000, 999999999);
        try {
            db()->prepare($sql)->execute(array_merge([$ref, $type, $user['login']], $values));
            return $ref;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' || $try >= 5) throw $e; // 23000 = ref ซ้ำ สุ่มใหม่
        }
    }
}

function booking_update(string $ref, array $d): void
{
    $set = implode(',', array_map(fn($f) => "$f = ?", BOOKING_FIELDS));
    // แก้ไขข้อมูลแล้ว PDF เดิมใช้ไม่ได้ ต้องสร้างใหม่ตอนส่ง
    $sql = "UPDATE bookings SET $set, pdf_generated_at = NULL, dms_sent_at = NULL WHERE ref = ? AND dms_fetched_at IS NULL";
    db()->prepare($sql)->execute(array_merge(array_map(fn($f) => $d[$f], BOOKING_FIELDS), [$ref]));
    @unlink(pdf_path($ref));
}

function booking_delete(string $ref): void
{
    db()->prepare('DELETE FROM bookings WHERE ref = ? AND dms_fetched_at IS NULL')->execute([$ref]);
    @unlink(pdf_path($ref));
}

function dms_link(array $b): string
{
    $form = config('dms.forms.' . $b['form_type']);
    return config('dms.link_url') . '?' . http_build_query([
        'ref' => $b['ref'],
        'con' => $form['con'] ?? '',
        'sub' => $form['sub'] ?? '',
    ]);
}

function dms_configured(string $type): bool
{
    $form = config('dms.forms.' . $type, []);
    return ($form['con'] ?? '') !== '' && ($form['sub'] ?? '') !== '';
}

function pdf_path(string $ref): string
{
    return rtrim((string)config('pdf_dir'), '/\\') . DIRECTORY_SEPARATOR . preg_replace('/[^a-f0-9]/', '', $ref) . '.pdf';
}

/**
 * สร้าง PDF ด้วย Chrome Headless (ตามคู่มือ DMS_Connect.md ขั้นตอนที่ 2)
 * @throws RuntimeException เมื่อสร้างไม่สำเร็จ
 */
function pdf_generate(array $b): string
{
    $chrome  = (string)config('chrome_path');
    $profile = (string)config('chrome_profile');
    $target  = pdf_path($b['ref']);
    $tmp     = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';

    $dir     = dirname($target);
    $chromeLog = $dir . DIRECTORY_SEPARATOR . 'chrome_last.log';

    if (!is_file($chrome)) throw new RuntimeException('ไม่พบ Chrome ที่ ' . $chrome . ' (แก้ chrome_path ใน config.php)');

    // 1) โฟลเดอร์ต้องเขียนได้โดย identity ของ IIS App Pool
    $probe = $dir . DIRECTORY_SEPARATOR . '.write_test';
    if (@file_put_contents($probe, 'x') === false) {
        throw new RuntimeException("โปรเซสเว็บเขียนไฟล์ในโฟลเดอร์ $dir ไม่ได้ — ให้สิทธิ์ Modify แก่ IIS_IUSRS (ดู README ขั้นตอนที่ 4)");
    }
    @unlink($probe);
    if (!is_dir($profile) && !@mkdir($profile, 0775, true)) throw new RuntimeException("สร้างโฟลเดอร์ $profile ไม่ได้ — ตรวจสอบสิทธิ์โฟลเดอร์");

    $base = rtrim((string)(config('internal_base_url') ?: config('base_url')), '/');
    $printUrl = $base . '/print_booking.php?ref=' . rawurlencode($b['ref']);

    // บน Windows IIS ใช้ cmd.exe /c เพื่อไม่ให้ติด Access Denied ของ ProcessSingleton
    $cmd = 'cmd.exe /c ""' . $chrome . '" --headless=new --disable-gpu --no-sandbox --disable-crash-reporter --print-background --print-to-pdf-no-header --user-data-dir="' . $profile . '" --print-to-pdf="' . $tmp . '" "' . $printUrl . '" > "' . $chromeLog . '" 2>&1"';
    exec($cmd);

    if (!is_file($tmp) || filesize($tmp) < 1000) {
        @unlink($tmp);
        $tail = trim(implode("\n", array_slice(@file($chromeLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -3)));
        throw new RuntimeException("Chrome ไม่ได้สร้างไฟล์ PDF" . ($tail !== '' ? " — Chrome: $tail" : '')
            . " — log เต็มอยู่ที่ $chromeLog");
    }
    @unlink($target);
    if (!rename($tmp, $target)) throw new RuntimeException('บันทึกไฟล์ PDF ไม่สำเร็จ');

    db()->prepare('UPDATE bookings SET pdf_generated_at = NOW() WHERE ref = ?')->execute([$b['ref']]);
    return $target;
}

function booking_room_label(array $b): string
{
    if ($b['form_type'] === 'alc') return 'Active Learning Classroom';
    if ($b['room_kind'] === 'hybrid') return 'Hybrid Classroom ' . $b['room_name'];
    return 'ห้อง ' . $b['room_name'] . ($b['building'] !== '' ? ' อาคาร ' . $b['building'] : '');
}
