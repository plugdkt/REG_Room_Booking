<?php
/** กรอก / แก้ไขเอกสาร: form.php?form=<code> (ใหม่) หรือ form.php?ref=<ref> (แก้ไข) */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$user = require_login();

$ref = (string)($_GET['ref'] ?? '');
$sub = $ref !== '' ? sub_for_user($ref, $user) : null;
if ($sub && !sub_owned($sub, $user)) {
    flash('แก้ไขได้เฉพาะเอกสารของตนเอง', 'warn');
    redirect('view.php?ref=' . $ref);
}
$form = $sub ? $sub['form'] : form_get((string)($_GET['form'] ?? ''));
if (!$form) redirect('index.php');
if ($sub && sub_locked($sub)) {
    flash('เอกสารนี้ส่งเข้า DMS แล้ว ไม่สามารถแก้ไขได้', 'warn');
    redirect('view.php?ref=' . $ref);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$data, $errors] = form_validate($form, $_POST);
    if (!$errors) {
        if ($sub) {
            sub_update($sub['ref'], $data);
        } else {
            $ref = sub_create($form['code'], $user, $data);
        }
        // จำค่าที่กรอกไว้เติมให้ฟอร์มถัดไป (เฉพาะช่องที่ตั้ง remember)
        foreach (form_fields($form) as $name => $f) {
            if (!empty($f['remember'])) $_SESSION['remember'][$name] = $data[$name];
        }
        flash('บันทึกเอกสารเรียบร้อย ตรวจสอบข้อมูลแล้วกด "ส่งเข้าระบบ DMS"');
        redirect('view.php?ref=' . $ref);
    }
} elseif ($sub) {
    $data = $sub['data'];
} else {
    $data = form_defaults($form, $user, $_SESSION['remember'] ?? []);
}

page_header($sub ? 'แก้ไขเอกสาร' : 'สร้างเอกสาร');
?>
<h1><?= $sub ? 'แก้ไขเอกสาร' : 'สร้างเอกสาร' ?></h1>
<p class="sub"><?= h($form['title']) ?></p>

<?php if ($errors): ?><div class="alert alert-err">กรุณาตรวจสอบข้อมูลที่ไฮไลต์สีแดง</div><?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <?php foreach ($form['sections'] as $section): ?>
  <div class="card">
    <fieldset>
      <legend><?= h($section['title']) ?></legend>
      <?php if (isset($section['intro'])): ?><p><?= $section['intro'] /* HTML จากไฟล์นิยามฟอร์ม */ ?></p><?php endif; ?>
      <div class="grid">
        <?php foreach ($section['fields'] as $f) render_field($f, $data, $errors); ?>
      </div>
    </fieldset>
  </div>
  <?php endforeach; ?>

  <div class="actions">
    <button class="btn btn-primary" type="submit">บันทึกเอกสาร</button>
    <a class="btn" href="<?= h(url($sub ? 'view.php?ref=' . $sub['ref'] : 'index.php')) ?>">ยกเลิก</a>
  </div>
</form>
<?php page_footer();
