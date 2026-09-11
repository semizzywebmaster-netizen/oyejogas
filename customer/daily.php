<?php
/**
 * Oyejo Gas - customer daily earn hub (check-in, streak, missions).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/wallet.php';
require_once BASE_PATH . '/includes/daily.php';

if (!daily_enabled()) {
    http_response_code(403);
    $page_title = 'Daily rewards disabled';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Daily rewards are currently disabled.</h1><p>Please check back later.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$me = current_user();
$cid = daily_customer_id((int) $me['id']);
$errors = [];
$success = '';
$payload = null;

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } elseif ($cid < 1) {
        $errors[] = 'Customer account missing.';
    } else {
        $action = (string) post('action', 'checkin');
        if ($action === 'mission') {
            [$ok, $msg] = daily_claim_mission($cid, (int) $me['id'], (string) post('mission_key', ''));
            $ok ? $success = $msg : $errors[] = $msg;
        } else {
            [$ok, $msg, $payload] = daily_checkin($cid, (int) $me['id']);
            $ok ? $success = $msg : $errors[] = $msg;
        }
    }
}

$claimed = $cid ? daily_claimed_today($cid) : false;
$streak = $cid ? daily_next_streak($cid) : 1;
$cfg = daily_settings();
[$base, $step, $week, , $preview] = daily_compute_reward($streak, $cfg, 101);
$week_cal = $cid ? daily_week($cid) : [];
[$earned, $target, $pct] = $cid ? daily_meter($cid) : [0, (int) $cfg['daily_meter_target_minor'], 0];
$history = $cid ? daily_history($cid) : [];
$today_n = 0;
try {
    $today_n = daily_checked_in_count();
} catch (Throwable $t) {
    $today_n = 0;
}
$missions = daily_missions();

$page_title = 'Daily earn';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Daily earn</p>
<h1>Daily gas credits</h1>
<p class="section-lead">Log in every day, keep your streak, and bank wallet credit toward your next refill. Miss a day and the streak resets.</p>
<?php if ($success) : ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card daily-hero">
  <p class="pill">Africa/Lagos · resets at midnight</p>
  <h2><?php if ($claimed) : ?>You’re in for today<?php else : ?>Claim today’s credit<?php endif; ?></h2>
  <p class="daily-amount"><?= e(format_money($claimed && $payload ? $payload['total'] : $preview)) ?></p>
  <p class="result-meta">
    Streak <strong><?= (int) $streak ?></strong>
    <?php if ($week > 0 && !$claimed) : ?> · 7-day bonus of <?= e(format_money($week)) ?> waiting<?php endif; ?>
    <?php if ((int) $cfg['daily_mystery_chance'] > 0) : ?> · 1 in <?= (int) $cfg['daily_mystery_chance'] ?> chance of a mystery bonus<?php endif; ?>
  </p>
  <?php if (!$claimed) : ?>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="checkin">
      <p><button class="btn primary" type="submit">Claim <?= e(format_money($preview)) ?> now</button></p>
    </form>
  <?php else : ?>
    <p><span class="badge">Come back tomorrow to keep the streak</span></p>
  <?php endif; ?>
  <?php if ($today_n > 0) : ?>
    <p class="result-meta"><?= number_format($today_n) ?> customer<?= $today_n === 1 ? '' : 's' ?> already claimed today.</p>
  <?php endif; ?>
</div>

<h2>This week</h2>
<ol class="daily-week">
  <?php foreach ($week_cal as $d => $on) : ?>
    <li class="<?= $on ? 'on' : '' ?><?= $d === daily_today() ? ' today' : '' ?>">
      <span><?= e(date('D', strtotime($d))) ?></span>
      <strong><?= $on ? '✓' : '·' ?></strong>
    </li>
  <?php endforeach; ?>
</ol>

<h2>Free refill meter</h2>
<div class="card">
  <p>Wallet credits from daily earn toward a <?= e(format_money($target)) ?> refill.</p>
  <div class="meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $pct ?>">
    <span style="width:<?= (int) $pct ?>%"></span>
  </div>
  <p class="result-meta"><?= e(format_money($earned)) ?> of <?= e(format_money($target)) ?> (<?= (int) $pct ?>%)</p>
  <?php if ($pct >= 100) : ?>
    <p><a class="btn primary" href="<?= e(url('shop.php')) ?>">You’ve banked a refill — shop with wallet</a></p>
  <?php else : ?>
    <p class="result-meta">Keep checking in. Credits sit in your wallet and come off the bill at checkout.</p>
  <?php endif; ?>
</div>

<h2>Today’s missions</h2>
<div class="grid">
  <?php foreach ($missions as $key => $m) : ?>
    <?php
    [$label, $hint, $amount, $mode] = $m;
    $once = $mode === 'once';
    $done = $cid ? daily_mission_claimed($cid, $key, $once) : false;
    $ready = $cid ? daily_mission_ready($cid, $key) : false;
    ?>
    <article class="card">
      <h3><?= e($label) ?></h3>
      <p><?= e($hint) ?></p>
      <p><strong><?= e(format_money($amount)) ?></strong><?= $once ? ' <span class="badge">once</span>' : '' ?></p>
      <?php if ($done) : ?>
        <p><span class="badge">Claimed</span></p>
      <?php elseif ($ready) : ?>
        <form method="post" action="" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="mission">
          <input type="hidden" name="mission_key" value="<?= e($key) ?>">
          <p><button class="btn primary" type="submit">Claim</button></p>
        </form>
      <?php elseif ($key === 'shop') : ?>
        <p><a class="btn ghost" href="<?= e(url('shop.php')) ?>">Open shop</a></p>
      <?php elseif ($key === 'refer') : ?>
        <p><a class="btn ghost" href="<?= e(url('customer/referrals.php')) ?>">Open referrals</a></p>
      <?php elseif ($key === 'profile') : ?>
        <p><a class="btn ghost" href="<?= e(url('customer/profile.php')) ?>">Complete profile</a></p>
      <?php else : ?>
        <p><a class="btn ghost" href="<?= e(url('shop.php')) ?>">Place an order</a></p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>

<h2>Check-in history</h2>
<?php if (!$history) : ?>
  <div class="card"><p>No check-ins yet — claim today to start a streak.</p></div>
<?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Date</th><th>Streak</th><th>Credit</th><th>Mystery</th></tr></thead>
    <tbody>
      <?php foreach ($history as $h) : ?>
        <tr>
          <td><?= e($h['checkin_date']) ?></td>
          <td><?= (int) $h['streak'] ?></td>
          <td class="txn-credit"><?= e(format_money((int) $h['reward_minor'])) ?></td>
          <td><?= (int) $h['bonus_minor'] > 0 ? e(format_money((int) $h['bonus_minor'])) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
