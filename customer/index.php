<?php
/**
 * Oyejo Gas - customer dashboard (Phase 10).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/cart.php';

$me = current_user();
$uid = (int) $me['id'];
$u = auth_db_user($uid);
$cid = cart_customer_id($uid);

$s = db()->prepare('SELECT * FROM `customers` WHERE `id` = ? LIMIT 1');
$s->execute([$cid]);
$customer = $s->fetch();

$s = db()->prepare('SELECT COUNT(*) FROM `orders` WHERE `customer_id` = ?');
$s->execute([$cid]);
$order_count = (int) $s->fetchColumn();

$s = db()->prepare("SELECT COUNT(*) FROM `support_tickets` WHERE `customer_id` = ? AND `status` IN ('open','pending')");
$s->execute([$cid]);
$open_tickets = (int) $s->fetchColumn();

$s = db()->prepare('SELECT * FROM `wallets` WHERE `customer_id` = ? LIMIT 1');
$s->execute([$cid]);
$wallet = $s->fetch();

$s = db()->prepare('SELECT * FROM `orders` WHERE `customer_id` = ? ORDER BY `id` DESC LIMIT 5');
$s->execute([$cid]);
$recent_orders = $s->fetchAll();

$recent_txns = [];
if ($wallet) {
    $s = db()->prepare('SELECT * FROM `wallet_transactions` WHERE `wallet_id` = ? ORDER BY `id` DESC LIMIT 5');
    $s->execute([$wallet['id']]);
    $recent_txns = $s->fetchAll();
}

$s = db()->prepare('SELECT COUNT(*) FROM `referrals` WHERE `referrer_customer_id` = ?');
$s->execute([$cid]);
$ref_count = (int) $s->fetchColumn();
$s = db()->prepare(
    'SELECT `r`.*, `cu`.`customer_code` FROM `referrals` `r`'
    . ' JOIN `customers` `cu` ON `cu`.`id` = `r`.`referred_customer_id`'
    . ' WHERE `r`.`referrer_customer_id` = ? ORDER BY `r`.`id` DESC LIMIT 5'
);
$s->execute([$cid]);
$referrals = $s->fetchAll();

$s = db()->prepare('SELECT * FROM `referral_rewards` WHERE `customer_id` = ? ORDER BY `id` DESC LIMIT 5');
$s->execute([$cid]);
$rewards = $s->fetchAll();

$s = db()->prepare('SELECT * FROM `support_tickets` WHERE `customer_id` = ? ORDER BY `id` DESC LIMIT 5');
$s->execute([$cid]);
$tickets = $s->fetchAll();

$notifs = notify_for_customer($cid, 5);

$s = db()->prepare('SELECT COUNT(*) FROM `customer_addresses` WHERE `customer_id` = ?');
$s->execute([$cid]);
$addr_count = (int) $s->fetchColumn();

$methods = cart_payment_methods();
$page_title = 'My account';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-hero">
  <p class="pill">Customer portal</p>
  <h1>Welcome, <?= e($u['name']) ?>!</h1>
</div>

<div class="grid dash-stats">
  <article class="card"><h3><?= $order_count ?></h3><p>Orders</p><p><a href="<?= e(url('customer/orders.php')) ?>">View orders</a></p></article>
  <article class="card"><h3><?= $wallet ? e(format_money($wallet['balance_minor'])) : e(format_money(0)) ?></h3><p>Wallet balance</p><p><a href="<?= e(url('customer/wallet.php')) ?>">Open wallet</a></p></article>
  <article class="card"><h3><?= $ref_count ?></h3><p>Referrals</p><p class="result-meta">Reward capture opens in Phase 21</p></article>
  <article class="card"><h3><?= $open_tickets ?></h3><p>Open tickets</p><p><a href="<?= e(url('customer/tickets.php')) ?>">Support</a></p></article>
</div>

<div class="grid dash-grid">
  <article class="card">
    <h2>Recent orders</h2>
    <?php if (!$recent_orders) : ?><p class="result-meta">No orders yet. <a href="<?= e(url('shop.php')) ?>">Start shopping</a>.</p>
    <?php else : ?>
      <ul>
        <?php foreach ($recent_orders as $o) : ?>
          <li><a href="<?= e(url('customer/orders.php?view=' . $o['id'])) ?>"><?= e($o['order_number']) ?></a>
            — <?= e(ucfirst($o['status'])) ?> — <?= e(format_money($o['total_minor'])) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </article>
  <article class="card">
    <h2>Wallet activity</h2>
    <?php if (!$recent_txns) : ?><p class="result-meta">No wallet activity yet.</p>
    <?php else : ?>
      <ul>
        <?php foreach ($recent_txns as $t) : ?>
          <li><?= e(ucfirst($t['type'])) ?> <?= $t['direction'] === 'credit' ? '+' : '−' ?><?= e(format_money($t['amount_minor'])) ?>
            <span class="result-meta">(<?= e($t['status']) ?>)</span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </article>
  <article class="card">
    <h2>My referral link</h2>
    <p><input value="<?= e(url('customer/register.php?ref=' . $customer['referral_code'])) ?>" readonly onclick="this.select()"></p>
    <?php if (!$referrals) : ?><p class="result-meta">No referrals yet.</p>
    <?php else : ?>
      <ul>
        <?php foreach ($referrals as $r) : ?>
          <li><?= e($r['customer_code']) ?> — <?= e($r['status']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($rewards) : ?>
      <h3>Rewards</h3>
      <ul>
        <?php foreach ($rewards as $rw) : ?>
          <li><?= e(ucfirst($rw['kind'])) ?> <?= e(format_money($rw['amount_minor'])) ?> — <?= e($rw['status']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </article>
  <article class="card">
    <h2>Notifications</h2>
    <?php if (!$notifs) : ?><p class="result-meta">No notifications yet.</p>
    <?php else : ?>
      <ul>
        <?php foreach ($notifs as $n) : ?>
          <li><?= e($n['subject'] ?: $n['event']) ?> <span class="result-meta">(<?= e($n['status']) ?>)</span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p><a href="<?= e(url('customer/notifications.php')) ?>">Open inbox</a></p>
  </article>
  <article class="card">
    <h2>Support tickets</h2>
    <?php if (!$tickets) : ?><p class="result-meta">No tickets yet.</p>
    <?php else : ?>
      <ul>
        <?php foreach ($tickets as $t) : ?>
          <li><a href="<?= e(url('customer/tickets.php?view=' . $t['id'])) ?>"><?= e($t['ticket_number']) ?></a>
            — <?= e($t['subject']) ?> (<?= e($t['status']) ?>)</li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p><a href="<?= e(url('customer/tickets.php')) ?>">Open support</a></p>
  </article>
  <article class="card">
    <h2>Account</h2>
    <ul>
      <li><a href="<?= e(url('customer/profile.php')) ?>">Profile</a></li>
      <li><a href="<?= e(url('customer/addresses.php')) ?>">Addresses (<?= $addr_count ?>)</a></li>
      <li><a href="<?= e(url('customer/phones.php')) ?>">Phones</a></li>
      <li><a href="<?= e(url('customer/refills.php')) ?>">Refills</a></li>
      <li><a href="<?= e(url('customer/pickups.php')) ?>">Pickups</a></li>
      <li><a href="<?= e(url('customer/payments.php')) ?>">Payments</a></li>
      <li><a href="<?= e(url('customer/security.php')) ?>">Security</a></li>
      <li><a href="<?= e(url('customer/logout.php')) ?>">Log out</a></li>
    </ul>
  </article>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
