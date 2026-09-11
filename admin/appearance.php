<?php
/**
 * Oyejo Gas - appearance desk: logo, favicon and website colours.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('settings.view');
require_once BASE_PATH . '/includes/admin.php';

$me = current_user();
$can_edit = has_permission('settings.edit');
$message = '';
$errors = [];

/** Store one branding image (logo/favicon). Returns [ok, msg]. */
function appearance_upload($field, $prefix, $allow_ico) {
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Choose an image to upload.'];
    }
    if ((int) $file['size'] > 512 * 1024) {
        return [false, 'Image must be 512 KB or smaller.'];
    }
    $is_ico = $allow_ico && preg_match('/\.ico$/i', (string) ($file['name'] ?? ''));
    $ext = '';
    if ($is_ico) {
        $ext = 'ico';
    } else {
        $info = @getimagesize($file['tmp_name']);
        $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($map[$info[2]])) {
            return [false, 'Only JPG, PNG or WebP images are accepted.'];
        }
        $ext = $map[$info[2]];
    }
    $dir = BASE_PATH . '/uploads/branding';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return [false, 'Could not store the image.'];
    }
    $name = $prefix . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        report_error('files', 'error', 'Branding upload failed (' . $prefix . ')');
        return [false, 'Could not store the image.'];
    }
    return [true, 'branding/' . $name];
}

function appearance_set($key, $value, $actor_id) {
    db()->prepare(
        'INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group_name` = VALUES(`group_name`)'
    )->execute([$key, $value, 'appearance']);
    adm_audit('admin.settings', $actor_id, null, ['group' => 'appearance'], [$key => $value]);
}

function appearance_delete_file($rel) {
    $rel = (string) $rel;
    if ($rel === '' || !str_starts_with($rel, 'uploads/branding/') || str_contains($rel, '..')) {
        return;
    }
    @unlink(BASE_PATH . '/' . $rel);
}

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$can_edit) {
        $errors[] = 'You do not have permission to edit appearance.';
    } else {
        $action = (string) post('action', '');
        if ($action === 'upload_logo' || $action === 'upload_favicon') {
            $is_logo = $action === 'upload_logo';
            [$ok, $msg] = appearance_upload($is_logo ? 'logo' : 'favicon', $is_logo ? 'logo' : 'fav', !$is_logo);
            if ($ok) {
                appearance_delete_file(setting($is_logo ? 'site_logo' : 'site_favicon', ''));
                appearance_set($is_logo ? 'site_logo' : 'site_favicon', 'uploads/' . $msg, (int) $me['id']);
                $message = $is_logo ? 'Logo updated.' : 'Favicon updated.';
            } else {
                $errors[] = $msg;
            }
        } elseif ($action === 'save_colors') {
            $primary = trim((string) post('site_color_primary', ''));
            $accent = trim((string) post('site_color_accent', ''));
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary) || !preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
                $errors[] = 'Colours must be hex like #0b6b3a.';
            } else {
                appearance_set('site_color_primary', $primary, (int) $me['id']);
                appearance_set('site_color_accent', $accent, (int) $me['id']);
                $message = 'Website colours saved.';
            }
        } elseif ($action === 'reset') {
            appearance_delete_file(setting('site_logo', ''));
            appearance_delete_file(setting('site_favicon', ''));
            appearance_set('site_logo', '', (int) $me['id']);
            appearance_set('site_favicon', '', (int) $me['id']);
            appearance_set('site_color_primary', '#0b6b3a', (int) $me['id']);
            appearance_set('site_color_accent', '#ff9d2e', (int) $me['id']);
            $message = 'Appearance reset to defaults.';
        }
    }
}

$logo = setting('site_logo', '');
$favicon = setting('site_favicon', '');
$primary = setting('site_color_primary', '#0b6b3a');
$accent = setting('site_color_accent', '#ff9d2e');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary)) {
    $primary = '#0b6b3a';
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
    $accent = '#ff9d2e';
}

$page_title = 'Appearance';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Appearance</p>
<h1>Appearance</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <h2>Logo</h2>
  <p><img src="<?= e(url($logo !== '' ? $logo : 'assets/images/logo.svg')) ?>" alt="Current logo" width="72" height="72"></p>
  <?php if ($can_edit) : ?>
    <form method="post" action="" enctype="multipart/form-data" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload_logo">
      <label>Upload new logo (JPG/PNG/WebP, max 512 KB)<input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" required></label>
      <p><button class="btn small primary" type="submit">Upload logo</button></p>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Favicon</h2>
  <p><img src="<?= e(url($favicon !== '' ? $favicon : 'assets/images/logo.svg')) ?>" alt="Current favicon" width="32" height="32"></p>
  <?php if ($can_edit) : ?>
    <form method="post" action="" enctype="multipart/form-data" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload_favicon">
      <label>Upload new favicon (PNG/ICO, max 512 KB)<input type="file" name="favicon" accept=".png,.ico" required></label>
      <p><button class="btn small primary" type="submit">Upload favicon</button></p>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Website colours</h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_colors">
    <label>Primary colour<input type="color" name="site_color_primary" value="<?= e($primary) ?>"<?= $can_edit ? '' : ' disabled' ?>></label>
    <label>Accent colour<input type="color" name="site_color_accent" value="<?= e($accent) ?>"<?= $can_edit ? '' : ' disabled' ?>></label>
    <?php if ($can_edit) : ?><p><button class="btn small primary" type="submit">Save colours</button></p><?php endif; ?>
  </form>
</div>

<?php if ($can_edit) : ?>
<div class="card">
  <h2>Reset</h2>
  <form method="post" action="" onsubmit="return confirm('Reset logo, favicon and colours to defaults?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reset">
    <p><button class="btn small" type="submit">Reset to defaults</button></p>
  </form>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
