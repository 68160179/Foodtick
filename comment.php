<?php
require 'inc/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
verify_csrf();
require_login();
$uid = current_user()['id'];
$rid = valid_id($_POST['recipe_id'] ?? '');
$back = $rid ? 'recipe.php?id=' . $rid . '#comments' : 'index.php';

if (($_POST['action'] ?? '') === 'delete') {
  // Comment author or the recipe owner can delete (like TikTok)
  $cid = valid_id($_POST['comment_id'] ?? '');
  $stmt = $cid ? db_query($conn, 'DELETE c FROM recipe_comment c JOIN recipe r ON r.id = c.recipe_id
    WHERE c.id = ? AND (c.user_id = ? OR r.user_id = ?)', 'iii', [$cid, $uid, $uid]) : false;
  if ($stmt) { flash(mysqli_stmt_affected_rows($stmt) ? 'ลบคอมเมนต์แล้ว' : 'ลบคอมเมนต์นี้ไม่ได้'); mysqli_stmt_close($stmt); }
  header('Location: ' . $back); exit;
}

$body = trim((string)($_POST['body'] ?? ''));
if (!$rid || !db_one($conn, "SELECT id FROM recipe WHERE id = ? AND status = 'published'", 'i', [$rid])) flash('ไม่พบสูตรนี้');
elseif ($body === '' || mb_strlen($body) > 500) flash('คอมเมนต์ต้องยาว 1–500 ตัวอักษร');
elseif ($stmt = db_query($conn, 'INSERT INTO recipe_comment (recipe_id, user_id, body) VALUES (?, ?, ?)', 'iis', [$rid, $uid, $body])) mysqli_stmt_close($stmt);
else flash('ส่งคอมเมนต์ไม่สำเร็จ ลองใหม่อีกครั้ง');
header('Location: ' . $back);
