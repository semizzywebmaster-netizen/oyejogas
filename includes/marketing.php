<?php
/**
 * Oyejo Gas - marketing & CMS engine: banners, campaigns, announcements,
 * FAQs, posts, homepage sections, newsletter, reports (Phase 19).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(404);
    exit;
}

function mk_audit($action, $user_id, $entity_id, $old, $new) {
    $stmt = db()->prepare(
        'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id ?: null, $action, 'marketing', $entity_id ?: null,
        $old === null ? null : json_encode($old),
        $new === null ? null : json_encode($new),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function mk_slug($text) {
    $s = strtolower(trim((string) $text));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? substr($s, 0, 140) : 'item';
}

/** Validate a datetime input; returns normalized string, null (empty) or false (invalid). */
function mk_dt($v) {
    $v = trim((string) $v);
    if ($v === '') {
        return null;
    }
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $f) {
        $d = DateTime::createFromFormat($f, $v);
        if ($d && $d->format($f) === $v) {
            return $d->format('Y-m-d H:i:s');
        }
    }
    return false;
}

function mk_window_ok($starts_at, $ends_at) {
    return $starts_at === null || $ends_at === null || $ends_at > $starts_at;
}

/* ---------------- banners (MK-01) ---------------- */

function mk_banner_positions() {
    return ['home_top' => 'Homepage top', 'home_bottom' => 'Homepage bottom', 'shop_top' => 'Shop top'];
}

function mk_banners_all() {
    return db()->query('SELECT * FROM `banners` ORDER BY `position`, `sort_order`, `id`')->fetchAll();
}

function mk_banners_live($position) {
    $stmt = db()->prepare(
        "SELECT * FROM `banners` WHERE `position` = ? AND `is_active` = 1
         AND (`starts_at` IS NULL OR `starts_at` <= NOW())
         AND (`ends_at` IS NULL OR `ends_at` >= NOW())
         ORDER BY `sort_order`, `id` LIMIT 10"
    );
    $stmt->execute([$position]);
    return $stmt->fetchAll();
}

function mk_banner_save($id, $data, $actor_id) {
    $title = trim((string) ($data['title'] ?? ''));
    $image = trim((string) ($data['image'] ?? ''));
    $link = trim((string) ($data['link_url'] ?? ''));
    $pos = trim((string) ($data['position'] ?? 'home_top'));
    if (mb_strlen($title) < 3 || mb_strlen($title) > 190) {
        return [false, 'Title must be 3–190 characters.'];
    }
    if (mb_strlen($image) > 255 || mb_strlen($link) > 255) {
        return [false, 'Image/link URLs are too long (max 255).'];
    }
    if ($link !== '' && !preg_match('#^(/|https?://)#i', $link)) {
        return [false, 'Link must be a site path or http(s) URL.'];
    }
    if (!preg_match('/^[a-z0-9_]{1,50}$/', $pos)) {
        return [false, 'Invalid position.'];
    }
    $starts = mk_dt($data['starts_at'] ?? '');
    $ends = mk_dt($data['ends_at'] ?? '');
    if ($starts === false || $ends === false) {
        return [false, 'Dates must look like YYYY-MM-DD HH:MM:SS.'];
    }
    if (!mk_window_ok($starts, $ends)) {
        return [false, 'End date must be after start date.'];
    }
    $sort = max(0, min(9999, (int) ($data['sort_order'] ?? 0)));
    $active = isset($data['is_active']) ? 1 : 0;
    $id = (int) $id;
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM `banners` WHERE `id` = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) {
            return [false, 'Banner not found.'];
        }
        db()->prepare(
            'UPDATE `banners` SET `title` = ?, `image` = ?, `link_url` = ?, `position` = ?,
             `sort_order` = ?, `starts_at` = ?, `ends_at` = ?, `is_active` = ? WHERE `id` = ?'
        )->execute([$title, $image ?: null, $link ?: null, $pos, $sort, $starts, $ends, $active, $id]);
        mk_audit('marketing.banner_save', $actor_id, $id, ['title' => $old['title']], ['title' => $title]);
        return [true, 'Banner saved.'];
    }
    db()->prepare(
        'INSERT INTO `banners` (`title`, `image`, `link_url`, `position`, `sort_order`, `starts_at`, `ends_at`, `is_active`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$title, $image ?: null, $link ?: null, $pos, $sort, $starts, $ends, $active]);
    mk_audit('marketing.banner_create', $actor_id, (int) db()->lastInsertId(), null, ['title' => $title]);
    return [true, 'Banner created.'];
}

function mk_banner_delete($id, $actor_id) {
    $stmt = db()->prepare('SELECT `title` FROM `banners` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetchColumn();
    if ($t === false) {
        return [false, 'Banner not found.'];
    }
    db()->prepare('DELETE FROM `banners` WHERE `id` = ?')->execute([(int) $id]);
    mk_audit('marketing.banner_delete', $actor_id, (int) $id, ['title' => $t], null);
    return [true, 'Banner deleted.'];
}

/** Validate + store an uploaded banner image. Returns [ok, path-or-error]. */
function mk_banner_upload($file) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Choose a banner image to upload.'];
    }
    if ((int) $file['size'] > 1024 * 1024) {
        return [false, 'Banner image must be 1 MB or smaller.'];
    }
    $info = @getimagesize($file['tmp_name']);
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($map[$info[2]])) {
        return [false, 'Only JPG, PNG or WebP banner images are accepted.'];
    }
    $dir = BASE_PATH . '/uploads/banners';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return [false, 'Could not store the banner image.'];
    }
    $name = 'BNR-' . bin2hex(random_bytes(6)) . '.' . $map[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        report_error('files', 'error', 'Banner upload failed');
        return [false, 'Could not store the banner image.'];
    }
    return [true, 'uploads/banners/' . $name];
}

/* ---------------- campaigns (MK-02) ---------------- */

function mk_campaigns_all() {
    return db()->query('SELECT * FROM `campaigns` ORDER BY `id` DESC')->fetchAll();
}

function mk_campaigns_live() {
    return db()->query(
        "SELECT * FROM `campaigns` WHERE `is_active` = 1
         AND (`starts_at` IS NULL OR `starts_at` <= NOW())
         AND (`ends_at` IS NULL OR `ends_at` >= NOW())
         ORDER BY `id` DESC LIMIT 20"
    )->fetchAll();
}

function mk_campaign_save($id, $data, $actor_id) {
    $name = trim((string) ($data['name'] ?? ''));
    $type = trim((string) ($data['type'] ?? 'promo'));
    $desc = trim((string) ($data['description'] ?? ''));
    if (mb_strlen($name) < 3 || mb_strlen($name) > 190) {
        return [false, 'Name must be 3–190 characters.'];
    }
    if (!preg_match('/^[a-z0-9_]{1,50}$/', $type)) {
        return [false, 'Invalid campaign type.'];
    }
    if (mb_strlen($desc) > 5000) {
        return [false, 'Description is too long (max 5000).'];
    }
    $starts = mk_dt($data['starts_at'] ?? '');
    $ends = mk_dt($data['ends_at'] ?? '');
    if ($starts === false || $ends === false) {
        return [false, 'Dates must look like YYYY-MM-DD HH:MM:SS.'];
    }
    if (!mk_window_ok($starts, $ends)) {
        return [false, 'End date must be after start date.'];
    }
    $active = isset($data['is_active']) ? 1 : 0;
    $id = (int) $id;
    if ($id > 0) {
        $stmt = db()->prepare('SELECT `name` FROM `campaigns` WHERE `id` = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old === false) {
            return [false, 'Campaign not found.'];
        }
        db()->prepare(
            'UPDATE `campaigns` SET `name` = ?, `type` = ?, `description` = ?, `starts_at` = ?, `ends_at` = ?, `is_active` = ?
             WHERE `id` = ?'
        )->execute([$name, $type, $desc ?: null, $starts, $ends, $active, $id]);
        mk_audit('marketing.campaign_save', $actor_id, $id, ['name' => $old], ['name' => $name]);
        return [true, 'Campaign saved.'];
    }
    db()->prepare(
        'INSERT INTO `campaigns` (`name`, `type`, `description`, `starts_at`, `ends_at`, `is_active`)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$name, $type, $desc ?: null, $starts, $ends, $active]);
    mk_audit('marketing.campaign_create', $actor_id, (int) db()->lastInsertId(), null, ['name' => $name]);
    return [true, 'Campaign created.'];
}

function mk_campaign_delete($id, $actor_id) {
    $stmt = db()->prepare('SELECT `name` FROM `campaigns` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetchColumn();
    if ($t === false) {
        return [false, 'Campaign not found.'];
    }
    db()->prepare('DELETE FROM `campaigns` WHERE `id` = ?')->execute([(int) $id]);
    mk_audit('marketing.campaign_delete', $actor_id, (int) $id, ['name' => $t], null);
    return [true, 'Campaign deleted.'];
}

/* ---------------- announcements (MK-07) ---------------- */

function mk_announce_audiences() {
    return ['all' => 'Everyone', 'customers' => 'Customers', 'drivers' => 'Drivers'];
}

function mk_announce_all() {
    return db()->query('SELECT * FROM `announcements` ORDER BY `id` DESC')->fetchAll();
}

function mk_announce_for($audience) {
    $stmt = db()->prepare(
        "SELECT `title`, `body` FROM `announcements` WHERE `is_active` = 1 AND `audience` IN ('all', ?)
         AND (`starts_at` IS NULL OR `starts_at` <= NOW())
         AND (`ends_at` IS NULL OR `ends_at` >= NOW())
         ORDER BY `id` DESC LIMIT 5"
    );
    $stmt->execute([$audience]);
    return $stmt->fetchAll();
}

function mk_announce_save($id, $data, $actor_id) {
    $title = trim((string) ($data['title'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    $aud = (string) ($data['audience'] ?? 'all');
    if (mb_strlen($title) < 3 || mb_strlen($title) > 190) {
        return [false, 'Title must be 3–190 characters.'];
    }
    if (mb_strlen($body) < 3 || mb_strlen($body) > 10000) {
        return [false, 'Body must be 3–10000 characters.'];
    }
    if (!isset(mk_announce_audiences()[$aud])) {
        return [false, 'Invalid audience.'];
    }
    $starts = mk_dt($data['starts_at'] ?? '');
    $ends = mk_dt($data['ends_at'] ?? '');
    if ($starts === false || $ends === false) {
        return [false, 'Dates must look like YYYY-MM-DD HH:MM:SS.'];
    }
    if (!mk_window_ok($starts, $ends)) {
        return [false, 'End date must be after start date.'];
    }
    $active = isset($data['is_active']) ? 1 : 0;
    $id = (int) $id;
    if ($id > 0) {
        $stmt = db()->prepare('SELECT `title` FROM `announcements` WHERE `id` = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old === false) {
            return [false, 'Announcement not found.'];
        }
        db()->prepare(
            'UPDATE `announcements` SET `title` = ?, `body` = ?, `audience` = ?, `starts_at` = ?, `ends_at` = ?, `is_active` = ?
             WHERE `id` = ?'
        )->execute([$title, $body, $aud, $starts, $ends, $active, $id]);
        mk_audit('marketing.announce_save', $actor_id, $id, ['title' => $old], ['title' => $title]);
        return [true, 'Announcement saved.'];
    }
    db()->prepare(
        'INSERT INTO `announcements` (`title`, `body`, `audience`, `starts_at`, `ends_at`, `is_active`)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$title, $body, $aud, $starts, $ends, $active]);
    mk_audit('marketing.announce_create', $actor_id, (int) db()->lastInsertId(), null, ['title' => $title]);
    return [true, 'Announcement created.'];
}

function mk_announce_delete($id, $actor_id) {
    $stmt = db()->prepare('SELECT `title` FROM `announcements` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetchColumn();
    if ($t === false) {
        return [false, 'Announcement not found.'];
    }
    db()->prepare('DELETE FROM `announcements` WHERE `id` = ?')->execute([(int) $id]);
    mk_audit('marketing.announce_delete', $actor_id, (int) $id, ['title' => $t], null);
    return [true, 'Announcement deleted.'];
}

/* ---------------- FAQs (MK-06) ---------------- */

function mk_faqs_all() {
    return db()->query('SELECT * FROM `faqs` ORDER BY `category`, `sort_order`, `id`')->fetchAll();
}

function mk_faq_save($id, $data, $actor_id) {
    $q = trim((string) ($data['question'] ?? ''));
    $a = trim((string) ($data['answer'] ?? ''));
    $cat = strtolower(trim((string) ($data['category'] ?? 'general')));
    if (mb_strlen($q) < 5 || mb_strlen($q) > 255) {
        return [false, 'Question must be 5–255 characters.'];
    }
    if (mb_strlen($a) < 5 || mb_strlen($a) > 10000) {
        return [false, 'Answer must be 5–10000 characters.'];
    }
    if (!preg_match('/^[a-z0-9 _-]{1,100}$/', $cat)) {
        return [false, 'Invalid category.'];
    }
    $sort = max(0, min(9999, (int) ($data['sort_order'] ?? 0)));
    $active = isset($data['is_active']) ? 1 : 0;
    $id = (int) $id;
    if ($id > 0) {
        $stmt = db()->prepare('SELECT `question` FROM `faqs` WHERE `id` = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old === false) {
            return [false, 'FAQ not found.'];
        }
        db()->prepare('UPDATE `faqs` SET `question` = ?, `answer` = ?, `category` = ?, `sort_order` = ?, `is_active` = ? WHERE `id` = ?')
            ->execute([$q, $a, $cat, $sort, $active, $id]);
        mk_audit('marketing.faq_save', $actor_id, $id, ['q' => mb_substr($old, 0, 80)], ['q' => mb_substr($q, 0, 80)]);
        return [true, 'FAQ saved.'];
    }
    db()->prepare('INSERT INTO `faqs` (`question`, `answer`, `category`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, ?)')
        ->execute([$q, $a, $cat, $sort, $active]);
    mk_audit('marketing.faq_create', $actor_id, (int) db()->lastInsertId(), null, ['q' => mb_substr($q, 0, 80)]);
    return [true, 'FAQ created.'];
}

function mk_faq_delete($id, $actor_id) {
    $stmt = db()->prepare('SELECT `question` FROM `faqs` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetchColumn();
    if ($t === false) {
        return [false, 'FAQ not found.'];
    }
    db()->prepare('DELETE FROM `faqs` WHERE `id` = ?')->execute([(int) $id]);
    mk_audit('marketing.faq_delete', $actor_id, (int) $id, ['q' => mb_substr($t, 0, 80)], null);
    return [true, 'FAQ deleted.'];
}

/* ---------------- posts / blog (MK-08, MK-12) ---------------- */

function mk_posts_all() {
    return db()->query('SELECT * FROM `posts` ORDER BY `id` DESC')->fetchAll();
}

function mk_posts_published($limit = 20) {
    $stmt = db()->prepare(
        "SELECT `slug`, `title`, `body`, `published_at` FROM `posts`
         WHERE `status` = 'published' AND (`published_at` IS NULL OR `published_at` <= NOW())
         ORDER BY COALESCE(`published_at`, `created_at`) DESC LIMIT " . max(1, min(50, (int) $limit))
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function mk_post_by_slug($slug) {
    $stmt = db()->prepare(
        "SELECT `slug`, `title`, `body`, `published_at` FROM `posts`
         WHERE `slug` = ? AND `status` = 'published' AND (`published_at` IS NULL OR `published_at` <= NOW()) LIMIT 1"
    );
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function mk_post_save($id, $data, $actor_id) {
    $title = trim((string) ($data['title'] ?? ''));
    $slug = trim((string) ($data['slug'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    $status = (string) ($data['status'] ?? 'draft');
    if (mb_strlen($title) < 5 || mb_strlen($title) > 190) {
        return [false, 'Title must be 5–190 characters.'];
    }
    if (mb_strlen($body) < 10 || mb_strlen($body) > 60000) {
        return [false, 'Body must be 10–60000 characters.'];
    }
    if (!in_array($status, ['draft', 'published'], true)) {
        return [false, 'Invalid status.'];
    }
    $slug = $slug !== '' ? mk_slug($slug) : mk_slug($title);
    $id = (int) $id;
    $stmt = db()->prepare('SELECT `id` FROM `posts` WHERE `slug` = ? AND `id` <> ? LIMIT 1');
    $stmt->execute([$slug, $id]);
    if ($stmt->fetchColumn()) {
        return [false, 'That slug is already used.'];
    }
    if ($id > 0) {
        $stmt = db()->prepare('SELECT `title`, `status`, `published_at` FROM `posts` WHERE `id` = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) {
            return [false, 'Post not found.'];
        }
        if ($status === 'published' && $old['status'] !== 'published' && $old['published_at'] === null) {
            // Stamp with the database clock so read-side NOW() comparisons agree.
            db()->prepare("UPDATE `posts` SET `slug` = ?, `title` = ?, `body` = ?, `status` = ?, `published_at` = NOW() WHERE `id` = ?")
                ->execute([$slug, $title, $body, $status, $id]);
        } else {
            db()->prepare('UPDATE `posts` SET `slug` = ?, `title` = ?, `body` = ?, `status` = ? WHERE `id` = ?')
                ->execute([$slug, $title, $body, $status, $id]);
        }
        mk_audit('marketing.post_save', $actor_id, $id, ['status' => $old['status']], ['status' => $status]);
        return [true, 'Post saved.'];
    }
    if ($status === 'published') {
        db()->prepare("INSERT INTO `posts` (`slug`, `title`, `body`, `status`, `published_at`) VALUES (?, ?, ?, 'published', NOW())")
            ->execute([$slug, $title, $body]);
    } else {
        db()->prepare("INSERT INTO `posts` (`slug`, `title`, `body`, `status`, `published_at`) VALUES (?, ?, ?, 'draft', NULL)")
            ->execute([$slug, $title, $body]);
    }
    mk_audit('marketing.post_create', $actor_id, (int) db()->lastInsertId(), null, ['slug' => $slug]);
    return [true, 'Post created.'];
}

function mk_post_delete($id, $actor_id) {
    $stmt = db()->prepare('SELECT `slug` FROM `posts` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetchColumn();
    if ($t === false) {
        return [false, 'Post not found.'];
    }
    db()->prepare('DELETE FROM `posts` WHERE `id` = ?')->execute([(int) $id]);
    mk_audit('marketing.post_delete', $actor_id, (int) $id, ['slug' => $t], null);
    return [true, 'Post deleted.'];
}

/* ---------------- homepage sections (MK-09) ---------------- */

function mk_sections_all() {
    return db()->query('SELECT * FROM `homepage_sections` ORDER BY `sort_order`, `id`')->fetchAll();
}

function mk_sections_live() {
    return db()->query('SELECT `slug`, `title`, `content` FROM `homepage_sections` WHERE `is_active` = 1 ORDER BY `sort_order`, `id`')->fetchAll();
}

/** Render section content: blank-line-separated paragraphs, safely escaped. */
function mk_render_blocks($content) {
    $html = '';
    foreach (preg_split('/\R{2,}/', trim((string) $content)) as $para) {
        $para = trim($para);
        if ($para !== '') {
            $html .= '<p>' . nl2br(e($para)) . '</p>';
        }
    }
    return $html;
}

function mk_section_save($id, $data, $actor_id) {
    $slug = strtolower(trim((string) ($data['slug'] ?? '')));
    $title = trim((string) ($data['title'] ?? ''));
    $content = trim((string) ($data['content'] ?? ''));
    if (!preg_match('/^[a-z0-9_]{1,100}$/', $slug)) {
        return [false, 'Slug must be 1–100 chars: letters, numbers, underscore.'];
    }
    if (mb_strlen($title) < 3 || mb_strlen($title) > 190) {
        return [false, 'Title must be 3–190 characters.'];
    }
    if (mb_strlen($content) > 20000) {
        return [false, 'Content is too long (max 20000).'];
    }
    $sort = max(0, min(9999, (int) ($data['sort_order'] ?? 0)));
    $active = isset($data['is_active']) ? 1 : 0;
    $id = (int) $id;
    $stmt = db()->prepare('SELECT `id` FROM `homepage_sections` WHERE `slug` = ? AND `id` <> ? LIMIT 1');
    $stmt->execute([$slug, $id]);
    if ($stmt->fetchColumn()) {
        return [false, 'That slug is already used.'];
    }
    if ($id > 0) {
        $stmt = db()->prepare('SELECT `slug` FROM `homepage_sections` WHERE `id` = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() === false) {
            return [false, 'Section not found.'];
        }
        db()->prepare('UPDATE `homepage_sections` SET `slug` = ?, `title` = ?, `content` = ?, `sort_order` = ?, `is_active` = ? WHERE `id` = ?')
            ->execute([$slug, $title, $content ?: null, $sort, $active, $id]);
        mk_audit('marketing.section_save', $actor_id, $id, null, ['slug' => $slug]);
        return [true, 'Section saved.'];
    }
    db()->prepare('INSERT INTO `homepage_sections` (`slug`, `title`, `content`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, ?)')
        ->execute([$slug, $title, $content ?: null, $sort, $active]);
    mk_audit('marketing.section_create', $actor_id, (int) db()->lastInsertId(), null, ['slug' => $slug]);
    return [true, 'Section created.'];
}

function mk_section_delete($id, $actor_id) {
    $stmt = db()->prepare('SELECT `slug` FROM `homepage_sections` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetchColumn();
    if ($t === false) {
        return [false, 'Section not found.'];
    }
    db()->prepare('DELETE FROM `homepage_sections` WHERE `id` = ?')->execute([(int) $id]);
    mk_audit('marketing.section_delete', $actor_id, (int) $id, ['slug' => $t], null);
    return [true, 'Section deleted.'];
}

/* ---------------- newsletter (MK-10) ---------------- */

function mk_subscribers($status = '') {
    if (in_array($status, ['subscribed', 'unsubscribed'], true)) {
        $stmt = db()->prepare('SELECT * FROM `newsletter_subscribers` WHERE `status` = ? ORDER BY `id` DESC LIMIT 500');
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }
    return db()->query('SELECT * FROM `newsletter_subscribers` ORDER BY `id` DESC LIMIT 500')->fetchAll();
}

function mk_subscribe($email, $name) {
    $email = strtolower(trim((string) $email));
    $name = mb_substr(trim((string) $name), 0, 150);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        return [false, 'Enter a valid email address.'];
    }
    $stmt = db()->prepare('SELECT `id`, `status` FROM `newsletter_subscribers` WHERE `email` = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row) {
        if ($row['status'] === 'subscribed') {
            return [true, 'That email is already subscribed.'];
        }
        db()->prepare("UPDATE `newsletter_subscribers` SET `status` = 'subscribed', `name` = ? WHERE `id` = ?")
            ->execute([$name ?: null, (int) $row['id']]);
        return [true, 'Welcome back — you are subscribed again.'];
    }
    db()->prepare('INSERT INTO `newsletter_subscribers` (`email`, `name`, `status`) VALUES (?, ?, \'subscribed\')')
        ->execute([$email, $name ?: null]);
    return [true, 'Subscribed. Watch your inbox for offers.'];
}

function mk_unsubscribe($email) {
    $email = strtolower(trim((string) $email));
    $stmt = db()->prepare('SELECT `id` FROM `newsletter_subscribers` WHERE `email` = ? AND `status` = \'subscribed\'');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        return [false, 'That email is not subscribed.'];
    }
    db()->prepare("UPDATE `newsletter_subscribers` SET `status` = 'unsubscribed' WHERE `id` = ?")->execute([(int) $id]);
    return [true, 'Unsubscribed.'];
}

function mk_subscriber_delete($id, $actor_id) {
    $stmt = db()->prepare('SELECT `email` FROM `newsletter_subscribers` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetchColumn();
    if ($t === false) {
        return [false, 'Subscriber not found.'];
    }
    db()->prepare('DELETE FROM `newsletter_subscribers` WHERE `id` = ?')->execute([(int) $id]);
    mk_audit('marketing.subscriber_delete', $actor_id, (int) $id, ['email' => $t], null);
    return [true, 'Subscriber removed.'];
}

/* ---------------- reports (MK-11) ---------------- */

function mk_reports() {
    $pdo = db();
    return [
        'banners' => $pdo->query('SELECT `is_active`, COUNT(*) AS n FROM `banners` GROUP BY `is_active`')->fetchAll(),
        'campaigns' => $pdo->query('SELECT `is_active`, COUNT(*) AS n FROM `campaigns` GROUP BY `is_active`')->fetchAll(),
        'campaigns_live' => (int) $pdo->query(
            "SELECT COUNT(*) FROM `campaigns` WHERE `is_active` = 1
             AND (`starts_at` IS NULL OR `starts_at` <= NOW())
             AND (`ends_at` IS NULL OR `ends_at` >= NOW())"
        )->fetchColumn(),
        'posts' => $pdo->query('SELECT `status`, COUNT(*) AS n FROM `posts` GROUP BY `status`')->fetchAll(),
        'faqs' => $pdo->query('SELECT `is_active`, COUNT(*) AS n FROM `faqs` GROUP BY `is_active`')->fetchAll(),
        'announcements' => $pdo->query('SELECT `is_active`, COUNT(*) AS n FROM `announcements` GROUP BY `is_active`')->fetchAll(),
        'newsletter' => $pdo->query('SELECT `status`, COUNT(*) AS n FROM `newsletter_subscribers` GROUP BY `status`')->fetchAll(),
        'coupons' => $pdo->query(
            'SELECT `code`, `used_count`, `usage_limit`, `is_active` FROM `coupons` ORDER BY `used_count` DESC LIMIT 10'
        )->fetchAll(),
    ];
}
