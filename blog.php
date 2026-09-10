<?php
/**
 * Oyejo Gas - blog index, published posts only (Phase 19, MK-08).
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/marketing.php';

$posts = [];
try {
    $posts = mk_posts_published(20);
} catch (Throwable $t) {
    // Fail soft below.
}

$page_title = 'Blog';
require BASE_PATH . '/includes/header.php';
?>
<section class="page-hero">
  <p class="pill">News &amp; tips</p>
  <h1>Blog</h1>
</section>
<section class="stub" style="max-width:760px">
  <?php if (!$posts) : ?>
    <div class="card"><p>No posts yet. Check back soon.</p></div>
  <?php else : ?>
    <?php foreach ($posts as $p) : ?>
      <article class="card">
        <h2><a href="<?= e(url('post.php?slug=' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h2>
        <p class="result-meta"><?= e(substr((string) ($p['published_at'] ?? ''), 0, 10)) ?></p>
        <p><?= e(mb_strimwidth(strip_tags($p['body']), 0, 220, '…')) ?></p>
        <p><a href="<?= e(url('post.php?slug=' . $p['slug'])) ?>">Read more &rarr;</a></p>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
