-- Миграция: Обновление таблицы pending_payments
-- Дата: 2025-01-XX

-- Создаём таблицу если не существует
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

-- Процедура для добавления колонок
DROP PROCEDURE IF EXISTS update_pending_payments_table;

DELIMITER $$
CREATE PROCEDURE update_pending_payments_table()
BEGIN
    -- amount_usd
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pending_payments' AND COLUMN_NAME='amount_usd') THEN
        ALTER TABLE `pending_payments` ADD COLUMN `amount_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `payment_method`;
    END IF;
    
    -- amount_rub
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pending_payments' AND COLUMN_NAME='amount_rub') THEN
        ALTER TABLE `pending_payments` ADD COLUMN `amount_rub` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `amount_usd`;
    END IF;
    
    -- status
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pending_payments' AND COLUMN_NAME='status') THEN
        ALTER TABLE `pending_payments` ADD COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER `amount_rub`;
    END IF;
    
    -- transaction_id
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pending_payments' AND COLUMN_NAME='transaction_id') THEN
        ALTER TABLE `pending_payments` ADD COLUMN `transaction_id` INT UNSIGNED DEFAULT NULL AFTER `status`;
    END IF;
    
    -- updated_at
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pending_payments' AND COLUMN_NAME='updated_at') THEN
        ALTER TABLE `pending_payments` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
    END IF;
    
    -- idx_status
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pending_payments' AND INDEX_NAME='idx_status') THEN
        ALTER TABLE `pending_payments` ADD INDEX `idx_status` (`status`);
    END IF;
END$$
DELIMITER ;

CALL update_pending_payments_table();
DROP PROCEDURE IF EXISTS update_pending_payments_table;

