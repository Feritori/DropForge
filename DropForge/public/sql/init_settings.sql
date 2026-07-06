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
