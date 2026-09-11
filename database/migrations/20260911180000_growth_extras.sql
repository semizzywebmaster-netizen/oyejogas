-- Username + WhatsApp identity, banner captions, abandoned carts, location suggestions.

ALTER TABLE `users`
    ADD COLUMN `username` VARCHAR(40) NULL AFTER `email`,
    ADD COLUMN `whatsapp` VARCHAR(30) NULL AFTER `phone`,
    ADD COLUMN `whatsapp_verified_at` DATETIME NULL AFTER `phone_verified_at`;
ALTER TABLE `users` ADD UNIQUE KEY `uq_users_username` (`username`);
ALTER TABLE `users` ADD UNIQUE KEY `uq_users_whatsapp` (`whatsapp`);

ALTER TABLE `banners` ADD COLUMN `body` TEXT NULL AFTER `title`;

CREATE TABLE IF NOT EXISTS `abandoned_carts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `items` TEXT NOT NULL,
    `notified_at` DATETIME NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_abandoned_customer` (`customer_id`),
    KEY `idx_abandoned_notify` (`notified_at`, `updated_at`),
    CONSTRAINT `fk_abandoned_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `location_suggestions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `zone_id` INT UNSIGNED NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `review_note` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_locsug_status` (`status`, `created_at`),
    CONSTRAINT `fk_locsug_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_locsug_zone` FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_locsug_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
