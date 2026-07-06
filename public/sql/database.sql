-- ============================================
-- DropForge — CS:GO Case Site Database Schema
-- ============================================
-- Created: 2025
-- Database charset: utf8mb4
-- Collation: utf8mb4_unicode_ci
-- ============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `dropforge` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `dropforge`;

-- ============================================
-- 1. USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `steam_id` VARCHAR(64) NOT NULL UNIQUE,
    `steam_login` VARCHAR(100) DEFAULT NULL,
    `steam_avatar` VARCHAR(255) DEFAULT NULL,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `promo_bonus_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `balance_bonus` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `inventory_slots` INT NOT NULL DEFAULT 50,
    `ref_code` VARCHAR(10) DEFAULT NULL,
    `remember_token` VARCHAR(255) DEFAULT NULL,
    `remember_expires_at` DATETIME DEFAULT NULL,
    `last_bonus` DATETIME DEFAULT NULL,
    `last_free_case` DATETIME DEFAULT NULL,
    `last_daily_bonus` DATE DEFAULT NULL,
    `battle_pass_xp` INT NOT NULL DEFAULT 0,
    `battle_pass_level` INT NOT NULL DEFAULT 1,
    `battle_pass_premium` TINYINT(1) NOT NULL DEFAULT 0,
    `referrer_id` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_steam_id` (`steam_id`),
    UNIQUE KEY `unique_ref_code` (`ref_code`),
    INDEX `idx_steam_id` (`steam_id`),
    INDEX `idx_referrer_id` (`referrer_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. ADMIN USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `permissions` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. CATEGORIES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sort_order` (`sort_order`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. CASES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `cases` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_category_id` (`category_id`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. CASE ITEMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `case_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_id` INT UNSIGNED NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `item_image` VARCHAR(255) DEFAULT NULL,
    `rarity` VARCHAR(50) NOT NULL DEFAULT 'milspec',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `description` TEXT DEFAULT NULL,
    `chance` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    `is_souvenir` TINYINT(1) NOT NULL DEFAULT 0,
    `is_statrak` TINYINT(1) NOT NULL DEFAULT 0,
    `wear` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_case_id` (`case_id`),
    INDEX `idx_rarity` (`rarity`),
    FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. USER INVENTORY TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `user_inventory` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `item_image` VARCHAR(255) DEFAULT NULL,
    `rarity` VARCHAR(50) NOT NULL DEFAULT 'milspec',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `case_id` INT UNSIGNED DEFAULT NULL,
    `case_name` VARCHAR(255) DEFAULT NULL,
    `is_souvenir` TINYINT(1) NOT NULL DEFAULT 0,
    `is_statrak` TINYINT(1) NOT NULL DEFAULT 0,
    `wear` VARCHAR(20) DEFAULT NULL,
    `is_sold` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('new', 'active', 'sold', 'withdrawn', 'expired') NOT NULL DEFAULT 'new',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_is_sold` (`is_sold`),
    INDEX `idx_created_at` (`created_at` DESC),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. TRANSACTIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `type` ENUM('deposit', 'withdraw', 'case_open', 'item_sell', 'item_buy', 'bonus', 'referral', 'transfer') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `balance_before` DECIMAL(10,2) NOT NULL,
    `balance_after` DECIMAL(10,2) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `metadata` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at` DESC),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. PAYMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'RUB',
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `transaction_id` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_transaction_id` (`transaction_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. PENDING PAYMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `pending_payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `order_id` VARCHAR(100) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT '',
    `amount_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `amount_rub` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `transaction_id` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_order` (`order_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 10. SETTINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 11. FREE CASES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `free_cases` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `min_deposit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cooldown_hours` INT NOT NULL DEFAULT 24,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. FREE CASE ITEMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `free_case_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_id` INT UNSIGNED NOT NULL COMMENT 'ID бесплатного кейса',
    `item_name` VARCHAR(255) NOT NULL COMMENT 'Название предмета',
    `item_image` VARCHAR(500) DEFAULT NULL COMMENT 'URL изображения предмета',
    `rarity` VARCHAR(50) DEFAULT 'milspec' COMMENT 'Редкость: consumer, industrial, milspec, restricted, classified, covert, extraordinary',
    `price` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Цена предмета',
    `weight` INT NOT NULL DEFAULT 1 COMMENT 'Вес (вероятность выпадения)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Активен ли предмет',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `case_id` (`case_id`),
    KEY `rarity` (`rarity`),
    KEY `is_active` (`is_active`),
    KEY `idx_case_active_weight` (`case_id`, `is_active`, `weight`),
    CONSTRAINT `fk_free_case_items_case` FOREIGN KEY (`case_id`) REFERENCES `free_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. DAILY BONUS REWARDS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `daily_bonus_rewards` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `day` INT NOT NULL,
    `type` VARCHAR(20) NOT NULL DEFAULT 'balance' COMMENT 'Тип награды: balance, case, promo, free_case',
    `amount` DECIMAL(10,2) DEFAULT NULL,
    `item_id` INT UNSIGNED DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 14. BATTLE PASS SEASONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `battle_pass_seasons` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 2.99,
    `max_level` INT UNSIGNED NOT NULL DEFAULT 50,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 15. USER BATTLE PASS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `user_battle_pass` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `season_id` INT UNSIGNED NOT NULL,
    `current_level` INT UNSIGNED NOT NULL DEFAULT 1,
    `experience` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_premium` TINYINT(1) NOT NULL DEFAULT 0,
    `purchased_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `unique_user_season` (`user_id`, `season_id`),
    INDEX `idx_season` (`season_id`),
    INDEX `idx_premium` (`is_premium`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 16. BATTLE PASS REWARDS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `battle_pass_rewards` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `season_id` INT UNSIGNED NOT NULL,
    `level` INT UNSIGNED NOT NULL,
    `reward_type` ENUM('balance', 'case', 'promo') NOT NULL DEFAULT 'balance',
    `reward_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reward_description` VARCHAR(255) NOT NULL,
    `is_premium_only` TINYINT(1) NOT NULL DEFAULT 0,
    `case_id` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_season` (`season_id`),
    INDEX `idx_season_level` (`season_id`, `level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 17. BATTLE PASS TASKS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `battle_pass_tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `season_id` INT UNSIGNED NOT NULL,
    `task_type` VARCHAR(50) NOT NULL DEFAULT 'case_open',
    `task_description` VARCHAR(255) NOT NULL,
    `target_value` INT UNSIGNED NOT NULL DEFAULT 1,
    `experience_reward` INT UNSIGNED NOT NULL DEFAULT 100,
    `is_repeatable` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_season` (`season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 18. USER BATTLE PASS TASKS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `user_battle_pass_tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `task_id` INT UNSIGNED NOT NULL,
    `season_id` INT UNSIGNED NOT NULL,
    `progress` INT UNSIGNED NOT NULL DEFAULT 0,
    `completed` TINYINT(1) NOT NULL DEFAULT 0,
    `completed_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `unique_user_task_season` (`user_id`, `task_id`, `season_id`),
    INDEX `idx_season` (`season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 19. USER BATTLE PASS CLAIMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `user_battle_pass_claims` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `season_id` INT UNSIGNED NOT NULL,
    `reward_id` INT UNSIGNED NOT NULL,
    `claimed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_reward` (`user_id`, `reward_id`),
    INDEX `idx_season` (`season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 20. PROMO CODES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `promo_codes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `type` ENUM('balance', 'case', 'item') NOT NULL,
    `amount` DECIMAL(10,2) DEFAULT NULL,
    `item_id` INT UNSIGNED DEFAULT NULL,
    `max_uses` INT DEFAULT NULL,
    `used_count` INT NOT NULL DEFAULT 0,
    `expires_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_code` (`code`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 21. PROMO CODE USES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `promo_code_uses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `promo_code_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `used_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_promo_code_id` (`promo_code_id`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`promo_code_id`) REFERENCES `promo_codes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 22. BP PROMO CODES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `bp_promo_codes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `season_id` INT UNSIGNED NOT NULL,
    `reward_id` INT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `bonus_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_code` (`code`),
    INDEX `idx_user` (`user_id`, `season_id`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 23. LIVE WINS TABLE
-- ============================================
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

-- ============================================
-- 24. SUPPORT TICKETS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('open', 'pending', 'closed') NOT NULL DEFAULT 'open',
    `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 25. SUPPORT MESSAGES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `support_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ticket_id` (`ticket_id`),
    INDEX `idx_created_at` (`created_at` ASC),
    FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 26. REFERRALS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `referrals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referrer_id` INT UNSIGNED NOT NULL,
    `referral_id` INT UNSIGNED NOT NULL,
    `commission_earned` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_referrer_id` (`referrer_id`),
    INDEX `idx_referral_id` (`referral_id`),
    FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`referral_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 27. WITHDRAW REQUESTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `withdraw_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_ids` TEXT NOT NULL COMMENT 'JSON array of inventory item ids',
    `total_value` DECIMAL(10,2) NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending',
    `admin_note` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 28. CONTRACT ITEMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `contract_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contract_id` INT UNSIGNED NOT NULL,
    `inventory_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_contract_id` (`contract_id`),
    INDEX `idx_inventory_id` (`inventory_id`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 29. UPGRADE GAMES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `upgrade_games` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `sell_item_id` INT UNSIGNED NOT NULL,
    `target_item_id` INT UNSIGNED NOT NULL,
    `chance` DECIMAL(5,2) NOT NULL,
    `result` ENUM('win', 'lose') NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 30. STEAM ITEMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `steam_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `market_hash_name` VARCHAR(255) NOT NULL,
    `type` VARCHAR(100) DEFAULT '',
    `rarity` VARCHAR(50) DEFAULT '',
    `icon_url` TEXT,
    `price_usd` DECIMAL(10,2) DEFAULT 0.00,
    `is_graduated` TINYINT(1) DEFAULT 0,
    INDEX `idx_name` (`market_hash_name`(100)),
    INDEX `idx_rarity` (`rarity`),
    INDEX `idx_type` (`type`),
    INDEX `idx_graduated` (`is_graduated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INSERT DEFAULT SETTINGS
-- ============================================

INSERT INTO `settings` (`key`, `value`) VALUES
    ('site_name', 'DropForge'),
    ('site_description', 'Лучший CS:GO кейс сайт'),
    ('support_email', 'support@dropforge.gg'),
    ('support_telegram', ''),
    ('upgrade_enabled', '1'),
    ('contract_enabled', '1'),
    ('battle_pass_enabled', '1'),
    ('daily_bonus_enabled', '1'),
    ('free_case_enabled', '1'),
    ('referrals_enabled', '1'),
    ('inventory_enabled', '1'),
    ('min_case_price', '0.50'),
    ('max_case_price', '100.00'),
    ('sell_price_percent', '70'),
    ('max_open_qty', '10'),
    ('transparent_rig', '1'),
    ('ref_commission', '5'),
    ('first_deposit_bonus', '20'),
    ('crypto_bonus', '5'),
    ('usd_rub_auto', '0'),
    ('usd_rub_rate', '90.00'),
    ('fk_merchant_id', ''),
    ('fk_phrase1', ''),
    ('fk_phrase2', ''),
    ('fk_mode', 'test'),
    ('ym_shopid', ''),
    ('ym_password', ''),
    ('ym_event_url', ''),
    ('ym_mode', 'test'),
    ('steam_api_key', 'F4079FAFACBF691AA299B49429430713'),
    ('registration_enabled', '1'),
    ('min_deposit', '1'),
    ('max_deposit', '10000'),
    ('custom_css', ''),
    ('footer_html', ''),
    ('payment_freekassa_enabled', '0'),
    ('payment_yoomoney_enabled', '0'),
    ('payment_enot_enabled', '0'),
    ('enot_shop_id', ''),
    ('enot_secret_key', ''),
    ('enot_mode', 'test')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- ============================================
-- INSERT DEFAULT DAILY BONUS REWARDS
-- ============================================

INSERT INTO `daily_bonus_rewards` (`day`, `type`, `amount`) VALUES
    (1, 'balance', 0.50),
    (2, 'balance', 0.75),
    (3, 'case', NULL),
    (4, 'balance', 1.00),
    (5, 'balance', 1.50),
    (6, 'free_case', NULL),
    (7, 'balance', 2.00),
    (8, 'balance', 2.50),
    (9, 'case', NULL),
    (10, 'balance', 3.00),
    (11, 'balance', 3.50),
    (12, 'free_case', NULL),
    (13, 'balance', 4.00),
    (14, 'balance', 5.00)
ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`);

-- ============================================
-- INSERT DEFAULT CATEGORIES
-- ============================================

INSERT INTO `categories` (`name`, `description`, `sort_order`, `is_active`) VALUES
    ('Budget', 'Кейсы до $1', 1, 1),
    ('Standard', 'Кейсы от $1 до $10', 2, 1),
    ('Premium', 'Кейсы от $10 до $50', 3, 1),
    ('Legendary', 'Кейсы от $50', 4, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ============================================
-- END OF DATABASE SCHEMA
-- ============================================
