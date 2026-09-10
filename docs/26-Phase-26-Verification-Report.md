# Phase 26 verification report — Add-on and future-feature architecture

Date: 2026-09-11 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered

- `includes/addons.php` engine: manifest scan/validation, registration
  + version refresh (AO-01/AO-02), dependency checks against platform,
  PHP and other add-ons (AO-08), install running migrations/toggles/
  permissions/settings (AO-03/AO-04/AO-06/AO-07), enable/disable with
  dep re-check (AO-12), update with pending migrations (AO-10),
  per-add-on activity log (AO-11), console menus (AO-05).
- Install/update status surfaced on the rebuilt `admin/addons.php` desk
  (AO-09/AO-10): manifest vs installed versions, update badges, pending
  migrations, missing-files warnings, dependency results, migration
  ledger, activity log, settings shortcut.
- `admin/addon.php` router: serves only manifest-declared pages of
  enabled add-ons inside the core layout, with system-toggle, status,
  permission and path-containment checks.
- `addons.installed_version` + `addon_logs.actor_id` columns and new
  `addon_migrations` ledger (64 tables).
- `OYEJO_VERSION` bumped 0.6.0 → 0.26.0 (platform version manifests
  declare against; shown in footer, stamped on backups).
- Add-on settings merge into Admin → Settings as `addon_<slug>` groups
  (core saver reused); add-on toggles appear under Feature toggles;
  add-on permissions appear under Roles (group `addon:<slug>`), granted
  to Super Admin + Admin at install.
- `addons/` blocked from direct web access (root rule widened +
  `addons/.htaccess`).
- Reference `example` add-on (permission, toggle, setting, page,
  migration) replaces the `_example` skeleton; 11 planned add-ons ship
  as stub-ready manifests + READMEs (AO-13): loyalty-points,
  gas-subscriptions, corporate-accounts, multi-branch, franchise,
  accounting, whatsapp-automation, route-optimization,
  wallet-withdrawals, multi-language, multi-currency.
- `docs/ADDON-DEVELOPMENT.md` developer guide (Z-33) and rewritten
  `addons/README.md`.

## Rules enforced (server-side)

- Manifests are validated (slug/folder match, semver, typed lists);
  invalid manifests are reported, never half-registered.
- Dependencies fail closed; enable re-checks; illegal transitions
  rejected; undeclared pages unreachable; traversal slugs rejected.
- Every scan/install/enable/disable/update writes an `addon_logs` entry
  and an audit row with actor.

## Evidence (E2E `oyejo_p26`, fresh DB, 43/43 green)

- CLI 15/15: 12-manifest scan, idempotent sync, dep checks fail closed
  (platform/php/missing/not-enabled), constraint parser, SQL splitter
  (quotes/comments), full install (migration + `example_notes` table,
  toggle, permission + 2 role grants, setting, logs + audit), install
  guards, status flow + menus + illegal transitions, version refresh,
  update bump + idempotence, migration ledger, settings merge + save,
  resolver gating, disabled-unreachable, slug validation.
- Static 3/3: 11 stubs, example page/migration, htaccess blocks.
- HTTP 25/25: desk list/detail, stub install + enable persisted,
  update badge + update, scan re-register, console menu shown/hidden by
  permission, router 200 with live setting + toggle, router 403/403/404/
  404 gates, disable hides + re-enable restores, settings save + toggle
  flip reflected, roles desk lists permission, manager/customer 403.
- Server log scanned: no PHP warnings/notices.
- `php tests/foundation-check.php`: 434/434.

## Issues found and fixed (before approval)

1. Install used a `permissions.label` column that does not exist
   (column is `name`) — fixed, install green.
2. `OYEJO_VERSION` was already defined (0.6.0, Phase 6) — removed the
   duplicate and bumped the platform version to 0.26.0.

No known issues outstanding. Approved for commit under autopilot authority.
