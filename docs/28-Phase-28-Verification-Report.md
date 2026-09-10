# Phase 28 verification report — Final deployment, verification, ZIP

Date: 2026-09-11 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered

- Final documentation set (ships inside the ZIP):
  `docs/05-CPANEL-DEPLOYMENT.md` (Z-29: upload/extract, DB wizard,
  installer, 4 cron lines, SSL/mail, post-deploy security checklist,
  release upgrades), `docs/06-BACKUP-RESTORE-GUIDE.md` (Z-30:
  scheduled + manual backups, typed-confirm restore with pre-restore
  snapshot, disaster recovery), `docs/07-ADMINISTRATOR-GUIDE.md`
  (Z-31: roles/staff, daily desks, marketing, support, settings/
  toggles/add-ons, troubleshooting table). Z-32 testing checklist
  (`01-MASTER-REQUIREMENTS-CHECKLIST.md`) and Z-33 add-on docs
  (`ADDON-DEVELOPMENT.md`) already shipped in earlier phases.
- `oyejo-gas.zip` built from git HEAD via `git archive`: 216 tracked
  files, single folder, no `.env`, no install lock, no dumps/logs
  (Z-22…Z-28 verified below).
- Checker manifest extended with the 3 guides + this report.

## Rules enforced (server-side)

- Refill/delivery/pickup lifecycles move through legal transitions
  only; illegal jumps rejected (`requested→completed`,
  `assigned→delivered` both refused, state unchanged).
- Refill `assigned` requires an active driver; delivery `delivered`
  requires receiver + handover note; failed needs a reason.
- Drivers can only touch deliveries assigned to them; customers can
  only view/cancel their own refills (cross-customer view = 404).
- Backup download/restore/delete need `backups.restore` (manager =
  403); restore demands typed-filename confirmation, verifies sha256
  and keeps a pre-restore snapshot first.
- Cron scripts are CLI-only (`cron/*.php` over HTTP = 403).

## Evidence (fresh DBs, 103/103 green + checker 458/458)

- P27 regression on `oyejo_p27`: CLI 11/11 + HTTP 52/52 (Z-08/09/10/
  11/16/17/19/20/21: auth, RBAC, checkout totals, wallet isolation,
  uploads, notifications, PWA, mobile, security suite).
- P28 part 1 on `oyejo_p28` (32/32): refill request→assigned→
  processing→ready→completed with driver + illegal-jump gates
  (Z-12), dispatch create→assign→out_for_delivery→delivered with
  proof-of-delivery (Z-14/Z-15), unassigned-driver denial, pickup
  request→schedule→collect→complete (Z-13), cross-customer 404s,
  portal 403s, backup run→file→gzip-download→restore+snapshot
  (Z-18), queued mail sent via worker + mail log growth, all 4 cron
  scripts exit 0 (Z-17).
- P28 part 2 (8/8): anon portals 302→login incl. `next` landing
  (Z-04), 88-link crawl with zero 404/500 (Z-03), 141 POST forms all
  carry CSRF (Z-07), 61 code-referenced tables all in schema
  (Z-02), 561 static includes resolve (Z-06).
- `php tests/foundation-check.php`: 458/458 (Z-01 missing files,
  Z-05 `php -l` on all PHP files).
- ZIP verification: `unzip -l` shows single-folder tree with 121
  PHP files, CSS/JS, `database/schema.sql` + `seeds.sql`, `install/`,
  `.env.example`, icons, manifest, SW; absent: `.env`,
  `install.lock`, `*.sql.gz`, `*.log`. Fresh extract redirects
  `/` → `/install/` (302, correct with no `.env`); installer
  requirements 10 PASS; full end-to-end install run green —
  65/65 tables, 12/12 roles, 80 permissions, 25/25 toggles,
  seeded catalogue, installed admin logs in and opens `/admin/`
  200.
- Server logs scanned after every run: no PHP warnings/notices/
  fatals.

## Issues found and fixed (before approval)

1. Sandbox `.git` rolled back to Phase 24 a third time (plus working-
   tree losses: add-on manifests, PWA icons, PWA asset JS). History
   restored from the verified `oyejo-gas-p27.bundle` (`bac97aa`);
   lost files checked out from the commit; P27 regression re-run
   63/63 to prove the recovered tree. Bundle re-created after the
   P28 commit.
2. REAL bug: installer validation hardcoded 60 tables while the
   schema ships 65 — every fresh install falsely reported
   "Validation failed" (install itself succeeded). Expected count is
   now derived from `database/schema.sql`; full installer run on the
   rebuilt ZIP passes all 12 validation rows.
3. Harness corrections only (no app bugs): refill `assigned` needs
   `driver_id`; delivery `delivered` needs `proof_note`; backup
   forms live under `?tab=backups`; refill/pickup tokens are on the
   `?view=` pages; restore params are `item_id`+`confirm`; Z-02
   scan excludes `ON DUPLICATE KEY UPDATE` column false-positives.

No known issues outstanding (Z-34). Approved for commit under autopilot authority.
