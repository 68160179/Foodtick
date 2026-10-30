<?php
require 'inc/bootstrap.php';
$rows = db_all($conn, "SELECT c.slug, c.name, c.emoji, COUNT(r.id) AS total
  FROM category c LEFT JOIN recipe r ON r.category_id = c.id AND r.status = 'published'
  GROUP BY c.id, c.slug, c.name, c.emoji, c.sort_order ORDER BY c.sort_order, c.id");
page_start('หมวดหมู่', 'cats');
?>
<section class="hero">
  <h1>หมวดหมู่</h1>
  <p class="muted">ทอด นึ่ง ต้ม ผัด ตุ๋น ย่าง ปิ้ง และอื่น ๆ</p>
</section>
<div class="cat-grid">
  <?php foreach ($rows as $c): ?>
    <a class="cat-tile" href="index.php?cat=<?= h($c['slug']) ?>">
      <span class="cat-name"><?= h($c['name']) ?></span>
      <span class="cat-count"><?= (int)$c['total'] ?> สูตร</span>
    </a>
  <?php endforeach; ?>
</div>
<?php page_end(); ?>
