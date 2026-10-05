<?php
declare(strict_types=1);

/**
 * แบบฟอร์มการขออนุมัติใช้ห้อง Active Learning Classroom
 * พิกัด layout วัดจาก template.pdf ด้วย `python tools/build_templates.py --fields room_alc` (หน่วย pt)
 */
$purposes = [
    'activity' => 'จัดกิจกรรม',
    'project'  => 'โครงการ',
    'seminar'  => 'สัมมนา',
    'other'    => 'อื่น',
];

return [
    'title'    => 'แบบฟอร์มการขออนุมัติใช้ห้อง Active Learning Classroom',
    'short'    => 'ขอใช้ห้อง Active Learning Classroom',
    'category' => 'ขอใช้ห้องเรียน',
    'order'    => 10,
    'dms'      => ['con' => '8041', 'sub' => '52'],

    'sections' => [
        ['title' => 'ข้อมูลผู้ขอใช้ห้อง', 'fields' => requester_fields()],
        ['title' => 'วันเวลาที่ขอใช้', 'intro' => 'ขออนุมัติใช้ห้อง <strong>Active Learning Classroom</strong>', 'fields' => [
            ['name' => 'booking_date', 'label' => 'วันที่ใช้ห้อง', 'type' => 'date', 'required' => true, 'cls' => 'c4', 'min_working_days' => 3],
            ['name' => 'time_start', 'label' => 'ตั้งแต่เวลา', 'type' => 'time', 'required' => true, 'cls' => 'c4'],
            ['name' => 'time_end', 'label' => 'ถึงเวลา', 'type' => 'time', 'required' => true, 'cls' => 'c4', 'after' => 'time_start'],
        ]],
        ['title' => 'โดยมีวัตถุประสงค์เพื่อ', 'fields' => [
            ['name' => 'purpose', 'label' => 'วัตถุประสงค์', 'type' => 'radio', 'required' => true, 'options' => $purposes],
            ['name' => 'purpose_detail', 'label' => 'รายละเอียด', 'type' => 'text', 'required' => true, 'max' => 500,
             'placeholder' => 'เช่น ชื่อกิจกรรม / ชื่อโครงการ / หัวข้อสัมมนา'],
        ]],
    ],

    'summary'    => fn(array $d) => 'Active Learning Classroom · ' . ($purposes[$d['purpose']] ?? '') . ' ' . $d['purpose_detail'],
    'date_field' => 'booking_date',

    // ---------- พิมพ์ลงแบบฟอร์ม ----------
    'template' => __DIR__ . '/template.svg',
    'values' => function (array $d, string $signedOn) {
        return requester_values($d, $signedOn) + date_time_values($d) + [
            'box_' . $d['purpose'] => '✓',
            $d['purpose']          => $d['purpose_detail'],
        ];
    },
    // [x เริ่ม, x สิ้นสุด, y เส้นฐาน, จัดชิด l|c] — box_* คือวงเล็บ ( ) สำหรับ ✓
    'layout' => [
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
    // ย้ายหัวข้อ "ผู้ขอใช้ห้องเรียน" ขึ้นหนึ่งบรรทัด เว้นที่ว่างเหนือ (ชื่อ) ไว้ลงลายเซ็นใน DMS: [x0, y0, x1, y1, dy]
    'moves' => [[408, 289, 476, 306.5, 280.6 - 301.7]],
];
