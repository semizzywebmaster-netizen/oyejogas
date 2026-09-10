# Add-ons

Each add-on is one folder: `addons/<slug>/` with an `addon.json` manifest
(see `example/`), plus optional `pages/`, `migrations/`, `assets/` and
`README.md`. Folders starting with `_` (like docs-only helpers) are never
scanned.

Lifecycle (Admin → Add-ons): **Scan** (register new manifests, refresh
versions) → **dependency check** (platform, PHP, other add-ons) →
**Install** (migrations, toggles, permissions, settings) →
**Enable/Disable** → **Update** (new manifest version + pending
migrations). Every step is logged per add-on and audited.

Add-on folders are blocked from direct web access (root `.htaccess` +
local `.htaccess`); pages render through `admin/addon.php`, which checks
the system toggle, add-on status and the manifest permission.

Full manifest reference and page-authoring rules:
`docs/ADDON-DEVELOPMENT.md`.
