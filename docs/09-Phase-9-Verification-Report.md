# Phase 9 Verification Report — cart, checkout, delivery fees, orders (autopilot approved)

Date: 2026-09-10. DB: MariaDB 11.8.6 (`oyejo_p9`, dropped after run). Commit: see log.

## What was built
- `includes/cart.php`: session cart (id→qty only), live price/stock revalidation with self-healing,
  server-side totals, coupon validation (window/limit/min/cap), zone fees + free-above threshold,
  method↔toggle map, customer-row provisioning, transactional `cart_place_order` (locked stock, race-safe
  coupon consume, address store, wallet debit, unique order numbers with collision retry, items + history),
  `order_cancel` (pending/confirmed only; restock, coupon release, wallet refund with linked ledger rows).
- `cart.php` (add/update/remove/clear), `checkout.php` (login + toggle-gated guest with account creation,
  saved/new address, zones, slots, coupons, 4 methods), `customer/orders.php` (list, confirmation/detail,
  cancel), product-page add-to-cart, header cart count, 2 demo coupons, cart/checkout CSS.

## Gate results (all green, 0 server errors)
- File checker: **143/143**, `php -l` clean on all 47 PHP files.
- Cart: add/update capped by live stock; out-of-stock, inactive, and over-stock adds rejected with
  exact messages; subtotal math exact (23,000 → 11,500).
- COD + WELCOME10: 1150000 − 115000 + 100000 = 1135000 verified in DB and UI; stock 40→39, coupon
  used 0→1, address stored as default; order number `OY-20260910-TVA84` (3/3 unique at close).
- Cancel: status cancelled, stock restored, coupon released; re-cancel rejected ("no longer be cancelled").
- Wallet: 2× refill = 2300000 with fee 0 (free-above threshold proven); paid, balance 5000000→2700000,
  payment txn linked (order/1); cancel → refunded, balance restored, 1 linked refund txn.
- Coupons: bogus, expired, and below-minimum codes rejected, no orders created.
- Guest: account created (pending), verification mail logged, order placed, session confirmation works.
- Toggles: COD-off blocks with full cart (no order); ordering-off → cart 403; re-enable restores.
- Isolation: user2 gets 404 on user1's order. Cleanup: test DB + user dropped, secrets removed.

## Bugs the gate caught and fixed
1. APP BUG: wallet payment linked its order via UPDATE on a `completed` ledger row → immutable-ledger
   trigger rolled the order back (order id consumed, no order). Fixed: order inserted first, wallet entry
   carries `related_type/id` at INSERT; debit flips the order to paid. Focused retest green.
2. Test-script: login field is `identifier` (email/phone), not `email`.
3. Test-script: CSRF tokens must come from the page-head meta tag — empty-cart pages render no form.

## Autopilot decision
Phase 9 approved; no carry-over issues. Online payments recorded as unpaid stub (gateway: Phase 17);
stock movement audit rows deferred to Phase 14 (inventory owner). Next: Phase 10 (customer dashboard).
