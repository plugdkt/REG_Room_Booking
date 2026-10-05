<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    flash('เข้าสู่ระบบไม่สำเร็จ: ไม่พบโทเคนยืนยันสิทธิ์จากระบบ MSC_ACC', 'err');
    redirect('login.php');
}

$sso = config('auth.sso');
$verifyUrl = (string)($sso['verify_url'] ?? 'https://www.medsci.up.ac.th/msc_acc/api/verify.php');
$clientId = (string)($sso['client_id'] ?? 'EFORM');
$clientSecret = (string)($sso['client_secret'] ?? '');

$ch = curl_init($verifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'token'         => $token,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    $err = curl_error($ch);
    curl_close($ch);
    flash('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ MSC_ACC เพื่อยืนยันโทเคนได้: ' . $err, 'err');
    redirect('login.php');
}
curl_close($ch);

$res = json_decode((string)$response, true);
if (!$res || ($res['status'] ?? '') !== 'success' || empty($res['user'])) {
    $msg = $res['message'] ?? 'โทเคนยืนยันสิทธิ์ไม่ถูกต้องหรือหมดอายุ';
    flash('เข้าสู่ระบบไม่สำเร็จ: ' . $msg, 'err');
    redirect('login.php');
}

$u = $res['user'];
$username = trim((string)($u['username'] ?? ''));
if ($username === '') {
    flash('เข้าสู่ระบบไม่สำเร็จ: ไม่พบชื่อผู้ใช้งานในระบบ MSC_ACC', 'err');
    redirect('login.php');
}

// ผูกข้อมูลเข้ากับรูปแบบ user session ของ eform
$user = [
    'login'      => strtolower($username),
    'name'       => trim((string)($u['name'] ?? $username)),
    'department' => trim((string)($u['div_name'] ?? 'คณะวิทยาศาสตร์การแพทย์')),
    'phone'      => trim((string)($u['phone'] ?? '')),
    'position'   => trim((string)($u['pos_name'] ?? 'บุคลากร')),
];

login_user($user);

// ตรวจสอบหน้าปลายทางที่ต้องการให้กลับไปหลังจากล็อกอิน
start_session();
$next = $_SESSION['sso_next'] ?? 'index.php';
unset($_SESSION['sso_next']);

redirect($next);
