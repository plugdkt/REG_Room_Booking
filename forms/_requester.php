<?php
declare(strict_types=1);

/**
 * ช่อง "ข้อมูลผู้ขอ" ที่ใช้ร่วมกันทุกแบบฟอร์ม
 * ชื่อจาก SSO มีคำนำหน้าอยู่แล้ว จึงไม่แยกช่องคำนำหน้า และสังกัดกำหนดตายตัวจาก config('faculty')
 */
function requester_fields(): array
{
    return [
        ['name' => 'fullname', 'label' => 'ชื่อ - สกุล (รวมคำนำหน้า)', 'type' => 'text', 'required' => true,
         'placeholder' => 'เช่น นายวิทยา สุนสะดี', 'remember' => true, 'default' => fn(array $u) => $u['name'] ?? ''],
        ['name' => 'faculty', 'label' => 'สังกัดคณะ/วิทยาลัย/กอง/ศูนย์', 'type' => 'fixed', 'cls' => 'c6',
         'value' => fn() => (string)config('faculty', 'คณะวิทยาศาสตร์การแพทย์')],
        ['name' => 'department', 'label' => 'สาขาวิชา/ส่วนงาน', 'type' => 'text', 'cls' => 'c6',
         'remember' => true, 'default' => fn(array $u) => $u['department'] ?? ''],
        ['name' => 'position', 'label' => 'ตำแหน่ง', 'type' => 'text', 'required' => true, 'cls' => 'c6',
         'remember' => true, 'default' => fn(array $u) => $u['position'] ?? ''],
        ['name' => 'phone', 'label' => 'โทร', 'type' => 'tel', 'required' => true, 'cls' => 'c6',
         'remember' => true, 'default' => fn(array $u) => $u['phone'] ?? ''],
    ];
}

/** ค่าที่พิมพ์ในส่วนผู้ขอและช่องลงนาม (ชื่อ/ตำแหน่ง/วันที่) — key ตรงกับ layout ของแต่ละฟอร์ม */
function requester_values(array $d, string $signedOn): array
{
    $s = thai_date_parts($signedOn);
    return [
        'fullname'      => $d['fullname'] ?? '',
        'faculty'       => $d['faculty'] ?? '',
        'department'    => $d['department'] ?? '',
        'phone'         => $d['phone'] ?? '',
        'sign_name'     => $d['fullname'] ?? '',
        'sign_position' => $d['position'] ?? '',
        'sign_day'      => $s['day'],
        'sign_month'    => $s['month'],
        'sign_year'     => $s['year'],
        'sign_date'     => "{$s['day']} {$s['month']} {$s['year']}",
    ];
}

/** ค่าวัน/เวลาใช้งาน แยกตามช่องในแบบฟอร์ม: ในวัน...ที่...เดือน...พ.ศ... ตั้งแต่เวลา...ถึงเวลา... */
function date_time_values(array $d, string $dateField = 'booking_date'): array
{
    $p = thai_date_parts($d[$dateField]);
    return [
        'dayname'    => $p['dayname'],
        'day'        => $p['day'],
        'month'      => $p['month'],
        'year'       => $p['year'],
        'time_start' => $d['time_start'] ?? '',
        'time_end'   => $d['time_end'] ?? '',
    ];
}
