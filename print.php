<?php
/**
 * หน้าเอกสารสำหรับพิมพ์ / ให้ Chrome Headless แปลงเป็น PDF: print.php?ref=<ref>
 * ใช้แบบฟอร์มต้นฉบับ (forms/<code>/template.svg ที่สร้างจากไฟล์ Word) เป็นพื้นหลัง
 * แล้ววางข้อมูลลงบนเส้นจุดตามพิกัด 'layout' ในไฟล์นิยามฟอร์ม
 */
require __DIR__ . '/inc/bootstrap.php';

$ref = (string)($_GET['ref'] ?? '');
$s = preg_match('/^[a-f0-9]{8,32}$/', $ref) ? sub_find($ref) : null;
if (!$s || !$s['form']) {
    http_response_code(404);
    exit('ไม่พบเอกสาร');
}
$form = $s['form'];
$user = current_user();
$isOwner = $user && sub_owned($s, $user);
$showToolbar = $user && ($isOwner || is_admin($user)); // Chrome ที่สร้าง PDF ไม่มี session จึงไม่เห็นแถบเครื่องมือ

$values = ($form['values'])($s['data'], date('Y-m-d'));

// พื้นหลัง: SVG ของแบบฟอร์มต้นฉบับ ฝังในหน้าโดยตรงเพื่อให้ PDF เป็นเวกเตอร์คมชัด
$bg = (string)file_get_contents($form['template']);
$bg = preg_replace('/<svg\b([^>]*?)\swidth="[^"]*"\sheight="[^"]*"/', '<svg$1 class="bg" id="form-bg"', $bg, 1);

// ฟอนต์ข้อมูลที่กรอก: ฝัง TH Sarabun New ไว้ในหน้า ไม่พึ่งฟอนต์ที่ติดตั้งบนเซิร์ฟเวอร์
// (Chrome ที่รันโดย IIS มองไม่เห็นฟอนต์ที่ติดตั้งแบบ "เฉพาะผู้ใช้" จึงเคยออกมาเป็นฟอนต์อื่น)
$fontData = base64_encode((string)file_get_contents(APP_ROOT . '/assets/fonts/THSarabunNew.ttf'));
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="csrf" content="<?= $showToolbar ? h(csrf_token()) : '' ?>">
<title><?= h($form['title']) ?> — <?= h($s['ref']) ?></title>
<style>
  @page { size: A4; margin: 0; }
  html, body { margin: 0; background: #e9e6ee; }
  .page { position: relative; width: 595.32pt; height: 841.92pt; margin: 12px auto; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); overflow: hidden; }
  .page svg { position: absolute; inset: 0; width: 100%; height: 100%; }
  @font-face { font-family: "EForm Sarabun"; src: url(data:font/ttf;base64,<?= $fontData ?>) format("truetype"); font-display: block; }
  .ov text { font-family: "EForm Sarabun", "TH Sarabun New", "TH SarabunPSK", sans-serif; font-size: 15px; fill: #000; }
  .ov text.tick { font-family: "Segoe UI Symbol", "DejaVu Sans", sans-serif; font-size: 10px; font-weight: 700; }
  .toolbar { position: sticky; top: 0; z-index: 2; background: #3f1d5e; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; font-family: "Leelawadee UI", Tahoma, sans-serif; font-size: 14px; }
  .toolbar button, .toolbar a { font: inherit; padding: 4px 14px; border-radius: 6px; border: 0; cursor: pointer; text-decoration: none; background: #c9a227; color: #2b1c00; }
  .toolbar a { background: #fff; }
  .toolbar .send { background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; font-weight: bold; }
  .toolbar button[disabled] { opacity: .7; cursor: progress; }
  @media print {
    html, body { background: #fff; }
    .page { margin: 0; box-shadow: none; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>
<?php if ($showToolbar): ?>
<div class="toolbar no-print">
  <strong style="font-size: 15px;">ตัวอย่างเอกสาร</strong>
  <button type="button" onclick="window.print()">🖨️ พิมพ์เอกสาร</button>
  <?php if ($isOwner && form_dms_configured($form)): ?>
    <button type="button" class="send" id="dms-btn" data-ref="<?= h($s['ref']) ?>">
      🚀 <span id="dms-text"><?= $s['dms_sent_at'] ? 'ส่งเข้าระบบ DMS อีกครั้ง' : 'ส่งเข้าระบบ DMS' ?></span>
    </button>
  <?php endif; ?>
  <a href="<?= h(url('view.php?ref=' . $s['ref'])) ?>">↩️ กลับ</a>
</div>
<?php endif; ?>

<div class="page">
  <?= $bg ?>
  <!-- viewBox หน่วย pt ตรงกับพิกัดใน layout; TH Sarabun New 15pt สูงพอๆ กับ TH Niramit AS 12pt ของแบบฟอร์ม -->
  <svg class="ov" viewBox="0 0 595.32 841.92" xmlns="http://www.w3.org/2000/svg">
    <?php foreach ($form['moves'] ?? [] as $i => [$x0, $y0, $x1, $y1, $dy]): ?>
    <clipPath id="mv<?= $i ?>"><rect x="<?= $x0 ?>" y="<?= $y0 ?>" width="<?= $x1 - $x0 ?>" height="<?= $y1 - $y0 ?>"/></clipPath>
    <rect x="<?= $x0 ?>" y="<?= $y0 ?>" width="<?= $x1 - $x0 ?>" height="<?= $y1 - $y0 ?>" fill="#fff"/>
    <use href="#form-bg" clip-path="url(#mv<?= $i ?>)" transform="translate(0 <?= $dy ?>)"/>
    <?php endforeach; ?>
    <?php foreach ($form['layout'] as $key => [$x0, $x1, $y, $align]):
        $val = (string)($values[$key] ?? '');
        if ($val === '') continue;
        $tick = str_starts_with($key, 'box_');
        $x = $align === 'c' ? ($x0 + $x1) / 2 : $x0;
    ?>
    <text x="<?= $x ?>" y="<?= $tick ? $y - 1 : $y - 2.5 ?>" data-max="<?= $x1 - $x0 ?>"<?= $align === 'c' ? ' text-anchor="middle"' : '' ?><?= $tick ? ' class="tick"' : '' ?>><?= h($val) ?></text>
    <?php endforeach; ?>
  </svg>
</div>

<script>
  // ข้อความที่ยาวเกินช่อง: บีบให้พอดีความยาวเส้นจุด — วัดใหม่หลังฟอนต์ที่ฝังโหลดเสร็จ
  function fitTexts() {
    document.querySelectorAll('.ov text').forEach(t => {
      t.removeAttribute('textLength');
      t.removeAttribute('lengthAdjust');
      const max = parseFloat(t.dataset.max);
      if (t.getComputedTextLength() > max) {
        t.setAttribute('textLength', max);
        t.setAttribute('lengthAdjust', 'spacingAndGlyphs');
      }
    });
  }
  fitTexts();
  if (document.fonts) document.fonts.ready.then(fitTexts);

  // ส่งเข้า DMS: สร้าง PDF บนเซิร์ฟเวอร์ก่อน (AJAX) แล้วจึงพาไปหน้า DMS (คู่มือ DMS_Connect.md ขั้นตอนที่ 1-3)
  const btn = document.getElementById('dms-btn');
  if (btn) btn.addEventListener('click', async () => {
    if (!confirm('ยืนยันส่งเอกสารหมายเลข #' + btn.dataset.ref + ' เข้าสู่ระบบ DMS?')) return;
    const text = document.getElementById('dms-text');
    const label = text.innerText;
    btn.disabled = true;
    text.innerText = 'กำลังสร้างและบันทึก PDF...';
    const csrf = document.querySelector('meta[name=csrf]').content;
    try {
      const res = await fetch('dms_pdf.php?generate=1&ref=' + encodeURIComponent(btn.dataset.ref), {
        method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: new URLSearchParams({ csrf })
      });
      const data = await res.json().catch(() => ({ success: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (HTTP ' + res.status + ')' }));
      if (!data.success || !data.redirect) throw new Error(data.message || 'ไม่สามารถส่งเข้า DMS ได้');
      text.innerText = 'สำเร็จ! กำลังเปิดระบบ DMS...';
      window.location.href = data.redirect;
    } catch (err) {
      alert('เกิดข้อผิดพลาด: ' + err.message);
      btn.disabled = false;
      text.innerText = label;
    }
  });
</script>
</body>
</html>
