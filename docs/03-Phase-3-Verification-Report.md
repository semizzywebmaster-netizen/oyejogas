# Phase 3 Verification Report — Database Architecture

**Date:** 2026-09-10
**Phase:** 3 of 28
**Result:** ✅ BUILT, TESTED, CORRECTED — awaiting owner approval
**Test engine:** MariaDB 11.8 (MySQL-compatible), scratch DB `oyejo_verify`

## 1. What was built

- `database/schema.sql` — complete schema, 1,066 lines, imports clean
- `docs/03-DATABASE-DICTIONARY.md` — 60-table catalogue + conventions
- `tests/phase3-verify.sql` — reusable read-only verification queries
- Checklist coverage: **D-01 … D-35** (all required tables + integrity rules)

## 2. Structure verification — all PASS

| Check | Expected | Got |
|-------|----------|-----|
| Tables created | 60 | 60 |
| Foreign keys | 83 | 83 (all resolve; see FK report in `phase3-verify.sql`) |
| Unique constraints | 40 | 40 (emails, order/payment refs, serials, codes, spin periods…) |
| Triggers | 2 | `trg_wtxn_no_update`, `trg_wtxn_no_delete` |
| CHECK constraints | 1 | `chk_reviews_rating` (1–5) |
| FULLTEXT product search | present | `ft_products(name, description)` |
| Import errors | 0 | `IMPORT_OK` (×3 fresh imports) |

## 3. Enforcement tests — 16/16 PASS

| Test | Behaviour | Result |
|------|-----------|--------|
| T1 user + bad `role_id` | rejected, ERR 1452 | ✅ FK enforced |
| T2 UPDATE posted wallet row | rejected, ERR 1644 | ✅ immutable |
| T3 DELETE wallet row | rejected, ERR 1644 | ✅ no deletes |
| T4a insert `pending` wallet row | accepted | ✅ |
| T4b `pending` → `completed` | accepted | ✅ lifecycle works |
| T4c UPDATE newly-completed row | rejected, ERR 1644 | ✅ frozen after posting |
| T5 duplicate spin, same period | rejected, ERR 1062 | ✅ one-spin-per-period |
| T5b same user, next-day period | accepted | ✅ |
| T6 spin + bad campaign FK | rejected, ERR 1452 | ✅ FK enforced |
| T7 review rating 9 | rejected, ERR 4025 | ✅ CHECK enforced |
| T7b review rating 5 | accepted | ✅ |
| T8 duplicate email | rejected, ERR 1062 | ✅ unique enforced |
| T9 order delete → items | 1 item → 0 left | ✅ CASCADE |
| T10 delete role in use | rejected, ERR 1451 | ✅ RESTRICT |

## 4. Corrections during testing

1. **Test-harness pipe bug:** `cmd \| head` masked MySQL exit codes, making
   rejections look like passes — rewrote the harness to capture exit codes
   in variables. (The schema was rejecting correctly all along.)
2. **Auto-increment assumption:** a deliberately-failed insert consumed id 1,
   breaking hard-coded `id=1` setup rows — rewrote setup with `SELECT`-based
   id lookups. Added as permanent practice for future test scripts.
3. **Cascade-count query quoting:** rewrote T9b with simple quoting and
   re-verified (1 → 0).

The schema itself imported clean on first attempt and passed every test after
the harness fixes. No schema defects remain.

## 5. Compatibility notes

- Requires MySQL 5.7+ / MariaDB 10.2+. CHECK is ignored (harmless) on 5.7;
  the app validates ratings regardless.
- All money columns are integer minor units (D-34 ✅); ledger immutability
  is enforced by triggers, not just convention (D-35 ✅).
- Phase 4 installer must parse `DELIMITER $$` blocks (triggers) — documented
  in the dictionary.

## 6. Approval request

Please reply **"Phase 3 approved — proceed to Phase 4"** to build the
**installation and seed system** (installer, seed data, default admin,
reinstall protection).
