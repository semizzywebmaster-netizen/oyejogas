<?php
/**
 * Oyejo Gas - FAQ page (Phase 7). Reads the faqs table (managed in Phase 19).
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();

$groups = [];
try {
    foreach (db()->query(
        'SELECT `question`, `answer`, `category` FROM `faqs`
         WHERE `is_active` = 1 ORDER BY `category`, `sort_order`'
    )->fetchAll() as $r) {
        $groups[$r['category']][] = $r;
    }
} catch (Throwable $t) {
    // Fail soft below.
}

$page_title = 'FAQs';
require BASE_PATH . '/includes/header.php';
?>
<section class="page-hero">
  <p class="pill">Good to know</p>
  <h1>Frequently asked questions</h1>
</section>
<section class="stub" style="max-width:760px">
  <?php if (!$groups) : ?>
    <div class="card"><p>FAQs are unavailable right now. Please <a href="<?= e(url('contact.php')) ?>">contact us</a>.</p></div>
  <?php else : ?>
    <?php foreach ($groups as $cat => $items) : ?>
      <h2><?= e(ucfirst($cat)) ?></h2>
      <div class="faq-list">
        <?php foreach ($items as $f) : ?>
          <details class="card">
            <summary><?= e($f['question']) ?></summary>
            <p><?= e($f['answer']) ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
