# Phase 4 Verification Report — Installation and Seed System

**Date:** 2026-09-10
**Phase:** 4 of 28
**Result:** ✅ BUILT, TESTED, CORRECTED — awaiting owner approval

## 1. What was built

- `install/index.php` — wizard: pre-flight (9 checks) → site/DB/admin form →
  install → validation report (12 checks); 403 "Already installed" when locked
- `install/installer.php` — library: DELIMITER-aware SQL splitter, `.env`
  writer, schema/seed importer, bcrypt admin creation, validation suite
- `database/seeds.sql` — 12 roles, 78 permissions + per-role mapping, 25
  feature toggles, 13 settings, 4 categories, 5 cylinder sizes, 4 zones,
  3 slots, 6 notification templates, 4 FAQs, 3 homepage sections, baseline
- `includes/bootstrap.php` — uninstalled apps redirect to `/install/`
- `config/config.php` — hardened base-URL auto-detection (see §4)
- `docs/04-INSTALLATION-GUIDE.md` — local + cPanel instructions

## 2. File check + lint — 81/81 PASS, `php -l` clean on 25 files

## 3. End-to-end install test (live HTTP + MariaDB) — all PASS

| # | Test | Expected | Got |
|---|------|----------|-----|
| A | `/`, `/customer/`, `/driver/`, `/admin/` without lock | 302 → `…/install/` | 302 → exact URL on all four |
| B | `GET /install/` | 200, 9/9 pre-flight PASS, CSRF token | 200, 9 PASS, 0 FAIL, 64-char token |
| C | POST with wrong DB password | Friendly error, no fatal, no lock | "Could not connect to MySQL", 0 fatals, no lock |
| D | Real install POST | Complete + 12/12 validation | "Installation complete": schema 67 stmts, seeds 24 stmts, admin #1, 12 PASS / 0 FAIL |
| E | Database state | 60 tables, 12 roles, 78 perms, admin #1 | 60 / 12 / 78, bcrypt hash (60 chars), super_admin holds 78/78 perms |
| F | `.env` + lock | DB creds in `.env`, no admin password, lock written | ✅ all confirmed |
| G | `GET /install/` after lock | 403 "Already installed" | 403 ✅ reinstall blocked |
| H | Homepage with lock | 200 | 200 ✅ redirect cleared |

Server log: 0 PHP warnings/fatals. Workspace restored pristine afterwards
(scratch DB/user dropped, test `.env` + lock removed).

## 4. Corrections during testing

1. **Base-URL bug (real):** auto-detection used `dirname(SCRIPT_NAME)`, so
   `/customer/` computed the app root as `/customer` and redirected to
   `/customer/install/`. Rewrote detection to map `SCRIPT_FILENAME` against
   the app folder — now correct for every entry point and subdir installs.
   Re-verified on all four portals (§3A).
2. **Missing sandbox extension:** `pdo_mysql` was not installed, so pre-flight
   correctly hid the install form (9th check FAIL). Installed `php8.4-mysql`,
   re-ran: 9/9 PASS and full install green. The installer behaved exactly as
   designed under missing requirements.
3. **Test-script typo (cosmetic):** one ad-hoc verification query used
   `FROM toggles` instead of `feature_toggles`. Toggles were still verified
   (installer's own 12/12 validation asserts all 25); noted for accuracy.

## 5. Approval request

Please reply **"Phase 4 approved — proceed to Phase 5"** to build
**authentication and account registration** (registration, login/logout,
hashing, reset, verification, sessions, suspension, throttling, redirects).
