<?php
/**
 * Oyejo Gas - PWA settings desk: installable-app name, colours, manifest.
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

/** Rebuild manifest.webmanifest from settings. Returns [ok, msg]. */
function pwa_regenerate($saved = []) {
    $g = function ($k, $d) use ($saved) {
        return isset($saved[$k]) ? $saved[$k] : setting($k, $d);
    };
    // Fixed template (2-space, compact icons) so regeneration is byte-stable.
    $esc = function ($s) {
        return json_encode((string) $s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    };
    $json = "{\n"
        . '  "name": ' . $esc($g('pwa_name', 'Oyejo Gas - LPG ordering, refills & delivery')) . ",\n"
        . '  "short_name": ' . $esc($g('pwa_short_name', 'Oyejo Gas')) . ",\n"
        . '  "description": ' . $esc(setting('tagline', 'Order cooking gas, book refills and track deliveries.')) . ",\n"
        . '  "start_url": "./index.php",' . "\n"
        . '  "scope": "./",' . "\n"
        . '  "display": "standalone",' . "\n"
        . '  "orientation": "portrait-primary",' . "\n"
        . '  "background_color": ' . $esc($g('pwa_bg_color', '#f6f8f6')) . ",\n"
        . '  "theme_color": ' . $esc($g('pwa_theme_color', '#0b6b3a')) . ",\n"
        . '  "icons": [' . "\n"
        . '    { "src": "assets/icons/icon-192.png", "sizes": "192x192", "type": "image/png", "purpose": "any" },' . "\n"
        . '    { "src": "assets/icons/icon-512.png", "sizes": "512x512", "type": "image/png", "purpose": "any" },' . "\n"
        . '    { "src": "assets/icons/maskable-192.png", "sizes": "192x192", "type": "image/png", "purpose": "maskable" },' . "\n"
        . '    { "src": "assets/icons/maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }' . "\n"
        . '  ]' . "\n"
        . '}';
    $path = BASE_PATH . '/manifest.webmanifest';
    if (@file_put_contents($path, $json . "\n") === false) {
        report_error('files', 'error', 'PWA manifest regeneration failed (not writable)');
        return [false, 'Could not write manifest.webmanifest — make the file writable (664).'];
    }
    return [true, 'Manifest regenerated.'];
}

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$can_edit) {
        $errors[] = 'You do not have permission to edit PWA settings.';
    } else {
        $name = trim((string) post('pwa_name', ''));
        $short = trim((string) post('pwa_short_name', ''));
        $theme = trim((string) post('pwa_theme_color', ''));
        $bg = trim((string) post('pwa_bg_color', ''));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            $errors[] = 'App name must be 3–120 characters.';
        } elseif (mb_strlen($short) < 2 || mb_strlen($short) > 30) {
            $errors[] = 'Short name must be 2–30 characters.';
        } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $theme) || !preg_match('/^#[0-9a-fA-F]{6}$/', $bg)) {
            $errors[] = 'Colours must be hex like #0b6b3a.';
        } else {
            $vals = [
                'pwa_name' => $name, 'pwa_short_name' => $short,
                'pwa_theme_color' => $theme, 'pwa_bg_color' => $bg,
            ];
            [$ok, $msg] = adm_settings_save('pwa', $vals, (int) $me['id']);
            if (!$ok) {
                $errors[] = $msg;
            } else {
                [$rok, $rmsg] = pwa_regenerate($vals);
                $rok ? $message = 'PWA settings saved. ' . $rmsg : $errors[] = $rmsg;
            }
        }
    }
}

$page_title = 'PWA settings';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; PWA settings</p>
<h1>PWA settings</h1>
<p class="result-meta">Controls the installable app (Add-to-homescreen name, splash colours). Saving regenerates <code>manifest.webmanifest</code>. HTTPS is required for install prompts.</p>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <h2>App identity</h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <label>App name<input name="pwa_name" value="<?= e(setting('pwa_name', '')) ?>" maxlength="120" required<?= $can_edit ? '' : ' disabled' ?>></label>
    <label>Short name (homescreen)<input name="pwa_short_name" value="<?= e(setting('pwa_short_name', '')) ?>" maxlength="30" required<?= $can_edit ? '' : ' disabled' ?>></label>
    <label>Theme colour<input type="color" name="pwa_theme_color" value="<?= e(setting('pwa_theme_color', '#0b6b3a')) ?>"<?= $can_edit ? '' : ' disabled' ?>></label>
    <label>Background colour<input type="color" name="pwa_bg_color" value="<?= e(setting('pwa_bg_color', '#f6f8f6')) ?>"<?= $can_edit ? '' : ' disabled' ?>></label>
    <?php if ($can_edit) : ?><p><button class="btn small primary" type="submit">Save &amp; regenerate manifest</button></p><?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>Current manifest</h2>
  <pre><?= e((string) @file_get_contents(BASE_PATH . '/manifest.webmanifest')) ?></pre>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
