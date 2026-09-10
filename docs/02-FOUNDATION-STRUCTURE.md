# Oyejo Gas — Foundation Structure (Phase 2)

Single-folder, single-repo layout. The folder root **is** the web root
(upload its contents to `public_html` on cPanel). Requires PHP >= 7.4
(8.x recommended) + MySQL. No composer packages, no build step.

## Folder map

```text
oyejo-gas/
├── index.php            # Homepage (full site in Phase 7)
├── .htaccess            # Error pages, folder blocks, security headers
├── .env.example         # Copy to .env (NEVER commit a real .env)
├── .gitignore
├── config/              # config.php (env+constants), database.php (PDO)
├── includes/            # bootstrap.php, functions.php, header.php, footer.php
├── admin/               # Admin console (portal stub → Phase 15)
├── customer/            # Customer portal (stub → Phases 5, 10)
├── driver/              # Driver portal (stub → Phase 16)
├── api/                 # JSON stub → endpoints arrive with their phases
├── errors/              # Standalone 404/403/500 pages (→ Phase 25)
├── install/             # One-click installer: wizard + library (Phase 4)
├── assets/css|js|images|icons/
├── uploads/             # User uploads (PHP execution OFF, no indexing)
├── database/            # schema.sql, seeds.sql, migrations/ (→ Phases 3–4)
├── storage/logs|backups|cache/   # Private runtime (web access denied)
├── addons/              # Add-on skeleton + _example manifest (→ Phase 26)
├── cron/                # CLI-only jobs (→ Phases 22, 24)
├── pwa/                 # PWA helpers (manifest/SW/offline → Phase 27)
├── tests/               # foundation-check.php + later test scripts
└── docs/                # Requirements, plans, guides, reports
```

## Conventions (binding on all later phases)

1. **Entry pages** start with `require_once .../includes/bootstrap.php`, then
   set `$page_title`, include `header.php` / `footer.php`.
2. **Include files** start with the `OYEJO_BOOT` guard (403 on direct access).
   Folders carry `index.php` 403 guards as second-layer defense.
3. **Never trust the browser**: prices, wallet math, spin results, toggles and
   permissions are enforced server-side.
4. **Money = integer minor units** (kobo), formatted by `format_money()`.
5. **All DB access via PDO prepared statements** (`db()` helper).
6. **Mutating forms** carry `csrf_field()` and verify with `csrf_verify()`.
7. **Output** is escaped with `e()` unless intentionally raw HTML.
8. Use `url()` for absolute links, `asset()` for base-aware local paths so
   subdirectory installs keep working.

## Security model (foundation layer)

| Layer | Mechanism |
|-------|-----------|
| Web server | `.htaccess` blocks `config/`, `includes/`, `storage/`, `database/`, `tests/`, `docs/`, `cron/`; denies `.env`, `.sql`, `.log`, `.md`; `uploads/` cannot execute scripts |
| PHP | `OYEJO_BOOT` + `index.php` guards, secure session cookies, CSRF helpers |
| Secrets | `.env` only (see `.env.example`); nothing secret in code or docs |

## Run locally

```bash
cd oyejo-gas
cp .env.example .env
php -S localhost:8000
# open http://localhost:8000
```

Verify any time with `php tests/foundation-check.php` (exit 0 = all pass).

## Where later phases plug in

| Phase | Lands in |
|-------|----------|
| 3–4 schema/installer | `database/`, `install/` |
| 5–6 auth/RBAC | `includes/`, `customer/`, `admin/` |
| 7–10 public site/shop/account | `index.php`, `assets/`, new `shop/`, `customer/` |
| 11 wallet, 12 refill, 13 cylinders | new `wallet/`, `refill/`, `cylinders/` + `customer/` |
| 14–17 inventory/admin/logistics/payments | `admin/`, `driver/`, new `inventory/` |
| 18–19 support/marketing | new `support/`, `admin/`, CMS tables |
| 20–21 spin/referrals | new `rewards/` + `customer/` |
| 22 notifications | new `notifications/` + `cron/` |
| 23 toggles | `admin/` + `oyejo_feature()` wiring |
| 24 backups/health | `admin/`, `storage/backups/`, `cron/` |
| 25 errors | `errors/`, `includes/`, `storage/logs/` |
| 26 add-ons | `addons/` + loader |
| 27 PWA/security | `/manifest.webmanifest`, `/service-worker.js`, `/offline.php`, `assets/icons/` |
| 28 ship | `oyejo-gas.zip` + guides in `docs/` |
