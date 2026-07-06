use dropforge
-- Таблица сезонов Battle Pass
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

-- Таблица прогресса пользователей
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

-- Таблица наград
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
    INDEX `idx_level` (`season_id`, `level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица заданий
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

-- Таблица выполненных заданий
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

-- Таблица полученных наград
CREATE TABLE IF NOT EXISTS `user_battle_pass_claims` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `season_id` INT UNSIGNED NOT NULL,
    `reward_id` INT UNSIGNED NOT NULL,
    `claimed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_reward` (`user_id`, `reward_id`),
    INDEX `idx_season` (`season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- Миграция: Промокоды Battle Pass

-- 1. Таблица для генерируемых промокодов
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

-- 2. Убираем уникальность уровня, чтобы можно было несколько наград на 1 уровень
ALTER TABLE `battle_pass_rewards` DROP INDEX IF EXISTS `unique_season_level`;
ALTER TABLE `battle_pass_rewards` ADD INDEX `idx_season_level` (`season_id`, `level`);

-- 3. Поле для накопленного бонуса к пополнению
ALTER TABLE `users` ADD COLUMN `promo_bonus_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER balance;

-- 3. Добавляем тип 'promo' в reward_type (если есть проверка типа, иначе просто комментарий)
-- ALTER TABLE `battle_pass_rewards` MODIFY `reward_type` ENUM('balance', 'case', 'promo') NOT NULL DEFAULT 'balance';

ALTER TABLE `battle_pass_tasks` 
MODIFY COLUMN `task_type` VARCHAR(50) NOT NULL DEFAULT 'case_open';
ALTER TABLE battle_pass_rewards 
MODIFY COLUMN case_id INT UNSIGNED DEFAULT NULL;

-- Добавляем поле description в таблицу transactions
ALTER TABLE `transactions` ADD COLUMN `description` VARCHAR(255) NOT NULL DEFAULT '' AFTER `amount`;

-- Инициализация таблицы настроек
-- Запустить один раз при установке

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Основные настройки
INSERT INTO `settings` (`key`, `value`) VALUES
    ('site_name', 'DropForge'),
    ('site_description', 'Лучший CS:GO кейс сайт'),
    ('support_email', 'support@dropforge.gg'),
    ('support_telegram', ''),
    
    -- Страницы
    ('upgrade_enabled', '1'),
    ('contract_enabled', '1'),
    ('battle_pass_enabled', '1'),
    ('daily_bonus_enabled', '1'),
    ('free_case_enabled', '1'),
    ('referrals_enabled', '1'),
    ('inventory_enabled', '1'),
    
    -- Настройки кейсов
    ('min_case_price', '0.50'),
    ('max_case_price', '100.00'),
    ('sell_price_percent', '70'),
    ('max_open_qty', '10'),
    ('transparent_rig', '1'),
    
    -- Реферальная система
    ('ref_commission', '5'),
    ('first_deposit_bonus', '20'),
    ('crypto_bonus', '5'),
    
    -- Курс валют
    ('usd_rub_auto', '0'),
    ('usd_rub_rate', '90.00'),
    
    -- FreeKassa
    ('fk_merchant_id', ''),
    ('fk_phrase1', ''),
    ('fk_phrase2', ''),
    ('fk_mode', 'test'),
    
    -- YooMoney
    ('ym_shopid', ''),
    ('ym_password', ''),
    ('ym_event_url', ''),
    ('ym_mode', 'test'),
    
    -- Steam
    ('steam_api_key', 'F4079FAFACBF691AA299B49429430713'),
    
    -- Безопасность
    ('registration_enabled', '1'),
    ('min_deposit', '1'),
    ('max_deposit', '10000'),
    
    -- Кастомизация
    ('custom_css', ''),
    ('footer_html', '')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
