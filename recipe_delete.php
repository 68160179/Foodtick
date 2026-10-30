<?php
require 'inc/bootstrap.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
verify_csrf();
$id = valid_id($_POST['id'] ?? '');
$uid = current_user()['id'];
// WHERE user_id = ? so people can only delete their own recipes
$r = $id ? db_one($conn, 'SELECT image_path FROM recipe WHERE id = ? AND user_id = ?', 'ii', [$id, $uid]) : null;
if ($r && ($stmt = db_query($conn, 'DELETE FROM recipe WHERE id = ? AND user_id = ?', 'ii', [$id, $uid]))) {
  mysqli_stmt_close($stmt);
  if ($r['image_path']) @unlink(UPLOAD_DIR . '/' . basename($r['image_path']));
  flash('ลบสูตรแล้ว');
  header('Location: profile.php'); exit;
}
flash('ลบไม่สำเร็จ หรือคุณไม่ใช่เจ้าของสูตรนี้');
header('Location: ' . ($id ? 'recipe.php?id=' . $id : 'index.php'));
