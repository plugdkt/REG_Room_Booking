<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$user = require_login();
$b = booking_for_user((string)($_GET['ref'] ?? ''), $user);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    if (booking_locked($b)) {
        flash('เอกสารที่ส่งเข้า DMS แล้วไม่สามารถลบได้', 'err');
        redirect('booking_view.php?ref=' . $b['ref']);
    }
    booking_delete($b['ref']);
    flash('ลบเอกสารแล้ว');
    redirect('index.php');
}

$ft = FORM_TYPES[$b['form_type']];
[$statusLabel, $statusCls] = booking_status($b);
$locked = booking_locked($b);

page_header('รายละเอียดเอกสาร');
?>
<h1>รายละเอียดเอกสาร <span class="badge badge-<?= $statusCls ?>"><?= h($statusLabel) ?></span></h1>
<p class="sub"><?= h($ft['title']) ?> · เลขอ้างอิง <?= h($b['ref']) ?></p>

<div id="dms-status" class="alert" hidden></div>
<?php if (!dms_configured($b['form_type'])): ?>
  <div class="alert alert-warn">ยังไม่ได้ตั้งค่ารหัส con/sub ของ DMS สำหรับแบบฟอร์มนี้ใน config.php — ปุ่มส่งเข้า DMS จะใช้งานไม่ได้จนกว่าจะตั้งค่า</div>
<?php endif; ?>
<?php if ($locked): ?>
  <div class="alert alert-ok">ระบบ DMS รับเอกสารแล้วเมื่อ <?= h(thai_date($b['dms_fetched_at']) . ' ' . substr($b['dms_fetched_at'], 11, 5)) ?> น.</div>
<?php elseif ($b['dms_sent_at']): ?>
  <div class="alert alert-warn">กดส่งเข้า DMS แล้วแต่ DMS ยังไม่ได้ดึงเอกสาร — หากยังลงทะเบียนเอกสารใน DMS ไม่เสร็จ กดส่งอีกครั้งได้</div>
<?php endif; ?>

<div class="card">
  <dl class="detail">
    <dt>ผู้ขอใช้ห้อง</dt><dd><?= h($b['prefix'] . $b['fullname']) ?></dd>
    <dt>ตำแหน่ง</dt><dd><?= h($b['position']) ?></dd>
    <dt>สังกัด</dt><dd><?= h($b['faculty']) ?><?= $b['department'] !== '' ? ' / ' . h($b['department']) : '' ?></dd>
    <dt>โทร</dt><dd><?= h($b['phone']) ?></dd>
    <dt>ห้อง</dt><dd><?= h(booking_room_label($b)) ?></dd>
    <?php if ($b['alt_room'] !== ''): ?><dt>ห้องสำรอง (แทน)</dt><dd><?= h($b['alt_room']) ?></dd><?php endif; ?>
    <dt>วันเวลา</dt><dd><?= h(thai_date($b['booking_date'], true)) ?> เวลา <?= h(hm($b['time_start'])) ?>–<?= h(hm($b['time_end'])) ?> น.</dd>
    <dt>วัตถุประสงค์</dt><dd><?= h($ft['purposes'][$b['purpose']] ?? '') ?> <?= h($b['purpose_detail']) ?></dd>
  </dl>
</div>

<div class="actions">
  <?php if (dms_configured($b['form_type'])): ?>
    <button class="btn btn-dms" type="button" data-send-dms="<?= h(url('print_booking_pdf.php?generate=1&ref=' . $b['ref'])) ?>">
      <?= $b['dms_sent_at'] ? 'ส่งเข้าระบบ DMS อีกครั้ง' : 'ส่งเข้าระบบ DMS' ?>
    </button>
  <?php endif; ?>
  <a class="btn" href="<?= h(url('print_booking.php?ref=' . $b['ref'])) ?>" target="_blank">ดูตัวอย่าง / พิมพ์</a>
  <?php if (!$locked): ?>
    <a class="btn" href="<?= h(url('booking_form.php?ref=' . $b['ref'])) ?>">แก้ไข</a>
    <form method="post" data-confirm="ต้องการลบเอกสารนี้?" style="margin:0">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <button class="btn btn-danger" type="submit">ลบ</button>
    </form>
  <?php endif; ?>
  <a class="btn" href="<?= h(url('index.php')) ?>">กลับ</a>
</div>
<?php page_footer();
