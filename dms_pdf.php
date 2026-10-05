<?php
/**
 * PDF Endpoint สำหรับ DMS (คู่มือ DMS_Connect.md ขั้นตอนที่ 2 และ 4) — ใช้ร่วมกันทุกแบบฟอร์ม
 *
 *  POST ?ref=..&generate=1  — ผู้ใช้กดปุ่ม: สร้าง PDF ลงดิสก์ แล้วตอบ JSON พร้อม URL สำหรับ redirect ไป DMS
 *  GET  ?ref=..             — Connect Path ที่ลงทะเบียนกับ DMS: ส่งไฟล์ PDF ที่สร้างไว้แล้วกลับไปทันที
 *
 * Connect Path เดิม print_booking_pdf.php ยังใช้ได้ (เรียกไฟล์นี้ต่อ)
 */
require_once __DIR__ . '/inc/bootstrap.php';

$ref = (string)($_GET['ref'] ?? '');
if (!preg_match('/^[a-f0-9]{8,32}$/', $ref)) {
    dms_log('400 invalid ref');
    http_response_code(400);
    exit('invalid ref');
}

if (isset($_GET['generate'])) {
    $user = current_user();
    if (!$user) json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบใหม่'], 401);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();
    $s = sub_find($ref);
    if (!$s || !$s['form'] || !sub_owned($s, $user)) json_response(['success' => false, 'message' => 'ไม่พบเอกสาร'], 404);
    if (!form_dms_configured($s['form'])) json_response(['success' => false, 'message' => 'ยังไม่ได้ตั้งค่ารหัส DMS (con/sub) ของแบบฟอร์มนี้'], 500);
    // ตรวจเงื่อนไขของฟอร์มอีกครั้งตอนส่ง เช่น จองล่วงหน้า N วันทำการ (เอกสารที่ DMS รับไปแล้วส่งซ้ำได้)
    if (!sub_locked($s) && ($msg = form_send_error($s['form'], $s['data']))) {
        dms_log('blocked: ' . $msg);
        json_response(['success' => false, 'message' => $msg], 422);
    }

    session_write_close(); // ไม่ล็อก session ระหว่างรอ Chrome
    try {
        // เมื่อผู้ใช้กดส่ง ให้สร้าง PDF ฉบับล่าสุดเสมอเหมือนระบบ car_booking
        pdf_generate($s);
    } catch (Throwable $e) {
        error_log('[eform] pdf error ' . $ref . ': ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'สร้าง PDF ไม่สำเร็จ: ' . $e->getMessage()], 500);
    }
    sub_mark($ref, 'dms_sent_at');
    dms_log('generated, redirect user to ' . dms_link($s));
    json_response(['success' => true, 'redirect' => dms_link($s)]);
}

// ---------- DMS ดึงไฟล์ ----------
$s = sub_find($ref);
if (!$s || !$s['form']) {
    dms_log('404 ref not in database');
    http_response_code(404);
    exit('document not found');
}

$file = pdf_path($ref);
if (!is_file($file)) {
    // ระบบสำรอง: ยังไม่มีไฟล์บนดิสก์ (เช่นถูกลบ) สร้างสดตอนนี้
    try {
        pdf_generate($s);
    } catch (Throwable $e) {
        error_log('[eform] fallback pdf error ' . $ref . ': ' . $e->getMessage());
        dms_log('503 pdf generate failed: ' . $e->getMessage());
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        exit('<p>ยังไม่มีไฟล์เอกสาร กรุณากลับไปที่ระบบ E-Form แล้วกด "ส่งเข้าระบบ DMS" อีกครั้ง</p>');
    }
}

if ($s['dms_fetched_at'] === null) sub_mark($ref, 'dms_fetched_at');

dms_log('200 sent pdf ' . filesize($file) . ' bytes');
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . $s['form_code'] . '_' . $ref . '.pdf"');
header('Cache-Control: no-store');
readfile($file);
