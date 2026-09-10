<?php
/**
 * Oyejo Gas - staff support desk: tickets, complaints, escalations (Phase 18).
 * Perms: portal.admin + tickets.manage (ST-03…ST-06, ST-10).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('tickets.manage');
require_once BASE_PATH . '/includes/support.php';

$me = current_user();
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $id = (int) ($_POST['ticket_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'reply') {
            [$ok, $msg] = sup_reply_staff($id, (int) $me['id'], (string) ($_POST['body'] ?? ''), false);
        } elseif ($action === 'note') {
            [$ok, $msg] = sup_reply_staff($id, (int) $me['id'], (string) ($_POST['body'] ?? ''), true);
        } elseif ($action === 'status') {
            [$ok, $msg] = sup_set_status($id, (string) ($_POST['to'] ?? ''), (int) $me['id']);
        } elseif ($action === 'priority') {
            [$ok, $msg] = sup_set_priority($id, (string) ($_POST['to'] ?? ''), (int) $me['id']);
        } else {
            [$ok, $msg] = [false, 'Unknown action.'];
        }
        $ok ? $message = $msg : $errors[] = $msg;
    }
}

$cats = sup_categories();
$pris = sup_priorities();
$sts = sup_statuses();

$view = isset($_GET['view']) ? (int) $_GET['view'] : 0;
$ticket = $view > 0 ? sup_get($view) : null;
if ($view > 0 && !$ticket) {
    $errors[] = 'Ticket not found.';
}

$f_status = (string) ($_GET['status'] ?? '');
$f_cat = (string) ($_GET['category'] ?? '');
$f_pri = (string) ($_GET['priority'] ?? '');
$f_q = trim((string) ($_GET['q'] ?? ''));
$tickets = $ticket ? [] : sup_list($f_status, $f_cat, $f_pri, $f_q);

$page_title = $ticket ? ('Ticket ' . $ticket['ticket_number']) : 'Support tickets';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Support</p>
<h1>Support tickets</h1>
<?php if ($message) : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if ($ticket) : ?>
  <p><a href="<?= e(url('admin/tickets.php')) ?>">&larr; All tickets</a></p>
  <div class="card">
    <h2><?= e($ticket['ticket_number']) ?> — <?= e($ticket['subject']) ?></h2>
    <p>
      <span class="badge"><?= e($cats[$ticket['category']] ?? $ticket['category']) ?></span>
      <span class="badge"><?= e($pris[$ticket['priority']] ?? $ticket['priority']) ?></span>
      <span class="badge"><?= e($sts[$ticket['status']] ?? $ticket['status']) ?></span>
    </p>
    <p>
      From:
      <?php if (!empty($ticket['customer_name'])) : ?>
        <?= e($ticket['customer_name']) ?> (<?= e((string) ($ticket['customer_email'] ?? '')) ?>)
      <?php else : ?>
        Guest — <?= e((string) ($ticket['guest_email'] ?? '')) ?>
      <?php endif; ?>
      <?php if (!empty($ticket['order_number'])) : ?>
        · order <a href="<?= e(url('admin/orders.php?view=' . (int) $ticket['order_id'])) ?>"><?= e($ticket['order_number']) ?></a>
        <?php if ($ticket['category'] === 'refund') : ?>
          · <a href="<?= e(url('admin/finance.php?order_id=' . (int) $ticket['order_id'])) ?>">open in Finance</a>
        <?php endif; ?>
      <?php endif; ?>
    </p>
    <p class="result-meta">Opened <?= e($ticket['created_at']) ?> · updated <?= e($ticket['updated_at']) ?></p>
  </div>

  <h2>Conversation</h2>
  <?php foreach ($ticket['replies'] as $r) : ?>
    <article class="card">
      <p class="result-meta"><?= e($r['author'] ?: 'Customer') ?> · <?= e($r['created_at']) ?>
        <?php if ($r['is_internal']) : ?><span class="badge">internal note — hidden from customer</span><?php endif; ?></p>
      <p><?= nl2br(e($r['body'])) ?></p>
    </article>
  <?php endforeach; ?>

  <div class="card">
    <h2>Reply to customer</h2>
    <form method="post" action="<?= e(url('admin/tickets.php?view=' . (int) $ticket['id'])) ?>" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
      <input type="hidden" name="action" value="reply">
      <label>Reply<textarea name="body" rows="4" required maxlength="5000"></textarea></label>
      <p><button class="btn primary" type="submit">Send reply</button></p>
    </form>
  </div>

  <div class="card">
    <h2>Internal note</h2>
    <form method="post" action="<?= e(url('admin/tickets.php?view=' . (int) $ticket['id'])) ?>" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
      <input type="hidden" name="action" value="note">
      <label>Note<textarea name="body" rows="3" required maxlength="5000"></textarea></label>
      <p><button class="btn" type="submit">Save note</button></p>
    </form>
  </div>

  <div class="card">
    <h2>Status &amp; priority</h2>
    <form method="post" action="<?= e(url('admin/tickets.php?view=' . (int) $ticket['id'])) ?>" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
      <input type="hidden" name="action" value="status">
      <label>Status
        <select name="to">
          <?php foreach ($sts as $k => $label) : ?>
            <option value="<?= e($k) ?>"<?= $k === $ticket['status'] ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p><button class="btn" type="submit">Update status</button></p>
    </form>
    <form method="post" action="<?= e(url('admin/tickets.php?view=' . (int) $ticket['id'])) ?>" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
      <input type="hidden" name="action" value="priority">
      <label>Priority
        <select name="to">
          <?php foreach ($pris as $k => $label) : ?>
            <option value="<?= e($k) ?>"<?= $k === $ticket['priority'] ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p><button class="btn" type="submit">Update priority</button></p>
    </form>
  </div>
<?php else : ?>
  <form method="get" action="<?= e(url('admin/tickets.php')) ?>" class="filter-row">
    <label>Status
      <select name="status">
        <option value="">All</option>
        <?php foreach ($sts as $k => $label) : ?>
          <option value="<?= e($k) ?>"<?= $k === $f_status ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Category
      <select name="category">
        <option value="">All</option>
        <?php foreach ($cats as $k => $label) : ?>
          <option value="<?= e($k) ?>"<?= $k === $f_cat ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Priority
      <select name="priority">
        <option value="">All</option>
        <?php foreach ($pris as $k => $label) : ?>
          <option value="<?= e($k) ?>"<?= $k === $f_pri ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Search <input type="search" name="q" value="<?= e($f_q) ?>" placeholder="number, subject, name, email"></label>
    <p><button class="btn" type="submit">Filter</button></p>
  </form>

  <?php if (!$tickets) : ?>
    <div class="card"><p>No tickets match.</p></div>
  <?php else : ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Ticket</th><th>Subject</th><th>From</th><th>Category</th><th>Priority</th><th>Status</th><th>Updated</th></tr></thead>
        <tbody>
          <?php foreach ($tickets as $t) : ?>
            <tr>
              <td><a href="<?= e(url('admin/tickets.php?view=' . (int) $t['id'])) ?>"><?= e($t['ticket_number']) ?></a></td>
              <td><?= e(mb_strimwidth($t['subject'], 0, 60, '…')) ?></td>
              <td><?= e($t['customer_name'] ?? $t['guest_email'] ?? '—') ?></td>
              <td><?= e($cats[$t['category']] ?? $t['category']) ?></td>
              <td><?= e($pris[$t['priority']] ?? $t['priority']) ?></td>
              <td><?= e($sts[$t['status']] ?? $t['status']) ?></td>
              <td><?= e(substr($t['updated_at'], 0, 16)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
