<?php
require 'inc/bootstrap.php';
$cats = categories($conn);
$slug = (string)($_GET['cat'] ?? '');
$current = null;
foreach ($cats as $c) if ($c['slug'] === $slug) $current = $c;

if ($current) $rows = db_all($conn, RECIPE_CARD_SQL . " WHERE r.status = 'published' AND r.category_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 60", 'i', [$current['id']]);
else $rows = db_all($conn, RECIPE_CARD_SQL . " WHERE r.status = 'published' ORDER BY r.created_at DESC, r.id DESC LIMIT 60");

page_start($current ? $current['name'] : 'หน้าแรก', 'home');
?>
<section class="hero">
  <h1>สูตรอาหารจากทุกครัว</h1>
  <p class="muted">แบ่งปันเมนูที่ทำเอง เลือกหมวดที่อยากลอง</p>
</section>

<nav class="chips" aria-label="หมวดหมู่">
  <a class="chip <?= $current ? '' : 'active' ?>" href="index.php">ทั้งหมด</a>
  <?php foreach ($cats as $c): ?>
    <a class="chip <?= $current && $current['id'] === $c['id'] ? 'active' : '' ?>" href="index.php?cat=<?= h($c['slug']) ?>"><?= h($c['name']) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($rows) feed($rows);
else empty_state('bowl', $current ? 'หมวด “'.$current['name'].'” ยังไม่มีสูตร' : 'ยังไม่มีใครโพสต์สูตร', 'เป็นคนแรกที่แบ่งปันเมนูของคุณ', '<a class="btn" href="recipe_new.php">โพสต์สูตรแรก</a>'); ?>
<?php page_end(); ?>
