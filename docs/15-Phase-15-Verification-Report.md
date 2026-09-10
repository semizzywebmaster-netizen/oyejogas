# Phase 15 verification report — Admin, staff, customer, settings management

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered

- `includes/admin.php` — back-office library: customers, staff, drivers,
  roles/permissions, products/categories, order lifecycle, coupons,
  settings, toggles, add-ons, dashboard aggregates; every mutation audited
  (`entity_type = 'admin'`).
- `admin/index.php` (rewritten) — dashboard: customer/staff/open-order/
  revenue/refill/pickup/low-stock stats, permission-gated desk links,
  recent orders.
- `admin/customers.php` (AD-01) — search/filter, detail (orders, paid
  total, addresses), staff notes (`customers.edit`), suspend/reactivate
  (`customers.suspend`).
- `admin/staff.php` (AD-02/AD-04) — staff list, create with temp password
  (`users.create`), edit (`users.edit`), suspend (`users.suspend`),
  password reset; customer role blocked; own role/status untouchable.
- `admin/drivers.php` (AD-03) — driver profiles linked to driver-role
  users, unique codes, availability/status.
- `admin/roles.php` (AD-05/AD-06) — role CRUD + grouped permission matrix;
  system slugs immutable, own-role `roles.manage` lockout guard, no
  deleting own role / system roles / roles with users.
- `admin/products.php` (AD-07/AD-08) — product CRUD (SKU/slug uniqueness,
  promo < price, category/size validation) + category CRUD; deletes
  blocked while referenced.
- `admin/orders.php` (AD-09, AD-12 visibility) — search/filter, detail
  with items + delivery record + history, forward-only status flow with
  history + customer notification, staff cancel (pending/confirmed) with
  restock. Full dispatch board stays in Phase 16.
- `admin/coupons.php` (AD-14) — CRUD with percent/range/date validation;
  used coupons cannot be deleted.
- `admin/settings.php` (AD-15…AD-18) — 7 whitelisted groups incl. new
  payment (bank/transfer display info only — **no secrets**; keys stay in
  env) and notification sender groups; minor-unit conversion + wallet
  min≤max cross-check; full toggle board (`toggles.manage`).
- `admin/addons.php` (AD-19) — registry → installed → enabled/disabled
  lifecycle. CSS: `.perm-group`/`.check`.

## Rules enforced (server-side)

- Page entry + every POST action permission-checked (`users.*`,
  `customers.*`, `drivers.*`, `roles.*`, `products.*`, `categories.*`,
  `orders.*`, `coupons.*`, `settings.*`, `toggles.*`, `addons.*`).
- Self-protection: no self-suspend, no own role/status change, no own-role
  permission lockout or deletion.
- Order flow pending → confirmed → preparing → out_for_delivery →
  delivered → completed (+ failed, failed → preparing retry); terminal
  states immutable; cancels restock tracked lines.
- Referenced records (order/purchase lines, role users, category products,
  used coupons) cannot be deleted.

## Evidence

- `php tests/foundation-check.php`: **248/248**, `php -l` clean on 77 files.
- E2E `oyejo_p15` (super_admin, admin, support hire, driver user,
  customer, 2 seeded orders): dashboard renders; customer notes/
  suspend/reactivate; staff create → login 200, dup-email + customer-role
  rejected, password reset → new login 200, self-suspend blocked; driver
  create/dup/code-format; role matrix incl. system-slug + lockout +
  delete guards; admin without `roles.manage` reads but cannot save;
  category/product create, dup-SKU, promo-over-price, referenced-product
  and non-empty-category deletes blocked; full order lifecycle (5 history
  rows, inbox subjects), completed-cancel rejected, pending order
  cancelled with restock 100→103; coupon validation, used-coupon delete
  blocked, unused deleted; settings groups incl. invalid email/currency
  and wallet min>max rejected, payment group upserted (5 keys); toggle
  off/on + bogus key; add-on jump rejected then installed→enabled;
  customer → 403, support → 403 on roles desk; 25 admin audits.
- One real app bug found and fixed: `adm_product_delete` referenced a
  non-existent `cart_items` table (cart is session-based) — check removed.
- E2E `oyejo_p15b`: product + category deletes succeed, role create
  stores exactly 2 grants; **0 PHP errors** in both runs after the fix.
- Scratch DBs/users, `.env`, and `install.lock` removed after each run.

## Result

Phase 15 complete — no known issues. Approved under autopilot authority.
