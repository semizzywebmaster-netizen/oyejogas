<?php
/**
 * Oyejo Gas - customer support tickets: create, track, reply, close (Phase 18).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('tickets.own');
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/support.php';

if (!oyejo_feature('support_tickets')) {
    http_response_code(403);
    $page_title = 'Support disabled';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Support tickets are currently disabled.</h1><p>Please check back later.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$me = current_user();
$uid = (int) $me['id'];
$cid = cart_customer_id($uid);
$errors = [];
$view = (int) ($_GET['view'] ?? 0);

$categories = sup_categories();
$priorities = sup_priorities();

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $action = (string) post('action', '');
        if ($action === 'create') {
            [$created, $msg] = sup_create(
                $cid, '', (string) post('category', 'other'), (string) post('priority', 'normal'),
                (string) post('subject', ''), (string) post('body', ''),
                (int) post('order_id', 0), $uid
            );
            if ($created) {
                redirect(url('customer/tickets.php?view=' . $created . '&created=1'));
            }
            $errors[] = $msg;
        } elseif ($action === 'reply') {
            [$ok, $msg] = sup_reply_customer((int) post('ticket_id', 0), $cid, $uid, (string) post('body', ''));
            if ($ok) {
                redirect(url('customer/tickets.php?view=' . (int) post('ticket_id', 0) . '&replied=1'));
            }
            $errors[] = $msg;
        } elseif ($action === 'close') {
            [$ok, $msg] = sup_close_customer((int) post('ticket_id', 0), $cid, $uid);
            if ($ok) {
                redirect(url('customer/tickets.php?view=' . (int) post('ticket_id', 0) . '&closed=1'));
            }
            $errors[] = $msg;
        }
    }
}

$ticket = $view > 0 ? sup_get_for_customer($view, $cid) : null;
if ($view > 0 && !$ticket) {
    http_response_code(404);
    $page_title = 'Ticket not found';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Ticket not found</h1><p><a href="' . e(url('customer/tickets.php')) . '">Back to support</a></p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}
$replies = $ticket['replies'] ?? [];

$list = [];
$orders = [];
if (!$ticket) {
    $list = sup_for_customer($cid);
    $s = db()->prepare('SELECT `id`, `order_number` FROM `orders` WHERE `customer_id` = ? ORDER BY `id` DESC LIMIT 20');
    $s->execute([$cid]);
    $orders = $s->fetchAll();
}

$page_title = $ticket ? ('Ticket ' . $ticket['ticket_number']) : 'Support';
require BASE_PATH . '/includes/header.php';
?>
<?php if ($ticket) : ?>
  <?php if (isset($_GET['created'])) : ?><div class="alert alert-success">Ticket created. We will reply here.</div><?php endif; ?>
  <?php if (isset($_GET['replied'])) : ?><div class="alert alert-success">Reply sent.</div><?php endif; ?>
  <?php if (isset($_GET['closed'])) : ?><div class="alert alert-success">Ticket closed.</div><?php endif; ?>
  <?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
  <p class="crumbs"><a href="<?= e(url('customer/tickets.php')) ?>">Support</a> &rsaquo; <?= e($ticket['ticket_number']) ?></p>
  <h1><?= e($ticket['subject']) ?></h1>
  <p><span class="badge"><?= e($ticket['ticket_number']) ?></span>
    <span class="badge"><?= e($categories[$ticket['category']] ?? $ticket['category']) ?></span>
    <span class="badge"><?= e(ucfirst($ticket['priority'])) ?></span>
    <span class="badge"><?= e(ucfirst($ticket['status'])) ?></span>
    <?php if (!empty($ticket['order_number'])) : ?><span class="badge">Order <?= e($ticket['order_number']) ?></span><?php endif; ?></p>
  <?php foreach ($replies as $r) : ?>
    <article class="card">
      <p class="result-meta"><?= e($r['author'] ?: 'Support') ?> · <?= e($r['created_at']) ?></p>
      <p><?= nl2br(e($r['body'])) ?></p>
    </article>
  <?php endforeach; ?>
  <?php if ($ticket['status'] !== 'closed') : ?>
    <div class="card">
      <h2>Add a reply</h2>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
        <label>Message<textarea name="body" rows="4" required maxlength="5000"></textarea></label>
        <p><button class="btn primary" type="submit">Send reply</button></p>
      </form>
    </div>
    <div class="card">
      <h2>Close this ticket</h2>
      <form method="post" action="" class="stack" onsubmit="return confirm('Close this ticket?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="close">
        <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
        <p><button class="btn" type="submit">Close ticket</button></p>
      </form>
    </div>
  <?php else : ?>
    <p class="result-meta">This ticket is closed.</p>
  <?php endif; ?>
<?php else : ?>
  <p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Support</p>
  <h1>Support tickets</h1>
  <?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
  <?php if ($list) : ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($list as $t) : ?>
            <tr>
              <td><?= e($t['ticket_number']) ?></td>
              <td><?= e($t['subject']) ?><?php if (!empty($t['order_number'])) : ?> <span class="badge"><?= e($t['order_number']) ?></span><?php endif; ?></td>
              <td><?= e(ucfirst($t['status'])) ?></td><td><?= e(substr($t['updated_at'], 0, 16)) ?></td>
              <td><a href="<?= e(url('customer/tickets.php?view=' . $t['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else : ?>
    <div class="card"><p>No tickets yet.</p></div>
  <?php endif; ?>
  <div class="card">
    <h2>Open a ticket</h2>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <label>Subject<input name="subject" required maxlength="190"></label>
      <label>Category
        <select name="category">
          <?php foreach ($categories as $k => $v) : ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Priority
        <select name="priority">
          <?php foreach ($priorities as $k => $v) : ?><option value="<?= $k ?>"<?= $k === 'normal' ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
      </label>
      <?php if ($orders) : ?>
        <label>Related order (optional)
          <select name="order_id">
            <option value="0">— none —</option>
            <?php foreach ($orders as $o) : ?><option value="<?= (int) $o['id'] ?>"><?= e($o['order_number']) ?></option><?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <label>Message<textarea name="body" rows="5" required maxlength="5000"></textarea></label>
      <p><button class="btn primary" type="submit">Open ticket</button></p>
    </form>
  </div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
