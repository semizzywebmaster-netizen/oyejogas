# Phase 13 verification report — Cylinder pickup, return & exchange

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered

- `includes/pickups.php` — `pickup_types()` (pickup_only/exchange/return),
  `pickup_request()`, `pickup_schedule()`, `pickup_collect()`,
  `pickup_complete()`, `pickup_cancel()`, `pickup_get()`,
  `pickup_for_customer()`, `cylinders_held()`, `pickup_serial_ok()`;
  numbers `PCK-YYYYMMDD-XXXXX` with retry on collision.
- `customer/pickups.php` — booking form (gated behind saved address),
  My-cylinders hold list, history, detail + serial manifest, customer cancel.
- `admin/pickups.php` — `pickups.manage` desk: status filter, scheduling,
  per-unit collection form, completion, cancellation.
- Nav: customer header Pickup link + dashboard Pickups shortcut.
- Guards: booking needs `cylinder_pickups` toggle; exchange additionally
  needs `cylinder_exchange`; staff desk needs `pickups.manage`
  (`pickups.update` covers schedule/collect/complete).

## Rules enforced (server-side)

- Request: type/size/qty (1–20) valid, owned address, active slot, date ≥
  today; serials optional, format-checked, count ≤ qty, stored in notes.
- Deposit snapshot = size deposit × qty for exchange only (charging lands
  in Phase 17); pickup_only/return deposit 0.
- Flow requested → scheduled (active slot + active driver) → collected →
  completed; customer cancels requested/scheduled, staff cancels any open
  pickup; terminal states immutable.
- Collection (transaction): collected units upsert the cylinder registry
  (damaged → `damaged`, else `awaiting_refill`, holder cleared); delivered
  units must already be full company stock of the requested size and move
  into the customer's hold; every unit written to `pickup_cylinders`.
- Customer isolation (`pickup_for_customer`), 404/403 handling, CSRF +
  audit + inbox notification on every transition.

## Evidence

- `php tests/foundation-check.php`: **213/213**, `php -l` clean on 66 files.
- E2E `oyejo_p13` (seeded admin/driver, 4 company cylinders, 2 customers):
  - pickup_only 2×12.5kg booked (302, deposit 0, serials in notes);
    exchange booked with deposit 400000; return booked.
  - Rejected: bogus type, bad serial, serials > qty, past date,
    exchange with toggle off; count stayed 3.
  - Schedule rejected for past date and driver 0, accepted with slot +
    active driver; duplicate-serial collection rejected.
  - P1 collected (2 units → `awaiting_refill`); exchange rejects for
    unknown serial, wrong size (6kg on 12.5kg), non-full stock, then
    collects (CO-FULL-01 full, held by customer; CUST-OLD-1 awaiting).
  - Damaged unit → `damaged`; P1 completed; customer cancels requested
    pickup; completed-cancel rejected; staff cancels collected pickup.
  - Inbox contains requested/scheduled/collected/completed/cancelled
    subjects; userB view → 404; toggle off → 403; customer on desk → 403;
    pickup numbers unique (4/4); **0 PHP errors**.
- E2E `oyejo_p13b` (address-gating branch): no address → "Add an address"
  prompt; with address → all three type options render; 0 errors.
- Scratch DBs/users, `.env`, and `install.lock` removed after each run.

## Result

Phase 13 complete — no known issues. Approved under autopilot authority.
