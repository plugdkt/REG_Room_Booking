<?php
declare(strict_types=1);

/**
 * พิกัดช่องกรอกบนแบบฟอร์มต้นฉบับ (หน่วย pt, หน้า A4 = 595.32 x 841.92)
 * วัดจาก templates/*.pdf ด้วย `python tools/build_templates.py --fields`
 *
 * แต่ละช่อง: [x เริ่ม, x สิ้นสุด, y เส้นฐาน (baseline), จัดชิด 'l' | 'c']
 * ช่อง box_* คือวงเล็บ ( ) สำหรับทำเครื่องหมาย ✓
 */
const FORM_LAYOUT = [
    'alc' => [
        'fullname'   => [209, 535, 109.2, 'l'],
        'faculty'    => [152, 281, 125.3, 'l'],
        'department' => [346, 447, 125.3, 'l'],
        'phone'      => [461, 533, 125.3, 'l'],
        'dayname'    => [70, 161, 157.5, 'c'],
        'day'        => [167, 258, 157.5, 'c'],
        'month'      => [279, 391, 157.5, 'c'],
        'year'       => [405, 532, 157.5, 'c'],
        'time_start' => [85, 122, 173.7, 'c'],
        'time_end'   => [154.5, 198, 173.7, 'c'],

        'box_activity' => [54.8, 66.3, 206.8, 'c'],
        'activity'     => [108, 287.5, 206.8, 'l'],
        'box_project'  => [290.0, 301.6, 206.8, 'c'],
        'project'      => [333, 522, 206.8, 'l'],
        'box_seminar'  => [52.4, 66.3, 238.9, 'c'],
        'seminar'      => [95, 285, 238.9, 'l'],
        'box_other'    => [289.9, 301.3, 238.9, 'c'],
        'other'        => [315, 520, 238.9, 'l'],

        'sign_name'     => [367, 525, 335.8, 'c'],
        'sign_position' => [393, 527, 352.0, 'c'],
        'sign_day'      => [398.4, 424.3, 368.2, 'c'],
        'sign_month'    => [426.5, 472.0, 368.2, 'c'],
        'sign_year'     => [474.1, 502.3, 368.2, 'c'],
    ],
    'classroom' => [
        'fullname'   => [209, 535, 99.3, 'l'],
        'faculty'    => [152, 281, 115.3, 'l'],
        'department' => [341, 448, 115.3, 'l'],
        'phone'      => [462, 534, 115.3, 'l'],

        'box_room_classroom' => [158.2, 178.8, 131.4, 'c'],
        'room_name'          => [214, 329.6, 131.4, 'l'],
        'building'           => [354, 535, 131.4, 'l'],
        'box_room_hybrid'    => [158.2, 178.8, 147.6, 'c'],
        'hybrid_room'        => [276, 535, 147.6, 'l'],

        'dayname'    => [70, 161, 163.7, 'c'],
        'day'        => [167, 258, 163.7, 'c'],
        'month'      => [279, 391, 163.7, 'c'],
        'year'       => [405, 532, 163.7, 'c'],
        'time_start' => [85, 122, 179.8, 'c'],
        'time_end'   => [154.5, 198, 179.8, 'c'],

        'box_tutorial' => [50.2, 61.6, 212.9, 'c'],
        'tutorial'     => [135, 303, 212.9, 'l'],
        'box_exam'     => [305.7, 317.2, 212.9, 'c'],
        'exam'         => [345, 526, 212.9, 'l'],
        'box_activity' => [52.4, 63.9, 245.2, 'c'],
        'activity'     => [131, 302, 245.2, 'l'],
        'box_other'    => [304.4, 315.8, 245.2, 'c'],
        'other'        => [334, 523, 245.2, 'l'],
        'alt_room'     => [148, 302, 261.3, 'l'],

        'sign_name'     => [347, 505, 330.6, 'c'],
        'sign_position' => [367, 505, 347.0, 'c'],
        'sign_date'     => [356, 507.7, 364.4, 'c'],
    ],
];

/** ค่าที่จะพิมพ์ลงแต่ละช่อง ตาม key ใน FORM_LAYOUT */
function form_values(array $b, string $signedOn): array
{
    $d = thai_date_parts($b['booking_date']);
    $s = thai_date_parts($signedOn);
    $v = [
        'fullname'   => $b['prefix'] . $b['fullname'],
        'faculty'    => $b['faculty'],
        'department' => $b['department'],
        'phone'      => $b['phone'],
        'dayname'    => $d['dayname'],
        'day'        => $d['day'],
        'month'      => $d['month'],
        'year'       => $d['year'],
        'time_start' => hm($b['time_start']),
        'time_end'   => hm($b['time_end']),
        'box_' . $b['purpose'] => '✓',
        $b['purpose'] => $b['purpose_detail'],
        'sign_name'     => $b['prefix'] . $b['fullname'],
        'sign_position' => $b['position'],
        'sign_day'      => $s['day'],
        'sign_month'    => $s['month'],
        'sign_year'     => $s['year'],
        'sign_date'     => "{$s['day']} {$s['month']} {$s['year']}",
    ];
    if ($b['form_type'] === 'classroom') {
        $v['box_room_' . $b['room_kind']] = '✓';
        if ($b['room_kind'] === 'hybrid') {
            $v['hybrid_room'] = $b['room_name'];
        } else {
            $v['room_name'] = $b['room_name'];
            $v['building']  = $b['building'];
        }
        $v['alt_room'] = $b['alt_room'];
    }
    return $v;
}
