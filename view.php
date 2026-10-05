<?php
/** รายละเอียดเอกสาร: view.php?ref=<ref> — เจ้าของ หรือผู้ดูแลระบบ */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$user = require_login();
$s = sub_for_user((string)($_GET['ref'] ?? ''), $user);
$form = $s['form'];
$owner = sub_owned($s, $user);
$locked = sub_locked($s);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    if (!$owner || $locked) {
        flash($locked ? 'เอกสารที่ส่งเข้า DMS แล้วไม่สามารถลบได้' : 'ลบได้เฉพาะเอกสารของตนเอง', 'err');
        redirect('view.php?ref=' . $s['ref']);
    }
    sub_delete($s['ref']);
    flash('ลบเอกสารแล้ว');
    redirect('index.php');
}

[$statusLabel, $statusCls] = sub_status($s);

page_header('รายละเอียดเอกสาร');
?>
<h1>รายละเอียดเอกสาร <span class="badge badge-<?= $statusCls ?>"><?= h($statusLabel) ?></span></h1>
<p class="sub"><?= h($form['title']) ?> · เลขอ้างอิง <?= h($s['ref']) ?>
  <?php if (!$owner): ?> · ผู้สร้าง <?= h($s['user_login']) ?><?php endif; ?></p>

<?php if (!form_dms_configured($form)): ?>
  <div class="alert alert-warn">ยังไม่ได้ตั้งค่ารหัส con/sub ของ DMS สำหรับแบบฟอร์มนี้ — ส่งเข้า DMS ไม่ได้จนกว่าจะตั้งค่า</div>
<?php endif; ?>
<?php if ($locked): ?>
  <div class="alert alert-ok">ระบบ DMS รับเอกสารแล้วเมื่อ <?= h(thai_date($s['dms_fetched_at']) . ' ' . substr($s['dms_fetched_at'], 11, 5)) ?> น.</div>
<?php elseif ($s['dms_sent_at']): ?>
  <div class="alert alert-warn">กดส่งเข้า DMS แล้วแต่ DMS ยังไม่ได้ดึงเอกสาร — หากยังลงทะเบียนเอกสารใน DMS ไม่เสร็จ กดส่งอีกครั้งได้</div>
<?php endif; ?>

<?php foreach ($form['sections'] as $section): ?>
<div class="card">
  <h2><?= h($section['title']) ?></h2>
  <dl class="detail">
    <?php foreach ($section['fields'] as $f): $v = field_display($f, (string)($s['data'][$f['name']] ?? '')); if ($v === '') continue; ?>
      <dt><?= h($f['label']) ?></dt><dd><?= h($v) ?></dd>
    <?php endforeach; ?>
  </dl>
</div>
<?php endforeach; ?>

<div class="actions">
  <a class="btn btn-dms" href="<?= h(url('print.php?ref=' . $s['ref'])) ?>">
    <?= $owner ? 'ดูตัวอย่างเอกสาร / ส่งเข้าระบบ DMS' : 'ดูตัวอย่างเอกสาร' ?>
  </a>
  <?php if ($owner && !$locked): ?>
    <a class="btn" href="<?= h(url('form.php?ref=' . $s['ref'])) ?>">แก้ไข</a>
    <form method="post" data-confirm="ต้องการลบเอกสารนี้?" style="margin:0">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <button class="btn btn-danger" type="submit">ลบ</button>
    </form>
  <?php endif; ?>
  <a class="btn" href="<?= h(url($owner ? 'index.php' : 'admin/index.php')) ?>">กลับ</a>
</div>
<?php page_footer();
