<?php
declare(strict_types=1);

/**
 * ทะเบียนแบบฟอร์ม: 1 แบบฟอร์ม = 1 โฟลเดอร์ใน forms/<code>/ ที่มี form.php คืนค่า array นิยามฟอร์ม
 * (ดูตัวอย่าง forms/room_alc/form.php และวิธีเพิ่มฟอร์มใหม่ใน README)
 *
 * ชนิดช่อง (type): text, tel, textarea, date, time (24 ชม.), radio, select, fixed (ค่าตายตัว ผู้ใช้แก้ไม่ได้)
 * ตัวเลือกของช่อง: required, cls (c3/c4/c6/...), placeholder, options, max (ความยาวสูงสุด, ค่าเริ่มต้น 200),
 *   show_if => ['ช่องอื่น' => [ค่า,...]]  แสดง/บังคับกรอกเฉพาะเมื่อช่องนั้นมีค่าตามที่กำหนด
 *   min_working_days => N               (date) ต้องเป็นวันที่หลังวันนี้อย่างน้อย N วันทำการ ตรวจทั้งตอนบันทึกและตอนส่ง DMS
 *   after => 'ช่องเวลาอื่น'              (time) ต้องมากกว่าเวลาในช่องนั้น
 *   default => fn(array $user): string  ค่าเริ่มต้นสำหรับเอกสารใหม่
 *   remember => true                    จำค่าที่กรอกล่าสุดไว้เติมให้ฟอร์มถัดไป
 *   value => fn(): string               (fixed) ค่าที่บังคับใช้
 */

const FIELD_TYPES = ['text', 'tel', 'textarea', 'date', 'time', 'radio', 'select', 'fixed'];

/** @return array<string,array> code => นิยามฟอร์ม เรียงตาม order */
function forms_all(): array
{
    static $forms = null;
    if ($forms !== null) return $forms;

    require_once APP_ROOT . '/forms/_requester.php';
    $forms = [];
    foreach (glob(APP_ROOT . '/forms/*/form.php') as $file) {
        $code = basename(dirname($file));
        if (!preg_match('/^[a-z0-9_]+$/', $code)) continue;
        $def = (static fn() => require $file)();
        $def['code'] = $code;
        $def['dms'] = array_merge($def['dms'] ?? [], (array)config("dms.forms.$code", [])); // config.php ทับค่าในฟอร์มได้
        $forms[$code] = $def;
    }
    uasort($forms, fn($a, $b) => [$a['order'] ?? 100, $a['title']] <=> [$b['order'] ?? 100, $b['title']]);
    return $forms;
}

function form_get(string $code): ?array
{
    return forms_all()[$code] ?? null;
}

/** @return array<string,array> ทุกช่องของฟอร์ม name => field */
function form_fields(array $form): array
{
    $out = [];
    foreach ($form['sections'] as $s) {
        foreach ($s['fields'] as $f) $out[$f['name']] = $f;
    }
    return $out;
}

function field_visible(array $field, array $data): bool
{
    foreach ($field['show_if'] ?? [] as $other => $values) {
        if (!in_array((string)($data[$other] ?? ''), $values, true)) return false;
    }
    return true;
}

function form_dms_configured(array $form): bool
{
    return ($form['dms']['con'] ?? '') !== '' && ($form['dms']['sub'] ?? '') !== '';
}

/** ข้อมูลเริ่มต้นของเอกสารใหม่: default ของช่อง ทับด้วยค่าที่ผู้ใช้กรอกล่าสุด (remember) */
function form_defaults(array $form, array $user, array $remembered): array
{
    $d = [];
    foreach (form_fields($form) as $name => $f) {
        $d[$name] = $f['type'] === 'fixed' ? (string)($f['value'])() : '';
        if (!empty($f['remember']) && ($remembered[$name] ?? '') !== '') {
            $d[$name] = $remembered[$name];
        } elseif (isset($f['default'])) {
            $d[$name] = (string)($f['default'])($user);
        }
    }
    return $d;
}

/**
 * ตรวจและทำความสะอาดข้อมูลจากฟอร์ม
 * @return array{0: array, 1: array<string,string>} [ข้อมูล, ข้อผิดพลาดรายช่อง]
 */
function form_validate(array $form, array $in): array
{
    $fields = form_fields($form);
    $d = [];
    foreach ($fields as $name => $f) {
        if ($f['type'] === 'fixed') {
            $d[$name] = (string)($f['value'])();
        } elseif ($f['type'] === 'time' && ($in[$name . '_h'] ?? '') !== '' && ($in[$name . '_m'] ?? '') !== '') {
            $d[$name] = sprintf('%02d:%02d', (int)$in[$name . '_h'], (int)$in[$name . '_m']);
        } else {
            $d[$name] = trim((string)($in[$name] ?? ''));
        }
    }

    $e = [];
    foreach ($fields as $name => $f) {
        if (!field_visible($f, $d)) { $d[$name] = ''; continue; } // ช่องที่ซ่อนไม่เก็บค่า
        $v = $d[$name];
        if ($v === '') {
            if (!empty($f['required'])) {
                $e[$name] = in_array($f['type'], ['radio', 'select', 'date', 'time'], true) ? "กรุณาเลือก{$f['label']}" : "กรุณากรอก{$f['label']}";
            }
            continue;
        }
        switch ($f['type']) {
            case 'radio':
            case 'select':
                if (!isset($f['options'][$v])) $e[$name] = "กรุณาเลือก{$f['label']}";
                break;
            case 'date':
                $dt = DateTime::createFromFormat('!Y-m-d', $v);
                if (!$dt || $dt->format('Y-m-d') !== $v) {
                    $e[$name] = 'วันที่ไม่ถูกต้อง';
                } elseif (!empty($f['min_working_days']) && ($msg = working_days_error($v, (int)$f['min_working_days']))) {
                    $e[$name] = $msg;
                }
                break;
            case 'time':
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                    $e[$name] = 'เวลาไม่ถูกต้อง';
                } elseif (!empty($f['after']) && ($d[$f['after']] ?? '') !== '' && $v <= $d[$f['after']]) {
                    $e[$name] = "{$f['label']}ต้องหลัง{$fields[$f['after']]['label']}";
                }
                break;
        }
        if (!isset($e[$name]) && mb_strlen($v) > ($f['max'] ?? 200)) $e[$name] = 'ข้อความยาวเกินไป';
    }
    if (isset($form['validate'])) $e += ($form['validate'])($d);
    return [$d, $e];
}

/** ตรวจซ้ำก่อนส่งเข้า DMS (เช่น บันทึกร่างไว้นานจนเลยกำหนดวันทำการ) — คืนข้อความแจ้ง หรือ null */
function form_send_error(array $form, array $d): ?string
{
    foreach (form_fields($form) as $name => $f) {
        if ($f['type'] === 'date' && !empty($f['min_working_days']) && ($d[$name] ?? '') !== ''
            && ($msg = working_days_error($d[$name], (int)$f['min_working_days']))) {
            return "$msg กรุณาแก้ไข{$f['label']}ในเอกสารก่อนส่ง";
        }
    }
    return null;
}

/** เงื่อนไข "ต้องดำเนินการก่อน N วันทำการ" นับจากวันนี้ */
function working_days_error(string $date, int $days): ?string
{
    $min = earliest_booking_date($days);
    if ($date >= $min) return null;
    return "ต้องดำเนินการก่อน $days วันทำการ — วันนี้เลือกได้ตั้งแต่ " . thai_date($min, true) . ' เป็นต้นไป';
}

/** ค่าที่แสดงให้คนอ่าน (หน้ารายละเอียด, ส่งออก Excel) */
function field_display(array $f, string $v): string
{
    if ($v === '') return '';
    return match ($f['type']) {
        'radio', 'select' => (string)($f['options'][$v] ?? $v),
        'date'            => thai_date($v, true),
        'time'            => "$v น.",
        default           => $v,
    };
}

// ---------- แสดงช่องกรอกในหน้าเว็บ ----------

function render_field(array $f, array $data, array $errors): void
{
    $name = $f['name'];
    $v = (string)($data[$name] ?? '');
    $err = $errors[$name] ?? null;
    $cls = 'f ' . ($f['cls'] ?? '') . ($err ? ' has-err' : '');
    $show = '';
    foreach ($f['show_if'] ?? [] as $other => $values) {
        $show = ' data-show-field="' . h($other) . '" data-show-in="' . h(implode(',', $values)) . '"';
        if (!field_visible($f, $data)) $cls .= ' is-hidden';
    }
    $req = !empty($f['required']) ? ' <span class="req">*</span>' : '';
    $id = 'f-' . $name;
    $ph = isset($f['placeholder']) ? ' placeholder="' . h($f['placeholder']) . '"' : '';
    $max = ' maxlength="' . (int)($f['max'] ?? 200) . '"';
    echo '<div class="' . h(trim($cls)) . '"' . $show . '>';

    switch ($f['type']) {
        case 'radio':
            echo '<span class="label">' . h($f['label']) . $req . '</span><div class="radios">';
            foreach ($f['options'] as $ov => $ol) {
                echo '<label><input type="radio" name="' . h($name) . '" value="' . h((string)$ov) . '"' . ((string)$ov === $v ? ' checked' : '') . '> ' . h($ol) . '</label>';
            }
            echo '</div>';
            break;
        case 'select':
            echo '<label for="' . $id . '">' . h($f['label']) . $req . '</label><select id="' . $id . '" name="' . h($name) . '"><option value="">— เลือก —</option>';
            foreach ($f['options'] as $ov => $ol) {
                echo '<option value="' . h((string)$ov) . '"' . ((string)$ov === $v ? ' selected' : '') . '>' . h($ol) . '</option>';
            }
            echo '</select>';
            break;
        case 'textarea':
            echo '<label for="' . $id . '">' . h($f['label']) . $req . '</label><textarea id="' . $id . '" name="' . h($name) . '" rows="3"' . $ph . ' maxlength="' . (int)($f['max'] ?? 1000) . '">' . h($v) . '</textarea>';
            break;
        case 'fixed':
            echo '<label for="' . $id . '">' . h($f['label']) . '</label><input type="text" id="' . $id . '" value="' . h($v) . '" readonly class="readonly">';
            break;
        case 'date':
            $min = !empty($f['min_working_days']) ? earliest_booking_date((int)$f['min_working_days']) : '';
            echo '<label for="' . $id . '">' . h($f['label']) . $req . '</label><input type="date" id="' . $id . '" name="' . h($name) . '" value="' . h($v) . '"' . ($min ? ' min="' . $min . '"' : '') . '>';
            if ($min) echo '<span class="hint">ต้องดำเนินการก่อน ' . (int)$f['min_working_days'] . ' วันทำการ — เลือกได้ตั้งแต่ ' . h(thai_date($min, true)) . '</span>';
            break;
        case 'time':
            render_time_picker($f, $v, $req);
            break;
        default:
            echo '<label for="' . $id . '">' . h($f['label']) . $req . '</label><input type="' . ($f['type'] === 'tel' ? 'tel' : 'text') . '" id="' . $id . '" name="' . h($name) . '" value="' . h($v) . '"' . $ph . $max . '>';
    }
    if ($err) echo '<span class="err-msg">' . h($err) . '</span>';
    echo '</div>';
}

/** เลือกเวลาแบบ 24 ชั่วโมง (ชั่วโมง : นาที) — ไม่ใช้ <input type=time> เพราะบางเครื่องแสดง AM/PM ทำให้ผู้ใช้สับสน */
function render_time_picker(array $f, string $v, string $req): void
{
    $name = $f['name'];
    [$hh, $mm] = array_pad(explode(':', $v), 2, '');
    $minutes = array_map(fn($m) => sprintf('%02d', $m), range(0, 55, 5));
    if ($mm !== '' && !in_array($mm, $minutes, true)) $minutes[] = $mm; // เวลาเดิมที่ไม่ลงตัว 5 นาที
    sort($minutes);
    echo '<label for="f-' . h($name) . '_h">' . h($f['label']) . ' (24 ชั่วโมง)' . $req . '</label><div class="timepick">';
    echo '<select id="f-' . h($name) . '_h" name="' . h($name) . '_h" aria-label="ชั่วโมง"><option value="">ชม.</option>';
    for ($i = 0; $i < 24; $i++) {
        $o = sprintf('%02d', $i);
        echo '<option value="' . $o . '"' . ($hh === $o ? ' selected' : '') . '>' . $o . '</option>';
    }
    echo '</select><span>:</span><select name="' . h($name) . '_m" aria-label="นาที"><option value="">นาที</option>';
    foreach ($minutes as $o) {
        echo '<option value="' . $o . '"' . ($mm === $o ? ' selected' : '') . '>' . $o . '</option>';
    }
    echo '</select><span>น.</span></div>';
}
