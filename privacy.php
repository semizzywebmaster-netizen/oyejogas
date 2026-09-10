<?php
/**
 * Oyejo Gas - privacy policy (Phase 7; editable CMS version in Phase 19).
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
$page_title = 'Privacy policy';
require BASE_PATH . '/includes/header.php';
?>
<section class="page-hero">
  <p class="pill">Your data</p>
  <h1>Privacy policy</h1>
</section>
<section class="stub legal" style="max-width:760px">
  <div class="card">
    <h2>1. What we collect</h2>
    <p>Account details (name, email, phone), delivery addresses, order and
      payment records, support messages, and basic security logs (such as
      login attempts). Payment card details are processed by our payment
      providers and never stored on our servers.</p>
    <h2>2. How we use it</h2>
    <p>To fulfil orders, arrange pickup and delivery, process payments and
      refunds, provide support, prevent fraud, and — with your consent — send
      offers. You can opt out of marketing and WhatsApp messages at any time.</p>
    <h2>3. Sharing</h2>
    <p>We share only what is needed to operate: delivery riders (your address
      and phone for your delivery), payment providers, and messaging providers.
      We never sell your data.</p>
    <h2>4. Security</h2>
    <p>Passwords are stored as one-way hashes, sessions expire automatically,
      and access to customer data is restricted by staff role.</p>
    <h2>5. Your rights</h2>
    <p>You may request a copy, correction or deletion of your data via the
      contact page, subject to records we must keep for accounting and safety.</p>
    <h2>6. Cookies</h2>
    <p>We use strictly-necessary cookies for login, cart and security. No
      advertising trackers.</p>
  </div>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
