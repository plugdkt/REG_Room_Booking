<?php
declare(strict_types=1);

/**
 * แบบฟอร์มการขออนุมัติใช้ห้องเรียน/ ห้องเรียน Hybrid Classroom
 * พิกัด layout วัดจาก template.pdf ด้วย `python tools/build_templates.py --fields room_classroom` (หน่วย pt)
 */
$purposes = [
    'tutorial' => 'จัดสอนเสริมในรายวิชา',
    'exam'     => 'จัดสอบ',
    'activity' => 'จัดกิจกรรม/โครงการ',
    'other'    => 'อื่นๆ',
];

return [
    'title'    => 'แบบฟอร์มการขออนุมัติใช้ห้องเรียน/ ห้องเรียน Hybrid Classroom',
    'short'    => 'ขอใช้ห้องเรียน / ห้องเรียน Hybrid Classroom',
    'category' => 'ขอใช้ห้องเรียน',
    'order'    => 20,
    'dms'      => ['con' => '8041', 'sub' => '52'],

    'sections' => [
        ['title' => 'ข้อมูลผู้ขอใช้ห้อง', 'fields' => requester_fields()],
        ['title' => 'ห้องและวันเวลาที่ขอใช้', 'fields' => [
            ['name' => 'room_kind', 'label' => 'ขออนุมัติใช้', 'type' => 'radio', 'required' => true,
             'options' => ['classroom' => 'ห้องเรียน', 'hybrid' => 'ห้องเรียน Hybrid Classroom']],
            ['name' => 'room_name', 'label' => 'ห้องเรียน', 'type' => 'text', 'required' => true, 'cls' => 'c6',
             'placeholder' => 'เช่น CE09101', 'show_if' => ['room_kind' => ['classroom']]],
            ['name' => 'building', 'label' => 'อาคาร', 'type' => 'text', 'cls' => 'c6',
             'placeholder' => 'เช่น อาคารเรียนรวม (CE)', 'show_if' => ['room_kind' => ['classroom']]],
            ['name' => 'hybrid_room', 'label' => 'ห้อง Hybrid Classroom', 'type' => 'text', 'required' => true,
             'show_if' => ['room_kind' => ['hybrid']]],
            ['name' => 'booking_date', 'label' => 'วันที่ใช้ห้อง', 'type' => 'date', 'required' => true, 'cls' => 'c4', 'min_working_days' => 3],
            ['name' => 'time_start', 'label' => 'ตั้งแต่เวลา', 'type' => 'time', 'required' => true, 'cls' => 'c4'],
            ['name' => 'time_end', 'label' => 'ถึงเวลา', 'type' => 'time', 'required' => true, 'cls' => 'c4', 'after' => 'time_start'],
        ]],
        ['title' => 'โดยมีวัตถุประสงค์เพื่อ', 'fields' => [
            ['name' => 'purpose', 'label' => 'วัตถุประสงค์', 'type' => 'radio', 'required' => true, 'options' => $purposes],
            ['name' => 'purpose_detail', 'label' => 'รายละเอียด', 'type' => 'text', 'required' => true, 'max' => 500,
             'placeholder' => 'เช่น ชื่อรายวิชา / ชื่อกิจกรรม / ชื่อโครงการ'],
            ['name' => 'alt_room', 'label' => 'กรณีห้องเรียนไม่ว่าง ใช้ห้องเรียน (แทน)', 'type' => 'text', 'placeholder' => 'ไม่บังคับ'],
        ]],
    ],

    'summary' => function (array $d) use ($purposes) {
        $room = $d['room_kind'] === 'hybrid'
            ? 'Hybrid Classroom ' . $d['hybrid_room']
            : 'ห้อง ' . $d['room_name'] . ($d['building'] !== '' ? ' อาคาร ' . $d['building'] : '');
        return $room . ' · ' . ($purposes[$d['purpose']] ?? '') . ' ' . $d['purpose_detail'];
    },
    'date_field' => 'booking_date',

    // ---------- พิมพ์ลงแบบฟอร์ม ----------
    'template' => __DIR__ . '/template.svg',
    'values' => function (array $d, string $signedOn) {
        return requester_values($d, $signedOn) + date_time_values($d) + [
            'box_room_' . $d['room_kind'] => '✓',
            'room_name'   => $d['room_name'],
            'building'    => $d['building'],
            'hybrid_room' => $d['hybrid_room'],
            'box_' . $d['purpose'] => '✓',
            $d['purpose'] => $d['purpose_detail'],
            'alt_room'    => $d['alt_room'],
        ];
    },
    'layout' => [
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
    'moves' => [[393, 291, 461, 308.5, 286.7 - 303.8]],
];
