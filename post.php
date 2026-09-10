<?php
/**
 * Oyejo Gas - single blog post, published only (Phase 19, MK-08).
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/marketing.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$post = null;
try {
    $post = $slug !== '' ? mk_post_by_slug($slug) : null;
} catch (Throwable $t) {
    $post = null;
}
if (!$post) {
    http_response_code(404);
    $page_title = 'Post not found';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><p class="pill">Blog</p><h1>Post not found</h1>'
        . '<p>That post does not exist or is not published.</p>'
        . '<p><a class="btn primary" href="' . e(url('blog.php')) . '">Back to blog</a></p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$page_title = $post['title'];
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('blog.php')) ?>">Blog</a> &rsaquo; <?= e($post['title']) ?></p>
<article class="card">
  <h1><?= e($post['title']) ?></h1>
  <p class="result-meta"><?= e(substr((string) ($post['published_at'] ?? ''), 0, 10)) ?></p>
  <?= mk_render_blocks($post['body']) ?>
</article>
<p><a href="<?= e(url('blog.php')) ?>">&larr; All posts</a></p>
<?php require BASE_PATH . '/includes/footer.php'; ?>
