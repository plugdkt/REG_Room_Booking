<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

define('APP_ROOT', dirname(__DIR__));

if (!is_file(APP_ROOT . '/config.php')) {
    http_response_code(500);
    exit('Missing config.php — copy config.sample.php to config.php and edit it.');
}
$GLOBALS['CONFIG'] = require APP_ROOT . '/config.php';

function config(string $key, $default = null)
{
    $value = $GLOBALS['CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(config('db.dsn'), config('db.user'), config('db.password'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string)config('base_url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    // URL เต็ม หรือ path จาก root (เช่น /eform/booking_view.php จาก REQUEST_URI) ใช้ตามนั้น ไม่เติม base_url ซ้ำ
    header('Location: ' . (preg_match('~^(https?://|/)~', $path) ? $path : url($path)));
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('REGROOMSESS');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => str_starts_with((string)config('base_url'), 'https://'),
        ]);
        session_start();
    }
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    start_session();
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่');
    }
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    start_session();
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** ลายเซ็น HMAC สำหรับ URL หน้าพิมพ์ที่ Chrome Headless เปิดโดยไม่มี session */
function sign_ref(string $ref): string
{
    return hash_hmac('sha256', 'print:' . $ref, (string)config('app_secret'));
}

// ---------- วันที่ภาษาไทย ----------

const THAI_MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const THAI_DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

/** แยกวันที่เป็นส่วนๆ ตามช่องในแบบฟอร์ม: วัน / ที่ / เดือน / พ.ศ. */
function thai_date_parts(string $ymd): array
{
    $t = strtotime($ymd);
    return [
        'dayname' => THAI_DAYS[(int)date('w', $t)],
        'day'     => (string)(int)date('j', $t),
        'month'   => THAI_MONTHS[(int)date('n', $t)],
        'year'    => (string)((int)date('Y', $t) + 543),
    ];
}

function thai_date(string $ymd, bool $withDay = false): string
{
    $p = thai_date_parts($ymd);
    return ($withDay ? 'วัน' . $p['dayname'] . 'ที่ ' : '') . "{$p['day']} {$p['month']} {$p['year']}";
}

/** รูปแบบ dd/mm/yyyy (พ.ศ.) ตามช่องวันที่ลงนามในแบบฟอร์ม */
function thai_short_date(string $ymd): string
{
    $t = strtotime($ymd);
    return date('j', $t) . '/' . THAI_MONTHS[(int)date('n', $t)] . '/' . ((int)date('Y', $t) + 543);
}

function hm(string $time): string
{
    return substr($time, 0, 5);
}

/** วันทำการถัดไปที่ n นับจากวันนี้ (ไม่นับเสาร์-อาทิตย์และวันหยุดใน config) */
function earliest_booking_date(int $workingDays, ?string $from = null): string
{
    $holidays = array_flip((array)config('holidays', []));
    $t = strtotime($from ?? date('Y-m-d'));
    $count = 0;
    while ($count < $workingDays) {
        $t = strtotime('+1 day', $t);
        $dow = (int)date('N', $t);
        if ($dow < 6 && !isset($holidays[date('Y-m-d', $t)])) {
            $count++;
        }
    }
    return date('Y-m-d', $t);
}

require __DIR__ . '/auth.php';
require __DIR__ . '/forms.php';
require __DIR__ . '/submissions.php';
