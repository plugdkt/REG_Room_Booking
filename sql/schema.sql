CREATE DATABASE IF NOT EXISTS reg_room_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reg_room_booking;

-- เอกสารที่ผู้ใช้กรอก ทุกแบบฟอร์มใช้ตารางเดียว ข้อมูลแต่ละช่องเก็บเป็น JSON ใน data
CREATE TABLE IF NOT EXISTS submissions (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref              VARCHAR(32)  NOT NULL UNIQUE,   -- รหัสอ้างอิงที่ส่งให้ DMS (ตัวเลข 9 หลัก)
    form_code        VARCHAR(50)  NOT NULL,          -- ชื่อโฟลเดอร์ใน forms/
    user_login       VARCHAR(100) NOT NULL,          -- UP Account ของผู้สร้าง
    data             LONGTEXT     NOT NULL,          -- JSON {ชื่อช่อง: ค่า}

    pdf_generated_at DATETIME NULL,
    dms_sent_at      DATETIME NULL,                  -- ผู้ใช้กดส่งเข้า DMS
    dms_fetched_at   DATETIME NULL,                  -- DMS ดึงไฟล์ PDF ไปแล้ว (ล็อกการแก้ไข)

    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_login, created_at),
    INDEX idx_form (form_code, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
