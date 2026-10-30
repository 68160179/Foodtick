<?php
require 'inc/bootstrap.php';
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
if (current_user()) { header('Location: ' . $next); exit; }
$error = ''; $username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $username = trim((string)($_POST['username'] ?? ''));
  $pass = (string)($_POST['password'] ?? '');
  $u = $username === '' ? null : db_one($conn, 'SELECT id, username, display_name, avatar_path, password_hash FROM app_user WHERE username = ?', 's', [$username]);
  if ($u && password_verify($pass, $u['password_hash'])) {
    login_user($u);
    flash('ยินดีต้อนรับกลับ '.$u['display_name']);
    header('Location: ' . $next); exit;
  }
  $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}
page_start('เข้าสู่ระบบ', 'me');
?>
<section class="auth">
  <div class="auth-card">
    <h1>เข้าสู่ระบบ</h1>
    <p class="muted">ดูและแบ่งปันสูตรอาหารกับทุกคน</p>
    <?php if ($error) echo '<p class="err" role="alert">'.h($error).'</p>'; ?>
    <form method="post" class="form">
      <?php csrf_field(); ?>
      <input type="hidden" name="next" value="<?= h($next) ?>">
      <label for="username">ชื่อผู้ใช้</label>
      <input id="username" name="username" required maxlength="30" autocomplete="username" value="<?= h($username) ?>">
      <label for="password">รหัสผ่าน</label>
      <input id="password" name="password" type="password" required autocomplete="current-password">
      <button class="btn btn-block" type="submit">เข้าสู่ระบบ</button>
    </form>
    <p class="auth-switch">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
  </div>
</section>
<?php page_end(); ?>
