# Oyejo Gas Platform

Single-folder PHP + MySQL LPG e-commerce and gas-service platform.

> Developed in **28 verified phases**. Each phase is built, checked for missing
> files, tested, corrected, and approved before the next phase begins.

## Project location

Everything lives in this one folder (`oyejo-gas/`) and one GitHub repository.
The folder root **is** the web root (upload its contents to `public_html`).

```text
oyejo-gas/
├── index.php  admin/  customer/  driver/  api/  errors/  install/
├── config/  includes/  assets/  uploads/  database/  storage/
├── addons/  cron/  pwa/  tests/  docs/
├── .htaccess  .env.example  .gitignore
```

## Current status

| Phase | Title | Status |
|-------|-------|--------|
| 1 | Requirements and master feature checklist | ✅ Approved |
| 2 | Single-folder project foundation | ✅ READY FOR YOUR APPROVAL |
| 3–28 | See `docs/00-PROJECT-PLAN-28-PHASES.md` | ⬜ Not started |

## Phase workflow (every phase)

1. **Build** — create the phase's files/features
2. **Missing-file check** — verify every expected file exists
3. **Test** — run syntax checks + functional checks
4. **Correct** — fix all known issues found
5. **Approve** — you approve before the next phase begins

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
cp .env.example .env
php -S localhost:8000
php tests/foundation-check.php   # exit 0 = foundation intact
```

## Documents

- `docs/00-PROJECT-PLAN-28-PHASES.md` — the 28-phase tracker
- `docs/01-MASTER-REQUIREMENTS-CHECKLIST.md` — **master checklist for final testing**
- `docs/02-FOUNDATION-STRUCTURE.md` — folder map, conventions, security model
- `docs/02-Phase-2-Verification-Report.md` — Phase 2 test evidence
