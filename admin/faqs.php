<?php
/**
 * Oyejo Gas - FAQ management desk (Phase 19, MK-06).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('marketing.faqs');
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
            [$ok, $msg] = mk_faq_save((int) ($_POST['item_id'] ?? 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'delete') {
            [$ok, $msg] = mk_faq_delete((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

$rows = mk_faqs_all();
$edit = null;
if (isset($_GET['edit'])) {
    foreach ($rows as $r) {
        if ((int) $r['id'] === (int) $_GET['edit']) {
            $edit = $r;
        }
    }
}

$page_title = 'FAQs';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; FAQs</p>
<h1>FAQs</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Question</th><th>Category</th><th>Order</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r) : ?>
        <tr><td><?= e(mb_strimwidth($r['question'], 0, 80, '…')) ?></td><td><?= e($r['category']) ?></td>
          <td><?= (int) $r['sort_order'] ?></td><td><?= (int) $r['is_active'] ? 'Yes' : 'No' ?></td>
          <td><a href="<?= e(url('admin/faqs.php?edit=' . (int) $r['id'])) ?>">Edit</a>
            <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this FAQ?');">
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
  <h2><?= $edit ? 'Edit FAQ' : 'Create FAQ' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Question<input name="question" value="<?= e((string) ($edit['question'] ?? '')) ?>" maxlength="255" required></label>
    <label>Answer<textarea name="answer" rows="4" maxlength="10000" required><?= e((string) ($edit['answer'] ?? '')) ?></textarea></label>
    <label>Category<input name="category" value="<?= e((string) ($edit['category'] ?? 'general')) ?>" maxlength="100" required></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>" min="0" max="9999"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create FAQ' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/faqs.php')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
