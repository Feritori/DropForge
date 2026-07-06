<?php
/**
 * DropForge — Configuration
 */

define('DB_HOST',     '127.0.0.1');
define('DB_NAME',     'dropforge');
define('DB_USER',     'dropforge');
define('DB_PASS',     'Dd5g6TQAF7hxN');
define('DB_CHARSET',  'utf8mb4');

define('SITE_URL',    'https://drop-forge.ru');
define('STEAM_API_KEY', 'F4079FAFACBF691AA299B49429430713');
define('SITE_NAME',   'DropForge');

define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
define('ASSETS_DIR',  __DIR__ . '/../public/assets/');

// ==================== FreeKassa (настраивается в админке) ====================
// Эти значения теперь хранятся в БД и редактируются через admin/index.php?section=payment
// Оставлены как fallback, но реальные настройки берутся из таблицы settings

// 'FK_MERCHANT_ID',   '46128'           // ← Твой ID мерчанта из кабинета FreeKassa
// 'FK_PHRASE1',       'secret_phrase_1' // ← Фраза 1 из кабинета
// 'FK_PHRASE2',       'secret_phrase_2' // ← Фраза 2 из кабинета
// 'FK_MODE',          'test'            // test | production

define('RAIRITY_ORDER', [
    'consumer',        // 0
    'industrial',      // 1
    'milspec',         // 2
    'restricted',      // 3
    'classified',      // 4
    'covert',          // 5
    'extraordinary',   // 6
    'contraband'       // 7
]);

define('RAIRITY_COLORS', [
    'consumer'        => '#b0c3d9',
    'industrial'      => '#5e98d9',
    'milspec'         => '#4b69ff',
    'restricted'      => '#8847ff',
    'classified'      => '#d32ce6',
    'covert'          => '#eb4b4b',
    'extraordinary'   => '#e4ae39',
    'contraband'      => '#de9b35'
]);
