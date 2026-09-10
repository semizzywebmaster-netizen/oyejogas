# Phase 23 verification report — Feature control & website settings

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered (25 toggles, server-side enforced)

- `oyejo_maintenance_gate()` in `includes/bootstrap.php` — when
  `maintenance_mode` is ON every web request gets `errors/503.php`
  (503 + `Retry-After`) except CLI/cron, `/install/*`, `/admin/*`,
  the login page and static assets; logged-in staff
  (`portal.admin`) bypass everywhere. Header banner retained.
- Toggle gaps closed: homepage announcements/banners/sections/
  campaigns gated on `marketing_campaigns` (`index.php`); add-on
  desk shows a disabled notice and `adm_addon_status` transitions
  are refused while `addons` is off; `push_notifications` mapped
  in `notify_channel_toggles` (send skips, worker pauses rows —
  re-enable resumes, nothing dropped).
- Enforcement audit: all 25 toggles mapped to server-side gates —
  payment toggles via the `cart_payment_methods()` toggle map +
  `cart_place_order` rejection, notification toggles via the
  notify send/deliver checks, the rest via page/placement gates.
  `pwa_installation` is reserved (no PWA runtime until Phase 27;
  row exists, default ON).
- Runtime `setting($key, $default)` reader (per-request cached,
  fail-soft for the installer) + live wirings: `site_name`/
  `tagline` in header title/brand and footer; `currency_symbol`
  → `format_money` default; `orders_prefix` → `cart_order_number`
  (seed `ORD-`, invalid values fall back to `OY-`);
  `notif_from_name/email` → `send_mail` From fallback when the
  env keys are absent. `currency`/`timezone`/`admin_alert_email`
  settings remain forward-looking (effective values from env;
  no alert consumer yet) — validated CRUD, documented.
- Settings desk (Phase 15) kept: group validation (email,
  currency, timezone, money, min≤max, counts), permission gates
  (`settings.view/edit`, `toggles.manage`), CSRF, `admin.*` audits.

## Rules enforced (server-side)

- Maintenance lock cannot lock staff out: admin paths + staff
  bypass + login stay reachable; cron/CLI unaffected.
- Disabled features fail closed (503/403/404/skipped sends), never
  trust the browser; every toggle flip and settings save is
  audited with actor + old/new values.

## Evidence (E2E `oyejo_p23`, fresh DB, 60/60 green)

- CLI 9/9: 25 toggles seeded (only maintenance off), 25 code
  defaults, `setting()` reads + defaults, money symbol default/
  explicit, `ORD-` order numbers, toggle persist + audit +
  unknown-key rejection, settings validation matrix, valid save.
- Probes 8/8 (fresh processes): custom currency symbol live,
  invalid prefix → `OY-`, custom prefix live, flipped toggle
  effective, push off → send 0 + worker skips, push on → send 1
  + worker delivers.
- Static map 24/24: every toggle except reserved `pwa_installation`
  gates code (payment/notification maps included).
- HTTP 19/19: announcement + site name on homepage, marketing
  off hides / on restores, maintenance 503 (+page text, guest
  portal 503, login 200, admin exempt, staff bypass, admin works,
  cron exempt, recovery), desk site save → live header, customer
  desk 403, add-ons disabled notice + blocked transition, desk
  rejects bad email.
- `php tests/foundation-check.php`: 377/377 (2 new file checks +
  10 new content checks), 4 consecutive green runs. (The first run
  showed one failure that cleared with no tree changes — attributed
  to a transient `php -l` spawn flake under load; not reproduced
  since.)

## Issues found and fixed (before approval)

1. `index.php` ternary edit left an incomplete expression (parse
   error) — replaced with explicit `if ($mkt)` blocks covering
   announcements, banners, sections and campaigns.
2. Harness: add-on desk has no CSRF token when zero rows exist —
   test now reuses the session token (tokens are per-session);
   P4b rerun collided with stale `PUSH23` rows — clean-room run
   is 60/60.

No known issues outstanding. Approved for commit under autopilot authority.
