# Phase 21 verification report — Referral codes, rewards, anti-fraud

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered (RR-01…RR-13)

- `includes/referrals.php` — referral engine: `ref_capture` at
  registration (unknown codes ignored silently, first referrer wins),
  self-referral rejection, velocity flags (N referrals/24h), duplicate
  phone flags via `customer_phones`, `ref_qualify_due` (delivered/
  completed volume ≥ min purchase → both sides paid), instant wallet
  credits with deferred-claim fallback (`ref_claim`), expiry sweep
  (`ref_expire_due`), staff reversal (`ref_reverse`), manual flag/unflag,
  settings (amounts, min purchase, expiry days, velocity), history and
  reports. Both sides notified on payout.
- `database/schema.sql` — `referrals.flag_note`; `seeds.sql` gains
  `referral_min_purchase_minor` (₦2,000), `referral_reward_expiry_days`
  (30), `referral_velocity_24h` (5).
- `customer/register.php` — `?ref=` prefill + optional code field;
  capture runs after account creation, gated on the `referrals` toggle.
- `customer/referrals.php` — link + code, people referred, reward ledger
  with claim buttons; qualification/expiry sweeps run on load.
- `admin/referrals.php` (`referrals.manage`) — tabbed desk: referral
  ledger with status filter, flag/unflag, qualify-now + expire-now,
  reward ledger with reversals, settings form.
- Dashboard referral card now links the new page; console gains the desk.

## Rules enforced (server-side)

- One referrer per referred account (unique key + first-wins); own codes
  rejected; flagged referrals never qualify or pay.
- Qualification needs real delivered/completed volume ≥ min purchase;
  rewards are ledger credits with expiry and audit trail, reversible only
  from `credited`, claimable only from `pending`.
- All staff actions need `referrals.manage`; CSRF everywhere; all
  mutations audit-logged (`referral.*`).

## Evidence (E2E `oyejo_p21`, 37/37 assertions green)

- T1 `?ref=` capture → pending row; referrer page shows link + friend.
- T2 no orders → 0 qualify; ₦3,000 delivered → both paid (₦500/₦250),
  2 notifies.
- T3 ₦1,000 stays pending under the ₦2,000 gate.
- T4 self-use rejected, unknown code ignored (CLI harness).
- T5 velocity=2 → third referral flagged with note; desk filter shows it.
- T6 duplicate phone flagged. T7 manual flag/unflag; reversal returns
  ₦500.00 via `reversal` entry.
- T8 frozen-wallet reward pends → claim credits; backdated pending
  reward expires via sweep.
- T9 manager/customer 403, toggle 403 + capture skipped, links; T10
  settings save; T11 zero fatals, ≥10 audits.
- `php tests/foundation-check.php`: 350/350 (4 new file checks + 10 new
  content checks).

## Issues found and fixed (before approval)

1. Test-script sequencing: T8 subjects were (correctly) velocity-flagged
   in T5, so no rewards existed to claim; the script now clears them via
   the staff unflag action first — which also exercises that path.
2. Removed a dead CLI probe block from the E2E script before the run.

No known issues outstanding. Approved for commit under autopilot authority.
