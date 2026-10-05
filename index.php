<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$user = require_login();
$bookings = booking_list($user['login']);

page_header('เอกสารของฉัน');
?>
<h1>สร้างเอกสารขออนุมัติใช้ห้อง</h1>
<p class="sub">เลือกประเภทแบบฟอร์ม กรอกข้อมูล แล้วกดส่งเข้าระบบ DMS ได้ทันที (ต้องจองล่วงหน้าอย่างน้อย <?= (int)config('min_working_days', 3) ?> วันทำการ)</p>

<div class="choose">
  <?php foreach (FORM_TYPES as $type => $ft): ?>
  <a href="<?= h(url('booking_form.php?type=' . $type)) ?>">
    <strong>+ <?= h($ft['short']) ?></strong>
    <span><?= h($ft['title']) ?></span>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>เอกสารของฉัน</h2>
  <?php if (!$bookings): ?>
    <p class="empty">ยังไม่มีเอกสาร</p>
  <?php else: ?>
  <table class="list">
    <thead><tr><th>วันที่ใช้ห้อง</th><th>ห้อง</th><th class="hide-sm">วัตถุประสงค์</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php foreach ($bookings as $b): [$label, $cls] = booking_status($b); ?>
      <tr>
        <td><a href="<?= h(url('booking_view.php?ref=' . $b['ref'])) ?>"><?= h(thai_date($b['booking_date'])) ?></a><br>
          <span class="hint"><?= h(hm($b['time_start']) . '–' . hm($b['time_end'])) ?> น.</span></td>
        <td><?= h(booking_room_label($b)) ?></td>
        <td class="hide-sm"><?= h(FORM_TYPES[$b['form_type']]['purposes'][$b['purpose']] ?? '') ?> <?= h($b['purpose_detail']) ?></td>
        <td><span class="badge badge-<?= $cls ?>"><?= h($label) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php page_footer();
