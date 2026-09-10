<?php
/**
 * Oyejo Gas - customer spin-to-win (Phase 20).
 * The wheel is pure theatre: the prize is drawn server-side (SP-13).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/wallet.php';
require_once BASE_PATH . '/includes/marketing.php';
require_once BASE_PATH . '/includes/spin.php';

if (!oyejo_feature('spin_to_win')) {
    http_response_code(403);
    $page_title = 'Spin disabled';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Spin-to-win is currently disabled.</h1><p>Please check back later.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$me = current_user();
$cid = cart_customer_id((int) $me['id']);
$errors = [];
$success = '';
$just_won = null;

spin_expire_due();

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $action = (string) post('action', 'spin');
        if ($action === 'claim') {
            [$ok, $msg] = spin_claim((int) post('spin_id', 0), $cid);
            $ok ? $success = $msg : $errors[] = $msg;
        } else {
            [$ok, $res] = spin_play((int) post('campaign_id', 0), $cid);
            if ($ok) {
                $just_won = $res;
                $success = 'You won: ' . $res['label'] . '!';
            } else {
                $errors[] = $res;
            }
        }
    }
}

$live = spin_campaigns_live();
$elig = [];
foreach ($live as $c) {
    [$ok, $why] = spin_eligibility((int) $c['id'], $cid);
    $elig[$c['id']] = [$ok, $why];
}
$history = spin_for_customer($cid);

$page_title = 'Spin to win';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Spin to win</p>
<h1>Spin to win</h1>
<?php if ($success) : ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if ($just_won && !empty($just_won['reward_code'])) : ?>
  <div class="card"><h2>Your reward code</h2>
    <p><strong><?= e($just_won['reward_code']) ?></strong></p>
    <p class="result-meta">Use it at checkout before it expires.</p>
  </div>
<?php elseif ($just_won && $just_won['reward_status'] === 'pending') : ?>
  <div class="card"><p>Your wallet reward is pending (wallet unavailable). Claim it below when ready.</p></div>
<?php endif; ?>

<h2>Live campaigns</h2>
<?php if (!$live) : ?>
  <div class="card"><p>No campaigns running right now. Check back soon.</p></div>
<?php else : ?>
  <div class="grid">
    <?php foreach ($live as $c) : ?>
      <article class="card">
        <h3><?= e($c['name']) ?></h3>
        <?php if (!empty($c['description'])) : ?><p><?= e($c['description']) ?></p><?php endif; ?>
        <p class="result-meta"><?= e(spin_rules()[$c['period_rule']]) ?> · ends <?= e(substr($c['ends_at'], 0, 16)) ?>
          <?php if ((int) $c['min_order_minor'] > 0) : ?>· needs <?= e(format_money((int) $c['min_order_minor'])) ?> in orders<?php endif; ?></p>
        <?php if ($elig[$c['id']][0]) : ?>
          <form method="post" action="" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="spin">
            <input type="hidden" name="campaign_id" value="<?= (int) $c['id'] ?>">
            <p><button class="btn primary" type="submit">Spin now</button></p>
          </form>
        <?php else : ?>
          <p><span class="badge"><?= e($elig[$c['id']][1]) ?></span></p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2>Your spins</h2>
<?php if (!$history) : ?>
  <div class="card"><p>No spins yet.</p></div>
<?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>When</th><th>Campaign</th><th>Prize</th><th>Reward</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($history as $s) : ?>
        <tr>
          <td><?= e(substr($s['created_at'], 0, 16)) ?></td>
          <td><?= e($s['campaign_name']) ?></td>
          <td><?= e($s['label']) ?></td>
          <td><?= !empty($s['reward_code']) ? e($s['reward_code']) : '—' ?></td>
          <td><?= e(ucfirst($s['reward_status'])) ?></td>
          <td>
            <?php if ($s['reward_status'] === 'pending' && $s['reward_type'] === 'wallet_credit') : ?>
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="claim">
                <input type="hidden" name="spin_id" value="<?= (int) $s['id'] ?>">
                <button class="btn small primary" type="submit">Claim</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
