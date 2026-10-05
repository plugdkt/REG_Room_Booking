<?php
/**
 * PDF Endpoint (คู่มือ DMS_Connect.md ขั้นตอนที่ 2 และ 4)
 *
 *  POST ?ref=..&generate=1  — ผู้ใช้กดปุ่ม: สร้าง PDF ลงดิสก์ แล้วตอบ JSON พร้อม URL สำหรับ redirect ไป DMS
 *  GET  ?ref=..             — Connect Path ที่ลงทะเบียนกับ DMS: ส่งไฟล์ PDF ที่สร้างไว้แล้วกลับไปทันที
 */
require __DIR__ . '/inc/bootstrap.php';

$ref = (string)($_GET['ref'] ?? '');
if (!preg_match('/^[a-f0-9]{8,32}$/', $ref)) {
    http_response_code(400);
    exit('invalid ref');
}

if (isset($_GET['generate'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'POST only'], 405);
    $user = current_user();
    if (!$user) json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบใหม่'], 401);
    csrf_check();
    $b = booking_find($ref);
    if (!$b || $b['user_login'] !== $user['login']) json_response(['success' => false, 'message' => 'ไม่พบเอกสาร'], 404);
    if (!dms_configured($b['form_type'])) json_response(['success' => false, 'message' => 'ยังไม่ได้ตั้งค่ารหัส DMS (con/sub)'], 500);

    session_write_close(); // ไม่ล็อก session ระหว่างรอ Chrome
    try {
        // เอกสารที่ DMS รับไปแล้วใช้ไฟล์เดิม เพื่อให้ตรงกับฉบับที่อยู่ใน DMS
        if (!booking_locked($b) || !is_file(pdf_path($ref))) {
            pdf_generate($b);
        }
    } catch (Throwable $e) {
        error_log('[reg_room_booking] pdf error ' . $ref . ': ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'สร้าง PDF ไม่สำเร็จ: ' . $e->getMessage()], 500);
    }
    db()->prepare('UPDATE bookings SET dms_sent_at = NOW() WHERE ref = ?')->execute([$ref]);
    json_response(['success' => true, 'redirect' => dms_link($b)]);
}

// ---------- DMS ดึงไฟล์ ----------
$b = booking_find($ref);
if (!$b) {
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
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        exit('<p>ยังไม่มีไฟล์เอกสาร กรุณากลับไปที่ระบบขอใช้ห้องเรียนแล้วกด "ส่งเข้าระบบ DMS" อีกครั้ง</p>');
    }
}

if ($b['dms_fetched_at'] === null) {
    db()->prepare('UPDATE bookings SET dms_fetched_at = NOW() WHERE ref = ?')->execute([$ref]);
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="room_booking_' . $ref . '.pdf"');
header('Cache-Control: no-store');
readfile($file);
