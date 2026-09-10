# Oyejo Gas Platform

Single-folder PHP + MySQL LPG e-commerce and gas-service platform.

> Developed in **28 verified phases**. Each phase is built, checked for missing
> files, tested, corrected, and approved before the next phase begins.
> Phases 6+ run on **autopilot**: verified + committed per phase, reported here.

## Project location

Everything lives in this one folder (`oyejo-gas/`) and one GitHub repository.
The folder root **is** the web root (upload its contents to `public_html`).

```text
oyejo-gas/
├── index.php  about.php  contact.php  faq.php  terms.php  privacy.php
├── admin/  customer/  driver/  api/  errors/  install/
├── config/  includes/  assets/  uploads/  database/  storage/
├── addons/  cron/  pwa/  tests/  docs/
├── .htaccess  .env.example  .gitignore
```

## Current status

| Phase | Title | Status |
|-------|-------|--------|
| 1–6 | Foundation → RBAC (all verified) | ✅ Approved |
| 7 | Public website and responsive design | 🟡 In progress (autopilot) |
| 8–28 | See `docs/00-PROJECT-PLAN-28-PHASES.md` | ⬜ Not started |

**New sites:** open the homepage once and the one-click installer
(see `docs/04-INSTALLATION-GUIDE.md`) sets up database, seeds and admin.

## Phase workflow (every phase, incl. autopilot)

1. **Build** — create the phase's files/features
2. **Missing-file check** — verify every expected file exists
3. **Test** — run syntax checks + functional checks
4. **Correct** — fix all known issues found
5.. **Approve/commit** — owner approval (phases 1–5) or autopilot sign-off (6+)

No phase is marked complete with known issues outstanding.

## Key constraints (locked)

- Single website folder, single GitHub repo
- PHP + MySQL, cPanel-compatible (no root-only dependencies)
- Payment/WhatsApp credentials stored outside publicly accessible logic
  (environment file / protected config — never hard-coded)
- Feature toggles enforced **server-side**, not only hidden in the browser
- Spin-to-win results generated **server-side** with secure randomness —
  the browser never determines the prize
- Wallet uses server-side calculations, immutable ledger, duplicate-transaction
  prevention, fraud checks, and audit logs
- Final deliverable: `oyejo-gas.zip` per Phase 28

## Quick start (local)

```bash
cd oyejo-gas
php -S localhost:8000   # then open http://localhost:8000/install/
php tests/foundation-check.php   # exit 0 = project files intact
```

## Documents

- `docs/00-PROJECT-PLAN-28-PHASES.md` — the 28-phase tracker
- `docs/01-MASTER-REQUIREMENTS-CHECKLIST.md` — **master checklist for final testing**
- `docs/02-FOUNDATION-STRUCTURE.md` — folder map, conventions, security model
- `docs/03-DATABASE-DICTIONARY.md` — 60-table catalogue + conventions
- `docs/04-INSTALLATION-GUIDE.md` — install, reinstall, troubleshooting
- `docs/05-Phase-5-Verification-Report.md` … `docs/28-*` — per-phase evidence
