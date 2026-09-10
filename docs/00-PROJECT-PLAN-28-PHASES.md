# Oyejo Gas — 28-Phase Project Plan & Approval Tracker

**Rule:** Each phase must be built, checked for missing files, tested,
corrected, and approved before the next phase begins.

**Legend:** ⬜ Not started · 🟡 In progress · 🔵 Built, awaiting correction/retest · 🟢 Approved

---

| # | Phase | Scope summary | Status | Approved |
|---|-------|---------------|--------|----------|
| 1 | Requirements and master feature checklist | Define every approved feature; create master requirements checklist used in final testing | 🟢 Approved | ☑ |
| 2 | Single-folder project foundation | Unified structure: PHP app, MySQL files, CSS, JS, images, uploads, admin/customer/driver pages, config, logs, backups, add-ons | 🟢 Approved | ☑ |
| 3 | Database architecture | Full MySQL schema: users→add-ons; FKs, indexes, constraints, relationships verified | 🟢 Approved | ☑ |
| 4 | Installation and seed system | Installer, env setup, seed data, roles, permissions, default admin, categories, settings, validation; reinstall protection | 🟢 Approved | ☑ |
| 5 | Authentication and account registration | Registration, login/logout, hashing, reset, email+phone verification, sessions, activation, suspension, throttling, secure redirects | 🟢 Approved | ☑ |
| 6 | Roles, permissions, access control | 12 roles + permission-level controls (not role-name only) | 🟢 Approved | ☑ |
| 7 | Public website and responsive design | Home, header/footer, hero, services, LPG/refill/pickup/delivery sections, about, contact, FAQ, terms, privacy, announcements, responsive | 🟢 Approved | ☑ |
| 8 | Product catalog and pricing | Categories, cylinder sizes, refills, new cylinders, exchange, accessories, descriptions, images, prices, promo prices, stock visibility, search/filter, status | 🟢 Approved | ☑ |
| 9 | Cart, checkout, delivery fees, orders | Cart ops, totals, address, zones, fees, slots, coupons, wallet/COD/transfer/online payment, confirmation, order numbers, cancellation rules | 🟢 Approved | ☑ |
| 10 | Customer dashboard | Profile, addresses, phones, order history/details/tracking, invoices, reorder, notifications, wallet, referrals, rewards, tickets, security | 🟢 Approved | ☑ |
| 11 | Customer wallet system | Account, balance, top-up, checkout payments, refunds-to-wallet, promo/referral/spin credits, history, statements, txn states, limits, admin adjustments; hardened ledger | 🟢 Approved | ☑ |
| 12 | Gas refill management | Refill form, size/qty, cylinder details, pickup-or-delivery, pricing, status flow, assignment, processing, completion, history, notifications | 🟢 Approved | ☑ |
| 13 | Cylinder pickup, return, exchange | Pickup requests, empty collection, exchange, condition, serials, customer- vs company-owned tracking, damage, deposits, scheduling, confirmation, returns, history | 🟡 In progress | ☐ |
| 14 | Inventory and cylinder tracking | Full/empty/awaiting-refill/damaged stock, serials, accessories, adjustments, transfers, deductions, additions, low-stock alerts, movements, suppliers, purchases, reports | ⬜ | ☐ |
| 15 | Admin, staff, customer, settings management | Admin for customers, staff, drivers, managers, roles, permissions, products, categories, orders, refills, pickups, deliveries, inventory, coupons, website/payment/notification settings, toggles, add-ons | ⬜ | ☐ |
| 16 | Drivers, delivery, dispatch, logistics | Driver accounts/dashboard/availability, assignments, zones, fees, slots, dispatch board, notes, instructions, status, failed delivery, POD, cash collection, performance, logistics reports | ⬜ | ☐ |
| 17 | Payments, invoices, refunds, reconciliation | COD, transfer, gateway support, verification, statuses, references, invoices, receipts, refunds (partial/wallet), driver cash reconciliation, finance reports, failed payments; credentials outside public files | ⬜ | ☐ |
| 18 | Support tickets, reviews, complaints, contact | Contact form, tickets (categories/priorities/statuses), customer/staff replies, internal notes, complaints, refund requests, product/delivery reviews, ratings, reports | ⬜ | ☐ |
| 19 | Marketing, CMS, banners, FAQs, announcements | Banners, campaigns, coupons, discount codes, featured products, FAQs, announcements, blog updates, editable homepage, newsletter, marketing reports, publishing controls | ⬜ | ☐ |
| 20 | Spin-to-win campaigns and rewards | Campaigns, prizes, reward types/limits, dates, eligibility, one-spin-per-period, min-order, history, admin controls, expiry, manual reversal; secure server-side random | ⬜ | ☐ |
| 21 | Referral codes, rewards, anti-fraud | Codes, links, tracking, qualification, referrer/referred rewards, history, expiry, min purchase, self-referral prevention, duplicate detection, suspicious flags, admin mgmt | ⬜ | ☐ |
| 22 | Email, SMS, push, WhatsApp notifications | 15 event types + WhatsApp (provider config, templates, opt-in/out, logs, statuses, retries, rate limits, secure credentials) | ⬜ | ☐ |
| 23 | Central feature-control and website settings | 25 ON/OFF toggles incl. maintenance mode; enforced server-side | ⬜ | ☐ |
| 24 | Backups, restore, site health, diagnostics | Manual/scheduled backups, history, status, secure downloads, restore confirmation, cleanup, failure alerts, 15+ health checks | ⬜ | ☐ |
| 25 | Error handling, logging, recovery | 404/403/419/429/500 + 8 failure domains; safe messages, protected logs, ref numbers, rotation, admin viewer, maintenance, recovery notifications, resolved tracking | ⬜ | ☐ |
| 26 | Add-on and future-feature architecture | Registration, versioning, settings, permissions, menus, migrations, toggles, dependencies, install/update status, logs, enable/disable; 11 future add-ons specified | ⬜ | ☐ |
| 27 | PWA, security, testing, performance | Manifest, icons, install, service worker, offline fallback, caching, connection detection, mobile nav, push, mobile dashboards; 15 security test areas | ⬜ | ☐ |
| 28 | Final deployment, verification, ZIP packaging | 20 check groups + `oyejo-gas.zip` with 11 contents; zero known issues at ship | ⬜ | ☐ |

---

## Approval gate (used every phase)

A phase is approved only when:

1. [ ] All planned files exist (missing-file check passed)
2. [ ] PHP syntax check passed (`php -l` on every PHP file)
3. [ ] Functional test of the phase's checklist items passed
4. [ ] All found issues fixed and retested
5. [ ] You (the owner) give explicit approval

## How to approve

Reply with e.g. **"Phase 1 approved — proceed to Phase 2"**.
If you want changes, list them and they will be corrected and re-presented.
