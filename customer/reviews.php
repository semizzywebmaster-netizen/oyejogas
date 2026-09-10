<?php
/**
 * Oyejo Gas - customer reviews and ratings (Phase 18, ST-11…ST-13).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/support.php';

if (!oyejo_feature('reviews')) {
    http_response_code(403);
    $page_title = 'Reviews disabled';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Reviews are currently disabled.</h1><p>Please check back later.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$me = current_user();
$uid = (int) $me['id'];
$cid = cart_customer_id($uid);
$errors = [];
$success = '';

$pre_kind = (string) ($_GET['kind'] ?? '');
$pre_ref = (int) ($_GET['ref'] ?? 0);
if (!in_array($pre_kind, ['product', 'delivery'], true)) {
    $pre_kind = '';
    $pre_ref = 0;
}

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $kind = (string) post('kind', '');
        $ref = (int) post('ref_id', 0);
        [$ok, $msg] = rev_submit($cid, $kind, $ref, post('rating', 0), (string) post('title', ''), (string) post('body', ''));
        if ($ok) {
            $success = $msg;
            $pre_kind = '';
            $pre_ref = 0;
        } else {
            $errors[] = $msg;
        }
    }
}

$products = rev_eligible_products($cid);
$deliveries = rev_eligible_deliveries($cid);
$mine = rev_for_customer($cid);

$page_title = 'My reviews';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Reviews</p>
<h1>My reviews</h1>
<?php if ($success) : ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <h2>Write a review</h2>
  <?php if (!$products && !$deliveries) : ?>
    <p class="result-meta">Nothing to review yet — delivered products and completed deliveries will appear here.</p>
  <?php else : ?>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <label>What are you reviewing?
        <select name="kind" id="revKind" required>
          <option value="">— choose —</option>
          <?php if ($products) : ?><option value="product"<?= $pre_kind === 'product' ? ' selected' : '' ?>>Product</option><?php endif; ?>
          <?php if ($deliveries) : ?><option value="delivery"<?= $pre_kind === 'delivery' ? ' selected' : '' ?>>Delivery</option><?php endif; ?>
        </select>
      </label>
      <label id="revProductWrap">Product
        <select name="ref_product">
          <?php foreach ($products as $p) : ?>
            <option value="<?= (int) $p['id'] ?>"<?= ($pre_kind === 'product' && $pre_ref === (int) $p['id']) ? ' selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label id="revDeliveryWrap">Delivery
        <select name="ref_delivery">
          <?php foreach ($deliveries as $d) : ?>
            <option value="<?= (int) $d['id'] ?>"<?= ($pre_kind === 'delivery' && $pre_ref === (int) $d['id']) ? ' selected' : '' ?>><?= e($d['delivery_number']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <input type="hidden" name="ref_id" id="revRef" value="<?= $pre_ref ?>">
      <label>Rating
        <select name="rating" required>
          <option value="5">★★★★★ (5)</option>
          <option value="4">★★★★☆ (4)</option>
          <option value="3">★★★☆☆ (3)</option>
          <option value="2">★★☆☆☆ (2)</option>
          <option value="1">★☆☆☆☆ (1)</option>
        </select>
      </label>
      <label>Title (optional)<input name="title" maxlength="190"></label>
      <label>Review (optional)<textarea name="body" rows="4" maxlength="5000"></textarea></label>
      <p><button class="btn primary" type="submit">Submit review</button></p>
    </form>
    <script>
    (function () {
      var kind = document.getElementById('revKind'),
          pw = document.getElementById('revProductWrap'),
          dw = document.getElementById('revDeliveryWrap'),
          ref = document.getElementById('revRef');
      function sync() {
        var isP = kind.value === 'product', isD = kind.value === 'delivery';
        if (pw) pw.style.display = isP ? '' : 'none';
        if (dw) dw.style.display = isD ? '' : 'none';
        if (ref) ref.value = isP ? (pw ? pw.querySelector('select').value : 0)
          : isD ? (dw ? dw.querySelector('select').value : 0) : 0;
      }
      if (kind) { kind.addEventListener('change', sync); sync(); }
      ['revProductWrap', 'revDeliveryWrap'].forEach(function (id) {
        var w = document.getElementById(id);
        if (w) w.querySelector('select').addEventListener('change', sync);
      });
    })();
    </script>
  <?php endif; ?>
</div>

<h2>Your reviews</h2>
<?php if (!$mine) : ?>
  <div class="card"><p>No reviews yet.</p></div>
<?php else : ?>
  <?php foreach ($mine as $r) : ?>
    <article class="card">
      <p><strong><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></strong>
        <?php if (!empty($r['product_name'])) : ?>on <?= e($r['product_name']) ?>
        <?php elseif (!empty($r['order_number'])) : ?>on order <?= e($r['order_number']) ?>
        <?php elseif (!empty($r['delivery_number'])) : ?>on delivery <?= e($r['delivery_number']) ?>
        <?php endif; ?>
        <span class="badge"><?= e(ucfirst($r['status'])) ?></span></p>
      <?php if (!empty($r['title'])) : ?><p><strong><?= e($r['title']) ?></strong></p><?php endif; ?>
      <?php if (!empty($r['body'])) : ?><p><?= nl2br(e($r['body'])) ?></p><?php endif; ?>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
