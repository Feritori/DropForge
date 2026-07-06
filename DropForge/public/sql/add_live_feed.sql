-- Live Feed (лента выигрышей)
CREATE TABLE IF NOT EXISTS `live_wins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `user_avatar` VARCHAR(255) DEFAULT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `item_image` VARCHAR(255) DEFAULT NULL,
    `rarity` VARCHAR(50) NOT NULL DEFAULT 'milspec',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `case_name` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_created_at` (`created_at` DESC),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
