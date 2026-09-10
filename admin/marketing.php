<?php
/**
 * Oyejo Gas - marketing desk: campaigns, banners, announcements, homepage
 * sections and marketing reports (Phase 19, MK-01/MK-02/MK-07/MK-09/MK-11).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_once BASE_PATH . '/includes/marketing.php';

$me = current_user();
$can_campaigns = has_permission('marketing.campaigns');
$can_banners = has_permission('marketing.banners');
if (!$can_campaigns && !$can_banners) {
    http_response_code(403);
    $page_title = 'Forbidden';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Forbidden</h1><p>You do not have marketing access.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$message = '';
$errors = [];
$tab = (string) ($_GET['tab'] ?? $_POST['tab'] ?? 'campaigns');
if (!in_array($tab, ['campaigns', 'banners', 'announcements', 'homepage', 'reports'], true)) {
    $tab = 'campaigns';
}
if ($tab === 'banners' && !$can_banners) {
    $tab = 'campaigns';
}
if ($tab !== 'banners' && !$can_campaigns) {
    $tab = 'banners';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $need_banners = in_array($action, ['banner_save', 'banner_delete'], true);
        if (($need_banners && !$can_banners) || (!$need_banners && !$can_campaigns)) {
            $errors[] = 'You do not have permission for that action.';
        } elseif ($action === 'campaign_save') {
            [$ok, $msg] = mk_campaign_save((int) ($_POST['item_id'] ?? 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'campaign_delete') {
            [$ok, $msg] = mk_campaign_delete((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'banner_save') {
            [$ok, $msg] = mk_banner_save((int) ($_POST['item_id'] ?? 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'banner_delete') {
            [$ok, $msg] = mk_banner_delete((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'announce_save') {
            [$ok, $msg] = mk_announce_save((int) ($_POST['item_id'] ?? 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'announce_delete') {
            [$ok, $msg] = mk_announce_delete((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'section_save') {
            [$ok, $msg] = mk_section_save((int) ($_POST['item_id'] ?? 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'section_delete') {
            [$ok, $msg] = mk_section_delete((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

$campaigns = $can_campaigns ? mk_campaigns_all() : [];
$banners = $can_banners ? mk_banners_all() : [];
$announcements = $can_campaigns ? mk_announce_all() : [];
$sections = $can_campaigns ? mk_sections_all() : [];
$reports = $can_campaigns ? mk_reports() : [];
$audiences = mk_announce_audiences();
$positions = mk_banner_positions();

$edit = null;
if (isset($_GET['edit'])) {
    $pool = $tab === 'banners' ? $banners : ($tab === 'announcements' ? $announcements : ($tab === 'homepage' ? $sections : $campaigns));
    foreach ($pool as $r) {
        if ((int) $r['id'] === (int) $_GET['edit']) {
            $edit = $r;
        }
    }
}
$base = 'admin/marketing.php?tab=' . $tab;

$page_title = 'Marketing';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Marketing</p>
<h1>Marketing</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<p>
  <?php if ($can_campaigns) : ?><a class="btn<?= $tab === 'campaigns' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/marketing.php?tab=campaigns')) ?>">Campaigns</a><?php endif; ?>
  <?php if ($can_banners) : ?><a class="btn<?= $tab === 'banners' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/marketing.php?tab=banners')) ?>">Banners</a><?php endif; ?>
  <?php if ($can_campaigns) : ?><a class="btn<?= $tab === 'announcements' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/marketing.php?tab=announcements')) ?>">Announcements</a><?php endif; ?>
  <?php if ($can_campaigns) : ?><a class="btn<?= $tab === 'homepage' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/marketing.php?tab=homepage')) ?>">Homepage</a><?php endif; ?>
  <?php if ($can_campaigns) : ?><a class="btn<?= $tab === 'reports' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/marketing.php?tab=reports')) ?>">Reports</a><?php endif; ?>
</p>

<?php if ($tab === 'campaigns') : ?>
<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Name</th><th>Type</th><th>Window</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($campaigns as $r) : ?>
        <tr><td><strong><?= e($r['name']) ?></strong></td><td><?= e($r['type']) ?></td>
          <td><?= e(substr((string) ($r['starts_at'] ?? '—'), 0, 16)) ?> → <?= e(substr((string) ($r['ends_at'] ?? '—'), 0, 16)) ?></td>
          <td><?= (int) $r['is_active'] ? 'Yes' : 'No' ?></td>
          <td><a href="<?= e(url($base . '&edit=' . (int) $r['id'])) ?>">Edit</a>
            <form method="post" action="<?= e(url($base)) ?>" class="inline-form" onsubmit="return confirm('Delete this campaign?');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="campaigns">
              <input type="hidden" name="action" value="campaign_delete">
              <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
              <button class="btn small ghost" type="submit">Delete</button>
            </form>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2><?= $edit ? 'Edit campaign' : 'Create campaign' ?></h2>
  <form method="post" action="<?= e(url($base)) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="campaigns">
    <input type="hidden" name="action" value="campaign_save">
    <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Name<input name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" maxlength="190" required></label>
    <label>Type<input name="type" value="<?= e((string) ($edit['type'] ?? 'promo')) ?>" maxlength="50" required></label>
    <label>Description<textarea name="description" rows="3" maxlength="5000"><?= e((string) ($edit['description'] ?? '')) ?></textarea></label>
    <label>Starts at<input name="starts_at" value="<?= e((string) ($edit['starts_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label>
    <label>Ends at<input name="ends_at" value="<?= e((string) ($edit['ends_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create campaign' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url($base)) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>

<?php elseif ($tab === 'banners') : ?>
<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Title</th><th>Position</th><th>Window</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($banners as $r) : ?>
        <tr><td><strong><?= e($r['title']) ?></strong></td><td><?= e($positions[$r['position']] ?? $r['position']) ?></td>
          <td><?= e(substr((string) ($r['starts_at'] ?? '—'), 0, 16)) ?> → <?= e(substr((string) ($r['ends_at'] ?? '—'), 0, 16)) ?></td>
          <td><?= (int) $r['is_active'] ? 'Yes' : 'No' ?></td>
          <td><a href="<?= e(url($base . '&edit=' . (int) $r['id'])) ?>">Edit</a>
            <form method="post" action="<?= e(url($base)) ?>" class="inline-form" onsubmit="return confirm('Delete this banner?');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="banners">
              <input type="hidden" name="action" value="banner_delete">
              <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
              <button class="btn small ghost" type="submit">Delete</button>
            </form>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2><?= $edit ? 'Edit banner' : 'Create banner' ?></h2>
  <form method="post" action="<?= e(url($base)) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="banners">
    <input type="hidden" name="action" value="banner_save">
    <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Title<input name="title" value="<?= e((string) ($edit['title'] ?? '')) ?>" maxlength="190" required></label>
    <label>Image URL (optional)<input name="image" value="<?= e((string) ($edit['image'] ?? '')) ?>" maxlength="255" placeholder="/assets/images/..."></label>
    <label>Link URL (optional)<input name="link_url" value="<?= e((string) ($edit['link_url'] ?? '')) ?>" maxlength="255" placeholder="/shop.php"></label>
    <label>Position
      <select name="position">
        <?php foreach ($positions as $k => $label) : ?>
          <option value="<?= e($k) ?>"<?= ($edit['position'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>" min="0" max="9999"></label>
    <label>Starts at<input name="starts_at" value="<?= e((string) ($edit['starts_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label>
    <label>Ends at<input name="ends_at" value="<?= e((string) ($edit['ends_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create banner' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url($base)) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>

<?php elseif ($tab === 'announcements') : ?>
<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Title</th><th>Audience</th><th>Window</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($announcements as $r) : ?>
        <tr><td><strong><?= e($r['title']) ?></strong></td><td><?= e($audiences[$r['audience']] ?? $r['audience']) ?></td>
          <td><?= e(substr((string) ($r['starts_at'] ?? '—'), 0, 16)) ?> → <?= e(substr((string) ($r['ends_at'] ?? '—'), 0, 16)) ?></td>
          <td><?= (int) $r['is_active'] ? 'Yes' : 'No' ?></td>
          <td><a href="<?= e(url($base . '&edit=' . (int) $r['id'])) ?>">Edit</a>
            <form method="post" action="<?= e(url($base)) ?>" class="inline-form" onsubmit="return confirm('Delete this announcement?');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="announcements">
              <input type="hidden" name="action" value="announce_delete">
              <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
              <button class="btn small ghost" type="submit">Delete</button>
            </form>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2><?= $edit ? 'Edit announcement' : 'Create announcement' ?></h2>
  <form method="post" action="<?= e(url($base)) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="announcements">
    <input type="hidden" name="action" value="announce_save">
    <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Title<input name="title" value="<?= e((string) ($edit['title'] ?? '')) ?>" maxlength="190" required></label>
    <label>Body<textarea name="body" rows="4" maxlength="10000" required><?= e((string) ($edit['body'] ?? '')) ?></textarea></label>
    <label>Audience
      <select name="audience">
        <?php foreach ($audiences as $k => $label) : ?>
          <option value="<?= e($k) ?>"<?= ($edit['audience'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Starts at<input name="starts_at" value="<?= e((string) ($edit['starts_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label>
    <label>Ends at<input name="ends_at" value="<?= e((string) ($edit['ends_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create announcement' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url($base)) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>

<?php elseif ($tab === 'homepage') : ?>
<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Slug</th><th>Title</th><th>Order</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($sections as $r) : ?>
        <tr><td><strong><?= e($r['slug']) ?></strong></td><td><?= e($r['title']) ?></td>
          <td><?= (int) $r['sort_order'] ?></td><td><?= (int) $r['is_active'] ? 'Yes' : 'No' ?></td>
          <td><a href="<?= e(url($base . '&edit=' . (int) $r['id'])) ?>">Edit</a>
            <form method="post" action="<?= e(url($base)) ?>" class="inline-form" onsubmit="return confirm('Delete this section?');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="homepage">
              <input type="hidden" name="action" value="section_delete">
              <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
              <button class="btn small ghost" type="submit">Delete</button>
            </form>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2><?= $edit ? 'Edit section' : 'Create section' ?></h2>
  <form method="post" action="<?= e(url($base)) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="homepage">
    <input type="hidden" name="action" value="section_save">
    <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Slug<input name="slug" value="<?= e((string) ($edit['slug'] ?? '')) ?>" maxlength="100" required></label>
    <label>Title<input name="title" value="<?= e((string) ($edit['title'] ?? '')) ?>" maxlength="190" required></label>
    <label>Content (blank line = new paragraph)<textarea name="content" rows="6" maxlength="20000"><?= e((string) ($edit['content'] ?? '')) ?></textarea></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>" min="0" max="9999"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create section' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url($base)) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>

<?php elseif ($tab === 'reports') : ?>
<div class="grid dash-grid">
  <article class="card">
    <h2>Banners</h2>
    <?php foreach ($reports['banners'] as $r) : ?><p><?= (int) $r['is_active'] ? 'Active' : 'Inactive' ?>: <strong><?= (int) $r['n'] ?></strong></p><?php endforeach; ?>
    <?php if (!$reports['banners']) : ?><p class="result-meta">None yet.</p><?php endif; ?>
  </article>
  <article class="card">
    <h2>Campaigns</h2>
    <p>Live now: <strong><?= (int) $reports['campaigns_live'] ?></strong></p>
    <?php foreach ($reports['campaigns'] as $r) : ?><p><?= (int) $r['is_active'] ? 'Active' : 'Inactive' ?>: <strong><?= (int) $r['n'] ?></strong></p><?php endforeach; ?>
  </article>
  <article class="card">
    <h2>Posts</h2>
    <?php foreach ($reports['posts'] as $r) : ?><p><?= e(ucfirst($r['status'])) ?>: <strong><?= (int) $r['n'] ?></strong></p><?php endforeach; ?>
    <?php if (!$reports['posts']) : ?><p class="result-meta">None yet.</p><?php endif; ?>
  </article>
  <article class="card">
    <h2>FAQs</h2>
    <?php foreach ($reports['faqs'] as $r) : ?><p><?= (int) $r['is_active'] ? 'Active' : 'Inactive' ?>: <strong><?= (int) $r['n'] ?></strong></p><?php endforeach; ?>
    <?php if (!$reports['faqs']) : ?><p class="result-meta">None yet.</p><?php endif; ?>
  </article>
  <article class="card">
    <h2>Announcements</h2>
    <?php foreach ($reports['announcements'] as $r) : ?><p><?= (int) $r['is_active'] ? 'Active' : 'Inactive' ?>: <strong><?= (int) $r['n'] ?></strong></p><?php endforeach; ?>
    <?php if (!$reports['announcements']) : ?><p class="result-meta">None yet.</p><?php endif; ?>
  </article>
  <article class="card">
    <h2>Newsletter</h2>
    <?php foreach ($reports['newsletter'] as $r) : ?><p><?= e(ucfirst($r['status'])) ?>: <strong><?= (int) $r['n'] ?></strong></p><?php endforeach; ?>
    <?php if (!$reports['newsletter']) : ?><p class="result-meta">None yet.</p><?php endif; ?>
  </article>
</div>
<div class="card">
  <h2>Coupon usage (top 10)</h2>
  <?php if (!$reports['coupons']) : ?><p class="result-meta">No coupons yet.</p>
  <?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Code</th><th>Used</th><th>Limit</th><th>Active</th></tr></thead>
    <tbody>
      <?php foreach ($reports['coupons'] as $c) : ?>
        <tr><td><strong><?= e($c['code']) ?></strong></td><td><?= number_format((int) $c['used_count']) ?></td>
          <td><?= $c['usage_limit'] !== null ? number_format((int) $c['usage_limit']) : '—' ?></td>
          <td><?= (int) $c['is_active'] ? 'Yes' : 'No' ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
