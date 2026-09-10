# Database migrations

- The base schema lives in `../schema.sql` (created in Phase 3).
- Name migrations `YYYYMMDDHHMMSS_short_description.sql`, e.g.
  `20260910090000_add_wallet_ledger.sql`.
- Each migration runs once; applied files are recorded in the `migrations`
  table (created in Phase 3).
- Add-on migrations live inside each add-on folder and are tracked the same
  way (Phase 26).
- Never edit a migration that has run on the live site — write a new one.
