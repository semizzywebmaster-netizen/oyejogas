# Phase 17 verification report — Payments, invoices, refunds, reconciliation

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered

- `includes/payments.php` — checkout finance hook (`PAY-…` payment rows +
  persistent `INV-…` invoices inside the cart transaction), transfer proof
  uploads, online gateway-ref confirmation, retries, staff verification
  (approve → order/invoice paid + notify; reject/fail → notify + retry),
  COD auto-verify on cash coverage, refund requests/decisions/completion
  with payment sync (`partially_refunded`/`refunded`), driver cash
  reconciliation, finance reports, env-only gateway config.
- `admin/finance.php` — `payments.view` desk: payment filters + verify/fail
  (`payments.verify`), refund queue + staff raises + approve/reject/
  complete (`refunds.manage`), invoice list, cash reconciliation queue,
  method/refund/cash/14-day-revenue reports.
- `customer/payments.php` — own payments with bank details for pending
  transfers, receipt upload, online "I have paid", retries, receipt links.
- `api/payments-callback.php` — HMAC-signed gateway webhook (secret from
  environment only; 503 when unconfigured, 403 on bad signature,
  idempotent replays).
- `customer/invoice.php` (rewritten) — persistent invoice numbers, receipt
  mode for paid orders, payment refs, refund history, customer refund form.
- Wiring edits: cart checkout hook + wallet-cancel payment/invoice sync,
  checkout redirect to My payments for non-wallet methods, COD hook in
  cash collection, `refund` added to wallet credit types, invoice void on
  staff cancel, Finance + Payments nav links.

## Rules enforced (server-side)

- Unique references/numbers for payments, invoices, refunds (retry loops).
- Only pending payments decidable; only paid orders refundable; only the
  still-refundable remainder; one pending refund per payment; refunds to
  wallet complete instantly with idempotent `RND-…` credits, bank/cash go
  approved → completed after manual payout.
- Gateway secrets never in repo/DB/web files (env only, PY-15).
- Granular `payments.verify` / `refunds.manage` checks on every POST.

## Evidence

- `php tests/foundation-check.php`: **274/274**, `php -l` clean on 82 files.
- E2E `oyejo_p17` (5 real checkouts: transfer/COD/online/transfer/COD):
  non-wallet checkouts 302 to My payments; payment+invoice rows created
  (pending/issued); bank details shown; non-image proof rejected, PNG
  accepted, receipt visible on desk; verify → paid/paid, double-decide
  blocked; partial then full wallet refunds → `partially_refunded` →
  `refunded`; duplicate request blocked; COD cash → auto-verified/paid;
  bank refund approve → complete; exhausted remainder blocked; online ref
  saved; webhook bad-sig 403, success verifies, replay idempotent; fail
  → retry → re-verify; receipt renders; reconcile + double-reconcile
  blocked; view-only denied on POST; customer → 403; refs/invoices/
  refunds unique (5/5, 5/5, 3/3); 16 audits; **0 PHP errors**.
- Two real app bugs found and fixed: finance desk missing the wallet
  library (`wallet_credit` undefined — wallet refunds now credit, txn
  type `refund`, notification sent) and invoices never voiding on cancel
  (sync added to both cancel paths; wallet cancels also flip the payment
  row to `refunded`).
- E2E `oyejo_p17b`/`oyejo_p17c`: wallet refund credits ₦700.00 with
  `refund` txn + inbox line; staff-cancel invoice `void`; customer
  wallet-cancel lands cancelled/refunded/void/refunded + ₦300.00 wallet
  restoration; over-refundable guard shows
  "Only ₦1,000.00 is still refundable."; 0 errors.
- Scratch DBs/users, `.env`, `install.lock`, test uploads removed.

## Result

Phase 17 complete — no known issues. Approved under autopilot authority.
