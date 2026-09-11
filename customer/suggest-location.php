<?php
/**
 * Oyejo Gas - customers suggest a delivery location; admin must approve.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/growth.php';

$me = current_user();
$cid = cart_customer_id((int) $me['id']);
$errors = [];
$name = '';
$city = '';
$description = '';

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $name = trim((string) post('name', ''));
        $city = trim((string) post('city', ''));
        $description = trim((string) post('description', ''));
        [$ok, $msg] = loc_suggest($cid, $name, $city, $description);
        if ($ok) {
            flash('success', $msg);
            redirect(url('customer/suggest-location.php'));
        }
        $errors[] = $msg;
    }
}

$rows = [];
try {
    $rows = loc_my_suggestions($cid);
} catch (Throwable $t) {
    $rows = [];
}

$page_title = 'Suggest a location';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Suggest a location</p>
<h1>Suggest a location</h1>
<p class="section-lead">Ask us to deliver to your area. Suggestions stay hidden until an admin approves them.</p>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <h2>New suggestion</h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <label>Area / neighbourhood<input name="name" value="<?= e($name) ?>" required maxlength="100" placeholder="e.g. Magodo Phase 2"></label>
    <label>City<input name="city" value="<?= e($city) ?>" required maxlength="100" placeholder="Lagos"></label>
    <label>Notes (landmarks, estate name)<input name="description" value="<?= e($description) ?>" maxlength="255"></label>
    <p><button class="btn primary" type="submit">Submit for approval</button></p>
  </form>
</div>

<div class="card">
  <h2>Your suggestions</h2>
  <?php if (!$rows) : ?><p class="result-meta">None yet.</p>
  <?php else : ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Location</th><th>City</th><th>Status</th><th>Submitted</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r) : ?>
          <tr>
            <td><?= e($r['name']) ?><?php if (!empty($r['description'])) : ?><br><span class="result-meta"><?= e($r['description']) ?></span><?php endif; ?></td>
            <td><?= e($r['city']) ?></td>
            <td><?= e(ucfirst($r['status'])) ?></td>
            <td><?= e(substr((string) $r['created_at'], 0, 16)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
