<?php
/**
 * PDF Endpoint (คู่มือ DMS_Connect.md ขั้นตอนที่ 2 และ 4)
 *
 *  POST ?ref=..&generate=1  — ผู้ใช้กดปุ่ม: สร้าง PDF ลงดิสก์ แล้วตอบ JSON พร้อม URL สำหรับ redirect ไป DMS
 *  GET  ?ref=..             — Connect Path ที่ลงทะเบียนกับ DMS: ส่งไฟล์ PDF ที่สร้างไว้แล้วกลับไปทันที
 */
require __DIR__ . '/inc/bootstrap.php';

/** บันทึกทุกครั้งที่มีการเรียก endpoint นี้ ไว้ตรวจสอบว่า DMS มาดึงไฟล์หรือไม่ (uploads/dms/dms_access.log) */
function dms_log(string $result): void
{
    $line = sprintf("[%s] %s %s %s ?%s -> %s | %s\n",
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? '-',
        $_SERVER['REQUEST_METHOD'] ?? '-',
        $_SERVER['SCRIPT_NAME'] ?? '-',
        $_SERVER['QUERY_STRING'] ?? '',
        $result,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 120));
    @file_put_contents(rtrim((string)config('pdf_dir'), '/\\') . '/dms_access.log', $line, FILE_APPEND | LOCK_EX);
}

$ref = (string)($_GET['ref'] ?? '');
if (!preg_match('/^[a-f0-9]{8,32}$/', $ref)) {
    dms_log('400 invalid ref');
    http_response_code(400);
    exit('invalid ref');
}

if (isset($_GET['generate'])) {
    $user = current_user();
    if (!$user) json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบใหม่'], 401);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
    }
    $b = booking_find($ref);
    if (!$b || $b['user_login'] !== $user['login']) json_response(['success' => false, 'message' => 'ไม่พบเอกสาร'], 404);
    if (!dms_configured($b['form_type'])) json_response(['success' => false, 'message' => 'ยังไม่ได้ตั้งค่ารหัส DMS (con/sub)'], 500);
    // ตรวจเงื่อนไข 3 วันทำการอีกครั้งตอนส่ง (เอกสารที่ DMS รับไปแล้วส่งซ้ำได้)
    if (!booking_locked($b) && ($msg = booking_date_too_soon($b['booking_date']))) {
        dms_log('blocked: booking date too soon ' . $b['booking_date']);
        json_response(['success' => false, 'message' => $msg . ' กรุณาแก้ไขวันที่ใช้ห้องในเอกสารก่อนส่ง'], 422);
    }

    session_write_close(); // ไม่ล็อก session ระหว่างรอ Chrome
    try {
        // เมื่อผู้ใช้กดส่ง ให้สร้าง PDF ฉบับล่าสุดเสมอเหมือนระบบ car_booking
        pdf_generate($b);
    } catch (Throwable $e) {
        error_log('[reg_room_booking] pdf error ' . $ref . ': ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'สร้าง PDF ไม่สำเร็จ: ' . $e->getMessage()], 500);
    }
    db()->prepare('UPDATE bookings SET dms_sent_at = NOW() WHERE ref = ?')->execute([$ref]);
    dms_log('generated, redirect user to ' . dms_link($b));
    json_response(['success' => true, 'redirect' => dms_link($b)]);
}

// ---------- DMS ดึงไฟล์ ----------
$b = booking_find($ref);
if (!$b) {
    dms_log('404 ref not in database');
    http_response_code(404);
    exit('document not found');
}

$file = pdf_path($ref);
if (!is_file($file)) {
    // ระบบสำรอง: ยังไม่มีไฟล์บนดิสก์ (เช่นถูกลบ) สร้างสดตอนนี้
    try {
        pdf_generate($b);
    } catch (Throwable $e) {
        error_log('[reg_room_booking] fallback pdf error ' . $ref . ': ' . $e->getMessage());
        dms_log('503 pdf generate failed: ' . $e->getMessage());
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        exit('<p>ยังไม่มีไฟล์เอกสาร กรุณากลับไปที่ระบบขอใช้ห้องเรียนแล้วกด "ส่งเข้าระบบ DMS" อีกครั้ง</p>');
    }
}

if ($b['dms_fetched_at'] === null) {
    db()->prepare('UPDATE bookings SET dms_fetched_at = NOW() WHERE ref = ?')->execute([$ref]);
}

dms_log('200 sent pdf ' . filesize($file) . ' bytes');
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="room_booking_' . $ref . '.pdf"');
header('Cache-Control: no-store');
readfile($file);
