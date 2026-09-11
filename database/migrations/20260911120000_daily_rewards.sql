-- Daily check-in, streaks and missions (wallet credits toward purchases).
CREATE TABLE IF NOT EXISTS `daily_checkins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `checkin_date` DATE NOT NULL,
    `streak` INT UNSIGNED NOT NULL DEFAULT 1,
    `reward_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units credited',
    `bonus_minor` INT NOT NULL DEFAULT 0 COMMENT 'mystery bonus portion',
    `wallet_txn_ref` VARCHAR(40) NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_checkin_day` (`customer_id`, `checkin_date`),
    KEY `idx_checkin_date` (`checkin_date`),
    CONSTRAINT `fk_checkin_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `daily_mission_claims` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `mission_key` VARCHAR(40) NOT NULL,
    `claim_date` DATE NOT NULL,
    `reward_minor` INT NOT NULL DEFAULT 0 COMMENT 'minor units',
    `wallet_txn_ref` VARCHAR(40) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_mission_day` (`customer_id`, `mission_key`, `claim_date`),
    CONSTRAINT `fk_mission_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
