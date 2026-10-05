<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
// อนุญาตเฉพาะ path ภายในระบบ ป้องกัน open redirect
if (!preg_match('~^/[^/\\\\]~', $next)) $next = '';

if (current_user()) redirect($next ?: 'index.php');

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = (string)($_POST['username'] ?? '');
    try {
        $user = authenticate($username, (string)($_POST['password'] ?? ''));
    } catch (Throwable $e) {
        error_log('[reg_room_booking] auth error: ' . $e->getMessage());
        $user = null;
        $error = 'ไม่สามารถเชื่อมต่อระบบยืนยันตัวตนได้ กรุณาติดต่อผู้ดูแลระบบ';
    }
    if ($user) {
        login_user($user);
        if ($next) {
            header('Location: ' . $next);
            exit;
        }
        redirect('index.php');
    }
    $error = $error ?: 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}

page_header('เข้าสู่ระบบ');
?>
<div class="card login">
  <h1>เข้าสู่ระบบ</h1>
  <p class="sub">ใช้ UP Account ของมหาวิทยาลัยพะเยา</p>
  <?php if ($error): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <div class="grid">
      <div class="f"><label for="u">UP Account</label><input type="text" id="u" name="username" value="<?= h($username) ?>" autocomplete="username" required autofocus></div>
      <div class="f"><label for="p">รหัสผ่าน</label><input type="password" id="p" name="password" autocomplete="current-password" required></div>
      <div class="f"><button class="btn btn-primary" type="submit">เข้าสู่ระบบ</button></div>
    </div>
  </form>
</div>
<?php page_footer();
