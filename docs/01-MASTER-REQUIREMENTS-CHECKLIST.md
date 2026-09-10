# Oyejo Gas — Master Requirements Checklist (Phase 1 Deliverable)

**Purpose:** This is the single master checklist used during final testing
(Phase 28). Every item traces to one of the 28 phases and to an approved
feature. Nothing ships with an unchecked required item or a known issue.

**How to use:** check `[ ]` → `[x]` only after testing on the built system.
`Test` column: V = visual/manual, F = functional click-through, S = syntax/
automated check, C = code review, D = database check.

**Global constraints (apply to all phases):**

- [ ] G-01 — Single website folder, single GitHub repository (Phase 2/28, C)
- [ ] G-02 — PHP + MySQL, cPanel-compatible, no root-only dependencies (all, C)
- [ ] G-03 — Responsive on phones, tablets, laptops, desktops (Phase 7/27, V)
- [ ] G-04 — Server-side enforcement for prices, wallet, spin results, toggles,
      permissions — never trust the browser (Phases 9/11/20/23/6, C+F)
- [ ] G-05 — No credentials in publicly accessible files; env/protected config
      only, with `.env.example` shipped (Phases 4/17/22/28, C)
- [ ] G-06 — Every phase passes missing-file check + `php -l` + functional test
      before approval (all, S+F)

---

## A. Feature catalogue — approved features (the 18 pillars)

Each pillar below expands into detailed requirements in sections B–AA.

| # | Approved feature | Core promise | Phase(s) |
|---|------------------|--------------|----------|
| 1 | LPG e-commerce | Buy gas products online end-to-end | 7, 8, 9 |
| 2 | Gas refill | Request refills by cylinder size, pickup or delivery | 12 |
| 3 | Cylinder sales | Buy new cylinders with deposits where applicable | 8, 9, 13 |
| 4 | Cylinder exchange | Swap empty for full with condition/serial tracking | 13 |
| 5 | Cylinder pickup | Schedule empty-cylinder collection | 13, 16 |
| 6 | Delivery and logistics | Zones, fees, slots, dispatch, drivers, POD | 9, 16 |
| 7 | Customer management | Admin CRUD, addresses, history, suspension | 15 |
| 8 | Driver management | Accounts, availability, assignments, performance | 16 |
| 9 | Customer wallet | Top-up, pay, refunds, credits, hardened ledger | 11 |
| 10 | Referrals | Codes, links, rewards, anti-fraud | 21 |
| 11 | Spin-to-win | Server-side-random campaigns with limits | 20 |
| 12 | WhatsApp notifications | Templated, opt-in, logged, rate-limited | 22 |
| 13 | PWA | Installable, offline fallback, mobile dashboards | 27 |
| 14 | Admin feature toggles | 25 server-enforced ON/OFF switches | 23 |
| 15 | Backups | Manual/scheduled, secure, restorable | 24 |
| 16 | Site health | 15+ diagnostics visible to admin | 24 |
| 17 | Error handling | Central handler, safe messages, protected logs | 25 |
| 18 | Future add-ons | Controlled add-on architecture for 11 named add-ons | 26 |

---

## B. Single-folder project foundation (Phase 2)

- [ ] F-01 — `PHP application files` present and organized (C)
- [ ] F-02 — `MySQL database files` (schema/migrations/seeds) present (C+D)
- [ ] F-03 — `CSS` folder with site styles (V+C)
- [ ] F-04 — `JavaScript` folder with site scripts (V+C)
- [ ] F-05 — `Images` folder with site imagery (V)
- [ ] F-06 — `Uploads` folder, write-protected against script execution (C+F)
- [ ] F-07 — `Admin pages` present (F)
- [ ] F-08 — `Customer pages` present (F)
- [ ] F-09 — `Driver pages` present (F)
- [ ] F-10 — `Configuration files` present, env-based, protected (C)
- [ ] F-11 — `Logs` folder, outside public reach or denied (C)
- [ ] F-12 — `Backup folders`, outside public reach, download only via admin auth (C+F)
- [ ] F-13 — `Add-on structure` skeleton present (C)
- [ ] F-14 — Everything remains in one folder / one repo — no split projects (C)

## C. Database architecture (Phase 3) — tables & integrity

Required tables (each: correct columns, PK, FKs, indexes, constraints):

- [ ] D-01 — users (D) · D-02 — roles (D) · D-03 — permissions (D)
- [ ] D-04 — role-permission map (D) · D-05 — customers (D) · D-06 — drivers (D)
- [ ] D-07 — products (D) · D-08 — categories (D) · D-09 — cylinders (D)
- [ ] D-10 — inventory + movements (D) · D-11 — orders + order items (D)
- [ ] D-12 — payments (D) · D-13 — wallets + wallet ledger (D)
- [ ] D-14 — referrals + referral rewards (D) · D-15 — spin campaigns + spins + prizes (D)
- [ ] D-16 — deliveries (D) · D-17 — pickups (D) · D-18 — refills (D)
- [ ] D-19 — notifications + message logs (D) · D-20 — support tickets + replies (D)
- [ ] D-21 — reports/saved data where applicable (D) · D-22 — backups log (D)
- [ ] D-23 — audit logs (D) · D-24 — feature toggles (D) · D-25 — add-ons + migrations log (D)
- [ ] D-26 — coupons (D) · D-27 — reviews/ratings (D) · D-28 — CMS content (banners/FAQs/announcements/posts) (D)
- [ ] D-29 — delivery zones/fees/slots (D) · D-30 — suppliers/purchases (D)
- [ ] D-31 — All foreign keys valid; no orphan-capable deletes without rules (D)
- [ ] D-32 — Indexes on FKs, order numbers, refs, serials, phones, emails (D)
- [ ] D-33 — Unique constraints: emails, order numbers, payment refs, serials,
      referral codes, wallet txn refs (D)
- [ ] D-34 — Money stored as integer minor units or DECIMAL — never float (C+D)
- [ ] D-35 — Wallet ledger immutable: no UPDATE/DELETE on posted entries
      (reversals as new entries) (C+D)

## D. Installation and seed system (Phase 4)

- [ ] I-01 — Database installer creates schema from scratch (F)
- [ ] I-02 — Initial configuration written (site name, URL, env) (F)
- [ ] I-03 — Environment setup via `.env` + `.env.example` documented (F+C)
- [ ] I-04 — Seed data loads (roles, permissions, categories, settings) (F+D)
- [ ] I-05 — Default roles seeded (all 12) (F+D)
- [ ] I-06 — Default permissions seeded and mapped to roles (F+D)
- [ ] I-07 — Default admin account setup works (first-run only) (F)
- [ ] I-08 — Initial product categories seeded (F)
- [ ] I-09 — Initial website settings seeded (F)
- [ ] I-10 — Installation validation page/reports success or exact errors (F)
- [ ] I-11 — **Reinstallation blocked on live site** (lock file/flag + warning) (F+C)

## E. Authentication and account registration (Phase 5)

- [ ] A-01 — Customer registration works with validation (F)
- [ ] A-02 — Login works (email/phone + password) (F)
- [ ] A-03 — Logout destroys session fully (F)
- [ ] A-04 — Passwords hashed (bcrypt/argon2), never plain text (C+D)
- [ ] A-05 — Password reset via tokenized link, expiring tokens (F)
- [ ] A-06 — Email verification flow (F)
- [ ] A-07 — Phone verification support (code path; SMS provider pluggable) (F+C)
- [ ] A-08 — Session management: regenerate on login, timeout, secure flags (C+F)
- [ ] A-09 — Account activation flow (F)
- [ ] A-10 — Account suspension blocks login with clear message (F)
- [ ] A-11 — Login throttling / rate limiting after failures (F)
- [ ] A-12 — Secure auth redirects (no open redirects; role-based landing) (C+F)

## F. Roles, permissions, access control (Phase 6)

Roles (each enforced with permission checks, not name checks):

- [ ] R-01 — Super admin · R-02 — Admin · R-03 — Manager (F)
- [ ] R-04 — Logistics manager · R-05 — Driver · R-06 — Customer support (F)
- [ ] R-07 — Marketing manager · R-08 — Inventory manager · R-09 — Finance manager (F)
- [ ] R-10 — Customer · R-11 — Registered user · R-12 — Guest user (F)
- [ ] R-13 — Every protected page/action checks **permission**, not just role name (C)
- [ ] R-14 — Unauthorized access returns 403, logged (F)
- [ ] R-15 — Privilege escalation attempts fail (tested, Phase 27) (F)

## G. Public website and responsive design (Phase 7)

- [ ] W-01 — Homepage (V) · W-02 — Header + navigation (V) · W-03 — Footer (V)
- [ ] W-04 — Hero section (V) · W-05 — Services section (V)
- [ ] W-06 — LPG product section (V+F) · W-07 — Gas refill section (V+F)
- [ ] W-08 — Pickup section (V+F) · W-09 — Delivery section (V+F)
- [ ] W-10 — About page (V) · W-11 — Contact page + form delivery (V+F)
- [ ] W-12 — FAQ page (V) · W-13 — Terms page (V) · W-14 — Privacy page (V)
- [ ] W-15 — Announcements display (V+F)
- [ ] W-16 — Responsive: phone 360px, tablet 768px, laptop 1280px, desktop 1440px+ (V)
- [ ] W-17 — No broken links on public pages (F)

## H. Product catalog and pricing (Phase 8)

- [ ] P-01 — Product categories CRUD + display (F)
- [ ] P-02 — LPG cylinder sizes as products/variants (F)
- [ ] P-03 — Gas refill products (F) · P-04 — New cylinders (F)
- [ ] P-05 — Cylinder exchange products (F) · P-06 — Accessories (F)
- [ ] P-07 — Descriptions (F) · P-08 — Product images incl. placeholder (V+F)
- [ ] P-09 — Prices (server-side truth) (F+C) · P-10 — Promotional prices with dates (F)
- [ ] P-11 — Stock visibility rules (in/out/low) (F)
- [ ] P-12 — Product search (F) · P-13 — Product filtering (category/price/status) (F)
- [ ] P-14 — Product status control (active/hidden/disabled) hides from shop (F)

## I. Cart, checkout, delivery fees, orders (Phase 9)

- [ ] O-01 — Add to cart (F) · O-02 — Update quantity (F) · O-03 — Remove items (F)
- [ ] O-04 — Cart totals computed **server-side** (F+C)
- [ ] O-05 — Delivery address capture + validation (F)
- [ ] O-06 — Delivery zones enforced (F) · O-07 — Delivery charges applied correctly (F)
- [ ] O-08 — Delivery time slots selectable (F)
- [ ] O-09 — Coupon application with validation (expiry, limits, min order) (F)
- [ ] O-10 — Wallet payment option (balance-checked server-side) (F)
- [ ] O-11 — Cash-on-delivery option (F) · O-12 — Bank transfer option (F)
- [ ] O-13 — Online payment option (gateway flow stub or live per config) (F)
- [ ] O-14 — Order confirmation page + reference (F)
- [ ] O-15 — Unique order number generation, no collisions (F+D)
- [ ] O-16 — Order cancellation rules enforced (window/status-based) (F)
- [ ] O-17 — Disabled features (guest checkout, ordering, COD, etc.) block
      server-side when toggled OFF (F+C)

## J. Customer dashboard (Phase 10)

- [ ] C-01 — Customer profile view/edit (F) · C-02 — Address management (F)
- [ ] C-03 — Phone management (F) · C-04 — Order history (F)
- [ ] C-05 — Order details (F) · C-06 — Order tracking view (F)
- [ ] C-07 — Invoice viewing (F) · C-08 — Reorder functionality (F)
- [ ] C-09 — Notifications inbox (F) · C-10 — Wallet balance display (F)
- [ ] C-11 — Referral records (F) · C-12 — Rewards history (F)
- [ ] C-13 — Support tickets (create/track/reply) (F)
- [ ] C-14 — Account security settings (password change, sessions) (F)

## K. Customer wallet system (Phase 11) — hardened

- [ ] WL-01 — Wallet account auto-created per customer (F+D)
- [ ] WL-02 — Wallet balance correct (derived from ledger) (F+D)
- [ ] WL-03 — Wallet top-up flow (F) · WL-04 — Wallet checkout payments (F)
- [ ] WL-05 — Refund-to-wallet (F) · WL-06 — Promotional credits (F)
- [ ] WL-07 — Referral credits (F) · WL-08 — Spin-to-win credits (F)
- [ ] WL-09 — Transaction history (F) · WL-10 — Wallet statements (F)
- [ ] WL-11 — Pending / WL-12 — Completed / WL-13 — Failed /
      WL-14 — Reversed states handled (F)
- [ ] WL-15 — Wallet limits enforced (min/max per txn, balance caps) (F)
- [ ] WL-16 — Admin wallet adjustments with reason + audit log (F)
- [ ] WL-17 — Server-side calculations only; no client-supplied balances (C)
- [ ] WL-18 — Immutable ledger records (C+D)
- [ ] WL-19 — Duplicate-transaction prevention (idempotency refs) (F+C)
- [ ] WL-20 — Fraud checks (velocity/limits/flags) (F+C)
- [ ] WL-21 — Audit logs for every wallet mutation (D+F)

## L. Gas refill management (Phase 12)

- [ ] RF-01 — Refill request form (F) · RF-02 — Cylinder-size selection (F)
- [ ] RF-03 — Quantity selection (F) · RF-04 — Customer cylinder details (F)
- [ ] RF-05 — Pickup-or-delivery option (F)
- [ ] RF-06 — Refill price calculation server-side (F+C)
- [ ] RF-07 — Refill status lifecycle (F) · RF-08 — Refill assignment (F)
- [ ] RF-09 — Refill processing (F) · RF-10 — Refill completion (F)
- [ ] RF-11 — Refill history (F) · RF-12 — Refill notifications sent (F)

## M. Cylinder pickup, return, exchange (Phase 13)

- [ ] CY-01 — Pickup requests (F) · CY-02 — Empty-cylinder collection (F)
- [ ] CY-03 — Cylinder exchange flow (F) · CY-04 — Cylinder condition records (F)
- [ ] CY-05 — Serial number capture/validation (F)
- [ ] CY-06 — Customer-owned cylinder tracking (F)
- [ ] CY-07 — Company-owned cylinder tracking (F)
- [ ] CY-08 — Damaged-cylinder records (F) · CY-09 — Cylinder deposits (F)
- [ ] CY-10 — Pickup scheduling (F) · CY-11 — Pickup confirmation (F)
- [ ] CY-12 — Return records (F) · CY-13 — Exchange history (F)

## N. Inventory and cylinder tracking (Phase 14)

- [ ] IV-01 — Full cylinder inventory (F) · IV-02 — Empty cylinder inventory (F)
- [ ] IV-03 — Cylinders awaiting refill (F) · IV-04 — Damaged cylinders (F)
- [ ] IV-05 — Cylinder serial numbers unique + searchable (F)
- [ ] IV-06 — Accessory inventory (F) · IV-07 — Stock adjustments w/ reason (F)
- [ ] IV-08 — Stock transfers (F) · IV-09 — Stock deductions (F)
- [ ] IV-10 — Stock additions (F) · IV-11 — Low-stock alerts (F)
- [ ] IV-12 — Inventory movement history (F) · IV-13 — Supplier records (F)
- [ ] IV-14 — Purchase records (F) · IV-15 — Inventory reports (F)

## O. Admin, staff, customer, settings management (Phase 15)

- [ ] AD-01 — Manage customers (F) · AD-02 — Manage staff (F)
- [ ] AD-03 — Manage drivers (F) · AD-04 — Manage managers (F)
- [ ] AD-05 — Manage roles (F) · AD-06 — Manage permissions (F)
- [ ] AD-07 — Manage products (F) · AD-08 — Manage categories (F)
- [ ] AD-09 — Manage orders (F) · AD-10 — Manage refills (F)
- [ ] AD-11 — Manage pickups (F) · AD-12 — Manage deliveries (F)
- [ ] AD-13 — Manage inventory (F) · AD-14 — Manage coupons (F)
- [ ] AD-15 — Website settings (F) · AD-16 — Payment settings (F)
- [ ] AD-17 — Notification settings (F) · AD-18 — Feature toggles (F)
- [ ] AD-19 — Add-ons (F)

## P. Drivers, delivery, dispatch, logistics (Phase 16)

- [ ] DL-01 — Driver accounts (F) · DL-02 — Driver dashboard (F)
- [ ] DL-03 — Driver availability toggle (F)
- [ ] DL-04 — Delivery assignments (F) · DL-05 — Pickup assignments (F)
- [ ] DL-06 — Delivery zones (F) · DL-07 — Delivery fees (F)
- [ ] DL-08 — Delivery time slots (F) · DL-09 — Dispatch board (F)
- [ ] DL-10 — Driver notes (F) · DL-11 — Customer delivery instructions (F)
- [ ] DL-12 — Delivery status updates (F) · DL-13 — Failed delivery handling (F)
- [ ] DL-14 — Proof of delivery (F) · DL-15 — Cash collection records (F)
- [ ] DL-16 — Driver performance history (F) · DL-17 — Logistics reports (F)

## Q. Payments, invoices, refunds, reconciliation (Phase 17)

- [ ] PY-01 — COD payments (F) · PY-02 — Bank-transfer payments + proof/verify (F)
- [ ] PY-03 — Online gateway support (config-driven) (F+C)
- [ ] PY-04 — Payment verification flow (F) · PY-05 — Payment statuses (F)
- [ ] PY-06 — Unique payment references (F+D)
- [ ] PY-07 — Invoices (F) · PY-08 — Receipts (F)
- [ ] PY-09 — Refund records (F) · PY-10 — Partial refunds (F)
- [ ] PY-11 — Wallet refunds (F) · PY-12 — Driver cash reconciliation (F)
- [ ] PY-13 — Finance reports (F) · PY-14 — Failed payment handling (F)
- [ ] PY-15 — Credentials outside publicly accessible files (C)

## R. Support tickets, reviews, complaints, contact (Phase 18)

- [ ] ST-01 — Contact form delivers to ticket/inbox (F)
- [ ] ST-02 — Support tickets create/track (F) · ST-03 — Categories (F)
- [ ] ST-04 — Priorities (F) · ST-05 — Statuses (F)
- [ ] ST-06 — Customer replies (F) · ST-07 — Staff replies (F)
- [ ] ST-08 — Internal staff notes (hidden from customer) (F)
- [ ] ST-09 — Complaint tracking (F) · ST-10 — Refund requests (F)
- [ ] ST-11 — Product reviews (F) · ST-12 — Delivery reviews (F)
- [ ] ST-13 — Rating system (F) · ST-14 — Support reports (F)

## S. Marketing, CMS, banners, FAQs, announcements (Phase 19)

- [ ] MK-01 — Homepage banners (F) · MK-02 — Promotional campaigns (F)
- [ ] MK-03 — Coupons (F) · MK-04 — Discount codes (F)
- [ ] MK-05 — Featured products (F) · MK-06 — FAQs manage/display (F)
- [ ] MK-07 — Announcements manage/display (F) · MK-08 — Blog-style updates (F)
- [ ] MK-09 — Editable homepage sections (F) · MK-10 — Newsletter subscribers (F)
- [ ] MK-11 — Marketing reports (F) · MK-12 — Content publishing controls
      (draft/published) (F)

## T. Spin-to-win campaigns and rewards (Phase 20)

- [ ] SP-01 — Spin campaigns CRUD (F) · SP-02 — Prize configuration (F)
- [ ] SP-03 — Reward types (wallet/promo/etc.) (F) · SP-04 — Reward limits (F)
- [ ] SP-05 — Campaign start/end dates enforced (F)
- [ ] SP-06 — Eligibility rules enforced (F)
- [ ] SP-07 — One-spin-per-period enforced server-side (F+C)
- [ ] SP-08 — Minimum-order requirements enforced (F)
- [ ] SP-09 — Reward history (F) · SP-10 — Admin campaign controls (F)
- [ ] SP-11 — Reward expiration (F) · SP-12 — Manual reward reversal (F)
- [ ] SP-13 — **Result generated server-side with secure random; browser never
      decides prize** (C+F)

## U. Referral codes, rewards, anti-fraud (Phase 21)

- [ ] RR-01 — Unique referral codes (F+D) · RR-02 — Referral links (F)
- [ ] RR-03 — Referral tracking (F) · RR-04 — Qualification rules (F)
- [ ] RR-05 — Referrer rewards (F) · RR-06 — Referred-user rewards (F)
- [ ] RR-07 — Referral history (F) · RR-08 — Reward expiration (F)
- [ ] RR-09 — Minimum purchase requirements (F)
- [ ] RR-10 — Self-referral prevention (F) · RR-11 — Duplicate-account detection (F)
- [ ] RR-12 — Suspicious-referral flags (F) · RR-13 — Admin referral management (F)

## V. Email, SMS, push, WhatsApp notifications (Phase 22)

Events (each configurable per channel):

- [ ] NT-01 — Registration · NT-02 — Verification · NT-03 — Password reset (F)
- [ ] NT-04 — Order confirmation · NT-05 — Payment confirmation (F)
- [ ] NT-06 — Refill requests · NT-07 — Pickup reminders (F)
- [ ] NT-08 — Driver assignment · NT-09 — Out-for-delivery · NT-10 — Delivery
      completion (F)
- [ ] NT-11 — Wallet transactions · NT-12 — Referral rewards · NT-13 — Spin
      rewards (F)
- [ ] NT-14 — Support-ticket updates · NT-15 — Promotional campaigns (F)
- [ ] WA-01 — Provider configuration (C+F) · WA-02 — Template messages (F)
- [ ] WA-03 — Customer opt-in and opt-out (F) · WA-04 — Message logs (F)
- [ ] WA-05 — Delivery statuses (F) · WA-06 — Failed-message retries (F)
- [ ] WA-07 — Rate limiting (F+C) · WA-08 — Secure credential storage (C)

## W. Central feature-control and website settings (Phase 23)

Each toggle: admin ON/OFF + **server-side enforcement**:

- [ ] FT-01 — Customer registration · FT-02 — Guest checkout · FT-03 — Product
      ordering · FT-04 — Gas refills · FT-05 — Cylinder pickups (F+C)
- [ ] FT-06 — Cylinder exchange · FT-07 — Delivery service · FT-08 — Driver
      portal · FT-09 — Online payments · FT-10 — Bank transfers (F+C)
- [ ] FT-11 — Cash on delivery · FT-12 — Customer wallet · FT-13 — Coupons ·
      FT-14 — Reviews · FT-15 — Support tickets (F+C)
- [ ] FT-16 — Referrals · FT-17 — Spin-to-win · FT-18 — Email notifications ·
      FT-19 — SMS notifications · FT-20 — WhatsApp notifications (F+C)
- [ ] FT-21 — Push notifications · FT-22 — PWA installation · FT-23 — Marketing
      campaigns · FT-24 — Add-ons · FT-25 — Maintenance mode (F+C)
- [ ] FT-26 — Disabled feature returns safe message/page, action blocked (F)
- [ ] FT-27 — Maintenance mode locks public site, allows admin (F)

## X. Backups, restore, site health, diagnostics (Phase 24)

- [ ] BK-01 — Manual database backup (F) · BK-02 — Scheduled backups (cron) (F)
- [ ] BK-03 — Backup history (F) · BK-04 — Backup status (F)
- [ ] BK-05 — Secure backup downloads (admin-only) (F+C)
- [ ] BK-06 — Restore confirmation (typed/locked confirm) (F)
- [ ] BK-07 — Backup cleanup/retention (F) · BK-08 — Backup failure alerts (F)
- [ ] SH-01 — Database health · SH-02 — Storage checks · SH-03 — Upload-dir
      checks · SH-04 — Cron checks (F)
- [ ] SH-05 — Mail checks · SH-06 — Payment config checks · SH-07 — Notification
      checks (F)
- [ ] SH-08 — Error count · SH-09 — Failed-login count · SH-10 — Suspicious-
      activity count (F)
- [ ] SH-11 — PWA status · SH-12 — SSL/HTTPS status · SH-13 — App version status (F)

## Y. Error handling, logging, recovery (Phase 25)

- [ ] ER-01 — 404 · ER-02 — 403 · ER-03 — 419 security · ER-04 — 429 rate-limit ·
      ER-05 — 500 pages (V+F)
- [ ] ER-06 — Database failures · ER-07 — Payment failures · ER-08 — Upload
      failures · ER-09 — Email failures (F)
- [ ] ER-10 — WhatsApp failures · ER-11 — Backup failures · ER-12 — Session
      failures · ER-13 — Permission failures (F)
- [ ] ER-14 — Safe customer-facing messages (no stack traces) (V+C)
- [ ] ER-15 — Protected technical logs (C) · ER-16 — Error reference numbers (F)
- [ ] ER-17 — Log rotation (F+C) · ER-18 — Admin error viewer (F)
- [ ] ER-19 — Maintenance mode (F) · ER-20 — Recovery notifications (F)
- [ ] ER-21 — Resolved-error tracking (F)

## Z. Add-on and future-feature architecture (Phase 26)

- [ ] AO-01 — Registration · AO-02 — Versioning · AO-03 — Settings (F+C)
- [ ] AO-04 — Permissions · AO-05 — Menus (F)
- [ ] AO-06 — Database migrations per add-on (F+D)
- [ ] AO-07 — Feature toggles per add-on (F)
- [ ] AO-08 — Dependency checks (F) · AO-09 — Installation status (F)
- [ ] AO-10 — Update status (F) · AO-11 — Activity logs (F)
- [ ] AO-12 — Enable/disable controls (F)
- [ ] AO-13 — Architecture supports (documented + stub-ready): loyalty points,
      gas subscriptions, corporate accounts, multiple branches, franchise
      management, accounting integration, WhatsApp automation, route
      optimization, wallet withdrawals, multi-language, multi-currency (C)

## AA. PWA, security, testing, performance (Phase 27)

PWA:

- [ ] PW-01 — Web app manifest (F) · PW-02 — App icons (V)
- [ ] PW-03 — Install support (F) · PW-04 — Service worker (F)
- [ ] PW-05 — Offline fallback page (F) · PW-06 — Static asset caching (F)
- [ ] PW-07 — Connection detection (F) · PW-08 — Mobile navigation (V)
- [ ] PW-09 — Push notification support (F) · PW-10 — Driver mobile dashboard (V+F)
- [ ] PW-11 — Customer mobile ordering (V+F) · PW-12 — Admin mobile access (V+F)

Security tests (each attempted + passing):

- [ ] SC-01 — SQL injection · SC-02 — XSS · SC-03 — CSRF (F+C)
- [ ] SC-04 — Session hijacking · SC-05 — Brute-force login (F+C)
- [ ] SC-06 — Broken permissions · SC-07 — File-upload attacks (F+C)
- [ ] SC-08 — Unauthorized wallet access · SC-09 — Referral abuse ·
      SC-10 — Spin-to-win abuse (F+C)
- [ ] SC-11 — Payment manipulation · SC-12 — Privilege escalation (F+C)
- [ ] SC-13 — Exposed credentials · SC-14 — Public backup access ·
      SC-15 — Unsafe PWA caching (F+C)

## AB. Final deployment, verification, ZIP (Phase 28)

Final checks:

- [ ] Z-01 — Missing files · Z-02 — Missing tables · Z-03 — Broken links ·
      Z-04 — Broken redirects (S+F)
- [ ] Z-05 — PHP syntax errors (`php -l` all files) (S)
- [ ] Z-06 — Invalid includes (S+F) · Z-07 — Form errors (F)
- [ ] Z-08 — Authentication errors · Z-09 — Permission errors · Z-10 — Checkout
      errors · Z-11 — Wallet errors (F)
- [ ] Z-12 — Refill errors · Z-13 — Pickup errors · Z-14 — Delivery errors ·
      Z-15 — Driver errors (F)
- [ ] Z-16 — Admin errors · Z-17 — Notification errors · Z-18 — Backup errors ·
      Z-19 — PWA errors (F)
- [ ] Z-20 — Mobile layout issues · Z-21 — Security issues (V+F)

`oyejo-gas.zip` contents:

- [ ] Z-22 — Complete single-folder website (F) · Z-23 — PHP files (F)
- [ ] Z-24 — CSS and JavaScript (F) · Z-25 — Database schema (F)
- [ ] Z-26 — Seed data (F) · Z-27 — Installation files (F)
- [ ] Z-28 — `.env.example` (F) · Z-29 — cPanel deployment instructions (V)
- [ ] Z-30 — Backup and restore instructions (V) · Z-31 — Administrator guide (V)
- [ ] Z-32 — Testing checklist (this document) (V) · Z-33 — Add-on documentation (V)
- [ ] Z-34 — Zero known issues at ship (F)

---

## Requirement totals

~290 testable items across 28 phases. This document is frozen as the test
baseline at Phase 1 approval; later scope changes require your written approval
and an entry in the change log below.

## Change log

| Date | Change | Approved by |
|------|--------|-------------|
| 2026-09-10 | Initial master checklist created (Phase 1) | _pending your approval_ |
