<?php
/**
 * Oyejo Gas - customer support tickets: create, track, reply (Phase 10).
 * Staff-side management, internal notes and complaints arrive in Phase 18.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('tickets.own');
require_once BASE_PATH . '/includes/cart.php';

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
$message = '';
$errors = [];
$view = (int) ($_GET['view'] ?? 0);

$categories = ['order' => 'Order', 'payment' => 'Payment', 'delivery' => 'Delivery', 'refill' => 'Refill', 'product' => 'Product', 'other' => 'Other'];
$priorities = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

function ticket_number() {
    $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $r = '';
    for ($i = 0; $i < 6; $i++) {
        $r .= $abc[random_int(0, strlen($abc) - 1)];
    }
    return 'TKT-' . $r;
}

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $action = (string) post('action', '');
        if ($action === 'create') {
            $subject = trim((string) post('subject', ''));
            $cat = (string) post('category', 'other');
            $pri = (string) post('priority', 'normal');
            $body = trim((string) post('body', ''));
            if (strlen($subject) < 5 || strlen($subject) > 190) {
                $errors[] = 'Subject must be 5–190 characters.';
            }
            if (!isset($categories[$cat])) {
                $errors[] = 'Choose a valid category.';
            }
            if (!isset($priorities[$pri])) {
                $errors[] = 'Choose a valid priority.';
            }
            if (strlen($body) < 5 || strlen($body) > 5000) {
                $errors[] = 'Message must be 5–5000 characters.';
            }
            if (!$errors) {
                $created = null;
                for ($i = 0; $i < 5 && !$created; $i++) {
                    try {
                        $num = ticket_number();
                        db()->prepare(
                            'INSERT INTO `support_tickets` (`ticket_number`, `customer_id`, `category`, `priority`, `status`, `subject`)'
                            . " VALUES (?, ?, ?, ?, 'open', ?)"
                        )->execute([$num, $cid, $cat, $pri, $subject]);
                        $created = (int) db()->lastInsertId();
                    } catch (PDOException $e) {
                        continue; // number collision: retry
                    }
                }
                if (!$created) {
                    $errors[] = 'Could not create your ticket. Please try again.';
                } else {
                    db()->prepare('INSERT INTO `ticket_replies` (`ticket_id`, `user_id`, `body`, `is_internal`) VALUES (?, ?, ?, 0)')
                        ->execute([$created, $uid, $body]);
                    redirect(url('customer/tickets.php?view=' . $created . '&created=1'));
                }
            }
        } elseif ($action === 'reply') {
            $id = (int) post('ticket_id', 0);
            $body = trim((string) post('body', ''));
            $s = db()->prepare('SELECT * FROM `support_tickets` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
            $s->execute([$id, $cid]);
            $t = $s->fetch();
            if (!$t) {
                $errors[] = 'Ticket not found.';
            } elseif ($t['status'] === 'closed') {
                $errors[] = 'This ticket is closed. Open a new one if needed.';
            } elseif (strlen($body) < 2 || strlen($body) > 5000) {
                $errors[] = 'Reply must be 2–5000 characters.';
            } else {
                db()->prepare('INSERT INTO `ticket_replies` (`ticket_id`, `user_id`, `body`, `is_internal`) VALUES (?, ?, ?, 0)')
                    ->execute([$id, $uid, $body]);
                if ($t['status'] === 'resolved') {
                    db()->prepare("UPDATE `support_tickets` SET `status` = 'open' WHERE `id` = ?")->execute([$id]);
                }
                redirect(url('customer/tickets.php?view=' . $id . '&replied=1'));
            }
        }
    }
}

$ticket = null;
$replies = [];
if ($view > 0) {
    $s = db()->prepare('SELECT * FROM `support_tickets` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
    $s->execute([$view, $cid]);
    $ticket = $s->fetch();
    if (!$ticket) {
        http_response_code(404);
        $page_title = 'Ticket not found';
        require BASE_PATH . '/includes/header.php';
        echo '<section class="stub"><h1>Ticket not found</h1><p><a href="' . e(url('customer/tickets.php')) . '">Back to support</a></p></section>';
        require BASE_PATH . '/includes/footer.php';
        exit;
    }
    $s = db()->prepare(
        'SELECT `r`.*, `u`.`name` AS `author` FROM `ticket_replies` `r`'
        . ' LEFT JOIN `users` `u` ON `u`.`id` = `r`.`user_id`'
        . ' WHERE `r`.`ticket_id` = ? AND `r`.`is_internal` = 0 ORDER BY `r`.`id`'
    );
    $s->execute([$ticket['id']]);
    $replies = $s->fetchAll();
}

$list = [];
if (!$ticket) {
    $s = db()->prepare('SELECT * FROM `support_tickets` WHERE `customer_id` = ? ORDER BY `id` DESC LIMIT 50');
    $s->execute([$cid]);
    $list = $s->fetchAll();
}

$page_title = $ticket ? ('Ticket ' . $ticket['ticket_number']) : 'Support';
require BASE_PATH . '/includes/header.php';
?>
<?php if ($ticket) : ?>
  <?php if (isset($_GET['created'])) : ?><div class="alert alert-success">Ticket created. We will reply here.</div><?php endif; ?>
  <?php if (isset($_GET['replied'])) : ?><div class="alert alert-success">Reply sent.</div><?php endif; ?>
  <?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
  <p class="crumbs"><a href="<?= e(url('customer/tickets.php')) ?>">Support</a> &rsaquo; <?= e($ticket['ticket_number']) ?></p>
  <h1><?= e($ticket['subject']) ?></h1>
  <p><span class="badge"><?= e($ticket['ticket_number']) ?></span>
    <span class="badge"><?= e($categories[$ticket['category']] ?? $ticket['category']) ?></span>
    <span class="badge"><?= e(ucfirst($ticket['priority'])) ?></span>
    <span class="badge"><?= e(ucfirst($ticket['status'])) ?></span></p>
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
              <td><?= e($t['ticket_number']) ?></td><td><?= e($t['subject']) ?></td>
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
      <label>Message<textarea name="body" rows="5" required maxlength="5000"></textarea></label>
      <p><button class="btn primary" type="submit">Open ticket</button></p>
    </form>
  </div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
