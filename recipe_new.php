<?php
// Create a recipe, or edit one of your own (?id=). "บันทึกร่าง" keeps it private; "โพสต์" publishes it.
require 'inc/bootstrap.php';
require_login();
$uid = current_user()['id'];
$cats = categories($conn);
$catIds = array_map(fn($c) => (int)$c['id'], $cats);

$id = valid_id($_GET['id'] ?? $_POST['id'] ?? '');
$existing = $id ? db_one($conn, 'SELECT * FROM recipe WHERE id = ? AND user_id = ?', 'ii', [$id, $uid]) : null;
if ($id && !$existing) {
  http_response_code(404); page_start('ไม่พบสูตร');
  empty_state('bowl', 'ไม่พบสูตรนี้ หรือคุณไม่ใช่เจ้าของ');
  page_end(); exit;
}
$wasPublished = $existing && $existing['status'] === 'published';
$image = $existing['image_path'] ?? null;

$error = '';
if ($existing) {
  $title = $existing['title']; $desc = (string)$existing['description']; $catId = (int)$existing['category_id'];
  $ingredients = db_all($conn, 'SELECT name, quantity FROM recipe_ingredient WHERE recipe_id = ? ORDER BY sort_order, id', 'i', [$id]);
  $steps = array_column(db_all($conn, 'SELECT instruction FROM recipe_step WHERE recipe_id = ? ORDER BY step_no, id', 'i', [$id]), 'instruction');
} else {
  $title = ''; $desc = ''; $catId = 0;
  $ingredients = []; $steps = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // A file larger than post_max_size makes PHP drop the whole POST body
  if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $error = 'รูปใหญ่เกินที่เซิร์ฟเวอร์รับได้ ลองย่อรูปแล้วอัปโหลดใหม่';
  } else {
    verify_csrf();
    // A published recipe can't go back to draft; its only button is "save changes"
    $publish = $wasPublished || ($_POST['action'] ?? '') === 'publish';
    $title = trim((string)($_POST['title'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $catId = (int)($_POST['category_id'] ?? 0);
    $names = (array)($_POST['ing_name'] ?? []); $qtys = (array)($_POST['ing_qty'] ?? []);
    $ingredients = [];
    foreach ($names as $k => $n) {
      $n = trim((string)$n); $q = trim((string)($qtys[$k] ?? ''));
      if ($n !== '' || $q !== '') $ingredients[] = ['name'=>$n, 'quantity'=>$q];
    }
    $steps = array_values(array_filter(array_map(fn($s) => trim((string)$s), (array)($_POST['step'] ?? [])), fn($s) => $s !== ''));
    if (!$publish && $title === '') $title = 'ร่างไม่มีชื่อ';
    $newUpload = isset($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;

    // Checks that apply to drafts too (lengths), then what a published post must have
    if ($title === '' || mb_strlen($title) > 150) $error = 'กรอกชื่อเมนู (ไม่เกิน 150 ตัวอักษร)';
    elseif (mb_strlen($desc) > 1000) $error = 'คำบรรยายยาวเกิน 1000 ตัวอักษร';
    elseif ($catId && !in_array($catId, $catIds, true)) $error = 'เลือกประเภทอาหาร';
    elseif (count($ingredients) > 40) $error = 'วัตถุดิบได้ไม่เกิน 40 อย่าง';
    elseif (array_filter($ingredients, fn($i) => $i['name'] === '' || mb_strlen($i['name']) > 120 || mb_strlen($i['quantity']) > 60)) $error = 'วัตถุดิบทุกแถวต้องมีชื่อ (ไม่เกิน 120 ตัว) และปริมาณไม่เกิน 60 ตัว';
    elseif (count($steps) > 30) $error = 'วิธีทำได้ไม่เกิน 30 ขั้นตอน';
    elseif (array_filter($steps, fn($s) => mb_strlen($s) > 2000)) $error = 'ขั้นตอนแต่ละข้อยาวไม่เกิน 2000 ตัวอักษร';
    elseif ($publish && !$catId) $error = 'เลือกประเภทอาหารก่อนโพสต์';
    elseif ($publish && !$ingredients) $error = 'ใส่วัตถุดิบอย่างน้อย 1 อย่างก่อนโพสต์';
    elseif ($publish && !$steps) $error = 'ใส่วิธีทำอย่างน้อย 1 ขั้นตอนก่อนโพสต์';
    elseif ($publish && !$image && !$newUpload) $error = 'ใส่รูปอาหารก่อนโพสต์';
    else {
      $newImage = $newUpload ? save_upload($_FILES['image'], $error) : null;
      if (!$newUpload || $newImage) {
        $img = $newImage ?: $image;
        $status = $publish ? 'published' : 'draft';
        $cat = $catId ?: null;
        mysqli_begin_transaction($conn);
        if ($existing) {
          // Publishing a draft bumps it to the top of the feed, like posting it today
          $bump = $publish && !$wasPublished ? ', created_at = NOW()' : '';
          $ok = (bool)db_query($conn, "UPDATE recipe SET category_id = ?, title = ?, description = ?, image_path = ?, status = ?$bump WHERE id = ? AND user_id = ?",
            'issssii', [$cat, $title, $desc, $img, $status, $id, $uid]);
          $rid = $id;
          $ok = $ok && db_query($conn, 'DELETE FROM recipe_ingredient WHERE recipe_id = ?', 'i', [$rid]) && db_query($conn, 'DELETE FROM recipe_step WHERE recipe_id = ?', 'i', [$rid]);
        } else {
          $ok = (bool)db_query($conn, 'INSERT INTO recipe (user_id, category_id, title, description, image_path, status) VALUES (?, ?, ?, ?, ?, ?)', 'iissss', [$uid, $cat, $title, $desc, $img, $status]);
          $rid = $ok ? mysqli_insert_id($conn) : 0;
        }
        foreach ($ingredients as $n => $i) {
          if (!$ok) break;
          $ok = (bool)db_query($conn, 'INSERT INTO recipe_ingredient (recipe_id, name, quantity, sort_order) VALUES (?, ?, ?, ?)', 'issi', [$rid, $i['name'], $i['quantity'], $n]);
        }
        foreach ($steps as $n => $s) {
          if (!$ok) break;
          $ok = (bool)db_query($conn, 'INSERT INTO recipe_step (recipe_id, step_no, instruction) VALUES (?, ?, ?)', 'iis', [$rid, $n + 1, $s]);
        }
        if ($ok) {
          mysqli_commit($conn);
          if ($newImage && $image) @unlink(UPLOAD_DIR . '/' . basename($image));
          if ($publish) { flash($wasPublished ? 'บันทึกการแก้ไขแล้ว' : 'โพสต์สูตรเรียบร้อย'); header('Location: recipe.php?id=' . $rid); }
          else { flash('บันทึกลงร่างแล้ว กลับมาโพสต์เมื่อไหร่ก็ได้'); header('Location: profile.php?tab=drafts'); }
          exit;
        }
        mysqli_rollback($conn);
        if ($newImage) @unlink(UPLOAD_DIR . '/' . basename($newImage));
        $error = 'บันทึกสูตรไม่สำเร็จ ลองใหม่อีกครั้ง';
      }
    }
  }
}
if (!$ingredients) $ingredients = [['name'=>'', 'quantity'=>''], ['name'=>'', 'quantity'=>''], ['name'=>'', 'quantity'=>'']];
if (!$steps) $steps = ['', ''];

$heading = $wasPublished ? 'แก้ไขสูตร' : ($existing ? 'แก้ไขร่าง' : 'โพสต์สูตรใหม่');
page_start($heading, $existing ? 'me' : 'new');
?>
<section class="hero hero-sm">
  <h1><?= h($heading) ?></h1>
  <p class="muted"><?= $wasPublished ? 'แก้แล้วกดบันทึก โพสต์จะอัปเดตทันที' : 'ยังไม่พร้อม? กด “บันทึกร่าง” เก็บไว้ก่อน แล้วกลับมาโพสต์วันหลังได้' ?></p>
</section>
<?php if ($error) echo '<p class="err" role="alert">'.h($error).'</p>'; ?>
<form method="post" enctype="multipart/form-data" class="form recipe-form" action="recipe_new.php<?= $existing ? '?id=' . (int)$id : '' ?>">
  <?php csrf_field(); ?>
  <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_BYTES ?>">

  <fieldset class="box">
    <legend><span class="box-no">1</span> รูปอาหารที่ทำ</legend>
    <label class="dropzone" for="image">
      <img id="preview" alt="" <?= $image ? 'src="'.h($image).'"' : 'hidden' ?>>
      <span class="dz-text" id="dz-text" <?= $image ? 'hidden' : '' ?>><?= icon('image', 28) ?>แตะเพื่อเลือกรูป<small>JPG, PNG, GIF, WEBP · ไม่เกิน 5 MB</small></span>
    </label>
    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp" <?= $image ? '' : 'required' ?> class="sr-only">
    <?php if ($image): ?><small class="hint">แตะรูปเพื่อเปลี่ยน</small><?php endif; ?>
    <label for="title">ชื่อเมนู</label>
    <input id="title" name="title" required maxlength="150" placeholder="เช่น ซุปฟักทองแม่มด" value="<?= h($title) ?>">
    <label for="category_id">ประเภทอาหาร</label>
    <select id="category_id" name="category_id" required>
      <option value="">— เลือกประเภท —</option>
      <?php foreach ($cats as $c) echo '<option value="'.(int)$c['id'].'"'.($catId === (int)$c['id'] ? ' selected' : '').'>'.h($c['emoji'].' '.$c['name']).'</option>'; ?>
    </select>
    <label for="description">คำบรรยายสั้น ๆ <span class="muted">(ไม่บังคับ)</span></label>
    <textarea id="description" name="description" rows="3" maxlength="1000" placeholder="เล่าเรื่องเมนูนี้สักนิด"><?= h($desc) ?></textarea>
  </fieldset>

  <fieldset class="box">
    <legend><span class="box-no">2</span> วัตถุดิบ &amp; ปริมาณ</legend>
    <div class="rows" id="ing-rows">
      <?php foreach ($ingredients as $i): ?>
        <div class="row ing-row">
          <input name="ing_name[]" maxlength="120" placeholder="วัตถุดิบ เช่น ฟักทอง" aria-label="ชื่อวัตถุดิบ" value="<?= h($i['name']) ?>">
          <input name="ing_qty[]" maxlength="60" placeholder="ปริมาณ เช่น 300 กรัม" aria-label="ปริมาณ" value="<?= h($i['quantity']) ?>">
          <button type="button" class="icon-btn" data-remove aria-label="ลบแถวนี้" title="ลบแถวนี้"><?= icon('x', 16) ?></button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-ghost btn-sm" data-add="ing-rows">+ เพิ่มวัตถุดิบ</button>
  </fieldset>

  <fieldset class="box">
    <legend><span class="box-no">3</span> วิธีทำ (สูตรอาหาร)</legend>
    <div class="rows" id="step-rows">
      <?php foreach ($steps as $s): ?>
        <div class="row step-row">
          <span class="step-no"></span>
          <textarea name="step[]" rows="2" maxlength="2000" placeholder="อธิบายขั้นตอน" aria-label="ขั้นตอน"><?= h($s) ?></textarea>
          <button type="button" class="icon-btn" data-remove aria-label="ลบขั้นตอนนี้" title="ลบขั้นตอนนี้"><?= icon('x', 16) ?></button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-ghost btn-sm" data-add="step-rows">+ เพิ่มขั้นตอน</button>
  </fieldset>

  <div class="submit-bar">
    <?php if ($wasPublished): ?>
      <button class="btn btn-lg" type="submit" name="action" value="publish">บันทึกการแก้ไข</button>
    <?php else: ?>
      <button class="btn btn-ghost btn-lg" type="submit" name="action" value="draft" formnovalidate>บันทึกร่าง</button>
      <button class="btn btn-lg" type="submit" name="action" value="publish">โพสต์สูตร</button>
    <?php endif; ?>
  </div>
  <?php if ($existing): ?>
    <button class="btn-link-danger" type="submit" form="delete-form"><?= $wasPublished ? 'ลบสูตรนี้' : 'ลบร่างนี้' ?></button>
  <?php endif; ?>
</form>
<?php if ($existing): ?>
  <form id="delete-form" method="post" action="recipe_delete.php" data-confirm="ลบ “<?= h($title) ?>” ถาวรใช่ไหม? ลบแล้วกู้คืนไม่ได้">
    <?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  </form>
<?php endif; ?>
<?php page_end(); ?>
