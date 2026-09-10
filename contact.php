<?php
/**
 * Oyejo Gas - contact page (Phase 18: every message becomes a support ticket).
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/support.php';

$contact = ['email' => env('MAIL_FROM', ''), 'phone' => '', 'address' => 'Lagos, Nigeria'];
try {
    foreach (db()->query("SELECT `key`, `value` FROM `settings` WHERE `key` IN ('contact_email','contact_phone','contact_address')")->fetchAll() as $r) {
        if ($r['key'] === 'contact_email') {
            $contact['email'] = (string) $r['value'];
        } elseif ($r['key'] === 'contact_phone') {
            $contact['phone'] = (string) $r['value'];
        } else {
            $contact['address'] = (string) $r['value'];
        }
    }
} catch (Throwable $t) {
    // Defaults above.
}

$errors = [];
$sent = false;
$name = '';
$email = '';
$subject = '';
$message = '';

if (request_method() === 'POST') {
    [$rl_ok, $rl_retry] = rate_limit('contact', rate_ip(), 5, 3600);
    if (!$rl_ok) {
        err_429($rl_retry);
    }
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } elseif (trim((string) post('company', '')) !== '') {
        $sent = true; // Honeypot: pretend success to bots.
    } else {
        $name = trim((string) post('name', ''));
        $email = trim((string) post('email', ''));
        $subject = trim((string) post('subject', ''));
        $message = trim((string) post('message', ''));
        if (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = 'Name must be 2–100 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($subject) < 5 || strlen($subject) > 150) {
            $errors[] = 'Subject must be 5–150 characters.';
        }
        if (strlen($message) < 10 || strlen($message) > 2000) {
            $errors[] = 'Message must be 10–2000 characters.';
        }
        $category = (string) post('category', 'other');
        if (!isset(sup_categories()[$category])) {
            $category = 'other';
        }
        if (!$errors) {
            $cid = null;
            $uid = 0;
            if (is_logged_in()) {
                $uid = (int) current_user()['id'];
                $cid = cart_customer_id($uid) ?: null;
            }
            [$ticket_id, $ticket_num] = sup_create(
                $cid, $cid ? '' : $email, $category, 'normal', $subject,
                $cid ? $message : ("Name: $name\nEmail: $email\n\n$message"),
                0, $uid
            );
            if (!$ticket_id) {
                $errors[] = $ticket_num;
            } else {
                send_mail(
                    $contact['email'] !== '' ? $contact['email'] : env('MAIL_FROM', 'no-reply@localhost'),
                    'New ticket ' . $ticket_num . ': ' . $subject,
                    "From: $name <$email>\nTicket: $ticket_num\n\n$message"
                );
                $sent = true;
            }
        }
    }
}

$page_title = 'Contact us';
require BASE_PATH . '/includes/header.php';
?>
<section class="page-hero">
  <p class="pill">We reply fast</p>
  <h1>Contact us</h1>
</section>
<section class="split">
  <div>
    <?php if ($sent) : ?>
      <div class="card"><p>Thanks — your message is on its way. We reply within one business day.<?php if (!empty($ticket_num)) : ?> Your reference is <strong><?= e($ticket_num) ?></strong><?php if (is_logged_in()) : ?> — track it under <a href="<?= e(url('customer/tickets.php')) ?>">Support</a><?php endif; ?>.<?php endif; ?></p></div>
    <?php else : ?>
      <?php foreach ($errors as $e) : ?>
        <div class="alert alert-error"><?= e($e) ?></div>
      <?php endforeach; ?>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <label style="display:none">Company (leave blank)<input name="company" value="" autocomplete="off"></label>
        <label>Your name<input name="name" value="<?= e($name) ?>" required maxlength="100"></label>
        <label>Email<input type="email" name="email" value="<?= e($email) ?>" required maxlength="190"></label>
        <label>Subject<input name="subject" value="<?= e($subject) ?>" required maxlength="150"></label>
        <label>Topic
          <select name="category">
            <?php foreach (sup_categories() as $k => $v) : ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Message<textarea name="message" rows="6" required maxlength="2000"><?= e($message) ?></textarea></label>
        <p><button class="btn primary" type="submit">Send message</button></p>
      </form>
    <?php endif; ?>
  </div>
  <div>
    <div class="card">
      <h2>Reach us directly</h2>
      <?php if ($contact['email'] !== '') : ?><p><strong>Email:</strong> <?= e($contact['email']) ?></p><?php endif; ?>
      <?php if ($contact['phone'] !== '') : ?><p><strong>Phone:</strong> <?= e($contact['phone']) ?></p><?php endif; ?>
      <p><strong>Area:</strong> <?= e($contact['address']) ?></p>
    </div>
  </div>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
