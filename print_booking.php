<?php
/**
 * หน้าเอกสารสำหรับพิมพ์ / ให้ Chrome Headless แปลงเป็น PDF
 * ใช้แบบฟอร์มต้นฉบับ (templates/*.svg ที่สร้างจากไฟล์ Word) เป็นพื้นหลัง แล้ววางข้อมูลลงบนเส้นจุดตามพิกัดใน inc/form_layout.php
 * เข้าถึงได้ 2 ทาง: เจ้าของเอกสารที่ล็อกอินอยู่ หรือ URL ที่มีลายเซ็น sig (ใช้โดย pdf_generate)
 */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/form_layout.php';

$ref = (string)($_GET['ref'] ?? '');
$sig = (string)($_GET['sig'] ?? '');
$b = preg_match('/^[a-f0-9]{8,32}$/', $ref) ? booking_find($ref) : null;

$allowed = (bool)$b;
if (!$allowed) {
    http_response_code(404);
    exit('ไม่พบเอกสาร');
}

$forPdf = $sig !== '';
$type   = $b['form_type'];
$values = form_values($b, date('Y-m-d'));

// พื้นหลัง: SVG ของแบบฟอร์มต้นฉบับ ฝังในหน้าโดยตรงเพื่อให้ PDF เป็นเวกเตอร์คมชัด
$bg = file_get_contents(APP_ROOT . "/templates/$type.svg");
$bg = preg_replace('/<svg\b([^>]*?)\swidth="[^"]*"\sheight="[^"]*"/', '<svg$1 class="bg"', $bg, 1);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title><?= h(FORM_TYPES[$type]['title']) ?> — <?= h($b['ref']) ?></title>
<style>
  @page { size: A4; margin: 0; }
  html, body { margin: 0; background: #e9e6ee; }
  .page { position: relative; width: 595.32pt; height: 841.92pt; margin: 12px auto; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); overflow: hidden; }
  .page svg { position: absolute; inset: 0; width: 100%; height: 100%; }
  .ov text { font-family: "TH Niramit AS", "TH NiramitAS", "TH Sarabun New", sans-serif; font-size: 12px; fill: #000; }
  .ov text.tick { font-family: "Segoe UI Symbol", "DejaVu Sans", sans-serif; font-size: 10px; font-weight: 700; }
  .toolbar { position: sticky; top: 0; z-index: 2; background: #3f1d5e; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font-family: "Leelawadee UI", Tahoma, sans-serif; font-size: 14px; }
  .toolbar button, .toolbar a { font: inherit; padding: 4px 14px; border-radius: 6px; border: 0; cursor: pointer; text-decoration: none; background: #c9a227; color: #2b1c00; }
  .toolbar a { background: #fff; }
  @media print {
    html, body { background: #fff; }
    .page { margin: 0; box-shadow: none; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>
<?php if (!$forPdf): ?>
<div class="toolbar no-print">
  <strong style="font-size: 15px;">ตัวอย่างเอกสารขออนุมัติ</strong>
  <button type="button" onclick="window.print()">🖨️ พิมพ์เอกสาร</button>
  <?php if (dms_configured($b['form_type'])): ?>
    <button type="button" id="dms-btn" onclick="submitToDMS('<?= h($b['ref']) ?>')" style="background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; font-weight: bold;">
      🚀 <span id="dms-text"><?= $b['dms_sent_at'] ? 'ส่งเข้าระบบ DMS อีกครั้ง' : 'ส่งเข้าระบบ DMS' ?></span>
    </button>
  <?php endif; ?>
  <a href="<?= h(url('booking_view.php?ref=' . $b['ref'])) ?>">↩️ กลับ</a>
</div>
<?php endif; ?>

<div class="page">
  <?= $bg ?>
  <!-- viewBox หน่วย pt ตรงกับพิกัดใน FORM_LAYOUT; font-size 12 = 12pt เท่าตัวอักษรในแบบฟอร์ม -->
  <svg class="ov" viewBox="0 0 595.32 841.92" xmlns="http://www.w3.org/2000/svg">
    <?php foreach (FORM_LAYOUT[$type] as $key => [$x0, $x1, $y, $align]):
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
  // ข้อความที่ยาวเกินช่อง: บีบให้พอดีความยาวเส้นจุด
  document.querySelectorAll('.ov text').forEach(t => {
    const max = parseFloat(t.dataset.max);
    if (t.getComputedTextLength() > max) {
      t.setAttribute('textLength', max);
      t.setAttribute('lengthAdjust', 'spacingAndGlyphs');
    }
  });

  function submitToDMS(ref) {
    if (!confirm('ยืนยันส่งเอกสารหมายเลข #' + ref + ' เข้าสู่ระบบ DMS?')) {
      return;
    }
    const btn = document.getElementById('dms-btn');
    const textSpan = document.getElementById('dms-text');
    const originalText = textSpan.innerText;

    btn.disabled = true;
    btn.style.opacity = '0.7';
    textSpan.innerText = 'กำลังสร้างและบันทึก PDF...';

    fetch('print_booking_pdf.php?ref=' + encodeURIComponent(ref) + '&generate=1')
      .then(res => res.json())
      .then(data => {
        if (data.success && data.redirect) {
          textSpan.innerText = 'สำเร็จ! กำลังเปิดระบบ DMS...';
          setTimeout(() => {
            window.location.href = data.redirect;
          }, 400);
        } else {
          alert('เกิดข้อผิดพลาด: ' + (data.message || 'ไม่สามารถส่งเข้า DMS ได้'));
          resetBtn();
        }
      })
      .catch(err => {
        console.error(err);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
        resetBtn();
      });

    function resetBtn() {
      btn.disabled = false;
      btn.style.opacity = '1';
      textSpan.innerText = originalText;
    }
  }
</script>
</body>
</html>
