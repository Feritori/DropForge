-- DropForge - Проверка и исправление структуры таблиц

-- 1. Проверяем структуру case_items
-- DESCRIBE case_items;

-- 2. Проверяем структуру daily_bonus_rewards
-- DESCRIBE daily_bonus_rewards;

-- 3. Исправляем тип колонки type в daily_bonus_rewards
-- Если колонка имеет тип VARCHAR(10) или меньше, расширяем до VARCHAR(20)
ALTER TABLE `daily_bonus_rewards` 
MODIFY COLUMN `type` VARCHAR(20) NOT NULL DEFAULT 'balance' 
COMMENT 'Тип награды: balance, case, promo, free_case';

-- 4. Добавляем колонку price в case_items, если её нет
-- MySQL 8.0+ поддерживает ADD COLUMN IF NOT EXISTS
-- Для старых версий нужно проверить сначала
SET @dbname = DATABASE();
SET @tablename = 'case_items';
SET @columnname = 'price';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' DECIMAL(10,2) NOT NULL DEFAULT ''0.00'' AFTER rarity')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 5. Добавляем колонку description в case_items, если её нет
SET @columnname = 'description';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' TEXT AFTER item_name')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
