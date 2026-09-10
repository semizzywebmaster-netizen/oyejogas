-- =====================================================================
-- Oyejo Gas - database schema (Phase 3). 60 tables, InnoDB, utf8mb4.
-- Executed once by the Phase 4 installer.
-- NOTE: this file contains DELIMITER blocks (triggers) - the installer
-- must honor DELIMITER when splitting statements. Requires MySQL 5.7+ /
-- MariaDB 10.2+. Money is stored as integer minor units, never floats.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------- migrations
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT NOT NULL DEFAULT 1,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_migrations_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ permissions
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(100) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `group_name` VARCHAR(100) NOT NULL DEFAULT 'general',
    `description` VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_slug` (`slug`),
    KEY `idx_permissions_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------- role_permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active','pending','suspended') NOT NULL DEFAULT 'pending',
    `email_verified_at` DATETIME NULL,
    `phone_verified_at` DATETIME NULL,
    `failed_logins` INT NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `last_login_at` DATETIME NULL,
    `last_login_ip` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    UNIQUE KEY `uq_users_phone` (`phone`),
    KEY `idx_users_role` (`role_id`),
    KEY `idx_users_status` (`status`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- password_resets
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(128) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pwresets_token` (`token_hash`),
    KEY `idx_pwresets_user` (`user_id`),
    CONSTRAINT `fk_pwresets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- customers
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `customer_code` VARCHAR(20) NOT NULL,
    `referral_code` VARCHAR(20) NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customers_user` (`user_id`),
    UNIQUE KEY `uq_customers_code` (`customer_code`),
    UNIQUE KEY `uq_customers_refcode` (`referral_code`),
    CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------- customer_addresses
CREATE TABLE IF NOT EXISTS `customer_addresses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `label` VARCHAR(50) NOT NULL DEFAULT 'Home',
    `recipient_name` VARCHAR(150) NULL,
    `phone` VARCHAR(30) NOT NULL,
    `address_line` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL DEFAULT 'Lagos',
    `landmark` VARCHAR(255) NULL,
    `zone_id` INT UNSIGNED NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_addr_customer` (`customer_id`),
    CONSTRAINT `fk_addr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_addr_zone` FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- customer_phones
CREATE TABLE IF NOT EXISTS `customer_phones` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `verified_at` DATETIME NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_custphones` (`customer_id`, `phone`),
    CONSTRAINT `fk_custphones_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- drivers
CREATE TABLE IF NOT EXISTS `drivers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `driver_code` VARCHAR(20) NOT NULL,
    `vehicle_info` VARCHAR(255) NULL,
    `license_no` VARCHAR(100) NULL,
    `availability` ENUM('available','busy','off_duty') NOT NULL DEFAULT 'off_duty',
    `status` ENUM('active','suspended') NOT NULL DEFAULT 'active',
    `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_drivers_user` (`user_id`),
    UNIQUE KEY `uq_drivers_code` (`driver_code`),
    KEY `idx_drivers_avail` (`availability`, `status`),
    CONSTRAINT `fk_drivers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- delivery_zones
CREATE TABLE IF NOT EXISTS `delivery_zones` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `fee_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `free_above_minor` INT NULL COMMENT 'free delivery threshold, minor units',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_zones_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- delivery_slots
CREATE TABLE IF NOT EXISTS `delivery_slots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `window_start` TIME NOT NULL,
    `window_end` TIME NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ------------------------------------------------------------- categories
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(100) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `image` VARCHAR(255) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- cylinder_sizes
CREATE TABLE IF NOT EXISTS `cylinder_sizes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL COMMENT 'e.g. 6kg, 12.5kg',
    `name` VARCHAR(100) NOT NULL,
    `weight_kg` DECIMAL(6,2) NOT NULL,
    `deposit_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sizes_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- products
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL,
    `size_id` INT UNSIGNED NULL,
    `sku` VARCHAR(60) NOT NULL,
    `slug` VARCHAR(150) NOT NULL,
    `name` VARCHAR(190) NOT NULL,
    `description` TEXT NULL,
    `type` ENUM('cylinder_new','refill','exchange','accessory','service') NOT NULL DEFAULT 'accessory',
    `price_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `promo_price_minor` INT NULL COMMENT 'minor units',
    `promo_starts_at` DATETIME NULL,
    `promo_ends_at` DATETIME NULL,
    `stock_qty` INT NOT NULL DEFAULT 0,
    `low_stock_at` INT NOT NULL DEFAULT 5,
    `track_inventory` TINYINT(1) NOT NULL DEFAULT 1,
    `image` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_sku` (`sku`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    KEY `idx_products_cat` (`category_id`, `is_active`),
    KEY `idx_products_type` (`type`, `is_active`),
    FULLTEXT KEY `ft_products` (`name`, `description`),
    CONSTRAINT `fk_products_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_products_size` FOREIGN KEY (`size_id`) REFERENCES `cylinder_sizes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- cylinders
CREATE TABLE IF NOT EXISTS `cylinders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial` VARCHAR(60) NOT NULL,
    `size_id` INT UNSIGNED NOT NULL,
    `ownership` ENUM('company','customer') NOT NULL DEFAULT 'company',
    `status` ENUM('full','empty','awaiting_refill','damaged','retired','in_transit') NOT NULL DEFAULT 'empty',
    `holder_customer_id` INT UNSIGNED NULL,
    `location_note` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cylinders_serial` (`serial`),
    KEY `idx_cylinders_size` (`size_id`, `status`),
    KEY `idx_cylinders_holder` (`holder_customer_id`),
    CONSTRAINT `fk_cylinders_size` FOREIGN KEY (`size_id`) REFERENCES `cylinder_sizes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cylinders_holder` FOREIGN KEY (`holder_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------- inventory_movements
CREATE TABLE IF NOT EXISTS `inventory_movements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NULL,
    `cylinder_id` INT UNSIGNED NULL,
    `movement` ENUM('addition','deduction','transfer_in','transfer_out','adjustment') NOT NULL,
    `qty` INT NOT NULL DEFAULT 1,
    `ref_type` VARCHAR(50) NULL COMMENT 'order|purchase|refill|pickup|manual',
    `ref_id` INT UNSIGNED NULL,
    `reason` VARCHAR(255) NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invmov_product` (`product_id`),
    KEY `idx_invmov_cylinder` (`cylinder_id`),
    KEY `idx_invmov_ref` (`ref_type`, `ref_id`),
    CONSTRAINT `fk_invmov_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invmov_cylinder` FOREIGN KEY (`cylinder_id`) REFERENCES `cylinders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invmov_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(190) NOT NULL,
    `contact_person` VARCHAR(150) NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(190) NULL,
    `address` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- purchases
CREATE TABLE IF NOT EXISTS `purchases` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_number` VARCHAR(30) NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `total_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `status` ENUM('ordered','received','cancelled') NOT NULL DEFAULT 'ordered',
    `received_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_purchases_number` (`purchase_number`),
    KEY `idx_purchases_supplier` (`supplier_id`),
    CONSTRAINT `fk_purchases_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_purchases_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------- purchase_items
CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NULL,
    `description` VARCHAR(255) NOT NULL,
    `qty` INT NOT NULL DEFAULT 1,
    `unit_cost_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    PRIMARY KEY (`id`),
    KEY `idx_purchitems_purchase` (`purchase_id`),
    CONSTRAINT `fk_purchitems_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_purchitems_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- coupons
CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(40) NOT NULL,
    `name` VARCHAR(150) NULL,
    `type` ENUM('percent','fixed') NOT NULL DEFAULT 'fixed',
    `value` INT NOT NULL DEFAULT 0 COMMENT 'percent 1-100 or minor units',
    `min_order_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `max_discount_minor` INT NULL COMMENT 'minor units',
    `usage_limit` INT NULL,
    `used_count` INT NOT NULL DEFAULT 0,
    `starts_at` DATETIME NULL,
    `ends_at` DATETIME NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_coupons_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------- orders
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(30) NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `status` ENUM('pending','confirmed','preparing','out_for_delivery','delivered','completed','cancelled','failed') NOT NULL DEFAULT 'pending',
    `subtotal_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `delivery_fee_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `discount_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `tax_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `total_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `payment_status` ENUM('unpaid','partial','paid','refunded','failed') NOT NULL DEFAULT 'unpaid',
    `payment_method` ENUM('wallet','cod','transfer','online') NULL,
    `zone_id` INT UNSIGNED NULL,
    `slot_id` INT UNSIGNED NULL,
    `address_text` VARCHAR(500) NULL COMMENT 'snapshot at checkout',
    `delivery_phone` VARCHAR(30) NULL,
    `coupon_id` INT UNSIGNED NULL,
    `notes` TEXT NULL,
    `cancelled_reason` VARCHAR(255) NULL,
    `cancelled_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_number` (`order_number`),
    KEY `idx_orders_customer` (`customer_id`, `created_at`),
    KEY `idx_orders_status` (`status`),
    KEY `idx_orders_paystatus` (`payment_status`),
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_zone` FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_orders_slot` FOREIGN KEY (`slot_id`) REFERENCES `delivery_slots` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ order_items
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(190) NOT NULL COMMENT 'snapshot at checkout',
    `qty` INT NOT NULL DEFAULT 1,
    `unit_price_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `total_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    PRIMARY KEY (`id`),
    KEY `idx_oitems_order` (`order_id`),
    CONSTRAINT `fk_oitems_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oitems_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------- order_status_history
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(30) NULL,
    `to_status` VARCHAR(30) NOT NULL,
    `changed_by` INT UNSIGNED NULL,
    `note` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_osh_order` (`order_id`),
    CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_osh_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ---------------------------------------------------------------- wallets
CREATE TABLE IF NOT EXISTS `wallets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `balance_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units, derived from ledger',
    `status` ENUM('active','frozen') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wallets_customer` (`customer_id`),
    CONSTRAINT `fk_wallets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------- wallet_transactions
-- IMMUTABLE LEDGER: only `pending` rows may change (to a terminal state);
-- posted rows are never updated or deleted (enforced by triggers below).
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `wallet_id` INT UNSIGNED NOT NULL,
    `reference` VARCHAR(40) NOT NULL COMMENT 'idempotency key',
    `type` ENUM('topup','payment','refund','promo','referral','spin','adjustment','reversal') NOT NULL,
    `direction` ENUM('credit','debit') NOT NULL,
    `amount_minor` INT NOT NULL COMMENT 'minor units',
    `balance_after_minor` INT NOT NULL COMMENT 'minor units',
    `status` ENUM('pending','completed','failed','reversed') NOT NULL DEFAULT 'pending',
    `related_type` VARCHAR(50) NULL COMMENT 'order|refund|spin|referral|...',
    `related_id` INT UNSIGNED NULL,
    `narration` VARCHAR(255) NULL,
    `meta` TEXT NULL COMMENT 'JSON',
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wtxn_reference` (`reference`),
    KEY `idx_wtxn_wallet` (`wallet_id`, `created_at`),
    KEY `idx_wtxn_status` (`status`),
    KEY `idx_wtxn_related` (`related_type`, `related_id`),
    CONSTRAINT `fk_wtxn_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_wtxn_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
DROP TRIGGER IF EXISTS `trg_wtxn_no_update`$$
CREATE TRIGGER `trg_wtxn_no_update`
BEFORE UPDATE ON `wallet_transactions`
FOR EACH ROW
BEGIN
    IF OLD.`status` <> 'pending' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'wallet_transactions: posted entries are immutable';
    END IF;
END$$

DROP TRIGGER IF EXISTS `trg_wtxn_no_delete`$$
CREATE TRIGGER `trg_wtxn_no_delete`
BEFORE DELETE ON `wallet_transactions`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'wallet_transactions: entries cannot be deleted';
END$$
DELIMITER ;

-- ---------------------------------------------------------------- refills
CREATE TABLE IF NOT EXISTS `refills` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `refill_number` VARCHAR(30) NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `size_id` INT UNSIGNED NOT NULL,
    `qty` INT NOT NULL DEFAULT 1,
    `customer_cylinder_details` VARCHAR(500) NULL,
    `fulfillment` ENUM('pickup','delivery') NOT NULL DEFAULT 'delivery',
    `address_id` INT UNSIGNED NULL,
    `zone_id` INT UNSIGNED NULL,
    `slot_id` INT UNSIGNED NULL,
    `status` ENUM('requested','assigned','processing','ready','completed','cancelled') NOT NULL DEFAULT 'requested',
    `assigned_driver_id` INT UNSIGNED NULL,
    `price_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_refills_number` (`refill_number`),
    KEY `idx_refills_customer` (`customer_id`, `status`),
    CONSTRAINT `fk_refills_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_refills_size` FOREIGN KEY (`size_id`) REFERENCES `cylinder_sizes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_refills_address` FOREIGN KEY (`address_id`) REFERENCES `customer_addresses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_refills_zone` FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_refills_slot` FOREIGN KEY (`slot_id`) REFERENCES `delivery_slots` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_refills_driver` FOREIGN KEY (`assigned_driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- pickups
CREATE TABLE IF NOT EXISTS `pickups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pickup_number` VARCHAR(30) NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `type` ENUM('pickup_only','exchange','return') NOT NULL DEFAULT 'pickup_only',
    `size_id` INT UNSIGNED NULL,
    `qty` INT NOT NULL DEFAULT 1,
    `deposit_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `address_id` INT UNSIGNED NULL,
    `slot_id` INT UNSIGNED NULL,
    `scheduled_date` DATE NULL,
    `status` ENUM('requested','scheduled','collected','completed','cancelled') NOT NULL DEFAULT 'requested',
    `driver_id` INT UNSIGNED NULL,
    `notes` TEXT NULL,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pickups_number` (`pickup_number`),
    KEY `idx_pickups_customer` (`customer_id`, `status`),
    KEY `idx_pickups_driver` (`driver_id`, `status`),
    CONSTRAINT `fk_pickups_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pickups_size` FOREIGN KEY (`size_id`) REFERENCES `cylinder_sizes` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pickups_address` FOREIGN KEY (`address_id`) REFERENCES `customer_addresses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pickups_slot` FOREIGN KEY (`slot_id`) REFERENCES `delivery_slots` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pickups_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------- pickup_cylinders
CREATE TABLE IF NOT EXISTS `pickup_cylinders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pickup_id` INT UNSIGNED NOT NULL,
    `cylinder_id` INT UNSIGNED NULL,
    `serial_snapshot` VARCHAR(60) NULL,
    `condition_on_collect` ENUM('good','worn','damaged') NULL,
    `direction` ENUM('collected','delivered') NOT NULL DEFAULT 'collected',
    `deposit_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    PRIMARY KEY (`id`),
    KEY `idx_pcy_pickup` (`pickup_id`),
    CONSTRAINT `fk_pcy_pickup` FOREIGN KEY (`pickup_id`) REFERENCES `pickups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pcy_cylinder` FOREIGN KEY (`cylinder_id`) REFERENCES `cylinders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- deliveries
CREATE TABLE IF NOT EXISTS `deliveries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `delivery_number` VARCHAR(30) NOT NULL,
    `order_id` INT UNSIGNED NULL,
    `refill_id` INT UNSIGNED NULL,
    `pickup_id` INT UNSIGNED NULL,
    `driver_id` INT UNSIGNED NULL,
    `zone_id` INT UNSIGNED NULL,
    `slot_id` INT UNSIGNED NULL,
    `status` ENUM('pending','assigned','out_for_delivery','delivered','failed','cancelled') NOT NULL DEFAULT 'pending',
    `driver_note` VARCHAR(500) NULL,
    `customer_instructions` VARCHAR(500) NULL,
    `proof_note` VARCHAR(500) NULL,
    `proof_image` VARCHAR(255) NULL,
    `failed_reason` VARCHAR(255) NULL,
    `cash_expected_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `cash_collected_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_deliveries_number` (`delivery_number`),
    KEY `idx_deliveries_driver` (`driver_id`, `status`),
    KEY `idx_deliveries_order` (`order_id`),
    KEY `idx_deliveries_status` (`status`),
    CONSTRAINT `fk_deliveries_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_deliveries_refill` FOREIGN KEY (`refill_id`) REFERENCES `refills` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_deliveries_pickup` FOREIGN KEY (`pickup_id`) REFERENCES `pickups` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_deliveries_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_deliveries_zone` FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_deliveries_slot` FOREIGN KEY (`slot_id`) REFERENCES `delivery_slots` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------- driver_cash_collections
CREATE TABLE IF NOT EXISTS `driver_cash_collections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `delivery_id` INT UNSIGNED NOT NULL,
    `driver_id` INT UNSIGNED NOT NULL,
    `amount_minor` INT NOT NULL COMMENT 'minor units',
    `collected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reconciled` TINYINT(1) NOT NULL DEFAULT 0,
    `reconciled_at` DATETIME NULL,
    `reconciled_by` INT UNSIGNED NULL,
    `notes` VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_dcc_driver` (`driver_id`, `reconciled`),
    CONSTRAINT `fk_dcc_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_dcc_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_dcc_user` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- payments
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_reference` VARCHAR(40) NOT NULL,
    `order_id` INT UNSIGNED NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `wallet_txn_id` INT UNSIGNED NULL,
    `method` ENUM('wallet','cod','transfer','online') NOT NULL,
    `gateway` VARCHAR(30) NULL COMMENT 'paystack|flutterwave',
    `gateway_ref` VARCHAR(100) NULL,
    `amount_minor` INT NOT NULL COMMENT 'minor units',
    `status` ENUM('pending','verified','failed','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
    `proof_image` VARCHAR(255) NULL,
    `verified_by` INT UNSIGNED NULL,
    `verified_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payments_reference` (`payment_reference`),
    KEY `idx_payments_order` (`order_id`),
    KEY `idx_payments_status` (`status`),
    KEY `idx_payments_gateway` (`gateway_ref`),
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_payments_wtxn` FOREIGN KEY (`wallet_txn_id`) REFERENCES `wallet_transactions` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_payments_user` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- invoices
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(30) NOT NULL,
    `order_id` INT UNSIGNED NOT NULL,
    `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `due_at` DATETIME NULL,
    `status` ENUM('issued','paid','void') NOT NULL DEFAULT 'issued',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoices_number` (`invoice_number`),
    CONSTRAINT `fk_invoices_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- refunds
CREATE TABLE IF NOT EXISTS `refunds` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `refund_number` VARCHAR(30) NOT NULL,
    `payment_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED NOT NULL,
    `amount_minor` INT NOT NULL COMMENT 'minor units',
    `reason` VARCHAR(255) NULL,
    `method` ENUM('wallet','bank','cash') NOT NULL DEFAULT 'wallet',
    `status` ENUM('pending','approved','completed','rejected') NOT NULL DEFAULT 'pending',
    `processed_by` INT UNSIGNED NULL,
    `processed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_refunds_number` (`refund_number`),
    KEY `idx_refunds_order` (`order_id`, `status`),
    CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_refunds_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_refunds_user` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- -------------------------------------------------------- support_tickets
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_number` VARCHAR(30) NOT NULL,
    `customer_id` INT UNSIGNED NULL,
    `guest_email` VARCHAR(190) NULL,
    `order_id` INT UNSIGNED NULL,
    `category` ENUM('order','payment','delivery','refill','product','complaint','refund','other') NOT NULL DEFAULT 'other',
    `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    `status` ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
    `subject` VARCHAR(190) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tickets_number` (`ticket_number`),
    KEY `idx_tickets_customer` (`customer_id`, `status`),
    KEY `idx_tickets_order` (`order_id`),
    CONSTRAINT `fk_tickets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_tickets_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- ticket_replies
CREATE TABLE IF NOT EXISTS `ticket_replies` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL COMMENT 'author; NULL = system',
    `body` TEXT NOT NULL,
    `is_internal` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_treplies_ticket` (`ticket_id`),
    CONSTRAINT `fk_treplies_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_treplies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- reviews
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NULL,
    `order_id` INT UNSIGNED NULL,
    `delivery_id` INT UNSIGNED NULL,
    `rating` TINYINT NOT NULL,
    `title` VARCHAR(190) NULL,
    `body` TEXT NULL,
    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_reviews_product` (`product_id`, `status`),
    KEY `idx_reviews_customer` (`customer_id`),
    CONSTRAINT `chk_reviews_rating` CHECK (`rating` BETWEEN 1 AND 5),
    CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reviews_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- banners
CREATE TABLE IF NOT EXISTS `banners` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(190) NOT NULL,
    `image` VARCHAR(255) NULL,
    `link_url` VARCHAR(255) NULL,
    `position` VARCHAR(50) NOT NULL DEFAULT 'home_top',
    `sort_order` INT NOT NULL DEFAULT 0,
    `starts_at` DATETIME NULL,
    `ends_at` DATETIME NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_banners_pos` (`position`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- announcements
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(190) NOT NULL,
    `body` TEXT NOT NULL,
    `audience` ENUM('all','customers','drivers') NOT NULL DEFAULT 'all',
    `starts_at` DATETIME NULL,
    `ends_at` DATETIME NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_announce_aud` (`audience`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------- faqs
CREATE TABLE IF NOT EXISTS `faqs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question` VARCHAR(255) NOT NULL,
    `answer` TEXT NOT NULL,
    `category` VARCHAR(100) NOT NULL DEFAULT 'general',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_faqs_cat` (`category`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ posts
CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(150) NOT NULL,
    `title` VARCHAR(190) NOT NULL,
    `body` MEDIUMTEXT NOT NULL,
    `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_posts_slug` (`slug`),
    KEY `idx_posts_status` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------ homepage_sections
CREATE TABLE IF NOT EXISTS `homepage_sections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(100) NOT NULL,
    `title` VARCHAR(190) NOT NULL,
    `content` TEXT NULL COMMENT 'JSON blocks',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_hsections_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------- newsletter_subscribers
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `name` VARCHAR(150) NULL,
    `status` ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
    `verified_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_newsletter_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- campaigns
CREATE TABLE IF NOT EXISTS `campaigns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(190) NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'promo',
    `description` TEXT NULL,
    `starts_at` DATETIME NULL,
    `ends_at` DATETIME NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campaigns_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- spin_campaigns
CREATE TABLE IF NOT EXISTS `spin_campaigns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(100) NOT NULL,
    `name` VARCHAR(190) NOT NULL,
    `description` TEXT NULL,
    `starts_at` DATETIME NOT NULL,
    `ends_at` DATETIME NOT NULL,
    `min_order_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `period_rule` ENUM('daily','weekly','campaign') NOT NULL DEFAULT 'daily',
    `max_spins_per_user` INT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_spincamp_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ spin_prizes
CREATE TABLE IF NOT EXISTS `spin_prizes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id` INT UNSIGNED NOT NULL,
    `label` VARCHAR(150) NOT NULL,
    `reward_type` ENUM('wallet_credit','promo_code','discount','free_delivery','none') NOT NULL DEFAULT 'none',
    `reward_value_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `promo_code` VARCHAR(40) NULL,
    `probability_weight` INT NOT NULL DEFAULT 0 COMMENT 'weighted server-side draw',
    `max_wins` INT NULL,
    `wins_count` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_spinprizes_camp` (`campaign_id`),
    CONSTRAINT `fk_spinprizes_camp` FOREIGN KEY (`campaign_id`) REFERENCES `spin_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------- spins
CREATE TABLE IF NOT EXISTS `spins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `prize_id` INT UNSIGNED NULL,
    `period_key` VARCHAR(20) NOT NULL COMMENT 'e.g. 2026-09-10, 2026-W37, or campaign',
    `reward_status` ENUM('pending','credited','expired','reversed') NOT NULL DEFAULT 'pending',
    `wallet_txn_ref` VARCHAR(40) NULL,
    `reward_code` VARCHAR(40) NULL,
    `expires_at` DATETIME NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_spins_period` (`campaign_id`, `customer_id`, `period_key`),
    KEY `idx_spins_expiry` (`reward_status`, `expires_at`),
    CONSTRAINT `fk_spins_camp` FOREIGN KEY (`campaign_id`) REFERENCES `spin_campaigns` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_spins_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_spins_prize` FOREIGN KEY (`prize_id`) REFERENCES `spin_prizes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- referrals
CREATE TABLE IF NOT EXISTS `referrals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referrer_customer_id` INT UNSIGNED NOT NULL,
    `referred_customer_id` INT UNSIGNED NOT NULL,
    `code_used` VARCHAR(20) NULL,
    `status` ENUM('pending','qualified','rewarded','expired','flagged') NOT NULL DEFAULT 'pending',
    `qualified_at` DATETIME NULL,
    `flag_note` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_referrals_referred` (`referred_customer_id`),
    KEY `idx_referrals_referrer` (`referrer_customer_id`, `status`),
    CONSTRAINT `fk_referrals_referrer` FOREIGN KEY (`referrer_customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_referrals_referred` FOREIGN KEY (`referred_customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- referral_rewards
CREATE TABLE IF NOT EXISTS `referral_rewards` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referral_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL COMMENT 'reward recipient',
    `kind` ENUM('referrer','referred') NOT NULL,
    `amount_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `wallet_txn_ref` VARCHAR(40) NULL,
    `status` ENUM('pending','credited','expired','reversed') NOT NULL DEFAULT 'pending',
    `expires_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_refrewards_ref` (`referral_id`),
    KEY `idx_refrewards_cust` (`customer_id`, `status`),
    CONSTRAINT `fk_refrewards_ref` FOREIGN KEY (`referral_id`) REFERENCES `referrals` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_refrewards_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------- notification_templates
CREATE TABLE IF NOT EXISTS `notification_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(120) NOT NULL,
    `channel` ENUM('email','sms','whatsapp','push') NOT NULL,
    `event` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(190) NULL,
    `body` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ntpl_slug` (`slug`),
    KEY `idx_ntpl_event` (`event`, `channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NULL,
    `channel` ENUM('email','sms','whatsapp','push') NOT NULL,
    `event` VARCHAR(100) NOT NULL,
    `recipient` VARCHAR(190) NOT NULL COMMENT 'email address, phone, or device token',
    `subject` VARCHAR(190) NULL,
    `body` TEXT NULL,
    `status` ENUM('queued','sent','delivered','failed') NOT NULL DEFAULT 'queued',
    `provider` VARCHAR(50) NULL,
    `provider_msg_id` VARCHAR(150) NULL,
    `error` TEXT NULL,
    `attempts` INT NOT NULL DEFAULT 0,
    `next_retry_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_notif_retry` (`status`, `next_retry_at`),
    KEY `idx_notif_customer` (`customer_id`),
    KEY `idx_notif_event` (`event`),
    CONSTRAINT `fk_notif_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------- whatsapp_subscriptions
CREATE TABLE IF NOT EXISTS `whatsapp_subscriptions` (
    `customer_id` INT UNSIGNED NOT NULL,
    `subscribed` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`customer_id`),
    CONSTRAINT `fk_wa_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ report_runs
CREATE TABLE IF NOT EXISTS `report_runs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `parameters` TEXT NULL COMMENT 'JSON',
    `file_path` VARCHAR(255) NULL,
    `generated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_reportruns_user` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ backups_log
CREATE TABLE IF NOT EXISTS `backups_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename` VARCHAR(255) NOT NULL,
    `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `type` ENUM('manual','scheduled') NOT NULL DEFAULT 'manual',
    `status` ENUM('running','success','failed') NOT NULL DEFAULT 'running',
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finished_at` DATETIME NULL,
    `error` TEXT NULL,
    `triggered_by` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    KEY `idx_backups_status` (`status`, `started_at`),
    CONSTRAINT `fk_backups_user` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(80) NULL,
    `entity_id` INT UNSIGNED NULL,
    `old_values` TEXT NULL COMMENT 'JSON',
    `new_values` TEXT NULL COMMENT 'JSON',
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_entity` (`entity_type`, `entity_id`),
    KEY `idx_audit_user` (`user_id`),
    KEY `idx_audit_created` (`created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- feature_toggles
CREATE TABLE IF NOT EXISTS `feature_toggles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(60) NOT NULL,
    `label` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_toggles_key` (`key`),
    CONSTRAINT `fk_toggles_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- settings
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NULL,
    `group_name` VARCHAR(60) NOT NULL DEFAULT 'site',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------- addons
CREATE TABLE IF NOT EXISTS `addons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(80) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `version` VARCHAR(20) NOT NULL DEFAULT '0.1.0',
    `status` ENUM('registered','installed','enabled','disabled') NOT NULL DEFAULT 'registered',
    `settings` TEXT NULL COMMENT 'JSON',
    `installed_at` DATETIME NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_addons_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- addon_logs
CREATE TABLE IF NOT EXISTS `addon_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `addon_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `detail` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_addonlogs_addon` (`addon_id`),
    CONSTRAINT `fk_addonlogs_addon` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- login_attempts
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_email` (`email`, `created_at`),
    KEY `idx_login_ip` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- backups
CREATE TABLE IF NOT EXISTS `backups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename` VARCHAR(120) NOT NULL,
    `bytes` INT UNSIGNED NOT NULL DEFAULT 0,
    `tables` INT UNSIGNED NOT NULL DEFAULT 0,
    `rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `seconds` DECIMAL(8,2) NOT NULL DEFAULT 0,
    `source` ENUM('manual','scheduled','pre-restore') NOT NULL DEFAULT 'manual',
    `status` ENUM('ok','failed') NOT NULL DEFAULT 'ok',
    `sha256` CHAR(64) NULL,
    `error` VARCHAR(500) NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_backups_file` (`filename`),
    KEY `idx_backups_created` (`created_at`),
    CONSTRAINT `fk_backups_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- error_reports
CREATE TABLE IF NOT EXISTS `error_reports` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `signature` CHAR(64) NOT NULL COMMENT 'sha256(domain|class|message|file|line)',
    `domain` VARCHAR(20) NOT NULL,
    `level` VARCHAR(10) NOT NULL DEFAULT 'error',
    `message` VARCHAR(500) NOT NULL,
    `file` VARCHAR(255) NULL,
    `line` INT UNSIGNED NULL,
    `occurrences` INT UNSIGNED NOT NULL DEFAULT 1,
    `first_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `status` ENUM('open','resolved') NOT NULL DEFAULT 'open',
    `resolved_by` INT UNSIGNED NULL,
    `resolved_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_errors_sig` (`signature`),
    KEY `idx_errors_status` (`status`, `last_seen`),
    CONSTRAINT `fk_errors_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- rate_limits
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope` VARCHAR(40) NOT NULL,
    `key_hash` CHAR(64) NOT NULL COMMENT 'sha256(scope|key): no raw IPs/emails',
    `hits` INT UNSIGNED NOT NULL DEFAULT 1,
    `window_start` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ratelimit_scope` (`scope`, `key_hash`),
    KEY `idx_ratelimit_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
SET FOREIGN_KEY_CHECKS = 1;
-- End of schema: 63 tables + 2 triggers.
-- =====================================================================
