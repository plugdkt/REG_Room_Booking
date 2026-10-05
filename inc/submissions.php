<?php
declare(strict_types=1);

/**
 * เอกสารที่ผู้ใช้กรอก (ตาราง submissions) — ข้อมูลแต่ละฟอร์มเก็บเป็น JSON ในคอลัมน์ data
 * จึงเพิ่มแบบฟอร์มใหม่ได้โดยไม่ต้องแก้ฐานข้อมูล
 */

function sub_decode(array|false $row): ?array
{
    if (!$row) return null;
    $row['data'] = json_decode((string)$row['data'], true) ?: [];
    $row['form'] = form_get($row['form_code']);
    return $row;
}

function sub_find(string $ref): ?array
{
    $st = db()->prepare('SELECT * FROM submissions WHERE ref = ?');
    $st->execute([$ref]);
    return sub_decode($st->fetch());
}

/** เอกสารที่ผู้ใช้เปิดได้: เจ้าของ หรือผู้ดูแลระบบ — ไม่เจอ = 404 */
function sub_for_user(string $ref, array $user): array
{
    $s = preg_match('/^[a-f0-9]{8,32}$/', $ref) ? sub_find($ref) : null;
    if (!$s || !$s['form'] || ($s['user_login'] !== $user['login'] && !is_admin($user))) {
        http_response_code(404);
        exit('ไม่พบเอกสาร');
    }
    return $s;
}

function sub_owned(array $s, array $user): bool
{
    return $s['user_login'] === $user['login'];
}

function sub_list_user(string $login): array
{
    $st = db()->prepare('SELECT * FROM submissions WHERE user_login = ? ORDER BY created_at DESC');
    $st->execute([$login]);
    return array_map('sub_decode', $st->fetchAll());
}

/**
 * ค้นหาเอกสารทั้งหมด (หน้าผู้ดูแล)
 * @param array{form?:string,status?:string,q?:string,from?:string,to?:string} $f
 * @return array{0: array, 1: int} [รายการ, จำนวนทั้งหมด]
 */
function sub_search(array $f, int $limit = 50, int $offset = 0): array
{
    $where = [];
    $args = [];
    if (($f['form'] ?? '') !== '') { $where[] = 'form_code = ?'; $args[] = $f['form']; }
    switch ($f['status'] ?? '') {
        case 'draft':    $where[] = 'dms_sent_at IS NULL AND dms_fetched_at IS NULL'; break;
        case 'pending':  $where[] = 'dms_sent_at IS NOT NULL AND dms_fetched_at IS NULL'; break;
        case 'received': $where[] = 'dms_fetched_at IS NOT NULL'; break;
    }
    if (($f['q'] ?? '') !== '') {
        $where[] = '(ref = ? OR user_login LIKE ? OR data LIKE ?)';
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        array_push($args, $f['q'], $like, $like);
    }
    if (($f['from'] ?? '') !== '') { $where[] = 'created_at >= ?'; $args[] = $f['from'] . ' 00:00:00'; }
    if (($f['to'] ?? '') !== '')   { $where[] = 'created_at <= ?'; $args[] = $f['to'] . ' 23:59:59'; }
    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $st = db()->prepare("SELECT COUNT(*) FROM submissions $sqlWhere");
    $st->execute($args);
    $total = (int)$st->fetchColumn();

    $st = db()->prepare("SELECT * FROM submissions $sqlWhere ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $st->execute($args);
    return [array_map('sub_decode', $st->fetchAll()), $total];
}

/** ตัวกรองของหน้าผู้ดูแล จาก query string — ค่าที่ไม่ถูกต้องถูกทิ้งเป็น '' */
function admin_filters(): array
{
    $date = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? (string)$v : '';
    $form = (string)($_GET['form'] ?? '');
    $status = (string)($_GET['status'] ?? '');
    return [
        'form'   => form_get($form) ? $form : '',
        'status' => isset(SUB_STATUSES[$status]) ? $status : '',
        'q'      => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100),
        'from'   => $date($_GET['from'] ?? ''),
        'to'     => $date($_GET['to'] ?? ''),
    ];
}

function sub_locked(array $s): bool
{
    return $s['dms_fetched_at'] !== null;
}

function sub_status(array $s): array
{
    if ($s['dms_fetched_at']) return ['DMS รับเอกสารแล้ว', 'sent'];
    if ($s['dms_sent_at'])    return ['กดส่ง DMS แล้ว', 'pending'];
    return ['ฉบับร่าง', 'draft'];
}

const SUB_STATUSES = ['draft' => 'ฉบับร่าง', 'pending' => 'กดส่ง DMS แล้ว', 'received' => 'DMS รับเอกสารแล้ว'];

function sub_summary(array $s): string
{
    $form = $s['form'];
    return isset($form['summary']) ? trim(($form['summary'])($s['data'])) : $form['short'];
}

function sub_json(array $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function sub_create(string $code, array $user, array $data): string
{
    $sql = 'INSERT INTO submissions (ref, form_code, user_login, data) VALUES (?, ?, ?, ?)';
    for ($try = 0; ; $try++) {
        // DMS ใช้ ref เป็นตัวเลข (เหมือนระบบขอรถ) จึงสุ่มเลข 9 หลัก — อยู่ในช่วง int 32 บิต และเดาเลขของคนอื่นไม่ได้
        $ref = (string)random_int(100000000, 999999999);
        try {
            db()->prepare($sql)->execute([$ref, $code, $user['login'], sub_json($data)]);
            return $ref;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' || $try >= 5) throw $e; // 23000 = ref ซ้ำ สุ่มใหม่
        }
    }
}

function sub_update(string $ref, array $data): void
{
    // แก้ไขข้อมูลแล้ว PDF เดิมใช้ไม่ได้ ต้องสร้างใหม่ตอนส่ง
    db()->prepare('UPDATE submissions SET data = ?, pdf_generated_at = NULL, dms_sent_at = NULL WHERE ref = ? AND dms_fetched_at IS NULL')
        ->execute([sub_json($data), $ref]);
    @unlink(pdf_path($ref));
}

function sub_delete(string $ref): void
{
    db()->prepare('DELETE FROM submissions WHERE ref = ? AND dms_fetched_at IS NULL')->execute([$ref]);
    @unlink(pdf_path($ref));
}

function sub_mark(string $ref, string $column): void
{
    if (!in_array($column, ['pdf_generated_at', 'dms_sent_at', 'dms_fetched_at'], true)) return;
    db()->prepare("UPDATE submissions SET $column = NOW() WHERE ref = ?")->execute([$ref]);
}

// ---------- DMS ----------

function dms_link(array $s): string
{
    return config('dms.link_url') . '?' . http_build_query([
        'ref' => $s['ref'],
        'con' => $s['form']['dms']['con'] ?? '',
        'sub' => $s['form']['dms']['sub'] ?? '',
    ]);
}

/** บันทึกทุกครั้งที่มีการเรียก endpoint ของ DMS ไว้ตรวจสอบ (uploads/dms/dms_access.log) */
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

// ---------- PDF ----------

function pdf_path(string $ref): string
{
    return rtrim((string)config('pdf_dir'), '/\\') . DIRECTORY_SEPARATOR . preg_replace('/[^a-f0-9]/', '', $ref) . '.pdf';
}

/**
 * สร้าง PDF ด้วย Chrome Headless (ตามคู่มือ DMS_Connect.md ขั้นตอนที่ 2)
 * @throws RuntimeException เมื่อสร้างไม่สำเร็จ
 */
function pdf_generate(array $s): string
{
    $chrome  = (string)config('chrome_path');
    $profile = (string)config('chrome_profile');
    $target  = pdf_path($s['ref']);
    $tmp     = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';

    $dir     = dirname($target);
    $chromeLog = $dir . DIRECTORY_SEPARATOR . 'chrome_last.log';

    if (!is_file($chrome)) throw new RuntimeException('ไม่พบ Chrome ที่ ' . $chrome . ' (แก้ chrome_path ใน config.php)');

    // 1) โฟลเดอร์ต้องเขียนได้โดย identity ของ IIS App Pool
    $probe = $dir . DIRECTORY_SEPARATOR . '.write_test';
    if (@file_put_contents($probe, 'x') === false) {
        throw new RuntimeException("โปรเซสเว็บเขียนไฟล์ในโฟลเดอร์ $dir ไม่ได้ — ให้สิทธิ์ Modify แก่ IIS_IUSRS (ดู README ขั้นตอนที่ 4)");
    }
    @unlink($probe);
    if (!is_dir($profile) && !@mkdir($profile, 0775, true)) throw new RuntimeException("สร้างโฟลเดอร์ $profile ไม่ได้ — ตรวจสอบสิทธิ์โฟลเดอร์");

    $base = rtrim((string)(config('internal_base_url') ?: config('base_url')), '/');
    $printUrl = $base . '/print.php?ref=' . rawurlencode($s['ref']);

    // บน Windows IIS ใช้ cmd.exe /c เพื่อไม่ให้ติด Access Denied ของ ProcessSingleton
    // --virtual-time-budget: รอให้ฟอนต์ที่ฝังโหลดและสคริปต์จัดข้อความทำงานเสร็จก่อนพิมพ์
    $cmd = 'cmd.exe /c ""' . $chrome . '" --headless=new --disable-gpu --no-sandbox --disable-crash-reporter --virtual-time-budget=5000 --print-background --print-to-pdf-no-header --user-data-dir="' . $profile . '" --print-to-pdf="' . $tmp . '" "' . $printUrl . '" > "' . $chromeLog . '" 2>&1"';
    exec($cmd);

    if (!is_file($tmp) || filesize($tmp) < 1000) {
        @unlink($tmp);
        $tail = trim(implode("\n", array_slice(@file($chromeLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -3)));
        throw new RuntimeException("Chrome ไม่ได้สร้างไฟล์ PDF" . ($tail !== '' ? " — Chrome: $tail" : '')
            . " — log เต็มอยู่ที่ $chromeLog");
    }
    @unlink($target);
    if (!rename($tmp, $target)) throw new RuntimeException('บันทึกไฟล์ PDF ไม่สำเร็จ');

    sub_mark($s['ref'], 'pdf_generated_at');
    return $target;
}

// ---------- สิทธิ์ ----------

/** ผู้ดูแลระบบ/เจ้าหน้าที่: ดูและส่งออกเอกสารของทุกคน — กำหนดใน config.php 'admins' => ['login', ...] */
function is_admin(?array $user): bool
{
    if (!$user) return false;
    return in_array(strtolower($user['login']), array_map('strtolower', (array)config('admins', [])), true);
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        http_response_code(403);
        exit('หน้านี้สำหรับผู้ดูแลระบบ');
    }
    return $user;
}
