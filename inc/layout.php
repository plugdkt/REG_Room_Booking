<?php
declare(strict_types=1);

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
<title><?= h($title) ?> · ระบบขอใช้ห้องเรียน</title>
<link rel="stylesheet" href="<?= h(url('assets/app.css')) ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= h(url('index.php')) ?>">
    <span class="brand-mark">กบศ.</span>
    <span>ระบบขออนุมัติใช้ห้องเรียน<small>กองบริการการศึกษา มหาวิทยาลัยพะเยา</small></span>
  </a>
  <?php if ($user): ?>
  <div class="who">
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
<script src="<?= h(url('assets/app.js')) ?>"></script>
</body>
</html>
<?php
}
