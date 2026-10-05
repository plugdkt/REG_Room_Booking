<?php
/** หน้าผู้ดูแลระบบ: ดูเอกสารของทุกคน ค้นหา กรองตามแบบฟอร์ม/สถานะ/ช่วงวันที่ และส่งออก Excel */
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';

require_admin();

$filters = admin_filters();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
[$rows, $total] = sub_search($filters, $perPage, ($page - 1) * $perPage);
$pages = max(1, (int)ceil($total / $perPage));
$query = http_build_query(array_filter($filters, fn($v) => $v !== ''));

page_header('ผู้ดูแลระบบ');
?>
<h1>เอกสารทั้งหมด</h1>
<p class="sub">ทั้งหมด <?= number_format($total) ?> รายการ</p>

<form class="card filters" method="get">
  <div class="grid">
    <div class="f c4"><label for="ff">แบบฟอร์ม</label>
      <select id="ff" name="form"><option value="">ทุกแบบฟอร์ม</option>
        <?php foreach (forms_all() as $code => $form): ?>
          <option value="<?= h($code) ?>"<?= $filters['form'] === $code ? ' selected' : '' ?>><?= h($form['short']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="f c4"><label for="fs">สถานะ</label>
      <select id="fs" name="status"><option value="">ทุกสถานะ</option>
        <?php foreach (SUB_STATUSES as $k => $label): ?>
          <option value="<?= $k ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="f c4"><label for="fq">ค้นหา</label>
      <input type="text" id="fq" name="q" value="<?= h($filters['q']) ?>" placeholder="ชื่อ, รายละเอียด, เลขอ้างอิง, UP Account"></div>
    <div class="f c4"><label for="from">สร้างตั้งแต่วันที่</label><input type="date" id="from" name="from" value="<?= h($filters['from']) ?>"></div>
    <div class="f c4"><label for="to">ถึงวันที่</label><input type="date" id="to" name="to" value="<?= h($filters['to']) ?>"></div>
    <div class="f c4 actions" style="align-self:end">
      <button class="btn btn-primary" type="submit">ค้นหา</button>
      <a class="btn" href="<?= h(url('admin/index.php')) ?>">ล้าง</a>
      <a class="btn" href="<?= h(url('admin/export.php' . ($query ? "?$query" : ''))) ?>">ส่งออก Excel</a>
    </div>
  </div>
</form>

<div class="card">
  <?php if (!$rows): ?>
    <p class="empty">ไม่พบเอกสาร</p>
  <?php else: ?>
  <table class="list">
    <thead><tr><th>สร้างเมื่อ</th><th>แบบฟอร์ม</th><th>ผู้ขอ</th><th class="hide-sm">รายละเอียด</th><th class="hide-sm">วันที่ใช้</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): [$label, $cls] = sub_status($s); $form = $s['form']; ?>
      <tr>
        <td><a href="<?= h(url('view.php?ref=' . $s['ref'])) ?>"><?= h(thai_date(substr($s['created_at'], 0, 10))) ?></a>
          <br><span class="hint">#<?= h($s['ref']) ?></span></td>
        <td><?= h($form['short'] ?? $s['form_code']) ?></td>
        <td><?= h($s['data']['fullname'] ?? '') ?><br><span class="hint"><?= h($s['user_login']) ?></span></td>
        <td class="hide-sm"><?= $form ? h(sub_summary($s)) : '' ?></td>
        <td class="hide-sm"><?= ($d = $s['data'][$form['date_field'] ?? ''] ?? '') ? h(thai_date($d)) : '' ?></td>
        <td><span class="badge badge-<?= $cls ?>"><?= h($label) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><strong><?= $i ?></strong>
        <?php else: ?><a href="?<?= h(http_build_query(array_filter($filters, fn($v) => $v !== '') + ['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php page_footer();
