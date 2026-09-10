<?php
/**
 * Oyejo Gas - products & categories desk (Phase 15, AD-07/AD-08).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('products.view');
require_once BASE_PATH . '/includes/admin.php';

$me = current_user();
$can_create = has_permission('products.create');
$can_edit = has_permission('products.edit');
$can_delete = has_permission('products.delete');
$can_cats = has_permission('categories.manage');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'product_save') {
            $is_new = ((int) ($_POST['product_id'] ?? 0)) === 0;
            if (($is_new && !$can_create) || (!$is_new && !$can_edit)) {
                $errors[] = 'You do not have permission to manage products.';
            } else {
                [$ok, $msg] = adm_product_save((int) ($_POST['product_id'] ?? 0), $_POST, (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'product_delete') {
            if (!$can_delete) {
                $errors[] = 'You do not have permission to delete products.';
            } else {
                [$ok, $msg] = adm_product_delete((int) ($_POST['product_id'] ?? 0), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'category_save') {
            if (!$can_cats) {
                $errors[] = 'You do not have permission to manage categories.';
            } else {
                [$ok, $msg] = adm_category_save((int) ($_POST['category_id'] ?? 0), $_POST, (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'category_delete') {
            if (!$can_cats) {
                $errors[] = 'You do not have permission to manage categories.';
            } else {
                [$ok, $msg] = adm_category_delete((int) ($_POST['category_id'] ?? 0), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        }
    }
}

$cats = adm_categories();
$sizes = db()->query('SELECT `id`, `name` FROM `cylinder_sizes` ORDER BY `sort_order`')->fetchAll();
$f_cat = (int) ($_GET['cat'] ?? 0);
$sql = 'SELECT p.*, c.`name` AS category_name FROM `products` p JOIN `categories` c ON c.`id` = p.`category_id`';
$args = [];
if ($f_cat > 0) {
    $sql .= ' WHERE p.`category_id` = ?';
    $args[] = $f_cat;
}
$sql .= ' ORDER BY p.`name` LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($args);
$products = $stmt->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM `products` WHERE `id` = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}
$edit_cat = null;
if (isset($_GET['edit_cat'])) {
    foreach ($cats as $c) {
        if ((int) $c['id'] === (int) $_GET['edit_cat']) {
            $edit_cat = $c;
        }
    }
}

$page_title = 'Products';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Products</p>
<h1>Products &amp; categories</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <form method="get" action="" class="filter-row">
    <select name="cat" onchange="this.form.submit()">
      <option value="0">All categories</option>
      <?php foreach ($cats as $c) : ?>
        <option value="<?= (int) $c['id'] ?>"<?= $f_cat === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>SKU</th><th>Name</th><th>Type</th><th>Price</th><th>Stock</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($products as $p) : ?>
        <tr><td><?= e($p['sku']) ?></td><td><?= e($p['name']) ?></td><td><?= e($p['type']) ?></td>
          <td>₦<?= number_format((int) $p['price_minor'] / 100, 2) ?></td><td><?= number_format((int) $p['stock_qty']) ?></td>
          <td><?= (int) $p['is_active'] ? 'Yes' : 'No' ?></td>
          <td>
            <?php if ($can_edit) : ?><a href="<?= e(url('admin/products.php?edit=' . (int) $p['id'])) ?>">Edit</a><?php endif; ?>
            <?php if ($can_delete) : ?>
              <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this product?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="product_delete">
                <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                <button class="btn small ghost" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php if ($can_create || ($edit && $can_edit)) : ?>
<div class="card">
  <h2><?= $edit ? 'Edit product' : 'Add product' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="product_save">
    <input type="hidden" name="product_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Name<input name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" maxlength="190" required></label>
    <label>SKU<input name="sku" value="<?= e((string) ($edit['sku'] ?? '')) ?>" maxlength="60" required></label>
    <label>Slug (optional)<input name="slug" value="<?= e((string) ($edit['slug'] ?? '')) ?>" maxlength="150"></label>
    <label>Category
      <select name="category_id">
        <?php foreach ($cats as $c) : ?>
          <option value="<?= (int) $c['id'] ?>"<?= $edit && (int) $edit['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Type
      <select name="type">
        <?php foreach (adm_product_types() as $t) : ?>
          <option value="<?= $t ?>"<?= $edit && $edit['type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Cylinder size
      <select name="size_id">
        <option value="0">— none —</option>
        <?php foreach ($sizes as $z) : ?>
          <option value="<?= (int) $z['id'] ?>"<?= $edit && (int) $edit['size_id'] === (int) $z['id'] ? ' selected' : '' ?>><?= e($z['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Price (₦)<input name="price" inputmode="decimal" value="<?= e((string) (($edit['price_minor'] ?? 0) / 100)) ?>" required></label>
    <label>Promo price (₦, optional)<input name="promo_price" inputmode="decimal" value="<?= $edit && $edit['promo_price_minor'] !== null ? e((string) ($edit['promo_price_minor'] / 100)) : '' ?>"></label>
    <label>Promo starts<input name="promo_starts_at" value="<?= e((string) ($edit['promo_starts_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label>
    <label>Promo ends<input name="promo_ends_at" value="<?= e((string) ($edit['promo_ends_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label>
    <label>Stock qty<input type="number" name="stock_qty" value="<?= (int) ($edit['stock_qty'] ?? 0) ?>" min="0" max="1000000"></label>
    <label>Low-stock alert at<input type="number" name="low_stock_at" value="<?= (int) ($edit['low_stock_at'] ?? 5) ?>" min="0" max="1000000"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>" min="0" max="9999"></label>
    <label>Description<textarea name="description" rows="2"><?= e((string) ($edit['description'] ?? '')) ?></textarea></label>
    <label class="check"><input type="checkbox" name="track_inventory" value="1"<?= !$edit || (int) $edit['track_inventory'] ? ' checked' : '' ?>> Track inventory</label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <label class="check"><input type="checkbox" name="is_featured" value="1"<?= $edit && (int) $edit['is_featured'] ? ' checked' : '' ?>> Featured</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create product' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/products.php')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Categories (<?= count($cats) ?>)</h2>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Name</th><th>Slug</th><th>Products</th><th>Active</th><?php if ($can_cats) : ?><th></th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($cats as $c) : ?>
        <tr><td><?= e($c['name']) ?></td><td><?= e($c['slug']) ?></td><td><?= number_format((int) $c['product_count']) ?></td>
          <td><?= (int) $c['is_active'] ? 'Yes' : 'No' ?></td>
          <?php if ($can_cats) : ?><td>
            <a href="<?= e(url('admin/products.php?edit_cat=' . (int) $c['id'])) ?>">Edit</a>
            <?php if ((int) $c['product_count'] === 0) : ?>
              <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this category?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="category_delete">
                <input type="hidden" name="category_id" value="<?= (int) $c['id'] ?>">
                <button class="btn small ghost" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php if ($can_cats) : ?>
<div class="card">
  <h2><?= $edit_cat ? 'Edit category' : 'Add category' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="category_save">
    <input type="hidden" name="category_id" value="<?= (int) ($edit_cat['id'] ?? 0) ?>">
    <label>Name<input name="name" value="<?= e((string) ($edit_cat['name'] ?? '')) ?>" maxlength="150" required></label>
    <label>Slug (optional)<input name="slug" value="<?= e((string) ($edit_cat['slug'] ?? '')) ?>" maxlength="100"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int) ($edit_cat['sort_order'] ?? 0) ?>" min="0" max="9999"></label>
    <label>Description<textarea name="description" rows="2"><?= e((string) ($edit_cat['description'] ?? '')) ?></textarea></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit_cat || (int) $edit_cat['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit_cat ? 'Save changes' : 'Create category' ?></button>
    <?php if ($edit_cat) : ?><a class="btn ghost" href="<?= e(url('admin/products.php')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
