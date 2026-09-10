# Add-on development guide (Phase 26, Z-33)

## Folder layout

`addons/<slug>/` — slug is lowercase letters, digits and dashes, and must
match the manifest `slug`:

- `addon.json` — manifest (required, validated on scan/install/enable)
- `pages/<page>.php` — admin pages, served only via `admin/addon.php`
- `migrations/*.sql` — run once each, in filename order, on install/update
- `assets/` — images/CSS/JS referenced by your pages (served? No —
  keep admin pages CSS-light; assets are for future use)
- `README.md` — what the add-on does

## Manifest reference

```json
{
  "slug": "example",
  "name": "Example add-on",
  "version": "0.2.0",
  "description": "What it does.",
  "requires": { "oyejo": ">=0.26.0", "php": ">=7.4", "addons": ["other-slug"] },
  "permissions": [{ "slug": "example.view", "label": "View the example page" }],
  "toggles": [{ "key": "widget", "label": "Example widget", "default": true }],
  "settings": [{ "key": "welcome", "label": "Welcome text", "default": "Hi!" }],
  "menus": [{ "label": "Example page", "page": "hello", "permission": "example.view" }]
}
```

- `version` is `major.minor.patch`. The desk flags an update when the
  manifest version is newer than the installed version.
- `requires.oyejo` compares against the platform `OYEJO_VERSION`;
  `requires.php` against the runtime; `requires.addons` must each be
  installed **and enabled**.
- Permissions are created on install (group `addon:<slug>`, skipped if the
  slug already exists) and granted to Super Admin + Admin. Later grants are
  managed under Admin → Roles.
- Toggles are created as `addon_<slug>_<key>` and appear under
  Settings → Feature toggles. Check them with `oyejo_feature()`.
- Settings are created as `addon_<slug>_<key>` in group `addon_<slug>`
  and are editable under Admin → Settings. Read them with `setting()`.
- Menus appear on the admin console while the add-on is enabled, and only
  for staff holding the stated permission.

## What install / enable / update do

- **Install** (registered → installed): dependency check → pending
  migrations → toggles → permissions (+ role grants) → settings. One
  `addon_logs` entry plus an audit row.
- **Enable** (installed/disabled → enabled): re-checks files and
  dependencies first. **Disable** always succeeds.
- **Update**: runs pending migrations, then records the manifest version.
  Re-running on an up-to-date add-on is a safe no-op with a message.
- **Scan** registers new folders and refreshes names/versions; invalid
  manifests are reported, never half-registered.

## Page rules

- A page renders only if it is declared in `menus` and the file
  `pages/<page>.php` exists inside the add-on folder (path containment is
  enforced; `..` and absolute paths are rejected).
- Pages run inside the core layout with bootstrap loaded: use `e()` for
  output, `url()` for links, `csrf_field()`/`csrf_verify()` for forms, and
  `db()`/`setting()`/`oyejo_feature()` as needed. Start the file with the
  `OYEJO_BOOT` guard.
- Never trust the browser: re-check permissions and validate input
  server-side, exactly like core desks.

## Migration rules

- One concern per file, ordered names (`001-....sql`), `IF NOT EXISTS`
  style so re-runs are harmless. Statements are split on `;` outside
  quotes/comments; keep files free of stored procedures.
- A failing migration aborts install/update with the database error shown
  to the admin; already-applied files are recorded and skipped next run.

## The 11 planned add-ons (AO-13, stub-ready)

Each ships as a manifest-only stub declaring its planned permissions,
toggle and settings: `loyalty-points`, `gas-subscriptions`,
`corporate-accounts`, `multi-branch`, `franchise`, `accounting`,
`whatsapp-automation`, `route-optimization`, `wallet-withdrawals`,
`multi-language`, `multi-currency`. Build any of them by adding
`pages/` + `migrations/` to its folder — no core changes needed.
