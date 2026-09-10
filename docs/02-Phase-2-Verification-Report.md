# Phase 2 Verification Report — Single-Folder Project Foundation

**Date:** 2026-09-10
**Phase:** 2 of 28
**Result:** ✅ BUILT, TESTED, CORRECTED — awaiting owner approval

## 1. What was built (54 files)

- **PHP app:** `index.php`, `config/` (env loader, constants, PDO),
  `includes/` (bootstrap, helpers, header/footer shell)
- **Portals:** `admin/`, `customer/`, `driver/` stubs + `api/` JSON stub
- **Errors:** standalone `errors/404|403|500.php` + `install/` placeholder
- **Assets:** `assets/css/style.css` (responsive base), `assets/js/app.js`
  (nav, alerts, offline banner, CSRF fetch helper), `assets/images/logo.svg`
- **MySQL:** `database/schema.sql`, `seeds.sql` placeholders + `migrations/`
  conventions (real schema in Phase 3)
- **Protected dirs:** `uploads/` (scripts cannot execute), `storage/logs|backups|cache`
  (no web access), `config/`, `database/`, `tests/`, `docs/`, `cron/` blocked
- **Add-ons:** `addons/` skeleton + `_example/addon.json` manifest
- **Skeletons:** `cron/` (CLI-only), `pwa/` (Phase 27), `tests/foundation-check.php`
- **Docs:** `docs/02-FOUNDATION-STRUCTURE.md` (map, conventions, security model)

Checklist coverage: **F-01 … F-14** — PHP files, MySQL files, CSS, JS, images,
uploads, admin/customer/driver pages, config, logs, backups, add-on structure,
one folder / one repo.

## 2. Missing-file check — 69/69 PASS (`php tests/foundation-check.php`, exit 0)

- 55 expected files present, 13 protection/content assertions pass
  (ErrorDocument, folder blocks, security headers, uploads engine-off,
  storage deny, OYEJO_BOOT/CSRF/toggle guards, .env keys, CSS/JS markers,
  valid add-on manifest, PATH_INFO guard)
- `php -l` clean on all 24 PHP files (PHP 8.4; code targets >= 7.4)

## 3. Live smoke test (PHP server + curl) — all PASS, zero PHP errors

| URL | Expected | Got |
|-----|----------|-----|
| `/`, `/admin/`, `/customer/`, `/driver/`, `/install/` | 200 | 200 |
| `/api/` | 404 JSON | 404 JSON |
| `/config/*.php`, `/includes/*.php`, `/uploads/`, `/storage/*`, `/database/`, `/tests/`, `/cron/`, `/addons/` | 403 | 403 |
| `/nonexistent-page`, `/index.php/xyz`, `/admin/nope`, `/shop` | 404 page | 404 page |

## 4. Issue found and corrected

- **Found:** unknown single-segment URLs (e.g. `/nonexistent-page`) rendered
  the homepage (200) via the dev server's PATH_INFO fallback to `index.php`
  (same happens on Apache for `/index.php/xyz`).
- **Fix:** new `reject_path_info()` helper in `includes/functions.php`, called
  by all four entry pages; serves the 404 page for any PATH_INFO URL.
- **Retest:** all five unknown-URL cases now return the 404 page; valid pages
  unaffected; checker extended to 69 assertions, all passing.

## 5. Notes

- PHP was installed in the sandbox (`php-cli` 8.4) to run `php -l` and the
  smoke test; the app itself needs no server-side tooling beyond PHP + MySQL.
- On Apache/cPanel, `.htaccess` adds ErrorDocuments, folder blocks and
  security headers (verified by content assertion; live Apache test happens
  at deployment, Phase 28).

## 6. Approval request

Please reply **"Phase 2 approved — proceed to Phase 3"** to begin the
**database architecture** (full MySQL schema with keys, indexes, constraints).
