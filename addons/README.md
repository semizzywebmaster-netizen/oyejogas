# Add-ons (skeleton — full system in Phase 26)

Each add-on is one folder: `addons/<slug>/` with an `addon.json` manifest
(see `_example/addon.json`), plus optional `pages/`, `migrations/`,
`assets/` and `README.md`.

Lifecycle (Phase 26 implements): register → dependency check → install
(migrations) → enable/disable → update, all logged and permission-gated.

Planned future add-ons: loyalty points, gas subscriptions, corporate
accounts, multiple branches, franchise management, accounting integration,
WhatsApp automation, route optimization, wallet withdrawals, multi-language,
multi-currency.
