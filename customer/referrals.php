<?php
/**
 * Oyejo Gas - customer referrals and rewards (Phase 21).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/wallet.php';
require_once BASE_PATH . '/includes/referrals.php';

if (!oyejo_feature('referrals')) {
    http_response_code(403);
    $page_title = 'Referrals disabled';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Referrals are currently disabled.</h1><p>Please check back later.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$me = current_user();
$cid = cart_customer_id((int) $me['id']);
$errors = [];
$success = '';

ref_expire_due();
ref_qualify_due();

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        [$ok, $msg] = ref_claim((int) post('reward_id', 0), $cid);
        $ok ? $success = $msg : $errors[] = $msg;
    }
}

$s = db()->prepare('SELECT `referral_code` FROM `customers` WHERE `id` = ?');
$s->execute([$cid]);
$code = (string) $s->fetchColumn();
$cfg = ref_settings();
$refs = ref_for_referrer($cid);
$rewards = ref_rewards_for($cid);

$page_title = 'Referrals';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Referrals</p>
<h1>Referrals</h1>
<?php if ($success) : ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <h2>Your referral link</h2>
  <p><input value="<?= e(url('customer/register.php?ref=' . $code)) ?>" readonly onclick="this.select()" size="48"></p>
  <p class="result-meta">You earn <?= e(format_money($cfg['referral_reward_referrer_minor'])) ?> and your friend earns
    <?= e(format_money($cfg['referral_reward_referred_minor'])) ?> once they spend at least
    <?= e(format_money($cfg['referral_min_purchase_minor'])) ?> on delivered orders.</p>
</div>

<h2>People you referred</h2>
<?php if (!$refs) : ?>
  <div class="card"><p>No referrals yet — share your link.</p></div>
<?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Friend</th><th>Code</th><th>Status</th><th>Joined</th></tr></thead>
    <tbody>
      <?php foreach ($refs as $r) : ?>
        <tr><td><?= e($r['referred_name']) ?> (<?= e($r['customer_code']) ?>)</td>
          <td><?= e((string) $r['code_used']) ?></td><td><?= e(ucfirst($r['status'])) ?></td>
          <td><?= e(substr($r['created_at'], 0, 16)) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>

<h2>Your rewards</h2>
<?php if (!$rewards) : ?>
  <div class="card"><p>No rewards yet.</p></div>
<?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Kind</th><th>Amount</th><th>Status</th><th>Expires</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rewards as $r) : ?>
        <tr><td><?= e(ucfirst($r['kind'])) ?></td><td><?= e(format_money((int) $r['amount_minor'])) ?></td>
          <td><?= e(ucfirst($r['status'])) ?></td><td><?= e(substr((string) ($r['expires_at'] ?? '—'), 0, 16)) ?></td>
          <td>
            <?php if ($r['status'] === 'pending') : ?>
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="reward_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small primary" type="submit">Claim</button>
              </form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
