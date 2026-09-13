<?php
/**
 * Oyejo Gas - Plant Images Manager (New - Editable via Admin)
 * Allows admin to upload and manage homepage plant and refilling images
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('marketing.banners');
require_once BASE_PATH . '/includes/marketing.php';

$me = current_user();
$message = '';
$errors = [];

function plant_upload($field, $prefix) {
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Choose an image to upload.'];
    }
    if ((int)$file['size'] > 5*1024*1024) {
        return [false, 'Image must be 5 MB or smaller.'];
    }
    $info = @getimagesize($file['tmp_name']);
    $map = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_WEBP=>'webp'];
    if (!$info || !isset($map[$info[2]])) {
        return [false, 'Only JPG, PNG or WebP images are accepted.'];
    }
    $dir = BASE_PATH . '/uploads/banners';
    if (!is_dir($dir) && !mkdir($dir,0755,true)) {
        return [false, 'Could not store image.'];
    }
    $ext = $map[$info[2]];
    $name = $prefix . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir.'/'.$name)) {
        return [false, 'Could not store image.'];
    }
    return [true, 'uploads/banners/'.$name];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session expired.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'upload_plant') {
            [$ok,$msg] = plant_upload('plant_image','plant-hero');
            if ($ok) {
                // Update or create banner for plant
                $existing = db()->query("SELECT id FROM banners WHERE title LIKE '%Plant%' OR image LIKE '%plant-hero%' LIMIT 1")->fetch();
                if ($existing) {
                    db()->prepare("UPDATE banners SET image=?, is_active=1 WHERE id=?")->execute([$msg, $existing['id']]);
                } else {
                    db()->prepare("INSERT INTO banners (title,body,image,position,sort_order,is_active) VALUES (?,?,?,?,?,1)")->execute(['Oyejo Gas Plant - Owode-Egba','Safety, Quality, Trust — Energy for Every Home. Our LPG refilling plant in Owode-Egba.',$msg,'home_top',1]);
                }
                // Also copy to assets for direct use
                @copy(BASE_PATH.'/'.$msg, BASE_PATH.'/assets/images/plant/custom-plant-hero.png');
                $message = 'Plant image updated! Homepage will show new image.';
            } else $errors[] = $msg;
        } elseif ($action === 'upload_refilling') {
            [$ok,$msg] = plant_upload('refilling_image','refilling-action');
            if ($ok) {
                $existing = db()->query("SELECT id FROM banners WHERE title LIKE '%Refilling%' OR image LIKE '%refilling%' LIMIT 1")->fetch();
                if ($existing) {
                    db()->prepare("UPDATE banners SET image=?, is_active=1 WHERE id=?")->execute([$msg, $existing['id']]);
                } else {
                    db()->prepare("INSERT INTO banners (title,body,image,position,sort_order,is_active) VALUES (?,?,?,?,?,1)")->execute(['Staff Refilling Action','Watch our staff refill your cylinder with precision machine.',$msg,'home_bottom',1]);
                }
                @copy(BASE_PATH.'/'.$msg, BASE_PATH.'/assets/images/plant/custom-refilling-action.png');
                $message = 'Refilling action image updated!';
            } else $errors[] = $msg;
        } elseif ($action === 'reset_defaults') {
            @unlink(BASE_PATH.'/uploads/banners/plant-hero.png');
            @unlink(BASE_PATH.'/uploads/banners/refilling-action.png');
            @copy(BASE_PATH.'/assets/images/plant/oyejogas-plant-hero-banner.png', BASE_PATH.'/uploads/banners/plant-hero.png');
            @copy(BASE_PATH.'/assets/images/plant/oyejogas-staff-refilling-action.png', BASE_PATH.'/uploads/banners/refilling-action.png');
            $message = 'Reset to default generated images.';
        }
    }
}

$plant_files = [
    'assets/images/plant/oyejogas-owode-egba-original-enhanced.png' => 'Original Plant Enhanced (from uploaded)',
    'assets/images/plant/oyejogas-plant-hero-banner.png' => 'Plant Hero Banner - With Staff Refilling (Generated)',
    'assets/images/plant/oyejogas-staff-refilling-action.png' => 'Staff Refilling Action - Wide',
    'assets/images/plant/oyejogas-staff-refilling-closeup.png' => 'Staff Refilling Closeup',
    'uploads/banners/plant-hero.png' => 'Current Plant Banner (Editable)',
    'uploads/banners/refilling-action.png' => 'Current Refilling Banner (Editable)',
];

$banners = mk_banners_all();
$sections = mk_sections_all();

$page_title = 'Plant Images Manager';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; <a href="<?= e(url('admin/marketing.php')) ?>">Marketing</a> &rsaquo; Plant Images</p>
<h1>🏭 Plant Images Manager</h1>
<p class="section-lead">Manage homepage plant and refilling images. All responsive and editable. Uploaded image from Owode-Egba plant is here.</p>

<?php if ($message) : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="grid" style="grid-template-columns:1fr 1fr; gap:24px;">
  <div class="card">
    <h2>🏭 Plant Hero Image</h2>
    <p>Main banner showing OYEJO GAS LPG Refilling Plant Owode-Egba. Used in homepage plant showcase (responsive overlay).</p>
    <?php foreach (['assets/images/plant/oyejogas-owode-egba-original-enhanced.png','assets/images/plant/oyejogas-plant-hero-banner.png','uploads/banners/plant-hero.png'] as $img) :
      if (is_file(BASE_PATH.'/'.$img)) : ?>
        <p><strong><?= e($img) ?></strong><br><img src="<?= e(url($img)) ?>" alt="" style="max-width:100%; max-height:180px; border-radius:8px; border:1px solid #ddd;"></p>
    <?php endif; endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload_plant">
      <label>Upload new plant image (JPG/PNG/WebP, max 5MB)<input type="file" name="plant_image" accept=".jpg,.jpeg,.png,.webp" required></label>
      <button class="btn primary" type="submit">Update Plant Image</button>
    </form>
  </div>

  <div class="card">
    <h2>⚙️ Refilling Action Image</h2>
    <p>Banner showing staff refilling customer's cylinder with machine. Used in refilling-banner section.</p>
    <?php foreach (['assets/images/plant/oyejogas-staff-refilling-action.png','assets/images/plant/oyejogas-staff-refilling-closeup.png','uploads/banners/refilling-action.png'] as $img) :
      if (is_file(BASE_PATH.'/'.$img)) : ?>
        <p><strong><?= e($img) ?></strong><br><img src="<?= e(url($img)) ?>" alt="" style="max-width:100%; max-height:180px; border-radius:8px; border:1px solid #ddd;"></p>
    <?php endif; endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload_refilling">
      <label>Upload new refilling image (JPG/PNG/WebP, max 5MB)<input type="file" name="refilling_image" accept=".jpg,.jpeg,.png,.webp" required></label>
      <button class="btn primary" type="submit">Update Refilling Image</button>
    </form>
  </div>
</div>

<div class="card">
  <h2>📦 All Product Images (19 branded)</h2>
  <p>Branded cylinders from 3kg to 50kg with OYEJO GAS brand. Already auto-mapped to shop.</p>
  <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px,1fr)); gap:12px;">
    <?php foreach (glob(BASE_PATH.'/assets/images/products/oyejogas-*.png') as $f) :
      $rel = str_replace(BASE_PATH.'/', '', $f); ?>
      <div style="text-align:center; background:#f8faf8; padding:8px; border-radius:8px;">
        <img src="<?= e(url($rel)) ?>" alt="" style="width:100%; height:90px; object-fit:contain;"><br>
        <small style="font-size:0.7rem; word-break:break-all;"><?= e(basename($f)) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h2>📝 Homepage Sections (Editable)</h2>
  <p>Texts for plant and product showcase are editable via Marketing → Homepage tab.</p>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Slug</th><th>Title</th><th>Active</th><th>Edit</th></tr></thead>
    <tbody>
      <?php foreach ($sections as $s) : if (in_array($s['slug'], ['plant_showcase','refilling_action','product_showcase','safety_note','service_area'])) : ?>
        <tr><td><?= e($s['slug']) ?></td><td><?= e($s['title']) ?></td><td><?= $s['is_active']?'Yes':'No' ?></td>
        <td><a href="<?= e(url('admin/marketing.php?tab=homepage&edit='.$s['id'])) ?>">Edit</a></td></tr>
      <?php endif; endforeach; ?>
    </tbody>
  </table></div>
  <p><a class="btn ghost" href="<?= e(url('admin/marketing.php?tab=homepage')) ?>">Go to Homepage Sections Editor</a></p>
</div>

<div class="card">
  <h2>🎨 Banners (Editable)</h2>
  <p>Plant and refilling banners are also editable as banners (home_top and home_bottom positions).</p>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Title</th><th>Position</th><th>Image</th><th>Active</th><th>Edit</th></tr></thead>
    <tbody>
      <?php foreach ($banners as $b) : ?>
        <tr><td><?= e($b['title']) ?></td><td><?= e($b['position']) ?></td><td><?= $b['image'] ? '<img src="'.e(url($b['image'])).'" style="height:40px">' : '—' ?></td><td><?= $b['is_active']?'Yes':'No' ?></td>
        <td><a href="<?= e(url('admin/marketing.php?tab=banners&edit='.$b['id'])) ?>">Edit</a></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card">
  <h2>♻️ Reset</h2>
  <form method="post" onsubmit="return confirm('Reset plant images to default generated versions?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reset_defaults">
    <button class="btn ghost" type="submit">Reset to Default Generated Images</button>
  </form>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>
