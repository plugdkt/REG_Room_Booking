<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$user = require_login();

$ref = (string)($_GET['ref'] ?? '');
$booking = $ref !== '' ? booking_for_user($ref, $user) : null;
$type = $booking['form_type'] ?? (string)($_GET['type'] ?? '');
if (!isset(FORM_TYPES[$type])) redirect('index.php');
if ($booking && booking_locked($booking)) {
    flash('เอกสารนี้ส่งเข้า DMS แล้ว ไม่สามารถแก้ไขได้', 'warn');
    redirect('booking_view.php?ref=' . $ref);
}
$ft = FORM_TYPES[$type];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$data, $errors] = booking_validate($type, $_POST);
    if (!$errors) {
        if ($booking) {
            booking_update($booking['ref'], $data);
        } else {
            $ref = booking_create($type, $user, $data);
        }
        // จำข้อมูลผู้ขอไว้เติมให้ครั้งถัดไป
        $_SESSION['last_requester'] = array_intersect_key($data, array_flip(['prefix', 'fullname', 'faculty', 'department', 'phone', 'position']));
        flash('บันทึกเอกสารเรียบร้อย ตรวจสอบข้อมูลแล้วกด "ส่งเข้าระบบ DMS"');
        redirect('booking_view.php?ref=' . $ref);
    }
} elseif ($booking) {
    $data = $booking;
    $data['time_start'] = hm($booking['time_start']);
    $data['time_end'] = hm($booking['time_end']);
} else {
    $data = array_fill_keys(BOOKING_FIELDS, '');
    $data = array_merge($data, $_SESSION['last_requester'] ?? [
        'fullname'   => $user['name'] ?? '',
        'department' => $user['department'] ?? '',
        'phone'      => $user['phone'] ?? '',
        'position'   => $user['position'] ?? '',
    ]);
}

$minDate = earliest_booking_date((int)config('min_working_days', 3));

function field(string $name, string $label, array $data, array $errors, string $cls = 'c6', array $attr = []): void
{
    $type = $attr['type'] ?? 'text';
    $id = $attr['id'] ?? "f-$name";
    unset($attr['type'], $attr['id']);
    $extra = '';
    foreach ($attr as $k => $v) $extra .= ' ' . $k . '="' . h((string)$v) . '"';
    ?>
    <div class="f <?= $cls ?><?= isset($errors[$name]) ? ' has-err' : '' ?>">
      <label for="<?= $id ?>"><?= h($label) ?></label>
      <input type="<?= $type ?>" id="<?= $id ?>" name="<?= $name ?>" value="<?= h((string)($data[$name] ?? '')) ?>"<?= $extra ?>>
      <?php if (isset($errors[$name])): ?><span class="err-msg"><?= h($errors[$name]) ?></span><?php endif; ?>
    </div>
    <?php
}

function err(string $name, array $errors): void
{
    if (isset($errors[$name])) echo '<span class="err-msg">' . h($errors[$name]) . '</span>';
}

page_header($booking ? 'แก้ไขเอกสาร' : 'สร้างเอกสาร');
?>
<h1><?= $booking ? 'แก้ไขเอกสาร' : 'สร้างเอกสาร' ?></h1>
<p class="sub"><?= h($ft['title']) ?></p>

<?php if ($errors): ?><div class="alert alert-err">กรุณาตรวจสอบข้อมูลที่ไฮไลต์สีแดง</div><?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

  <div class="card">
    <fieldset>
      <legend>ข้อมูลผู้ขอใช้ห้อง</legend>
      <div class="grid">
        <div class="f c3<?= isset($errors['prefix']) ? ' has-err' : '' ?>">
          <label for="f-prefix">คำนำหน้า</label>
          <select id="f-prefix" name="prefix">
            <option value="">— เลือก —</option>
            <?php foreach (PREFIXES as $p): ?>
              <option<?= $data['prefix'] === $p ? ' selected' : '' ?>><?= h($p) ?></option>
            <?php endforeach; ?>
          </select>
          <?php err('prefix', $errors); ?>
        </div>
        <?php field('fullname', 'ชื่อ - สกุล', $data, $errors, 'c9', ['required' => 'required']); ?>
        <?php field('faculty', 'สังกัดคณะ/วิทยาลัย/กอง/ศูนย์', $data, $errors, 'c6', ['required' => 'required']); ?>
        <?php field('department', 'สาขาวิชา/ส่วนงาน', $data, $errors, 'c6'); ?>
        <?php field('position', 'ตำแหน่ง', $data, $errors, 'c6', ['required' => 'required']); ?>
        <?php field('phone', 'โทร', $data, $errors, 'c6', ['type' => 'tel', 'required' => 'required']); ?>
      </div>
    </fieldset>
  </div>

  <div class="card">
    <fieldset>
      <legend>ห้องและวันเวลาที่ขอใช้</legend>
      <?php if ($type === 'classroom'): ?>
        <div class="room-opt">
          <label class="radios"><span><input type="radio" name="room_kind" value="classroom"<?= $data['room_kind'] === 'classroom' ? ' checked' : '' ?>> ห้องเรียน</span></label>
          <div class="grid">
            <?php field('room_name', 'ห้องเรียน', $data['room_kind'] === 'classroom' ? $data : [], $data['room_kind'] === 'classroom' ? $errors : [], 'c6', ['placeholder' => 'เช่น CE09101']); ?>
            <?php field('building', 'อาคาร', $data, $errors, 'c6', ['placeholder' => 'เช่น อาคารเรียนรวม (CE)']); ?>
          </div>
        </div>
        <div class="room-opt">
          <label class="radios"><span><input type="radio" name="room_kind" value="hybrid"<?= $data['room_kind'] === 'hybrid' ? ' checked' : '' ?>> ห้องเรียน Hybrid Classroom</span></label>
          <div class="grid">
            <?php field('room_name', 'ห้อง Hybrid Classroom', $data['room_kind'] === 'hybrid' ? $data : [], $data['room_kind'] === 'hybrid' ? $errors : [], '', ['id' => 'f-hybrid_room']); ?>
          </div>
        </div>
        <?php err('room_kind', $errors); ?>
      <?php else: ?>
        <p>ขออนุมัติใช้ห้อง <strong>Active Learning Classroom</strong></p>
      <?php endif; ?>

      <div class="grid" style="margin-top:8px">
        <?php field('booking_date', 'วันที่ใช้ห้อง', $data, $errors, 'c4', ['type' => 'date', 'min' => $minDate, 'required' => 'required']); ?>
        <?php field('time_start', 'ตั้งแต่เวลา (น.)', $data, $errors, 'c4', ['type' => 'time', 'required' => 'required']); ?>
        <?php field('time_end', 'ถึงเวลา (น.)', $data, $errors, 'c4', ['type' => 'time', 'required' => 'required']); ?>
        <div class="f"><span class="hint">การจองห้องจะต้องดำเนินการก่อน <?= (int)config('min_working_days', 3) ?> วันทำการ — จองได้ตั้งแต่ <?= h(thai_date($minDate, true)) ?> เป็นต้นไป</span></div>
      </div>
    </fieldset>
  </div>

  <div class="card">
    <fieldset>
      <legend>โดยมีวัตถุประสงค์เพื่อ</legend>
      <div class="radios<?= isset($errors['purpose']) ? ' has-err' : '' ?>">
        <?php foreach ($ft['purposes'] as $key => $label): ?>
          <label><input type="radio" name="purpose" value="<?= $key ?>" data-label="<?= h($label) ?>"<?= $data['purpose'] === $key ? ' checked' : '' ?>> <?= h($label) ?></label>
        <?php endforeach; ?>
      </div>
      <?php err('purpose', $errors); ?>
      <div class="grid" style="margin-top:10px">
        <div class="f<?= isset($errors['purpose_detail']) ? ' has-err' : '' ?>">
          <label for="f-purpose_detail" id="purpose-detail-label">รายละเอียด<?= isset($ft['purposes'][$data['purpose']]) ? ' (' . h($ft['purposes'][$data['purpose']]) . ')' : '' ?></label>
          <input type="text" id="f-purpose_detail" name="purpose_detail" maxlength="500" value="<?= h($data['purpose_detail']) ?>" placeholder="เช่น ชื่อรายวิชา / ชื่อกิจกรรม / ชื่อโครงการ">
          <?php err('purpose_detail', $errors); ?>
        </div>
        <?php if ($type === 'classroom') field('alt_room', 'กรณีห้องเรียนไม่ว่าง ใช้ห้องเรียน (แทน)', $data, $errors, 'c12', ['placeholder' => 'ไม่บังคับ']); ?>
      </div>
    </fieldset>
  </div>

  <div class="actions">
    <button class="btn btn-primary" type="submit">บันทึกเอกสาร</button>
    <a class="btn" href="<?= h(url($booking ? 'booking_view.php?ref=' . $booking['ref'] : 'index.php')) ?>">ยกเลิก</a>
  </div>
</form>
<?php page_footer();
