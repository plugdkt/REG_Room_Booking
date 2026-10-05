<?php
/**
 * คัดลอกไฟล์นี้เป็น config.php แล้วแก้ไขค่าให้ตรงกับเซิร์ฟเวอร์จริง
 * (config.php ถูกบล็อกไม่ให้เข้าถึงจากเว็บผ่าน web.config)
 */
return [
    // URL สาธารณะของระบบ (ไม่มี / ปิดท้าย) — DMS จะเรียก Connect Path ผ่าน URL นี้
    'base_url' => 'https://www.medsci.up.ac.th/eform',

    // URL ที่ Chrome Headless บนเซิร์ฟเวอร์ใช้เปิดหน้าเอกสาร — เว้นว่าง = ใช้ base_url (แบบเดียวกับระบบขอรถ)
    // ใส่ค่าเฉพาะเมื่อเซิร์ฟเวอร์เรียกโดเมนของตัวเองไม่ได้ เช่น 'http://localhost/eform'
    'internal_base_url' => '',

    'db' => [
        'dsn'      => 'mysql:host=127.0.0.1;dbname=reg_room_booking;charset=utf8mb4',
        'user'     => 'reg_room_booking',
        'password' => 'CHANGE_ME',
    ],

    // ใช้ลงลายมือชื่อ URL ที่ให้ Chrome เปิดหน้าพิมพ์โดยไม่ต้องล็อกอิน — ตั้งเป็นค่าสุ่มยาวๆ
    'app_secret' => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',

    // ---------- การยืนยันตัวตน ----------
    // 'sso'  = เข้าสู่ระบบส่วนกลางผ่าน MSC_ACC (Single Sign-On)
    // 'ldap' = ตรวจสอบกับ LDAP/AD ของมหาวิทยาลัย (ต้องเปิด extension=ldap ใน php.ini)
    // 'dev'  = โหมดทดสอบ รับทุก username/password ห้ามใช้บนเซิร์ฟเวอร์จริง
    'auth' => [
        'mode' => 'sso',
        'sso' => [
            'login_url'     => 'https://www.medsci.up.ac.th/msc_acc/sso/login.php',
            'verify_url'    => 'https://www.medsci.up.ac.th/msc_acc/api/verify.php',
            'client_id'     => 'EFORM',
            'client_secret' => 'CHANGE_ME',
        ],
        'ldap' => [
            'uri'          => 'ldaps://ldap.example.up.ac.th:636',
            // %s จะถูกแทนด้วย username ที่ผู้ใช้กรอก เช่น 'UP\\%s' หรือ 'uid=%s,ou=people,dc=up,dc=ac,dc=th'
            'bind_format'  => '%s@up.ac.th',
            'base_dn'      => 'dc=up,dc=ac,dc=th',
            'search_filter'=> '(sAMAccountName=%s)',
            // attribute ที่ใช้เติมข้อมูลผู้ขอล่วงหน้า (ถ้าไม่มีให้เว้นว่าง)
            'attr_name'       => 'displayName',
            'attr_department' => 'department',
            'attr_phone'      => 'telephoneNumber',
            'attr_position'   => 'title',
        ],
    ],

    // ---------- PDF (Chrome Headless) ----------
    'chrome_path'     => 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'pdf_dir'         => __DIR__ . '/uploads/dms',
    'chrome_profile'  => __DIR__ . '/uploads/dms/chrome_profile',
    'pdf_timeout_sec' => 60,

    // ---------- DMS ----------
    // Connect Path ที่ต้องลงทะเบียนกับ DMS: {base_url}/print_booking_pdf.php?ref={ref}
    'dms' => [
        'link_url' => 'https://dms.up.ac.th/dms_main/data/ck_link_conect.aspx',
        'forms' => [
            'alc'       => ['con' => '8041', 'sub' => '52'],  // แบบฟอร์มขอใช้ห้อง Active Learning Classroom
            'classroom' => ['con' => '8041', 'sub' => '52'],  // แบบฟอร์มขอใช้ห้องเรียน / Hybrid Classroom
        ],
    ],

    // ---------- กติกาการจอง ----------
    'min_working_days' => 3,      // ต้องจองล่วงหน้าอย่างน้อยกี่วันทำการ
    'holidays' => [               // วันหยุดราชการ (YYYY-MM-DD) ที่ไม่นับเป็นวันทำการ
        // '2026-10-13', '2026-10-23',
    ],

    // ชื่อผู้อำนวยการกองบริการการศึกษาที่พิมพ์ในแบบฟอร์ม
    'director_name'     => 'นางสาววิไลลักษณ์ ครุฑปาน',
    'director_position' => 'รักษาการแทนผู้อำนวยการกองบริการการศึกษา',
];
