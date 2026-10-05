<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

/**
 * แสดงหน้าแจ้งข้อผิดพลาดแทนการ redirect กลับ login.php
 * (login.php ในโหมด sso จะส่งกลับไป SSO ทันที ถ้า SSO ยังจำการล็อกอินไว้จะวนไม่จบและไม่เห็นข้อความ)
 */
function sso_fail(string $msg): never
{
    http_response_code(401);
    page_header('เข้าสู่ระบบไม่สำเร็จ');
    ?>
    <div class="card login">
      <h1>เข้าสู่ระบบไม่สำเร็จ</h1>
      <div class="alert alert-err"><?= h($msg) ?></div>
      <div class="actions"><a class="btn btn-primary" href="<?= h(url('login.php')) ?>">ลองเข้าสู่ระบบอีกครั้ง</a></div>
    </div>
    <?php
    page_footer();
    exit;
}

start_session();

// 1. ตรวจ state ก่อน ป้องกัน Login CSRF แล้วลบทิ้งทันที (ใช้ได้ครั้งเดียว)
$state = (string)($_GET['state'] ?? '');
$savedState = (string)($_SESSION['sso_state'] ?? '');
unset($_SESSION['sso_state']);
if ($state === '' || $savedState === '' || !hash_equals($savedState, $state)) {
    sso_fail('คำขอเข้าสู่ระบบหมดอายุหรือไม่ถูกต้อง กรุณาเริ่มเข้าสู่ระบบใหม่จากหน้านี้');
}

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    sso_fail('ไม่พบโทเคนยืนยันสิทธิ์จากระบบ MSC_ACC');
}

// 2. ส่ง token ไปตรวจกับ API ระบบกลาง
$sso = config('auth.sso');
$verifyUrl = (string)($sso['verify_url'] ?? 'https://www.medsci.up.ac.th/msc_acc/api/verify.php');

$ch = curl_init($verifyUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'token'         => $token,
        'client_id'     => (string)($sso['client_id'] ?? ''),
        'client_secret' => (string)($sso['client_secret'] ?? ''),
    ]),
    // ตรวจใบรับรอง SSL เสมอ เพราะส่ง client_secret ไปด้วย — ปิดได้เฉพาะเครื่องทดสอบผ่าน auth.sso.verify_ssl = false
    CURLOPT_SSL_VERIFYPEER => (bool)($sso['verify_ssl'] ?? true),
    CURLOPT_SSL_VERIFYHOST => ($sso['verify_ssl'] ?? true) ? 2 : 0,
    CURLOPT_TIMEOUT        => 15,
]);
$response = curl_exec($ch);
$curlError = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);

if ($curlError !== '') {
    error_log('[eform] SSO verify connection error: ' . $curlError);
    sso_fail('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ MSC_ACC เพื่อยืนยันโทเคนได้: ' . $curlError);
}

$res = json_decode((string)$response, true);
if (!is_array($res) || ($res['status'] ?? '') !== 'success' || empty($res['user'])) {
    sso_fail((string)($res['message'] ?? 'โทเคนยืนยันสิทธิ์ไม่ถูกต้องหรือหมดอายุ'));
}

$u = $res['user'];
$username = trim((string)($u['username'] ?? ''));
if ($username === '') {
    sso_fail('ไม่พบชื่อผู้ใช้งานในระบบ MSC_ACC');
}

// 3. ผูกข้อมูลเข้ากับรูปแบบ user session ของ eform
$next = (string)($_SESSION['sso_next'] ?? 'index.php');
unset($_SESSION['sso_next']);

login_user([
    'login'      => strtolower($username),
    'name'       => trim((string)($u['name'] ?? $username)),
    'department' => trim((string)($u['div_name'] ?? 'คณะวิทยาศาสตร์การแพทย์')),
    'phone'      => trim((string)($u['phone'] ?? '')),
    'position'   => trim((string)($u['pos_name'] ?? 'บุคลากร')),
]);

// redirect ออกจากหน้า callback ทันที ไม่ให้ URL ที่มี ?token= ค้างในประวัติเบราว์เซอร์
redirect($next);
