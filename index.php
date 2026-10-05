<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$user = require_login();
$subs = sub_list_user($user['login']);

$byCategory = [];
foreach (forms_all() as $code => $form) {
    $byCategory[$form['category'] ?? 'แบบฟอร์มทั่วไป'][$code] = $form;
}

page_header('หน้าหลัก');
?>
<h1>สร้างเอกสาร</h1>
<p class="sub">เลือกแบบฟอร์ม กรอกข้อมูล แล้วกดส่งเข้าระบบ DMS ได้ทันที</p>

<?php foreach ($byCategory as $category => $forms): ?>
  <h2 class="cat"><?= h($category) ?></h2>
  <div class="choose">
    <?php foreach ($forms as $code => $form): ?>
    <a href="<?= h(url('form.php?form=' . $code)) ?>">
      <strong>+ <?= h($form['short']) ?></strong>
      <span><?= h($form['description'] ?? $form['title']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<div class="card">
  <h2>เอกสารของฉัน</h2>
  <?php if (!$subs): ?>
    <p class="empty">ยังไม่มีเอกสาร</p>
  <?php else: ?>
  <table class="list">
    <thead><tr><th>สร้างเมื่อ</th><th>แบบฟอร์ม</th><th class="hide-sm">รายละเอียด</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php foreach ($subs as $s): if (!$s['form']) continue; [$label, $cls] = sub_status($s); ?>
      <tr>
        <td><a href="<?= h(url('view.php?ref=' . $s['ref'])) ?>"><?= h(thai_date(substr($s['created_at'], 0, 10))) ?></a></td>
        <td><?= h($s['form']['short']) ?></td>
        <td class="hide-sm"><?= h(sub_summary($s)) ?></td>
        <td><span class="badge badge-<?= $cls ?>"><?= h($label) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php page_footer();
