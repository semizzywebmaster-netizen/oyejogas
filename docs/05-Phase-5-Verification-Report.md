# Phase 5 Verification Report — Authentication and Account Registration

**Date:** 2026-09-10
**Phase:** 5 of 28
**Result:** ✅ BUILT, TESTED, CORRECTED — awaiting owner approval

## 1. What was built

- `includes/auth.php` — registration, login, logout, reset, email/phone
  verification, session expiry, throttling, safe redirects
- `includes/mailer.php` — logged mailbox (`mail.log`/`sms.log`) + `MAIL_REAL`
  gate; full providers arrive in Phase 22
- `customer/` pages — register, login, logout, forgot/reset password,
  verify-email (+resend), verify-phone; adaptive portal home + nav
- `config.php`/`installer`/`.env.example` — new per-install `APP_KEY` secret
  for signing links (installer generates 32 random bytes)
- `bootstrap.php` — loads auth, ticks sessions every request, `require_login()`
  now routes to `login.php?next=…`

Checklist coverage: **A-01 … A-12** (all twelve auth requirements).

## 2. File check + lint — 99/99 PASS, `php -l` clean on 34 files

## 3. Session unit tests (CLI) — 5/5 PASS, zero warnings

Idle expiry, device-binding rejection, absolute expiry, fresh-session pass,
`safe_next()` (evil URL → default, relative → allowed).

## 4. Auth E2E (live HTTP + MariaDB) — all PASS, 0 server errors

| # | Test | Result |
|---|------|--------|
| 1 | Register valid user | "Check your email"; DB: `pending`, bcrypt hash, customer + referral codes |
| 2 | Duplicate email | Rejected with friendly message |
| 3 | Login while pending | Blocked: "verify your email" |
| 4 | Email link (from `mail.log`) | Account activated; reuse → "already verified"; tampered → invalid |
| 5 | 6 wrong passwords | 5× "Incorrect…" then "Too many attempts"; user locked, attempts logged |
| 6 | Correct pw during lockout | Still blocked |
| 7 | Login after clearing | 302 → `/customer/`; portal shows "Welcome, Test User" |
| 8 | `?next=/driver/` | Allowed → `/driver/`; `?next=https://evil.com` → default landing (no open redirect) |
| 9 | Reset flow | Silent request → form → "password was changed" → reuse blocked → new-pw login works |
| 10 | Phone verify | Code sent (cooldown enforced) → wrong code rejected → correct code verified in DB |
| 11 | Logout | 302 home; nav back to Login (session destroyed) |
| 12 | Suspended account | Blocked with clear suspension message |
| 13 | `customer_registration` OFF | Register answers 403 "currently disabled" |

## 5. Corrections during testing

1. **Throttle clock-skew bug (real):** the 15-minute failure window was
   computed in PHP time but compared to DB-stamped rows — with PHP and MySQL
   in different timezones the window excluded fresh rows and throttling never
   fired. Fixed by computing the window in SQL (`NOW() - INTERVAL`), which is
   immune to skew. Re-verified: 6th attempt now blocks.
2. **Missing pre-flight gate (hardening):** auth uses `mb_*` functions, so
   `mbstring` joined the installer's pre-flight checks (now 10).
3. **Test-script bugs (no app impact):** reset-form CSRF was fetched without
   the POST session's cookie jar (→ token mismatch); one header grep stopped
   at the first match; CLI test echoed before regenerating sessions. All
   fixed; full suite re-run green.

## 6. Design notes (locked)

- Email links are stateless HMAC-signed tokens (no DB row, 24h expiry);
  reset links are single-use DB tokens (1h, hashed at rest).
- Phone codes are session-backed (cooldown/expiry/attempt caps) behind the
  `sms_send()` provider hook — documented upgrade path to a DB table.
- No account enumeration: reset/resend responses are identical either way.
- Wallet auto-creation stays in Phase 11 (with backfill for these accounts).

## 7. Approval request

Please reply **"Phase 5 approved — proceed to Phase 6"** to build **roles,
permissions and access control** (12 roles, permission-level enforcement).
