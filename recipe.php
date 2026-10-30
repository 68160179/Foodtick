<?php
require 'inc/bootstrap.php';
$id = valid_id($_GET['id'] ?? '');
$r = $id ? db_one($conn, 'SELECT r.*, u.display_name, u.avatar_path, c.name AS cat_name, c.emoji AS cat_emoji, c.slug AS cat_slug
  FROM recipe r JOIN app_user u ON u.id = r.user_id LEFT JOIN category c ON c.id = r.category_id WHERE r.id = ?', 'i', [$id]) : null;
$me = current_user();
$isOwner = $r && $me && $me['id'] === (int)$r['user_id'];
// Drafts are private: only the author can open them
if (!$r || ($r['status'] === 'draft' && !$isOwner)) {
  http_response_code(404); page_start('ไม่พบสูตร');
  empty_state('bowl', 'ไม่พบสูตรนี้', 'อาจถูกลบไปแล้ว', '<a class="btn" href="index.php">กลับหน้าแรก</a>');
  page_end(); exit;
}
$isDraft = $r['status'] === 'draft';
$ingredients = db_all($conn, 'SELECT name, quantity FROM recipe_ingredient WHERE recipe_id = ? ORDER BY sort_order, id', 'i', [$id]);
$steps = db_all($conn, 'SELECT instruction FROM recipe_step WHERE recipe_id = ? ORDER BY step_no, id', 'i', [$id]);
$uid = $me ? $me['id'] : 0;
$stats = db_one($conn, 'SELECT
  (SELECT COUNT(*) FROM recipe_like WHERE recipe_id = ?) AS likes,
  (SELECT COUNT(*) FROM recipe_save WHERE recipe_id = ?) AS saves,
  EXISTS (SELECT 1 FROM recipe_like WHERE recipe_id = ? AND user_id = ?) AS liked,
  EXISTS (SELECT 1 FROM recipe_save WHERE recipe_id = ? AND user_id = ?) AS saved', 'iiiiii', [$id, $id, $id, $uid, $id, $uid]);
$comments = db_all($conn, 'SELECT c.id, c.user_id, c.body, c.created_at, u.display_name, u.avatar_path
  FROM recipe_comment c JOIN app_user u ON u.id = c.user_id WHERE c.recipe_id = ? ORDER BY c.created_at, c.id', 'i', [$id]);

function toggle_button($type, $id, $active, $count, $icon, $label) { ?>
  <form method="post" action="toggle.php" class="js-toggle">
    <?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="type" value="<?= $type ?>">
    <button type="submit" class="act act-<?= $type ?><?= $active ? ' on' : '' ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>" aria-label="<?= h($label) ?>">
      <span class="act-icon"><?= icon($icon) ?></span><span class="act-count"><?= (int)$count ?></span>
    </button>
  </form>
<?php }

page_start($r['title']);
?>
<article class="post">
  <?php if ($isDraft): ?>
    <div class="draft-banner"><?= icon('draft', 18) ?><span>นี่คือ<strong>ร่าง</strong> มีแค่คุณที่เห็น</span> <a class="btn btn-sm" href="recipe_new.php?id=<?= (int)$r['id'] ?>">แก้ไขและโพสต์</a></div>
  <?php endif; ?>

  <figure class="post-img">
    <?php if ($r['image_path']): ?><img src="<?= h($r['image_path']) ?>" alt="<?= h($r['title']) ?>"><?php else: ?><div class="card-noimg"><?= icon('image', 32) ?></div><?php endif; ?>
  </figure>

  <div class="post-head">
    <?php if ($r['cat_name'] !== null): ?><a class="tag tag-link" href="index.php?cat=<?= h($r['cat_slug']) ?>"><?= h($r['cat_name']) ?></a><?php endif; ?>
    <h1><?= h($r['title']) ?></h1>
    <div class="post-row">
      <a class="post-author" href="profile.php?id=<?= (int)$r['user_id'] ?>"><?= avatar($r['display_name'], '', $r['avatar_path']) ?><span><strong><?= h($r['display_name']) ?></strong><small class="muted"><?= h(thai_date($r['created_at'])) ?></small></span></a>
      <?php if (!$isDraft): ?>
        <div class="actions" id="actions">
          <?php toggle_button('like', $id, $stats['liked'], $stats['likes'], 'heart', 'กดใจ'); ?>
          <?php toggle_button('save', $id, $stats['saved'], $stats['saves'], 'bookmark', 'บันทึกลงคลัง'); ?>
          <a class="act" href="#comments" aria-label="คอมเมนต์"><span class="act-icon"><?= icon('comment') ?></span><span class="act-count"><?= count($comments) ?></span></a>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($r['description'] !== null && $r['description'] !== ''): ?><p class="post-desc"><?= nl2br(h($r['description'])) ?></p><?php endif; ?>
  </div>

  <div class="boxes">
    <section class="box box-ing">
      <h2><span class="box-no">1</span> วัตถุดิบ &amp; ปริมาณ</h2>
      <ul class="ing-list">
        <?php foreach ($ingredients as $i): ?>
          <li><span class="ing-name"><?= h($i['name']) ?></span><span class="ing-qty"><?= h($i['quantity']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="box box-steps">
      <h2><span class="box-no">2</span> วิธีการทำ</h2>
      <ol class="step-list">
        <?php foreach ($steps as $s): ?><li><?= nl2br(h($s['instruction'])) ?></li><?php endforeach; ?>
      </ol>
    </section>
  </div>

  <?php if ($isOwner): ?>
    <div class="owner-bar">
      <span class="muted"><?= $isDraft ? 'ร่างของคุณ' : 'นี่คือสูตรของคุณ' ?></span>
      <div class="owner-actions">
        <a class="btn btn-ghost btn-sm" href="recipe_new.php?id=<?= (int)$r['id'] ?>">แก้ไข</a>
        <form method="post" action="recipe_delete.php" data-confirm="ลบสูตร “<?= h($r['title']) ?>” ถาวรใช่ไหม? ลบแล้วกู้คืนไม่ได้">
          <?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">ลบสูตรนี้</button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$isDraft): ?>
  <section class="comments" id="comments">
    <h2>คอมเมนต์ <span class="muted"><?= count($comments) ?></span></h2>
    <?php if ($me): ?>
      <form method="post" action="comment.php" class="comment-form">
        <?php csrf_field(); ?><input type="hidden" name="recipe_id" value="<?= (int)$id ?>">
        <?= avatar($me['display_name'], 'sm', $me['avatar_path']) ?>
        <label for="body" class="sr-only">เขียนคอมเมนต์</label>
        <input id="body" name="body" required maxlength="500" placeholder="เขียนคอมเมนต์…" autocomplete="off">
        <button class="btn btn-sm" type="submit">ส่ง</button>
      </form>
    <?php else: ?>
      <p class="muted"><a href="login.php?next=<?= urlencode('recipe.php?id=' . $id) ?>">เข้าสู่ระบบ</a> เพื่อคอมเมนต์</p>
    <?php endif; ?>

    <?php if (!$comments): ?>
      <p class="muted comment-empty">ยังไม่มีคอมเมนต์</p>
    <?php endif; ?>
    <ul class="comment-list">
      <?php foreach ($comments as $c): ?>
        <li class="comment">
          <a href="profile.php?id=<?= (int)$c['user_id'] ?>"><?= avatar($c['display_name'], 'sm', $c['avatar_path']) ?></a>
          <div class="comment-main">
            <div class="comment-head"><a href="profile.php?id=<?= (int)$c['user_id'] ?>"><strong><?= h($c['display_name']) ?></strong></a>
              <?php if ((int)$c['user_id'] === (int)$r['user_id']): ?><span class="tag">ผู้เขียน</span><?php endif; ?>
              <small class="muted"><?= h(thai_date($c['created_at'])) ?></small></div>
            <p><?= nl2br(h($c['body'])) ?></p>
          </div>
          <?php if ($me && ($me['id'] === (int)$c['user_id'] || $isOwner)): ?>
            <form method="post" action="comment.php" data-confirm="ลบคอมเมนต์นี้ใช่ไหม?">
              <?php csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="recipe_id" value="<?= (int)$id ?>"><input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
              <button class="icon-btn icon-btn-sm" type="submit" aria-label="ลบคอมเมนต์" title="ลบคอมเมนต์"><?= icon('x', 16) ?></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</article>
<?php page_end(); ?>
