-- DropForge Currency Support Update
-- Добавляет поддержку выбора валюты (USD/RUB/KZT)

-- Добавляем настройки валюты по умолчанию (если ещё не существуют)
INSERT INTO settings (`key`, value) VALUES 
    ('site_currency', 'USD'),
    ('currency_symbol', '$')
ON DUPLICATE KEY UPDATE value = value;

-- Курс USD/RUB (если не существует)
INSERT INTO settings (`key`, value) VALUES 
    ('usd_rub_rate', '90.00'),
    ('usd_rub_auto', '0')
ON DUPLICATE KEY UPDATE value = value;
