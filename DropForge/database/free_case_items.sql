-- ============================================
-- Free Case Items Table
-- Управление предметами в бесплатных кейсах
-- ============================================

CREATE TABLE IF NOT EXISTS `free_case_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) NOT NULL COMMENT 'ID бесплатного кейса',
  `item_name` varchar(255) NOT NULL COMMENT 'Название предмета',
  `item_image` varchar(500) DEFAULT NULL COMMENT 'URL изображения предмета',
  `rarity` varchar(50) DEFAULT 'milspec' COMMENT 'Редкость: consumer, industrial, milspec, restricted, classified, covert, extraordinary',
  `price` decimal(10,2) DEFAULT 0.00 COMMENT 'Цена предмета',
  `weight` int(11) NOT NULL DEFAULT 1 COMMENT 'Вес (вероятность выпадения)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Активен ли предмет',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `case_id` (`case_id`),
  KEY `rarity` (`rarity`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `fk_free_case_items_case` FOREIGN KEY (`case_id`) REFERENCES `free_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Индексы для оптимизации запросов
ALTER TABLE `free_case_items` ADD INDEX `idx_case_active_weight` (`case_id`, `is_active`, `weight`);
