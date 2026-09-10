-- Example add-on migration: a tiny notes table.
CREATE TABLE IF NOT EXISTS `example_notes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `note` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
