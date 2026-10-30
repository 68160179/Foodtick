<?php
require 'inc/bootstrap.php';
if (current_user()) { header('Location: index.php'); exit; }
$error = ''; $username = ''; $display = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $username = trim((string)($_POST['username'] ?? ''));
  $display = trim((string)($_POST['display_name'] ?? ''));
  $pass = (string)($_POST['password'] ?? '');
  $confirm = (string)($_POST['confirm'] ?? '');
  if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) $error = 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9 หรือ _ ยาว 3–30 ตัว';
  elseif ($display === '' || mb_strlen($display) > 60) $error = 'กรอกชื่อที่แสดง (ไม่เกิน 60 ตัวอักษร)';
  elseif (strlen($pass) < 6) $error = 'รหัสผ่านต้องยาวอย่างน้อย 6 ตัว';
  elseif ($pass !== $confirm) $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
  elseif (db_one($conn, 'SELECT id FROM app_user WHERE username = ?', 's', [$username])) $error = 'ชื่อผู้ใช้นี้มีคนใช้แล้ว ลองชื่ออื่นดูนะ';
  else {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = db_query($conn, 'INSERT INTO app_user (username, display_name, password_hash) VALUES (?, ?, ?)', 'sss', [$username, $display, $hash]);
    if ($stmt) {
      $id = mysqli_insert_id($conn); mysqli_stmt_close($stmt);
      login_user(['id'=>$id, 'username'=>$username, 'display_name'=>$display]);
      flash('ยินดีต้อนรับสู่ FOOD TRICKS, '.$display);
      header('Location: index.php'); exit;
    }
    $error = 'สมัครไม่สำเร็จ ลองใหม่อีกครั้ง';
  }
}
page_start('สมัครสมาชิก', 'me');
?>
<section class="auth">
  <div class="auth-card">
    <h1>สมัครสมาชิก</h1>
    <p class="muted">สมัครแล้วโพสต์สูตรอาหารของคุณได้เลย</p>
    <?php if ($error) echo '<p class="err" role="alert">'.h($error).'</p>'; ?>
    <form method="post" class="form">
      <?php csrf_field(); ?>
      <label for="username">ชื่อผู้ใช้ (ใช้ล็อกอิน)</label>
      <input id="username" name="username" required pattern="[A-Za-z0-9_]{3,30}" maxlength="30" autocomplete="username" value="<?= h($username) ?>">
      <small class="hint">a-z, 0-9, _ ยาว 3–30 ตัว</small>
      <label for="display_name">ชื่อที่แสดง</label>
      <input id="display_name" name="display_name" required maxlength="60" value="<?= h($display) ?>">
      <label for="password">รหัสผ่าน</label>
      <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password">
      <label for="confirm">ยืนยันรหัสผ่าน</label>
      <input id="confirm" name="confirm" type="password" required minlength="6" autocomplete="new-password">
      <button class="btn btn-block" type="submit">สร้างบัญชี</button>
    </form>
    <p class="auth-switch">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
  </div>
</section>
<?php page_end(); ?>
