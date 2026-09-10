# Phase 20 verification report — Spin-to-win campaigns and rewards

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered (SP-01…SP-13)

- `includes/spin.php` — spin engine: campaign CRUD (slug unique, required
  start/end window, min-order volume, daily/weekly/campaign period rule,
  per-user total cap), prize CRUD (wallet_credit / promo_code / discount /
  free_delivery / none; weights; max-wins with wins_count floor), weighted
  `random_int()` draw inside a `FOR UPDATE` transaction with a unique
  (campaign, customer, period) key as the backstop, reward fulfilment
  (instant wallet credits via the hardened ledger, shared promo codes,
  auto-minted single-use `SPN-…` coupons for discount/free-delivery),
  deferred-claim retries (`spin_claim`), expiry sweep (`spin_expire_due`),
  manual reversal (`spin_reverse`: wallet reversal entry, minted-coupon
  deactivation), history and reports.
- `database/schema.sql` — `spins.reward_code`, `spins.expires_at`
  (= campaign end) + expiry index.
- `customer/spin.php` (`spin_to_win` toggle) — live campaigns with per-user
  eligibility verdicts, one-click spin, reward-code reveal, spin history
  with claim buttons for deferred wallet rewards.
- `admin/spin.php` (`spin.manage`) — tabbed desk: campaigns, per-campaign
  prizes, filterable spin ledger with reversal + expire-now controls,
  reports (by status, by campaign, wallet paid out).
- Dasboard + console links (toggle-/perm-gated).

## Rules enforced (server-side)

- The browser never decides the prize: `random_int()` draw over eligible
  prizes only, inside a locked transaction (SP-13).
- One-spin-per-period enforced twice: pre-check plus unique DB key
  (SP-07); windows, eligibility, min-order volume, per-user caps and
  max-wins all re-checked at play time (SP-05/SP-06/SP-08/SP-04).
- Campaigns/prizes with history cannot be deleted (deactivate instead).
- Only credited rewards reversible; only pending wallet rewards claimable;
  expired rewards unclaimable; all mutations audit-logged (`spin.*`).

## Evidence (E2E `oyejo_p20`, 45/45 assertions green)

- T1 campaign/prize CRUD, wallet win → +₦500.00 ledger credit with
  `SPNCR-…` ref, second daily spin blocked.
- T2 discount win → minted single-use 10% coupon; weekly repeat blocked;
  ₦50,000 min-order gate holds against ₦12,500 volume.
- T3 max-wins=1 exhausts to "No prizes available"; total cap, inactive
  and future campaigns all blocked.
- T4 frozen-wallet spin defers to pending → claim credits after unfreeze;
  backdated pending reward expires via sweep and cannot be claimed.
- T5 wallet reversal removes ₦500.00 via `reversal` entry; coupon
  reversal deactivates the minted code.
- T6 manager/customer 403, marketing manager admitted; T7 five prize/
  delete validations; T8 toggle 403/200, history, links, reports;
  T9 zero PHP fatals, ≥15 spin audits.
- `php tests/foundation-check.php`: 336/336 (4 new file checks + 10 new
  content checks).

## Issues found and fixed (before approval)

1. Environment: the sandbox lost PHP/MySQL between turns; reinstalled
   PHP 8.4 + MariaDB via apt, plus the missing `php-mbstring` extension
   the app requires (first run failed 0/45 on `mb_strtolower`).
2. Test-script bug: an `INSERT … WHERE` pattern and a duplicate curl
   date parameter; both corrected in-script.
3. Test-script bug: freezing a not-yet-created wallet was a no-op, so
   the pending-claim path never triggered; the script now creates the
   wallet row before freezing.

No known issues outstanding. Approved for commit under autopilot authority.
