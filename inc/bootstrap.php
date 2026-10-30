<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

const UPLOAD_DIR = __DIR__ . '/../uploads';
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_field() { echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function verify_csrf() { if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid CSRF token'); } }
function valid_id($value) { $n = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]); return $n === false ? null : $n; }

// ---------- Database helpers (prepared statements only) ----------
function db_query($conn, $sql, $types = '', array $params = []) {
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) { error_log(mysqli_error($conn)); return false; }
  if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
  if (!mysqli_stmt_execute($stmt)) { error_log(mysqli_stmt_error($stmt)); mysqli_stmt_close($stmt); return false; }
  return $stmt;
}
function db_all($conn, $sql, $types = '', array $params = []) {
  $stmt = db_query($conn, $sql, $types, $params);
  if (!$stmt) return [];
  $res = mysqli_stmt_get_result($stmt);
  $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
  mysqli_stmt_close($stmt);
  return $rows;
}
function db_one($conn, $sql, $types = '', array $params = []) {
  $rows = db_all($conn, $sql, $types, $params);
  return $rows[0] ?? null;
}

// ---------- Auth ----------
function current_user() {
  global $conn;
  static $checked = false;
  // Once per request, reload the account: it may have been edited, or deleted (e.g. after re-importing the DB)
  if (!$checked && isset($_SESSION['user'])) {
    $checked = true;
    $u = db_one($conn, 'SELECT id, username, display_name, avatar_path FROM app_user WHERE id = ?', 'i', [$_SESSION['user']['id']]);
    if ($u) $_SESSION['user'] = ['id'=>(int)$u['id'], 'username'=>$u['username'], 'display_name'=>$u['display_name'], 'avatar_path'=>$u['avatar_path']];
    else unset($_SESSION['user']);
  }
  return $_SESSION['user'] ?? null;
}
function login_user(array $u) {
  session_regenerate_id(true);
  $_SESSION['user'] = ['id'=>(int)$u['id'], 'username'=>$u['username'], 'display_name'=>$u['display_name'], 'avatar_path'=>$u['avatar_path'] ?? null];
}
// Only allow redirects back to our own pages, e.g. "recipe.php?id=3"
function safe_next($next) {
  $next = (string)$next;
  return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9=&_%-]*)?$/', $next) ? $next : 'index.php';
}
function require_login() {
  if (current_user()) return;
  $here = basename($_SERVER['SCRIPT_NAME']) . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
  flash('กรุณาเข้าสู่ระบบก่อน');
  header('Location: login.php?next=' . urlencode($here));
  exit;
}

function flash($msg = null) {
  if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
  $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

// ---------- Data ----------
function categories($conn) {
  static $cache = null;
  if ($cache === null) $cache = db_all($conn, 'SELECT id, slug, name, emoji FROM category ORDER BY sort_order, id');
  return $cache;
}
// Shared SELECT for feed cards; callers append JOIN / WHERE / ORDER BY.
// Public lists must add "r.status = 'published'" (drafts are private).
const RECIPE_CARD_SQL = 'SELECT r.id, r.title, r.image_path, r.status, r.created_at, u.id AS user_id, u.display_name, u.avatar_path, c.name AS cat_name, c.emoji AS cat_emoji,
    (SELECT COUNT(*) FROM recipe_like l WHERE l.recipe_id = r.id) AS like_count
  FROM recipe r JOIN app_user u ON u.id = r.user_id LEFT JOIN category c ON c.id = r.category_id';

function save_upload($file, &$error) {
  if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) { $error = 'กรุณาเลือกรูป'; return null; }
  if ($file['error'] === UPLOAD_ERR_NO_FILE) { $error = 'กรุณาเลือกรูป'; return null; }
  if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > MAX_UPLOAD_BYTES) { $error = 'รูปใหญ่เกิน 5 MB ลองย่อรูปแล้วอัปโหลดใหม่'; return null; }
  if ($file['error'] !== UPLOAD_ERR_OK) { $error = 'อัปโหลดรูปไม่สำเร็จ ลองใหม่อีกครั้ง'; return null; }
  $info = @getimagesize($file['tmp_name']);
  $types = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_GIF=>'gif', IMAGETYPE_WEBP=>'webp'];
  if (!$info || !isset($types[$info[2]])) { $error = 'รองรับเฉพาะรูป JPG, PNG, GIF หรือ WEBP'; return null; }
  // Tell the site owner exactly what is wrong with the uploads folder (common on shared hosts like Angsila)
  if (!is_dir(UPLOAD_DIR) && !@mkdir(UPLOAD_DIR, 0777)) { $error = 'ไม่พบโฟลเดอร์ uploads บนเซิร์ฟเวอร์ (สร้างโฟลเดอร์ uploads ไว้ข้าง index.php)'; return null; }
  if (!is_writable(UPLOAD_DIR)) { $error = 'เว็บไม่มีสิทธิ์เขียนโฟลเดอร์ uploads (ตั้งสิทธิ์โฟลเดอร์ uploads เป็น 777)'; return null; }
  $name = bin2hex(random_bytes(16)) . '.' . $types[$info[2]];
  if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) { error_log('move_uploaded_file failed: ' . UPLOAD_DIR); $error = 'บันทึกรูปไม่สำเร็จ ลองใหม่อีกครั้ง'; return null; }
  return 'uploads/' . $name;
}

// ---------- Layout ----------
// Simple line icons (inline SVG, colour follows the text)
function icon($name, $size = 20) {
  static $paths = [
    'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'heart' => '<path d="M12 20s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 10c0 5.65-7 10-7 10z"/>',
    'bookmark' => '<path d="M6 3h12v18l-6-4-6 4z"/>',
    'comment' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
    'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
    'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
    'draft' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
    'bowl' => '<path d="M3 11h18a9 9 0 0 1-18 0z"/><path d="M8 7c0-1.5 1-2 1-3.5M12 7c0-1.5 1-2 1-3.5M16 7c0-1.5 1-2 1-3.5"/>',
    'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
  ];
  return '<svg class="icon" width="'.(int)$size.'" height="'.(int)$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
}
function avatar($name, $size = '', $path = null) {
  if ($path) return '<img class="avatar '.h($size).'" src="'.h($path).'" alt="">';
  // Skip Thai leading vowels (เ แ โ ใ ไ) so "แม่มด" shows "ม"
  $name = preg_replace('/^[\x{0E40}-\x{0E44}]+/u', '', (string)$name);
  $initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
  return '<span class="avatar '.h($size).'" aria-hidden="true">'.h($initial).'</span>';
}
function empty_state($iconName, $title, $text = '', $actionHtml = '') {
  echo '<div class="empty"><div class="empty-icon">'.icon($iconName, 28).'</div>';
  if ($title !== '') echo '<h2>'.h($title).'</h2>';
  if ($text !== '') echo '<p class="muted">'.h($text).'</p>';
  echo $actionHtml.'</div>';
}
function thai_date($dt) {
  $m = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
  $t = strtotime($dt);
  return (int)date('j', $t).' '.$m[(int)date('n', $t)].' '.((int)date('Y', $t) + 543);
}
function recipe_card(array $r) { $draft = $r['status'] === 'draft'; ?>
  <a class="card" href="<?= $draft ? 'recipe_new.php' : 'recipe.php' ?>?id=<?= (int)$r['id'] ?>">
    <div class="card-img">
      <?php if ($r['image_path']): ?><img src="<?= h($r['image_path']) ?>" alt="<?= h($r['title']) ?>" loading="lazy"><?php else: ?><div class="card-noimg"><?= icon('image', 28) ?></div><?php endif; ?>
      <?php if ($draft): ?><span class="tag tag-draft">ร่าง</span><?php elseif ($r['cat_name'] !== null): ?><span class="tag"><?= h($r['cat_name']) ?></span><?php endif; ?>
    </div>
    <div class="card-body">
      <h3><?= h($r['title']) ?></h3>
      <div class="card-meta"><?= avatar($r['display_name'], 'sm', $r['avatar_path']) ?><span class="card-author"><?= h($r['display_name']) ?></span>
        <?php if (!$draft): ?><span class="card-likes" aria-label="<?= (int)$r['like_count'] ?> ถูกใจ"><?= icon('heart', 14) ?><?= (int)$r['like_count'] ?></span><?php endif; ?></div>
    </div>
  </a>
<?php }
function feed(array $rows) {
  echo '<div class="feed">';
  foreach ($rows as $r) recipe_card($r);
  echo '</div>';
}

function page_start($title, $active = '') {
  $u = current_user();
  $nav = ['home'=>['index.php','หน้าแรก','home'], 'cats'=>['categories.php','หมวดหมู่','grid'], 'new'=>['recipe_new.php','โพสต์','plus'], 'search'=>['search.php','ค้นหา','search'], 'me'=>[$u ? 'profile.php' : 'login.php', $u ? 'ฉัน' : 'เข้าสู่ระบบ','user']];
  ?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> · FOOD TRICKS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-in">
    <a class="logo" href="index.php">FOOD TRICKS</a>
    <nav class="topnav" aria-label="เมนูหลัก">
      <?php foreach ($nav as $key => [$href, $label, $icon]) if ($key !== 'me') echo '<a href="'.$href.'"'.($key === $active ? ' class="active"' : '').'>'.$label.'</a>'; ?>
    </nav>
    <div class="userbox">
      <?php if ($u): ?>
        <a class="userlink" href="profile.php"><?= avatar($u['display_name'], 'sm', $u['avatar_path']) ?><span><?= h($u['display_name']) ?></span></a>
        <form method="post" action="logout.php"><?php csrf_field(); ?><button class="btn btn-ghost btn-sm" type="submit">ออกจากระบบ</button></form>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="login.php">เข้าสู่ระบบ</a>
        <a class="btn btn-sm" href="register.php">สมัครสมาชิก</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="wrap">
<?php if ($m = flash()) echo '<div class="flash" role="status">'.h($m).'</div>'; ?>
<?php
  $GLOBALS['__nav'] = [$nav, $active];
}

function page_end() {
  [$nav, $active] = $GLOBALS['__nav'];
  ?>
</main>
<nav class="bottomnav" aria-label="เมนูมือถือ">
  <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
    <a href="<?= $href ?>" class="<?= $key === $active ? 'active' : '' ?><?= $key === 'new' ? ' bn-new' : '' ?>"><span class="bn-icon"><?= icon($icon, 22) ?></span><span><?= $label ?></span></a>
  <?php endforeach; ?>
</nav>
<footer class="foot">FOOD TRICKS · แบ่งปันสูตรอาหารจากทุกครัว</footer>
<!-- In-page confirm (forms with data-confirm). Native confirm() is blocked in some embedded browsers. -->
<dialog class="dialog" id="confirm-dialog" aria-labelledby="confirm-text">
  <form method="dialog">
    <p id="confirm-text"></p>
    <div class="dialog-actions">
      <button class="btn btn-ghost" value="cancel">ยกเลิก</button>
      <button class="btn btn-danger" value="ok" id="confirm-ok">ลบ</button>
    </div>
  </form>
</dialog>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php }
