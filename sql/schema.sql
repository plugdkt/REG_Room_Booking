CREATE DATABASE IF NOT EXISTS reg_room_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reg_room_booking;

CREATE TABLE IF NOT EXISTS bookings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref             VARCHAR(32)  NOT NULL UNIQUE,          -- รหัสอ้างอิงที่ส่งให้ DMS
    form_type       ENUM('alc','classroom') NOT NULL,
    user_login      VARCHAR(100) NOT NULL,                 -- UP Account ของผู้สร้าง

    prefix          VARCHAR(20)  NOT NULL,
    fullname        VARCHAR(200) NOT NULL,
    faculty         VARCHAR(200) NOT NULL,                 -- คณะ/วิทยาลัย/กอง/ศูนย์
    department      VARCHAR(200) NOT NULL DEFAULT '',      -- สาขาวิชา/ส่วนงาน
    phone           VARCHAR(50)  NOT NULL,
    position        VARCHAR(200) NOT NULL,

    room_kind       ENUM('classroom','hybrid') NULL,       -- เฉพาะ form_type = classroom
    room_name       VARCHAR(200) NOT NULL DEFAULT '',
    building        VARCHAR(200) NOT NULL DEFAULT '',
    alt_room        VARCHAR(200) NOT NULL DEFAULT '',      -- กรณีห้องไม่ว่างใช้ห้อง...แทน

    booking_date    DATE NOT NULL,
    time_start      TIME NOT NULL,
    time_end        TIME NOT NULL,

    purpose         VARCHAR(20)  NOT NULL,
    purpose_detail  VARCHAR(500) NOT NULL DEFAULT '',

    pdf_generated_at DATETIME NULL,
    dms_sent_at      DATETIME NULL,                        -- ผู้ใช้กดส่งเข้า DMS
    dms_fetched_at   DATETIME NULL,                        -- DMS ดึงไฟล์ PDF ไปแล้ว (ล็อกการแก้ไข)

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_login, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
