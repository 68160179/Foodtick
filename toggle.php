<?php
// Like / save (bookmark) a recipe. Works as a normal form POST, or via fetch() from app.js (returns JSON).
require 'inc/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
verify_csrf();
$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
$id = valid_id($_POST['id'] ?? '');
$back = $id ? 'recipe.php?id=' . $id : 'index.php';

function reply($ajax, $back, array $data, $code = 200) {
  if ($ajax) { http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); }
  else header('Location: ' . ($data['login'] ?? $back));
  exit;
}

if (!current_user()) {
  flash('เข้าสู่ระบบก่อน แล้วค่อยกดใจหรือบันทึก');
  reply($ajax, $back, ['login' => 'login.php?next=' . urlencode($back)], 401);
}
// Table names come from this fixed list only, never from user input
$tables = ['like' => 'recipe_like', 'save' => 'recipe_save'];
$table = $tables[$_POST['type'] ?? ''] ?? null;
if (!$id || !$table || !db_one($conn, "SELECT id FROM recipe WHERE id = ? AND status = 'published'", 'i', [$id])) reply($ajax, $back, ['error' => 'not found'], 404);

$uid = current_user()['id'];
$has = db_one($conn, "SELECT 1 AS x FROM $table WHERE user_id = ? AND recipe_id = ?", 'ii', [$uid, $id]);
$sql = $has ? "DELETE FROM $table WHERE user_id = ? AND recipe_id = ?" : "INSERT IGNORE INTO $table (user_id, recipe_id) VALUES (?, ?)";
if ($stmt = db_query($conn, $sql, 'ii', [$uid, $id])) mysqli_stmt_close($stmt);
$count = (int)db_one($conn, "SELECT COUNT(*) AS n FROM $table WHERE recipe_id = ?", 'i', [$id])['n'];
reply($ajax, $back, ['active' => !$has, 'count' => $count]);
