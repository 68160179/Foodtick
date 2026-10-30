<?php
// Edit your own profile: photo, display name, bio, and optionally the password
require 'inc/bootstrap.php';
require_login();
$uid = current_user()['id'];
$me = db_one($conn, 'SELECT username, display_name, avatar_path, bio, password_hash FROM app_user WHERE id = ?', 'i', [$uid]);
$display = $me['display_name']; $bio = (string)$me['bio']; $avatarPath = $me['avatar_path'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $error = 'รูปใหญ่เกินที่เซิร์ฟเวอร์รับได้ ลองย่อรูปแล้วอัปโหลดใหม่';
  } else {
    verify_csrf();
    $display = trim((string)($_POST['display_name'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $removeAvatar = !empty($_POST['remove_avatar']);
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $newUpload = isset($_FILES['avatar']['error']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($display === '' || mb_strlen($display) > 60) $error = 'กรอกชื่อที่แสดง (ไม่เกิน 60 ตัวอักษร)';
    elseif (mb_strlen($bio) > 160) $error = 'คำแนะนำตัวยาวไม่เกิน 160 ตัวอักษร';
    elseif ($new !== '' && !password_verify($current, $me['password_hash'])) $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    elseif ($new !== '' && strlen($new) < 6) $error = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 6 ตัว';
    elseif ($new !== '' && $new !== $confirm) $error = 'รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน';
    else {
      $newAvatar = $newUpload ? save_upload($_FILES['avatar'], $error) : null;
      if (!$newUpload || $newAvatar) {
        $path = $newAvatar ?: ($removeAvatar ? null : $avatarPath);
        $hash = $new !== '' ? password_hash($new, PASSWORD_DEFAULT) : $me['password_hash'];
        $stmt = db_query($conn, 'UPDATE app_user SET display_name = ?, bio = ?, avatar_path = ?, password_hash = ? WHERE id = ?', 'ssssi', [$display, $bio === '' ? null : $bio, $path, $hash, $uid]);
        if ($stmt) {
          mysqli_stmt_close($stmt);
          // Remove the old photo file once it is no longer used
          if ($avatarPath && $path !== $avatarPath) @unlink(UPLOAD_DIR . '/' . basename($avatarPath));
          flash('บันทึกโปรไฟล์แล้ว');
          header('Location: profile.php'); exit;
        }
        if ($newAvatar) @unlink(UPLOAD_DIR . '/' . basename($newAvatar));
        $error = 'บันทึกไม่สำเร็จ ลองใหม่อีกครั้ง';
      }
    }
  }
}
page_start('แก้ไขโปรไฟล์', 'me');
?>
<section class="hero hero-sm">
  <h1>แก้ไขโปรไฟล์</h1>
</section>
<?php if ($error) echo '<p class="err" role="alert">'.h($error).'</p>'; ?>
<form method="post" enctype="multipart/form-data" class="form narrow-form">
  <?php csrf_field(); ?>
  <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_BYTES ?>">

  <fieldset class="box">
    <legend>รูปโปรไฟล์</legend>
    <div class="avatar-edit">
      <div id="avatar-preview"><?= avatar($display, 'xl', $avatarPath) ?></div>
      <div>
        <label for="avatar" class="btn btn-ghost btn-sm">เลือกรูปใหม่</label>
        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="sr-only">
        <small class="hint">JPG, PNG, GIF, WEBP · ไม่เกิน 5 MB</small>
        <?php if ($avatarPath): ?>
          <label class="check"><input type="checkbox" id="remove_avatar" name="remove_avatar" value="1"> ลบรูปโปรไฟล์</label>
        <?php endif; ?>
      </div>
    </div>
  </fieldset>

  <fieldset class="box">
    <legend>ข้อมูลทั่วไป</legend>
    <label for="username">ชื่อผู้ใช้</label>
    <input id="username" value="@<?= h($me['username']) ?>" disabled>
    <small class="hint">ชื่อผู้ใช้ใช้ล็อกอิน เปลี่ยนไม่ได้</small>
    <label for="display_name">ชื่อที่แสดง</label>
    <input id="display_name" name="display_name" required maxlength="60" value="<?= h($display) ?>">
    <label for="bio">แนะนำตัว <span class="muted">(ไม่บังคับ)</span></label>
    <textarea id="bio" name="bio" rows="3" maxlength="160" placeholder="เช่น ชอบทำอาหารเช้าง่าย ๆ"><?= h($bio) ?></textarea>
  </fieldset>

  <fieldset class="box">
    <legend>เปลี่ยนรหัสผ่าน <span class="muted">(ไม่บังคับ)</span></legend>
    <label for="current_password">รหัสผ่านปัจจุบัน</label>
    <input id="current_password" name="current_password" type="password" autocomplete="current-password">
    <label for="new_password">รหัสผ่านใหม่</label>
    <input id="new_password" name="new_password" type="password" minlength="6" autocomplete="new-password">
    <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
    <input id="confirm_password" name="confirm_password" type="password" minlength="6" autocomplete="new-password">
  </fieldset>

  <div class="submit-bar">
    <a class="btn btn-ghost btn-lg" href="profile.php">ยกเลิก</a>
    <button class="btn btn-lg" type="submit">บันทึก</button>
  </div>
</form>
<?php page_end(); ?>
