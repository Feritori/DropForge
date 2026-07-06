-- DropForge - Миграция для исправления ошибок
-- Запустить все SQL запросы по порядку

-- 1. Добавляем колонку price в таблицу case_items, если её нет
ALTER TABLE `case_items` 
ADD COLUMN IF NOT EXISTS `price` DECIMAL(10,2) NOT NULL DEFAULT '0.00' AFTER `rarity`;

-- 2. Добавляем колонку description в таблицу case_items, если её нет
ALTER TABLE `case_items` 
ADD COLUMN IF NOT EXISTS `description` TEXT AFTER `item_name`;

-- 3. Добавляем настройки валюты, если их нет
INSERT INTO `settings` (`key`, `value`) VALUES 
    ('site_currency', 'USD'),
    ('currency_symbol', '$')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- 4. Обновляем тип колонки type в daily_bonus_rewards, если нужно
-- Проверяем текущую структуру и расширяем при необходимости
ALTER TABLE `daily_bonus_rewards` 
MODIFY COLUMN `type` VARCHAR(20) NOT NULL DEFAULT 'balance' 
COMMENT 'balance, case, promo, free_case';
