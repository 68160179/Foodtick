<?php
require 'inc/bootstrap.php';
$id = valid_id($_GET['id'] ?? '');
if (!$id) { require_login(); $id = current_user()['id']; }
$person = db_one($conn, 'SELECT id, username, display_name, avatar_path, bio, created_at FROM app_user WHERE id = ?', 'i', [$id]);
if (!$person) { http_response_code(404); page_start('ไม่พบผู้ใช้'); empty_state('user', 'ไม่พบผู้ใช้นี้'); page_end(); exit; }
$mine = current_user() && current_user()['id'] === (int)$person['id'];

// Like TikTok: drafts, saved and liked are private to the owner
$tabs = ['posts' => 'โพสต์'];
if ($mine) $tabs += ['drafts' => 'ร่าง', 'saved' => 'คลัง', 'liked' => 'ถูกใจ'];
$tab = $_GET['tab'] ?? 'posts';
if (!isset($tabs[$tab])) $tab = 'posts';

$stats = db_one($conn, "SELECT
  (SELECT COUNT(*) FROM recipe WHERE user_id = ? AND status = 'published') AS posts,
  (SELECT COUNT(*) FROM recipe_like l JOIN recipe r ON r.id = l.recipe_id WHERE r.user_id = ? AND r.status = 'published') AS likes", 'ii', [$id, $id]);

switch ($tab) {
  case 'drafts':
    $rows = db_all($conn, RECIPE_CARD_SQL . " WHERE r.user_id = ? AND r.status = 'draft' ORDER BY r.created_at DESC, r.id DESC", 'i', [$id]); break;
  case 'saved':
    $rows = db_all($conn, RECIPE_CARD_SQL . " JOIN recipe_save s ON s.recipe_id = r.id WHERE s.user_id = ? AND r.status = 'published' ORDER BY s.created_at DESC", 'i', [$id]); break;
  case 'liked':
    $rows = db_all($conn, RECIPE_CARD_SQL . " JOIN recipe_like k ON k.recipe_id = r.id WHERE k.user_id = ? AND r.status = 'published' ORDER BY k.created_at DESC", 'i', [$id]); break;
  default:
    $rows = db_all($conn, RECIPE_CARD_SQL . " WHERE r.user_id = ? AND r.status = 'published' ORDER BY r.created_at DESC, r.id DESC", 'i', [$id]);
}
$empty = [
  'posts' => ['bowl', 'ยังไม่มีสูตร'],
  'drafts' => ['draft', 'ยังไม่มีร่าง ตอนเขียนสูตรกด “บันทึกร่าง” เพื่อเก็บไว้โพสต์วันหลัง'],
  'saved' => ['bookmark', 'คลังยังว่าง กดบันทึกในโพสต์ที่ชอบเพื่อเก็บสูตรไว้ที่นี่'],
  'liked' => ['heart', 'ยังไม่ได้กดใจสูตรไหน'],
];
page_start($person['display_name'], $mine ? 'me' : '');
?>
<section class="profile-head">
  <?= avatar($person['display_name'], 'xl', $person['avatar_path']) ?>
  <div class="profile-info">
    <h1><?= h($person['display_name']) ?></h1>
    <p class="muted">@<?= h($person['username']) ?> · สมาชิกตั้งแต่ <?= h(thai_date($person['created_at'])) ?></p>
    <?php if ($person['bio'] !== null && $person['bio'] !== ''): ?><p class="profile-bio"><?= h($person['bio']) ?></p><?php endif; ?>
    <p class="profile-stats"><strong><?= (int)$stats['posts'] ?></strong> โพสต์ <strong><?= (int)$stats['likes'] ?></strong> ถูกใจ</p>
  </div>
  <?php if ($mine): ?>
    <div class="profile-actions">
      <a class="btn btn-ghost" href="profile_edit.php">แก้ไขโปรไฟล์</a>
      <form method="post" action="logout.php"><?php csrf_field(); ?><button class="btn btn-ghost" type="submit">ออกจากระบบ</button></form>
    </div>
  <?php endif; ?>
</section>

<?php if (count($tabs) > 1): ?>
  <nav class="tabs" aria-label="แท็บโปรไฟล์">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="profile.php?tab=<?= $key ?>" class="<?= $key === $tab ? 'active' : '' ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php if ($rows) feed($rows);
else empty_state($empty[$tab][0], '', $empty[$tab][1], $mine && $tab === 'posts' ? '<a class="btn" href="recipe_new.php">โพสต์สูตรแรก</a>' : ''); ?>
<?php page_end(); ?>
