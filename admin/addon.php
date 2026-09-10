<?php
/**
 * Oyejo Gas - add-on page router (Phase 26, AO-05).
 * Serves `addons/<slug>/pages/<page>.php` inside the core layout after
 * checking the system toggle, add-on status and the manifest permission.
 * Add-on folders are never accessed directly over HTTP.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');

$slug = (string) ($_GET['addon'] ?? '');
$page = (string) ($_GET['page'] ?? '');
[$file, $need, $err] = addon_page_file($slug, $page);
if ($file === null) {
    show_error(404, $err !== '' ? $err : 'Add-on page not found.');
}
require_permission($need);

[$manifest] = addon_read_manifest($slug);
$page_title = $slug . ' / ' . $page;
if ($manifest !== null) {
    foreach ((array) ($manifest['menus'] ?? []) as $mnu) {
        if ($mnu['page'] === $page) {
            $page_title = $mnu['label'];
        }
    }
}
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo;
  <a href="<?= e(url('admin/addons.php')) ?>">Add-ons</a> &rsaquo; <?= e($page_title) ?></p>
<h1><?= e($page_title) ?></h1>
<?php require $file; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
