-- Миграция: управление платёжными шлюзами
-- Добавляет настройки доступности шлюзов и настройки enot.io

-- Включаем/выключаем шлюзы (по умолчанию все выключены)
INSERT INTO settings (`key`, value) VALUES ('payment_freekassa_enabled', '0') ON DUPLICATE KEY UPDATE value = VALUES(value);
INSERT INTO settings (`key`, value) VALUES ('payment_yoomoney_enabled', '0') ON DUPLICATE KEY UPDATE value = VALUES(value);
INSERT INTO settings (`key`, value) VALUES ('payment_enot_enabled', '0') ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Настройки enot.io
INSERT INTO settings (`key`, value) VALUES ('enot_shop_id', '') ON DUPLICATE KEY UPDATE value = VALUES(value);
INSERT INTO settings (`key`, value) VALUES ('enot_secret_key', '') ON DUPLICATE KEY UPDATE value = VALUES(value);
INSERT INTO settings (`key`, value) VALUES ('enot_mode', 'test') ON DUPLICATE KEY UPDATE value = VALUES(value);
