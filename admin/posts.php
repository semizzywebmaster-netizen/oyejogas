<?php
/**
 * Oyejo Gas - blog post management desk with publishing controls
 * (Phase 19, MK-08/MK-12).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('marketing.posts');
require_once BASE_PATH . '/includes/marketing.php';

$me = current_user();
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save') {
            [$ok, $msg] = mk_post_save((int) ($_POST['item_id'] ?? 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'delete') {
            [$ok, $msg] = mk_post_delete((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

$rows = mk_posts_all();
$edit = null;
if (isset($_GET['edit'])) {
    foreach ($rows as $r) {
        if ((int) $r['id'] === (int) $_GET['edit']) {
            $edit = $r;
        }
    }
}

$page_title = 'Blog posts';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Posts</p>
<h1>Blog posts</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Published</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r) : ?>
        <tr><td><strong><?= e($r['title']) ?></strong></td><td><?= e($r['slug']) ?></td>
          <td><?= e(ucfirst($r['status'])) ?></td><td><?= e(substr((string) ($r['published_at'] ?? '—'), 0, 16)) ?></td>
          <td><a href="<?= e(url('admin/posts.php?edit=' . (int) $r['id'])) ?>">Edit</a>
            <?php if ($r['status'] === 'published') : ?><a href="<?= e(url('post.php?slug=' . $r['slug'])) ?>">View</a><?php endif; ?>
            <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this post?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
              <button class="btn small ghost" type="submit">Delete</button>
            </form>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card">
  <h2><?= $edit ? 'Edit post' : 'Create post' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Title<input name="title" value="<?= e((string) ($edit['title'] ?? '')) ?>" maxlength="190" required></label>
    <label>Slug (blank = from title)<input name="slug" value="<?= e((string) ($edit['slug'] ?? '')) ?>" maxlength="150"></label>
    <label>Body<textarea name="body" rows="10" maxlength="60000" required><?= e((string) ($edit['body'] ?? '')) ?></textarea></label>
    <label>Status
      <select name="status">
        <option value="draft"<?= ($edit['status'] ?? '') === 'draft' || !$edit ? ' selected' : '' ?>>Draft</option>
        <option value="published"<?= ($edit['status'] ?? '') === 'published' ? ' selected' : '' ?>>Published</option>
      </select>
    </label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create post' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/posts.php')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
