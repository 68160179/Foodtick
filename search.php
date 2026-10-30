<?php
require 'inc/bootstrap.php';
$q = trim((string)($_GET['q'] ?? ''));
$catId = valid_id($_GET['cat'] ?? '');
$cats = categories($conn);
$rows = null;
if ($q !== '' || $catId) {
  // Escape LIKE wildcards so "%" or "_" typed by the user are matched literally
  $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
  $sql = RECIPE_CARD_SQL . " WHERE r.status = 'published' AND (r.title LIKE ? OR r.description LIKE ?
      OR EXISTS (SELECT 1 FROM recipe_ingredient i WHERE i.recipe_id = r.id AND i.name LIKE ?))";
  $types = 'sss'; $params = [$like, $like, $like];
  if ($catId) { $sql .= ' AND r.category_id = ?'; $types .= 'i'; $params[] = $catId; }
  $rows = db_all($conn, $sql . ' ORDER BY r.created_at DESC, r.id DESC LIMIT 60', $types, $params);
}
page_start('ค้นหา', 'search');
?>
<section class="hero">
  <h1>ค้นหาสูตร</h1>
  <p class="muted">ค้นจากชื่อเมนู คำบรรยาย หรือวัตถุดิบ เช่น “ฟักทอง”</p>
</section>
<form class="searchbar" method="get" role="search">
  <label for="q" class="sr-only">คำค้นหา</label>
  <input id="q" name="q" type="search" placeholder="เช่น ฟักทอง, ไก่ทอด, ซุป" value="<?= h($q) ?>" maxlength="100" autofocus>
  <label for="cat" class="sr-only">หมวดหมู่</label>
  <select id="cat" name="cat">
    <option value="">ทุกหมวด</option>
    <?php foreach ($cats as $c) echo '<option value="'.(int)$c['id'].'"'.($catId === (int)$c['id'] ? ' selected' : '').'>'.h($c['name']).'</option>'; ?>
  </select>
  <button class="btn" type="submit">ค้นหา</button>
</form>

<?php if ($rows === null) empty_state('search', '', 'พิมพ์ชื่อเมนูหรือวัตถุดิบที่อยากกิน');
elseif (!$rows) empty_state('search', 'ไม่พบสูตรที่ตรงกับ “'.$q.'”', 'ลองคำที่สั้นลง หรือเปลี่ยนหมวดหมู่');
else { echo '<p class="result-count">พบ '.count($rows).' สูตร</p>'; feed($rows); } ?>
<?php page_end(); ?>
