# Oyejo Gas — Database Dictionary (Phase 3)

60 tables, InnoDB, `utf8mb4_unicode_ci`. Source of truth:
`database/schema.sql` (executed once by the Phase 4 installer).
Verify any time: import into a scratch DB, then run `tests/phase3-verify.sql`.

## Conventions (binding on all later phases)

- **Money = `INT` minor units** (kobo), never floats. Display via `format_money()`.
- **IDs** are `INT UNSIGNED AUTO_INCREMENT`; FK columns match the parent type.
- **FK discipline:** `CASCADE` for owned children (items, replies, mapping rows);
  `RESTRICT` for business records that must never vanish silently (orders,
  payments, customers); `SET NULL` for optional links (zones, slots, verifiers).
- **Snapshots:** `order_items.name`, `orders.address_text`, `pickup_cylinders.serial_snapshot`
  freeze checkout-time values — history never rewrites itself.
- **JSON in `TEXT` columns** (`meta`, `parameters`, `settings`, `content`) for
  MySQL 5.7 / MariaDB compatibility — never rely on the JSON type.
- **Statuses are ENUMs** — the app validates transitions; the DB rejects
  anything outside the list.
- **CHECK constraints** are progressive enhancement (enforced on MySQL 8+ /
  MariaDB 10.2+, ignored on 5.7) — the app always validates too.

## Table catalogue

### Access control (Phases 5–6)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `roles` | 12 system roles | — |
| `permissions` | Granular permission slugs | — |
| `role_permissions` | Role ↔ permission map | → roles, permissions (CASCADE) |
| `users` | Every login: staff, drivers, customers | → roles (RESTRICT); unique email/phone |
| `password_resets` | Expiring reset tokens (hashed) | → users (CASCADE) |
| `login_attempts` | Throttling + audit of logins | standalone (indexed email/ip + time) |

### Customers & drivers (Phases 5, 10, 15–16)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `customers` | Customer profile + codes | → users (CASCADE); unique customer/referral codes |
| `customer_addresses` | Delivery address book | → customers (CASCADE), zones (SET NULL) |
| `customer_phones` | Extra/verified phones | → customers (CASCADE); unique per customer |
| `drivers` | Driver profile, availability, rating | → users (CASCADE); unique driver code |

### Fulfilment geography (Phases 9, 16)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `delivery_zones` | Zones, fees, free-delivery thresholds | — |
| `delivery_slots` | Delivery time windows | — |

### Catalogue & cylinders (Phases 8, 13–14)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `categories` | Product categories | — |
| `cylinder_sizes` | 6kg/12.5kg/… + deposits | — |
| `products` | Everything sellable (FULLTEXT search) | → categories (RESTRICT), sizes (SET NULL); unique SKU/slug |
| `cylinders` | Serial-tracked physical cylinders | → sizes (RESTRICT), holder customer (SET NULL); unique serial |
| `inventory_movements` | Append-only stock journal | → products/cylinders/users (SET NULL) |
| `suppliers` / `purchases` / `purchase_items` | Procurement | purchases → suppliers (RESTRICT); items → purchase (CASCADE) |

### Orders & checkout (Phase 9)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `coupons` | Codes, limits, windows | unique code |
| `orders` | Order header + totals + snapshots | → customers (RESTRICT), zones/slots/coupons (SET NULL); unique order number |
| `order_items` | Order lines | → orders (CASCADE), products (RESTRICT) |
| `order_status_history` | Status audit trail | → orders (CASCADE), users (SET NULL) |

### Wallet — immutable ledger (Phase 11)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `wallets` | One balance row per customer | → customers (CASCADE, unique) |
| `wallet_transactions` | Append-only ledger, unique idempotency `reference` | → wallets (RESTRICT) |

**Triggers:** `trg_wtxn_no_update` (only `pending` rows may transition),
`trg_wtxn_no_delete` (no deletes ever). Corrections are new `reversal` rows.

### Refill / pickup / delivery (Phases 12–13, 16)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `refills` | Refill requests + fulfilment | → customers/sizes (RESTRICT); address/zone/slot/driver (SET NULL) |
| `pickups` | Pickup / exchange / return jobs | → customers (RESTRICT); size/address/slot/driver (SET NULL) |
| `pickup_cylinders` | Serials + condition per job | → pickups (CASCADE), cylinders (SET NULL) |
| `deliveries` | One run per order/refill/pickup | → orders/refills/pickups/driver/zone/slot (SET NULL) |
| `driver_cash_collections` | COD cash + reconciliation | → deliveries/drivers (RESTRICT) |

### Payments (Phase 17)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `payments` | All payment attempts, gateway refs | → orders (SET NULL), customers (RESTRICT); unique reference |
| `invoices` | Invoice headers | → orders (RESTRICT); unique number |
| `refunds` | Refund workflow incl. wallet refunds | → payments/orders (RESTRICT) |

### Support & reviews (Phase 18)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `support_tickets` | Tickets + category/priority/status | → customers (RESTRICT) |
| `ticket_replies` | Thread incl. `is_internal` notes | → tickets (CASCADE), users (SET NULL) |
| `reviews` | Product/delivery ratings (CHECK 1–5) | → customers (CASCADE), products (CASCADE), orders/deliveries (SET NULL) |

### Marketing & CMS (Phase 19)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `banners` / `announcements` / `faqs` / `posts` / `homepage_sections` | All site content, scheduled + flaggable | standalone |
| `newsletter_subscribers` | Mailing list | unique email |
| `campaigns` | Promotional campaigns | standalone |

### Spin-to-win (Phase 20)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `spin_campaigns` | Campaigns, dates, eligibility | unique slug |
| `spin_prizes` | Weighted prize pool + win caps | → campaigns (CASCADE) |
| `spins` | One row per spin; **UNIQUE(campaign, customer, period)** enforces one-spin-per-period in the DB | → campaigns/customers (RESTRICT), prizes (SET NULL) |

### Referrals (Phase 21)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `referrals` | Referrer ↔ referred link + status | referrer (RESTRICT), referred (CASCADE, unique) |
| `referral_rewards` | Pending/credited/expired payouts | → referrals/customers (CASCADE) |

### Notifications (Phase 22)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `notification_templates` | Per-channel, per-event templates | unique slug |
| `notifications` | Queue + send log + retries | → customers (CASCADE, nullable) |
| `whatsapp_subscriptions` | Opt-in/out per customer | → customers (CASCADE, PK) |

### Operations & platform (Phases 23–26)
| Table | Purpose | Key relations |
|-------|---------|---------------|
| `report_runs` | Saved/generated reports | → users (SET NULL) |
| `backups_log` | Backup history + failures | → users (SET NULL) |
| `audit_logs` | Who changed what (JSON before/after) | → users (SET NULL) |
| `feature_toggles` | 25 server-enforced ON/OFF switches | unique `key` |
| `settings` | Website/payment/notification settings | unique `key` |
| `addons` / `addon_logs` | Add-on registry + activity | logs → addons (CASCADE) |
| `migrations` | Applied-migration journal | unique migration name |

## Integrity totals (verified)

60 tables · 83 foreign keys · 40 unique constraints · 2 triggers · 1 CHECK ·
FULLTEXT product search · composite + covering indexes on all hot paths
(FKs, numbers, references, serials, phones, emails, statuses, retry queues).

## Notes for Phase 4 (installer)

1. `schema.sql` starts with `SET FOREIGN_KEY_CHECKS=0` and re-enables at the
   end — import as one script.
2. The file contains `DELIMITER $$` trigger blocks — the PHP installer must
   parse DELIMITER (PDO cannot execute it as SQL).
3. `migrations` starts empty; the base schema is version "0" — record seed
   state there if needed.
