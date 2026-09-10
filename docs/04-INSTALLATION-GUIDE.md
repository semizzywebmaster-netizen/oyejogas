# Oyejo Gas — Installation Guide (Phase 4)

## Requirements

- PHP >= 7.4 (8.x recommended) with `pdo_mysql`
- MySQL 5.7+ / MariaDB 10.2+ (empty database + a user with CREATE rights,
  or pre-create the database yourself)
- Writable `storage/` and `uploads/` folders

## Install in 3 steps (local or cPanel — identical)

1. **Upload** the `oyejo-gas/` contents to your web root (`public_html`).
2. **Open the site** — an uninstalled app redirects to `/install/`
   automatically (re-running it later is blocked once installed).
3. **Fill the wizard**: the installer runs pre-flight checks, writes `.env`,
   creates the database (optional), imports the 60-table schema with its
   triggers, seeds roles/permissions/toggles/settings/catalog data, creates
   your super-admin account, validates everything (12 checks), and writes
   `storage/install.lock`.

## What the installer does (checklist I-01 … I-11)

| # | Item | How |
|---|------|-----|
| I-01 | Database installer | `install/index.php` wizard + `install/installer.php` library |
| I-02 | Initial configuration | Site name/URL, debug flag written to `.env` |
| I-03 | Environment setup | `.env` generated from `.env.example`; secrets never in code |
| I-04 | Seed data | `database/seeds.sql` (24 statements) |
| I-05 | Default roles | 12 roles (super_admin … guest) |
| I-06 | Default permissions | 78 permissions mapped per role; only super_admin holds `system.super` + `roles.manage` |
| I-07 | Default admin setup | Super-admin created from YOUR input; bcrypt hash; no default password exists |
| I-08 | Initial categories | LPG Cylinders, Gas Refills, Cylinder Exchange, Accessories (+ 5 cylinder sizes) |
| I-09 | Initial settings | Site, contact, locale, order, wallet and referral defaults; 25 feature toggles; zones, slots, templates, FAQs |
| I-10 | Installation validation | 12 post-install checks shown in the browser (tables, seeds, admin, files) |
| I-11 | Reinstall protection | `storage/install.lock` → installer answers 403 "Already installed" |

## Technical notes

- The SQL importer (`inst_split_sql()`) honors `DELIMITER` blocks, quoted
  strings and comments — triggers import correctly through plain PDO.
- Uninstalled apps redirect every portal page to `/install/` (see
  `includes/bootstrap.php`); the installer itself is standalone so it can
  run before the database exists.
- The admin password is bcrypt-hashed and never written to `.env` or logs.

## After installing (live sites)

1. **Delete the `install/` folder** — the lock already blocks it, deletion
   removes it entirely (a site-health check reminds you in Phase 24).
2. Keep a backup of `.env` somewhere safe — never commit it to Git.
3. Set `APP_DEBUG=false` (the installer default) on live sites.

## Reinstall deliberately (dev only)

Delete `storage/install.lock`, empty the database
(`DROP DATABASE x; CREATE DATABASE x;`), reload `/install/`.

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Pre-flight FAIL on `pdo_mysql` | Enable/install the PHP MySQL extension |
| "Could not connect to MySQL" | Check host/port/user/pass; on cPanel use the full `cpuser_dbuser` names |
| "Could not create database" | Pre-create it in cPanel and untick "create database" |
| "…already exists. Empty the database and retry" | A previous attempt partially seeded — empty the DB, keep `.env`, retry |
| Redirect loop to `/install/` | `storage/` not writable so the lock could not be saved — fix permissions |
