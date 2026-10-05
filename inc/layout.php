<?php
declare(strict_types=1);

/** URL ของไฟล์ css/js พร้อมเลขเวอร์ชันจากเวลาแก้ไขไฟล์ — หลัง git pull เบราว์เซอร์จะโหลดไฟล์ใหม่เอง ไม่ใช้ของเก่าใน cache */
function asset_url(string $path): string
{
    return url($path) . '?v=' . @filemtime(APP_ROOT . '/' . $path);
}

function app_name(): string
{
    return (string)config('app_name', 'ระบบแบบฟอร์มออนไลน์ (E-Form)');
}

function page_header(string $title): void
{
    $user = current_user();
    $flash = flash();
    ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= h(csrf_token()) ?>">
<title><?= h($title) ?> · <?= h(app_name()) ?></title>
<link rel="stylesheet" href="<?= h(asset_url('assets/app.css')) ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= h(url('index.php')) ?>">
    <span class="brand-mark">E-Form</span>
    <span><?= h(app_name()) ?><small><?= h((string)config('app_org', 'คณะวิทยาศาสตร์การแพทย์ มหาวิทยาลัยพะเยา')) ?></small></span>
  </a>
  <?php if ($user): ?>
  <div class="who">
    <?php if (is_admin($user)): ?><a href="<?= h(url('admin/index.php')) ?>">ผู้ดูแลระบบ</a><?php endif; ?>
    <span><?= h($user['name'] ?: $user['login']) ?></span>
    <a href="<?= h(url('logout.php')) ?>">ออกจากระบบ</a>
  </div>
  <?php endif; ?>
</header>
<?php if (config('auth.mode') === 'dev'): ?>
<div class="devbar">โหมดทดสอบ (auth.mode = dev) — ล็อกอินด้วยชื่อใดก็ได้ ห้ามใช้บนเซิร์ฟเวอร์จริง</div>
<?php endif; ?>
<main class="wrap">
<?php if ($flash): ?>
  <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif;
}

function page_footer(): void
{
    ?>
</main>
<script src="<?= h(asset_url('assets/app.js')) ?>"></script>
</body>
</html>
<?php
}
