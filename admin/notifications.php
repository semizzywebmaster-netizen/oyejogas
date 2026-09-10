<?php
/**
 * Oyejo Gas - notifications desk: queue, templates, per-event channels,
 * WhatsApp opt-ins and promo blasts (Phase 22).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('notifications.view');

$me = current_user();
$can_send = has_permission('notifications.send');
$message = '';
$errors = [];
$tab = (string) ($_GET['tab'] ?? $_POST['tab'] ?? 'queue');
if (!in_array($tab, ['queue', 'templates', 'config', 'whatsapp', 'promo'], true)) {
    $tab = 'queue';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $needs_send = in_array($action, ['process', 'retry', 'template_save', 'template_delete', 'config_save', 'promo'], true);
        if ($needs_send && !$can_send) {
            $errors[] = 'You do not have permission to send or change notifications.';
        } elseif ($action === 'process') {
            [$proc, $sent, $failed, $note] = notify_process_queue(100);
            $message = "Worker ran: $proc processed, $sent sent, $failed failed." . ($note !== '' ? " ($note)" : '');
        } elseif ($action === 'retry') {
            [$ok, $msg] = notify_retry((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'template_save') {
            [$ok, $msg] = notify_template_save((int) ($_POST['item_id'] ?? 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'template_delete') {
            [$ok, $msg] = notify_template_delete((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'config_save') {
            [$ok, $msg] = notify_config_save((string) ($_POST['event'] ?? ''), (array) ($_POST['channels'] ?? []), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'promo') {
            [$ok, $msg] = notify_promo((string) ($_POST['subject'] ?? ''), (string) ($_POST['body'] ?? ''),
                (array) ($_POST['channels'] ?? []), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'reminders') {
            $message = notify_pickup_reminders() . ' pickup reminder(s) queued.';
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

$f_status = (string) ($_GET['status'] ?? '');
$f_channel = (string) ($_GET['channel'] ?? '');
$f_event = trim((string) ($_GET['event'] ?? ''));
$queue = $tab === 'queue' ? notify_queue($f_status, $f_channel, $f_event) : [];
$templates = $tab === 'templates' ? notify_templates_all() : [];
$events = notify_events();
$channels = notify_channels();
$counts = notify_report_counts();
$wa = $tab === 'whatsapp' ? notify_wa_list() : [];
$wa_on = oyejo_feature('whatsapp_notifications');

$edit = null;
if ($tab === 'templates' && isset($_GET['edit'])) {
    foreach ($templates as $r) {
        if ((int) $r['id'] === (int) $_GET['edit']) {
            $edit = $r;
        }
    }
}
$prov = [
    'Email' => env_bool('MAIL_REAL', false) ? 'live (PHP mail)' : 'log only',
    'SMS' => (trim((string) env('SMS_API_URL', '')) !== '' ? 'configured (' . env('SMS_PROVIDER', 'custom') . ')' : 'log only'),
    'WhatsApp' => (trim((string) env('WHATSAPP_API_URL', '')) !== '' ? 'configured (' . env('WHATSAPP_PROVIDER', 'custom') . ')' : 'log only'),
];

$page_title = 'Notifications';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Notifications</p>
<h1>Notifications</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<p>
  <a class="btn<?= $tab === 'queue' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/notifications.php?tab=queue')) ?>">Queue</a>
  <a class="btn<?= $tab === 'templates' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/notifications.php?tab=templates')) ?>">Templates</a>
  <a class="btn<?= $tab === 'config' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/notifications.php?tab=config')) ?>">Channels</a>
  <a class="btn<?= $tab === 'whatsapp' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/notifications.php?tab=whatsapp')) ?>">WhatsApp</a>
  <a class="btn<?= $tab === 'promo' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/notifications.php?tab=promo')) ?>">Promo</a>
</p>

<?php if ($tab === 'queue') : ?>
<p>
  <?php foreach ($counts['by_status'] as $r) : ?><?= e(ucfirst($r['status'])) ?>: <strong><?= (int) $r['n'] ?></strong> &nbsp;<?php endforeach; ?>
</p>
<?php if ($can_send) : ?>
<form method="post" action="<?= e(url('admin/notifications.php?tab=queue')) ?>" class="filter-row">
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="queue">
  <p><button class="btn primary" type="submit" name="action" value="process">Run worker now</button>
  <button class="btn" type="submit" name="action" value="reminders">Queue pickup reminders</button></p>
</form>
<?php endif; ?>
<form method="get" action="<?= e(url('admin/notifications.php')) ?>" class="filter-row">
  <input type="hidden" name="tab" value="queue">
  <label>Status
    <select name="status">
      <option value="">All</option>
      <?php foreach (['queued' => 'Queued', 'sent' => 'Sent', 'delivered' => 'Delivered', 'failed' => 'Failed'] as $k => $label) : ?>
        <option value="<?= e($k) ?>"<?= $f_status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Channel
    <select name="channel">
      <option value="">All</option>
      <?php foreach ($channels as $k => $label) : ?>
        <option value="<?= e($k) ?>"<?= $f_channel === $k ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Event <input name="event" value="<?= e($f_event) ?>" placeholder="order_confirmation"></label>
  <p><button class="btn" type="submit">Filter</button></p>
</form>
<div class="card">
  <?php if (!$queue) : ?><p>No messages match.</p>
  <?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>ID</th><th>Customer</th><th>Channel</th><th>Event</th><th>Recipient</th><th>Status</th><th>Tries</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($queue as $n) : ?>
        <tr><td><?= (int) $n['id'] ?></td><td><?= e($n['customer_name'] ?? '—') ?></td>
          <td><?= e($n['channel']) ?></td><td><?= e($n['event']) ?></td>
          <td><?= e(mb_strimwidth($n['recipient'], 0, 28, '…')) ?></td>
          <td><?= e($n['status']) ?><?php if (!empty($n['error'])) : ?><br><span class="result-meta"><?= e(mb_strimwidth($n['error'], 0, 60, '…')) ?></span><?php endif; ?></td>
          <td><?= (int) $n['attempts'] ?></td>
          <td>
            <?php if ($can_send && in_array($n['status'], ['failed', 'queued'], true)) : ?>
              <form method="post" action="<?= e(url('admin/notifications.php?tab=queue')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="queue">
                <input type="hidden" name="action" value="retry">
                <input type="hidden" name="item_id" value="<?= (int) $n['id'] ?>">
                <button class="btn small ghost" type="submit">Retry</button>
              </form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'templates') : ?>
<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Slug</th><th>Channel</th><th>Event</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($templates as $r) : ?>
        <tr><td><strong><?= e($r['slug']) ?></strong></td><td><?= e($r['channel']) ?></td>
          <td><?= e($r['event']) ?></td><td><?= (int) $r['is_active'] ? 'Yes' : 'No' ?></td>
          <td><?php if ($can_send) : ?><a href="<?= e(url('admin/notifications.php?tab=templates&edit=' . (int) $r['id'])) ?>">Edit</a>
            <form method="post" action="<?= e(url('admin/notifications.php?tab=templates')) ?>" class="inline-form" onsubmit="return confirm('Delete this template?');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="templates">
              <input type="hidden" name="action" value="template_delete">
              <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
              <button class="btn small ghost" type="submit">Delete</button>
            </form><?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php if ($can_send) : ?>
<div class="card">
  <h2><?= $edit ? 'Edit template' : 'Create template' ?></h2>
  <p class="result-meta">Use {{name}}, {{order_number}} and other event variables in subject/body.</p>
  <form method="post" action="<?= e(url('admin/notifications.php?tab=templates')) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="templates">
    <input type="hidden" name="action" value="template_save">
    <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Slug<input name="slug" value="<?= e((string) ($edit['slug'] ?? '')) ?>" maxlength="120" required></label>
    <label>Channel
      <select name="channel">
        <?php foreach ($channels as $k => $label) : ?>
          <option value="<?= e($k) ?>"<?= ($edit['channel'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Event<input name="event" value="<?= e((string) ($edit['event'] ?? '')) ?>" maxlength="100" required></label>
    <label>Subject (email only)<input name="subject" value="<?= e((string) ($edit['subject'] ?? '')) ?>" maxlength="190"></label>
    <label>Body<textarea name="body" rows="5" maxlength="10000" required><?= e((string) ($edit['body'] ?? '')) ?></textarea></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create template' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/notifications.php?tab=templates')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>
<?php endif; ?>

<?php elseif ($tab === 'config') : ?>
<div class="card">
  <p class="result-meta">Which channels each event uses. Channel master switches still apply.</p>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Event</th><th>Channels</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($events as $ev => [$label, $def]) : ?>
        <?php $cur = notify_config($ev); ?>
        <tr><td><strong><?= e($label) ?></strong><br><span class="result-meta"><?= e($ev) ?></span></td>
          <td>
            <?php if ($can_send) : ?>
            <form method="post" action="<?= e(url('admin/notifications.php?tab=config')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="config">
              <input type="hidden" name="action" value="config_save">
              <input type="hidden" name="event" value="<?= e($ev) ?>">
              <?php foreach ($channels as $k => $clabel) : ?>
                <label class="check"><input type="checkbox" name="channels[]" value="<?= e($k) ?>"<?= in_array($k, $cur, true) ? ' checked' : '' ?>> <?= e($clabel) ?></label>
              <?php endforeach; ?>
              <button class="btn small primary" type="submit">Save</button>
            </form>
            <?php else : ?>
              <?= e(implode(', ', $cur)) ?>
            <?php endif; ?>
          </td>
          <td><span class="result-meta">default: <?= e(implode(', ', $def)) ?></span></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php elseif ($tab === 'whatsapp') : ?>
<div class="card">
  <h2>Provider</h2>
  <?php foreach ($prov as $k => $v) : ?><p><?= e($k) ?>: <strong><?= e($v) ?></strong></p><?php endforeach; ?>
  <p class="result-meta">Credentials live in .env only and are never displayed. WhatsApp master switch:
    <strong><?= $wa_on ? 'on' : 'off' ?></strong>.</p>
</div>
<div class="card">
  <h2>Opt-ins</h2>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Customer</th><th>Phone</th><th>Subscribed</th><th>Updated</th></tr></thead>
    <tbody>
      <?php foreach ($wa as $r) : ?>
        <tr><td><?= e($r['name']) ?></td><td><?= e((string) ($r['phone'] ?? '')) ?></td>
          <td><?= $r['subscribed'] === null || (int) $r['subscribed'] ? 'Yes' : 'No' ?></td>
          <td><?= e(substr((string) ($r['updated_at'] ?? '—'), 0, 16)) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php elseif ($tab === 'promo') : ?>
<?php if ($can_send) : ?>
<div class="card">
  <h2>Promotional blast</h2>
  <p class="result-meta">Queues to newsletter subscribers (email) and customers with phones (WhatsApp/SMS, opt-ins respected). Capped at 200 per channel per blast; the worker rate-limits delivery.</p>
  <form method="post" action="<?= e(url('admin/notifications.php?tab=promo')) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="promo">
    <input type="hidden" name="action" value="promo">
    <label>Subject<input name="subject" maxlength="190" required></label>
    <label>Message<textarea name="body" rows="5" maxlength="5000" required></textarea></label>
    <p>
      <label class="check"><input type="checkbox" name="channels[]" value="email" checked> Email</label>
      <label class="check"><input type="checkbox" name="channels[]" value="whatsapp"> WhatsApp</label>
      <label class="check"><input type="checkbox" name="channels[]" value="sms"> SMS</label>
    </p>
    <p><button class="btn primary" type="submit">Queue blast</button></p>
  </form>
</div>
<?php else : ?>
<div class="card"><p>You can view but not send.</p></div>
<?php endif; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
