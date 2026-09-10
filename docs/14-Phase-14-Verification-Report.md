# Phase 14 verification report — Inventory & cylinder tracking

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered

- `includes/inventory.php` — `inv_adjust()`, `inv_move()`,
  `inv_search_cylinders()`, `inv_cylinder_set_status()`,
  `inv_supplier_save()`/`inv_supplier_delete()`,
  `inv_purchase_create()`/`inv_purchase_receive()`/`inv_purchase_cancel()`,
  `inv_movements()`, `inv_low_stock()`, `inv_reports()`; numbers
  `PUR-YYYYMMDD-XXXXX` with collision retry; per-action `inv_audit()` rows.
- `admin/inventory.php` — `inventory.view` desk: status counts, low-stock
  alerts, accessory table with inline adjustments, movement form, serial
  search + status changes, movement history, valuation/movement/purchase
  reports. Mutations split by `inventory.adjust` / `inventory.transfer` /
  `cylinders.manage`.
- `admin/purchases.php` — `purchases.manage` desk: supplier add/edit/delete
  (`suppliers.manage`), 4-line purchase creation with optional product
  links, detail view, receive-into-stock, cancel.
- CSS: `.stat-grid`/`.stat`/`.inline-form` for the desks.

## Rules enforced (server-side)

- Adjustments set absolute levels (0–1,000,000) with mandatory reason;
  movements are relative (+/−, transfers) and can never drive stock below
  zero; untracked products are rejected; every mutation writes an
  `inventory_movements` row (ref `manual` or `purchase`) inside the same
  locked transaction as the stock update.
- Cylinder status changes validate against the registry enum; damaged /
  retired require a note; serials stay unique (`uq_cylinders_serial`) and
  searchable by serial/status/size.
- Purchases require a real supplier and ≥ 1 valid line; totals are
  recomputed server-side; only `ordered` purchases can be received
  (linked tracked lines increment stock) or cancelled; suppliers with
  purchase records cannot be deleted (RESTRICT mirrored in the guard).
- Granular permission checks on every POST action, not just page entry.

## Evidence

- `php tests/foundation-check.php`: **226/226**, `php -l` clean on 69 files.
- E2E `oyejo_p14` (inventory_manager, support, view-only role, seeded
  cylinders/products): adjust 20→30 with movement `Adjustment 20 → 30`
  logged; reason/range/untracked rejections; addition 30→40, deduction
  →35, over-deduction blocked, transfer_out →32, bogus movement rejected;
  serial search hit; damaged-without-note and unknown-status rejected;
  supplier add/edit/validation; empty purchase rejected; 2-line purchase
  total 1102500 (ordered); receive added 50 units (32→82) with
  `addition/purchase` movement; double-receive, received-cancel,
  supplier-with-purchases delete all blocked; clean supplier deleted;
  history shows the reason text; valuation renders; 12 inventory audits;
  support → 403 on both desks; view-only user reads desk but both
  adjust/transfer POSTs denied; purchase numbers unique (2/2);
  **0 PHP errors**.
- E2E `oyejo_p14b` (corrected param): damaged status change lands
  `damaged` + note `valve dent` with 1 audit row; 0 errors.
- Two real app bugs found and fixed during the gate: wrong RBAC helper
  (`rbac_has_permission` → `has_permission`) and wrong CSRF helper
  (`csrf_validate` → `csrf_verify`); repo-wide grep confirms zero
  remaining occurrences.
- Scratch DBs/users, `.env`, and `install.lock` removed after each run.

## Result

Phase 14 complete — no known issues. Approved under autopilot authority.
