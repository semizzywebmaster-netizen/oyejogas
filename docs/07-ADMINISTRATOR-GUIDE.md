# Administrator guide (Oyejo Gas)

Day-to-day running of the shop from `/admin/`. Every desk checks
permissions server-side: staff only see and change what their role
allows, and every money/status change is validated and logged.

## Roles and staff (Admin → Staff, Admin → Roles)

- Built-in roles: Super Admin, Admin, Manager, Logistics Manager,
  Driver, Customer Support, Marketing Manager, Inventory Manager,
  Finance Manager, Customer. Create one account per person —
  shared logins hide who did what.
- **Staff**: create/edit accounts, assign roles, activate/suspend.
  Suspended users cannot log in; nothing is hard-deleted.
- **Roles** (Super Admin/Admin): tick which desks each role may view
  and which actions (create/edit/approve/restore) it may run.
  Add-on permissions appear grouped as `addon:<slug>`.
- Logins are rate-limited with lockout; password resets go through
  e-mailed tokens, never through staff-typed passwords.

## Daily desks

- **Orders / Dispatch / Pickups / Refills** (`orders.php`,
  `dispatch.php`, `pickups.php`, `refills.php`): the fulfilment
  pipeline. Move orders through legal status transitions only — the
  server rejects jumps (e.g. pending → delivered) and records each
  step for tracking.
- **Drivers** (`drivers.php`): availability, assignment, delivery
  confirmations.
- **Customers** (`customers.php`): profiles, wallet balances,
  referral links; adjustments go through the wallet desk, never by
  editing numbers elsewhere.
- **Wallet** (`wallet.php`): approve/reject top-ups, view the ledger.
  Balances are ledger-derived; approvals credit atomically.
- **Finance** (`finance.php`): revenue, payouts, reconciliation views.
- **Products / Inventory / Purchases / Coupons** (`products.php`,
  `inventory.php`, `purchases.php`, `coupons.php`): catalogue,
  stock counts, supplier purchases, promo codes. Prices are always
  charged from the server catalogue — browser-sent totals are
  ignored at checkout.
- **Reviews** (`reviews.php`): approve/reject the moderation queue;
  only approved reviews show on the storefront.

## Marketing and content

- **Marketing / Newsletter / Posts / FAQs** (`marketing.php`,
  `newsletter.php`, `posts.php`, `faqs.php`): campaigns, subscriber
  list, blog posts, help articles.
- **Spin** (`spin.php`): prize campaigns — results are drawn on the
  server; one play per customer per campaign.
- **Referrals** (`referrals.php`) and **Notifications**
  (`notifications.php`): referral ledger/flags and the outbound
  message queue (queued → sent/failed with retries).

## Support and trust

- **Tickets** (`tickets.php`): customer support threads; replies and
  internal notes are separate.
- **Logs** (`logs.php`): app/ops/security events for audits.
- **Backups** (`backups.php`): run/download/restore/retention (see
  `06-BACKUP-RESTORE-GUIDE.md`). Viewing/running needs
  `backups.create`; download/restore/delete need `backups.restore`.

## Settings, toggles, add-ons

- **Settings** (`settings.php`): Website, Contact, Locale & currency,
  Orders, Wallet limits, Payments (labels only — gateway secrets
  live in `.env`, never in the database or the page), Notifications
  (SMTP/SMS/WhatsApp/VAPID via env or fields without echoing
  secrets).
- **Feature toggles** live on the same page: shop, reviews, spin,
  referrals, wallet top-ups etc. can be paused without code edits.
- **Add-ons** (`addons.php`): install/enable/disable/update from
  manifests; pending migrations show an Update badge — run them
  from the desk. Add-on pages open through the guarded router
  (`addon.php`) with permission checks.

## Troubleshooting

| Symptom | First check |
|---------|-------------|
| Staff locked out | Wait out the lockout, then reset via e-mail; check `logs.php` for attempts |
| Order stuck | Open the order — only legal next statuses are offered; check stock + payment state |
| Top-up pending | Wallet desk → verify the transfer, then approve/reject |
| E-mails not sending | Settings → Notifications SMTP test; `storage/logs/mail.log`; cron `send-notifications.php` running? |
| Backups failing | Backups desk status + `ops.log`; disk space; cron active? |
| Toggle/add-on confusion | Settings shows effective state; Add-ons desk shows versions + pending migrations |

Back up before upgrades and add-on installs; keep one off-server
backup copy weekly.
