<?php
/**
 * =============================================================================
 * DropForge — Автоматический установщик сайта
 * =============================================================================
 * 
 * ИНСТРУКЦИЯ ПО ИСПОЛЬЗОВАНИЮ:
 * 
 * 1. Загрузите этот файл и папку public/ на ваш VPS
 * 2. Откройте в браузере: http://ваш-домен/install.php
 * 3. Следуйте инструкциям мастера установки
 * 
 * ТРЕБОВАНИЯ:
 * - PHP 7.4 или выше
 * - MySQL 5.7 или выше / MariaDB 10.3+
 * - Расширения: pdo_mysql, json, curl, session
 * - Права на запись в директорию установки
 * 
 * =============================================================================
 */

// Отключаем кэш браузера
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);

session_start();

// =============================================================================
// КОНФИГУРАЦИЯ УСТАНОВЩИКА
// =============================================================================

$INSTALLER_VERSION = '2.0.0';
$REQUIRED_PHP_VERSION = '7.4';
$REQUIRED_MYSQL_VERSION = '5.7';

// =============================================================================
// ПРОВЕРКА: Установщик не должен быть доступен после установки
// =============================================================================

// Проверяем наличие config.php в нескольких возможных местах
$installedPaths = [
    __DIR__ . '/config/config.php',
    __DIR__ . '/config.php',
    dirname(__DIR__) . '/config/config.php',
    __DIR__ . '/../config/config.php',
];

foreach ($installedPaths as $path) {
    if (file_exists($path) && filesize($path) > 100) {
        // Config найден — показываем предупреждение
        $configFound = true;
        break;
    }
}

if (isset($configFound)) {
    // Пытаемся подключиться к БД для проверки
    try {
        require_once dirname(__DIR__) . '/config/config.php';
        $test = @new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        // Если подключение прошло — сайт установлен
        if ($test) {
            ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DropForge — Установщик уже завершён</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; color: #fff; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 600px; padding: 2rem; text-align: center; }
        .alert { background: rgba(255, 152, 0, 0.2); border: 1px solid #ff9800; border-radius: 12px; padding: 2rem; }
        .alert h1 { color: #ff9800; margin-bottom: 1rem; }
        .alert p { color: #ccc; margin-bottom: 1.5rem; line-height: 1.6; }
        .btn { display: inline-block; padding: 0.75rem 2rem; background: linear-gradient(135deg, #e4ae39, #d32ce6); color: #fff; border: none; border-radius: 8px; font-size: 1rem; text-decoration: none; cursor: pointer; }
        .code { background: rgba(0,0,0,0.5); padding: 0.5rem 1rem; border-radius: 4px; font-family: monospace; color: #4CAF50; }
    </style>
</head>
<body>
    <div class="container">
        <div class="alert">
            <h1>⚠️ Установщик уже завершён</h1>
            <p>Конфигурационный файл найден. Если вы не завершали установку, возможно, config.php был создан вручную.</p>
            <p><strong>Для безопасности удалите этот файл после установки:</strong></p>
            <p><code class="code"><?= e(basename(__FILE__)) ?></code></p>
            <br>
            <a href="/admin/index.php" class="btn">Перейти в админ-панель</a>
        </div>
    </div>
</body>
</html>
            <?php
            exit;
        }
    } catch (Exception $e) {
        // Config есть, но БД не подключается — продолжаем установку
    }
}

// =============================================================================
// ШАГИ УСТАНОВКИ
// =============================================================================

$steps = [
    'welcome'     => 'Добро пожаловать',
    'requirements'=> 'Проверка требований',
    'database'    => 'База данных',
    'tables'      => 'Создание таблиц',
    'settings'    => 'Настройки сайта',
    'admin'       => 'Администратор',
    'finish'      => 'Готово'
];

$currentStep = $_GET['step'] ?? 'welcome';
if (!isset($steps[$currentStep])) {
    $currentStep = 'welcome';
}

// =============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// =============================================================================

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formatMoney($amount) {
    return '$' . number_format((float)$amount, 2);
}

function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function checkPHPVersion() {
    global $REQUIRED_PHP_VERSION;
    return version_compare(PHP_VERSION, $REQUIRED_PHP_VERSION, '>=');
}

function checkMySQLExtension() {
    return extension_loaded('pdo_mysql');
}

function checkJSONExtension() {
    return extension_loaded('json');
}

function checkCurlExtension() {
    return extension_loaded('curl');
}

function checkSessionExtension() {
    return extension_loaded('session');
}

function checkWritableDir($dir) {
    return is_writable($dir);
}

function testDatabaseConnection($host, $port, $user, $pass, $database = null) {
    try {
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        if ($database) {
            $dsn .= ";dbname=$database";
        }
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return ['success' => true, 'pdo' => $pdo];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getMySQLVersion($pdo) {
    $stmt = $pdo->query("SELECT VERSION() as version");
    $row = $stmt->fetch();
    return $row['version'] ?? '0.0.0';
}

function createDatabase($pdo, $dbName) {
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function createUser($pdo, $username, $password, $host = '%') {
    try {
        $pdo->exec("CREATE USER IF NOT EXISTS '$username'@'$host' IDENTIFIED BY '$password'");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function grantPrivileges($pdo, $dbName, $username, $host = '%') {
    try {
        $pdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$username'@'$host'");
        $pdo->exec("FLUSH PRIVILEGES");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function createTables($pdo) {
    $errors = [];
    
    // ========================================================================
    // ТАБЛИЦА: users
    // ========================================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `steam_id` VARCHAR(64) NOT NULL UNIQUE,
                `steam_login` VARCHAR(100) DEFAULT NULL,
                `steam_avatar` VARCHAR(255) DEFAULT NULL,
                `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `balance_bonus` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `inventory_slots` INT NOT NULL DEFAULT 50,
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
                INDEX `idx_steam_id` (`steam_id`),
                INDEX `idx_referrer_id` (`referrer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "users: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: admin_users
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "admin_users: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: cases
    // ========================================================================
    try {
        $pdo->exec("
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
                INDEX `idx_is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "cases: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: case_items
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "case_items: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: categories
    // ========================================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `categories` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT DEFAULT NULL,
                `image_path` VARCHAR(255) DEFAULT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_sort_order` (`sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "categories: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: inventory
    // ========================================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `inventory` (
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
                `status` ENUM('new', 'active', 'sold', 'withdrawn', 'expired') NOT NULL DEFAULT 'new',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_status` (`status`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "inventory: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: transactions
    // ========================================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `transactions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `type` ENUM('deposit', 'withdraw', 'case_open', 'item_sell', 'item_buy', 'bonus', 'referral', 'transfer') NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `balance_before` DECIMAL(10,2) NOT NULL,
                `balance_after` DECIMAL(10,2) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `metadata` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_type` (`type`),
                INDEX `idx_created_at` (`created_at` DESC),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "transactions: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: payments
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "payments: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: pending_payments
    // ========================================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pending_payments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `payment_id` VARCHAR(255) NOT NULL,
                `gateway` VARCHAR(50) NOT NULL,
                `status` ENUM('pending', 'completed', 'failed', 'expired') NOT NULL DEFAULT 'pending',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `expires_at` DATETIME NOT NULL,
                `completed_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_payment_id` (`payment_id`),
                INDEX `idx_status` (`status`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "pending_payments: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: settings
    // ========================================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `settings` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(100) NOT NULL UNIQUE,
                `value` TEXT,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        $errors[] = "settings: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: free_cases
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "free_cases: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: free_case_items
    // ========================================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `free_case_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `free_case_id` INT UNSIGNED NOT NULL,
                `item_name` VARCHAR(255) NOT NULL,
                `item_image` VARCHAR(255) DEFAULT NULL,
                `rarity` VARCHAR(50) NOT NULL DEFAULT 'milspec',
                `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `chance` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_free_case_id` (`free_case_id`),
                FOREIGN KEY (`free_case_id`) REFERENCES `free_cases`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "free_case_items: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: daily_bonus_rewards
    // ========================================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `daily_bonus_rewards` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `day` INT NOT NULL,
                `type` VARCHAR(20) NOT NULL DEFAULT 'balance' COMMENT 'Тип награды: balance, case, promo, free_case',
                `amount` DECIMAL(10,2) DEFAULT NULL,
                `item_id` INT UNSIGNED DEFAULT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_day` (`day`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "daily_bonus_rewards: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: battle_pass_seasons
    // ========================================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `battle_pass_seasons` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT DEFAULT NULL,
                `start_date` DATETIME NOT NULL,
                `end_date` DATETIME NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "battle_pass_seasons: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: battle_pass_rewards
    // ========================================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `battle_pass_rewards` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `season_id` INT UNSIGNED NOT NULL,
                `level` INT NOT NULL,
                `type` ENUM('balance', 'case', 'item', 'xp', 'premium') NOT NULL,
                `amount` DECIMAL(10,2) DEFAULT NULL,
                `item_id` INT UNSIGNED DEFAULT NULL,
                `is_premium` TINYINT(1) NOT NULL DEFAULT 0,
                `claimed_by` TEXT DEFAULT NULL COMMENT 'JSON array user_ids',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_season_id` (`season_id`),
                INDEX `idx_level` (`level`),
                FOREIGN KEY (`season_id`) REFERENCES `battle_pass_seasons`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "battle_pass_rewards: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: promo_codes
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "promo_codes: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: promo_code_uses
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "promo_code_uses: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: live_wins
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "live_wins: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: support_tickets
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "support_tickets: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: support_messages
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "support_messages: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: referrals
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "referrals: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: withdraw_requests
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "withdraw_requests: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: contract_items
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "contract_items: " . $e->getMessage();
    }
    
    // ========================================================================
    // ТАБЛИЦА: upgrade_games
    // ========================================================================
    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        $errors[] = "upgrade_games: " . $e->getMessage();
    }
    
    return $errors;
}

function insertDefaultSettings($pdo) {
    $settings = [
        ['site_name', 'DropForge'],
        ['site_description', 'Лучший CS:GO кейс сайт'],
        ['support_email', 'support@dropforge.gg'],
        ['support_telegram', ''],
        ['upgrade_enabled', '1'],
        ['contract_enabled', '1'],
        ['battle_pass_enabled', '1'],
        ['daily_bonus_enabled', '1'],
        ['free_case_enabled', '1'],
        ['referrals_enabled', '1'],
        ['inventory_enabled', '1'],
        ['min_case_price', '0.50'],
        ['max_case_price', '100.00'],
        ['sell_price_percent', '70'],
        ['max_open_qty', '10'],
        ['transparent_rig', '1'],
        ['ref_commission', '5'],
        ['first_deposit_bonus', '20'],
        ['crypto_bonus', '5'],
        ['usd_rub_auto', '0'],
        ['usd_rub_rate', '90.00'],
        ['fk_merchant_id', ''],
        ['fk_phrase1', ''],
        ['fk_phrase2', ''],
        ['fk_mode', 'test'],
        ['ym_shopid', ''],
        ['ym_password', ''],
        ['ym_event_url', ''],
        ['ym_mode', 'test'],
        ['steam_api_key', 'F4079FAFACBF691AA299B49429430713'],
        ['registration_enabled', '1'],
        ['min_deposit', '1'],
        ['max_deposit', '10000'],
        ['custom_css', ''],
        ['footer_html', '']
    ];
    
    foreach ($settings as $setting) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO `settings` (`key`, `value`) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
            ");
            $stmt->execute($setting);
        } catch (PDOException $e) {
            // Игнорируем ошибки дубликатов
        }
    }
}

function insertDailyBonusRewards($pdo) {
    $rewards = [
        [1, 'balance', 0.50],
        [2, 'balance', 0.75],
        [3, 'case', null],
        [4, 'balance', 1.00],
        [5, 'balance', 1.50],
        [6, 'free_case', null],
        [7, 'balance', 2.00],
        [8, 'balance', 2.50],
        [9, 'case', null],
        [10, 'balance', 3.00],
        [11, 'balance', 3.50],
        [12, 'free_case', null],
        [13, 'balance', 4.00],
        [14, 'balance', 5.00]
    ];
    
    foreach ($rewards as $reward) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO `daily_bonus_rewards` (`day`, `type`, `amount`) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`)
            ");
            $stmt->execute($reward);
        } catch (PDOException $e) {
            // Игнорируем
        }
    }
}

function insertDemoData($pdo) {
    // Добавляем категории
    $categories = [
        ['Budget', 'Кейсы до $1', null, 1],
        ['Standard', 'Кейсы от $1 до $10', null, 2],
        ['Premium', 'Кейсы от $10 до $50', null, 3],
        ['Legendary', 'Кейсы от $50', null, 4]
    ];
    
    foreach ($categories as $cat) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO `categories` (`name`, `description`, `sort_order`, `is_active`)
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)
            ");
            $stmt->execute($cat);
        } catch (PDOException $e) {}
    }
}

// =============================================================================
// ОБРАБОТКА ФОРМ
// =============================================================================

$errors = [];
$success = [];
$dbConfig = [];
$adminConfig = [];
$siteConfig = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // -------------------------------------------------------------------------
    // ШАГ: НАСТРОЙКА БАЗЫ ДАННЫХ
    // -------------------------------------------------------------------------
    if ($action === 'database') {
        $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $useRoot = isset($_POST['use_root']) && $_POST['use_root'] === '1';
        $dbName = trim($_POST['db_name'] ?? 'dropforge');
        
        if ($useRoot) {
            // Простой режим: используем root для всего
            $dbRootUser = trim($_POST['db_root_user'] ?? 'root');
            $dbRootPass = trim($_POST['db_root_pass'] ?? '');
            
            // Тестируем подключение под root
            $test = testDatabaseConnection($dbHost, $dbPort, $dbRootUser, $dbRootPass);
            if (!$test['success']) {
                $errors[] = "Не удалось подключиться к MySQL: " . $test['error'];
            } else {
                $pdo = $test['pdo'];
                
                // Проверяем версию MySQL
                $mysqlVersion = getMySQLVersion($pdo);
                if (version_compare($mysqlVersion, $REQUIRED_MYSQL_VERSION, '<')) {
                    $errors[] = "Требуется MySQL версии $REQUIRED_MYSQL_VERSION или выше. Ваша версия: $mysqlVersion";
                }
                
                if (empty($errors)) {
                    // Создаём базу данных
                    if (!createDatabase($pdo, $dbName)) {
                        $errors[] = "Не удалось создать базу данных '$dbName'";
                    }
                    
                    if (empty($errors)) {
                        // Тестируем подключение к созданной БД под root
                        $dbPdo = testDatabaseConnection($dbHost, $dbPort, $dbRootUser, $dbRootPass, $dbName);
                        if ($dbPdo['success']) {
                            $_SESSION['db_config'] = [
                                'host' => $dbHost,
                                'port' => $dbPort,
                                'name' => $dbName,
                                'user' => $dbRootUser,
                                'pass' => $dbRootPass,
                                'use_root' => true
                            ];
                            header('Location: ?step=tables');
                            exit;
                        } else {
                            $errors[] = "Не удалось подключиться к базе данных: " . $dbPdo['error'];
                        }
                    }
                }
            }
        } else {
            // Продвинутый режим: создаём отдельного пользователя
            $dbRootUser = trim($_POST['db_root_user'] ?? 'root');
            $dbRootPass = trim($_POST['db_root_pass'] ?? '');
            $dbUser = trim($_POST['db_user'] ?? 'dropforge');
            $dbPass = trim($_POST['db_pass'] ?? generatePassword(16));
            
            // Тестируем подключение под root
            $test = testDatabaseConnection($dbHost, $dbPort, $dbRootUser, $dbRootPass);
            if (!$test['success']) {
                $errors[] = "Не удалось подключиться к MySQL: " . $test['error'];
            } else {
                $pdo = $test['pdo'];
                
                // Проверяем версию MySQL
                $mysqlVersion = getMySQLVersion($pdo);
                if (version_compare($mysqlVersion, $REQUIRED_MYSQL_VERSION, '<')) {
                    $errors[] = "Требуется MySQL версии $REQUIRED_MYSQL_VERSION или выше. Ваша версия: $mysqlVersion";
                }
                
                if (empty($errors)) {
                    // Создаём базу данных
                    if (!createDatabase($pdo, $dbName)) {
                        $errors[] = "Не удалось создать базу данных '$dbName'";
                    }
                    
                    // Создаём пользователя (опционально)
                    if (!empty($dbUser) && !empty($dbPass)) {
                        createUser($pdo, $dbUser, $dbPass);
                        grantPrivileges($pdo, $dbName, $dbUser);
                    }
                    
                    if (empty($errors)) {
                        // Тестируем подключение к созданной БД
                        $userPdo = testDatabaseConnection($dbHost, $dbPort, $dbUser, $dbPass, $dbName);
                        if ($userPdo['success']) {
                            $_SESSION['db_config'] = [
                                'host' => $dbHost,
                                'port' => $dbPort,
                                'name' => $dbName,
                                'user' => $dbUser,
                                'pass' => $dbPass,
                                'use_root' => false
                            ];
                            header('Location: ?step=tables');
                            exit;
                        } else {
                            // Пробуем подключиться под root к новой БД
                            $rootPdo = testDatabaseConnection($dbHost, $dbPort, $dbRootUser, $dbRootPass, $dbName);
                            if ($rootPdo['success']) {
                                $_SESSION['db_config'] = [
                                    'host' => $dbHost,
                                    'port' => $dbPort,
                                    'name' => $dbName,
                                    'user' => $dbRootUser,
                                    'pass' => $dbRootPass,
                                    'use_root' => true
                                ];
                                header('Location: ?step=tables');
                                exit;
                            }
                            $errors[] = "Не удалось подключиться к базе данных: " . $userPdo['error'];
                        }
                    }
                }
            }
        }
        
        $dbConfig = $_POST;
    }
    
    // -------------------------------------------------------------------------
    // ШАГ: СОЗДАНИЕ ТАБЛИЦ
    // -------------------------------------------------------------------------
    if ($action === 'tables') {
        if (!isset($_SESSION['db_config'])) {
            $errors[] = "Сначала настройте базу данных";
        } else {
            $db = $_SESSION['db_config'];
            $test = testDatabaseConnection($db['host'], $db['port'], $db['user'], $db['pass'], $db['name']);
            
            if (!$test['success']) {
                $errors[] = "Ошибка подключения к базе данных: " . $test['error'];
            } else {
                $pdo = $test['pdo'];
                $tableErrors = createTables($pdo);
                
                if (!empty($tableErrors)) {
                    $errors = array_merge($errors, $tableErrors);
                } else {
                    // Вставляем настройки по умолчанию
                    insertDefaultSettings($pdo);
                    insertDailyBonusRewards($pdo);
                    insertDemoData($pdo);
                    
                    $_SESSION['pdo'] = $pdo;
                    header('Location: ?step=settings');
                    exit;
                }
            }
        }
    }
    
    // -------------------------------------------------------------------------
    // ШАГ: НАСТРОЙКИ САЙТА
    // -------------------------------------------------------------------------
    if ($action === 'settings') {
        $siteConfig = [
            'site_name' => trim($_POST['site_name'] ?? 'DropForge'),
            'site_url' => trim($_POST['site_url'] ?? ''),
            'steam_api_key' => trim($_POST['steam_api_key'] ?? '')
        ];
        
        // Автоопределение URL
        if (empty($siteConfig['site_url'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $siteConfig['site_url'] = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        
        // Сохраняем в сессию
        $_SESSION['site_config'] = $siteConfig;
        
        // Обновляем настройки в БД
        if (isset($_SESSION['pdo'])) {
            $pdo = $_SESSION['pdo'];
            $stmt = $pdo->prepare("UPDATE `settings` SET `value` = ? WHERE `key` = 'site_name'");
            $stmt->execute([$siteConfig['site_name']]);
            $stmt = $pdo->prepare("UPDATE `settings` SET `value` = ? WHERE `key` = 'steam_api_key'");
            $stmt->execute([$siteConfig['steam_api_key']]);
        }
        
        header('Location: ?step=admin');
        exit;
    }
    
    // -------------------------------------------------------------------------
    // ШАГ: АДМИНИСТРАТОР
    // -------------------------------------------------------------------------
    if ($action === 'admin') {
        $adminConfig = [
            'username' => trim($_POST['admin_username'] ?? 'admin'),
            'password' => trim($_POST['admin_password'] ?? generatePassword(16)),
            'email' => trim($_POST['admin_email'] ?? '')
        ];
        
        // Валидация
        if (strlen($adminConfig['username']) < 3) {
            $errors[] = "Имя пользователя должно быть не менее 3 символов";
        }
        if (strlen($adminConfig['password']) < 8) {
            $errors[] = "Пароль должен быть не менее 8 символов";
        }
        
        if (empty($errors) && isset($_SESSION['pdo'])) {
            $pdo = $_SESSION['pdo'];
            
            // Создаём администратора
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO `admin_users` (`username`, `password_hash`, `email`, `permissions`)
                    VALUES (?, ?, ?, ?)
                ");
                $passwordHash = password_hash($adminConfig['password'], PASSWORD_DEFAULT);
                $permissions = json_encode(['all' => true]);
                $stmt->execute([$adminConfig['username'], $passwordHash, $adminConfig['email'], $permissions]);
                
                $_SESSION['admin_config'] = $adminConfig;
                header('Location: ?step=finish');
                exit;
            } catch (PDOException $e) {
                $errors[] = "Ошибка создания администратора: " . $e->getMessage();
            }
        }
    }
    
    // -------------------------------------------------------------------------
    // ШАГ: СОЗДАНИЕ CONFIG.PHP
    // -------------------------------------------------------------------------
    if ($action === 'generate_config') {
        if (!isset($_SESSION['db_config']) || !isset($_SESSION['site_config'])) {
            $errors[] = "Ошибка: данные конфигурации не найдены";
        } else {
            $db = $_SESSION['db_config'];
            $site = $_SESSION['site_config'];
            
            $configContent = generateConfigFile($db, $site);
            
            // Пытаемся записать файл в нескольких местах
            $configPaths = [
                __DIR__ . '/config/config.php',              // /DropForge/config/config.php
                __DIR__ . '/config.php',                      // /config/config.php
                dirname(__DIR__) . '/config/config.php',      // /var/www/site/config/config.php
                __DIR__ . '/../config/config.php',            // relative
            ];
            
            $written = false;
            foreach ($configPaths as $configPath) {
                $configDir = dirname($configPath);
                if (!is_dir($configDir)) {
                    if (!mkdir($configDir, 0755, true)) {
                        continue;
                    }
                }
                
                if (is_writable($configDir) || is_writable($configPath)) {
                    if (file_put_contents($configPath, $configContent) !== false) {
                        // Проверяем что файл действительно создан
                        clearstatcache(true, $configPath);
                        if (file_exists($configPath) && filesize($configPath) > 100) {
                            $written = $configPath;
                            break;
                        }
                    }
                }
            }
            
            if ($written) {
                $_SESSION['config_generated'] = true;
                $_SESSION['config_path'] = $written;
                // Очищаем конфиг из сессии, он уже на диске
                unset($_SESSION['config_download']);
                header('Location: ?step=finish&action=config_auto');
                exit;
            } else {
                // Не удалось записать — предлагаем скачать
                $_SESSION['config_download'] = $configContent;
                header('Location: ?step=finish&action=config_manual');
                exit;
            }
        }
    }
}

// =============================================================================
// ГЕНЕРАЦИЯ КОНФИГ-ФАЙЛА
// =============================================================================

function generateConfigFile($db, $site) {
    $configContent = "<?php\n";
    $configContent .= "/**\n";
    $configContent .= " * DropForge — Configuration\n";
    $configContent .= " * Сгенерировано установщиком " . date('Y-m-d H:i:s') . "\n";
    $configContent .= " */\n\n";
    $configContent .= "define('DB_HOST',     '" . addslashes($db['host']) . "');\n";
    $configContent .= "define('DB_NAME',     '" . addslashes($db['name']) . "');\n";
    $configContent .= "define('DB_USER',     '" . addslashes($db['user']) . "');\n";
    $configContent .= "define('DB_PASS',     '" . addslashes($db['pass']) . "');\n";
    $configContent .= "define('DB_CHARSET',  'utf8mb4');\n\n";
    $configContent .= "define('SITE_URL',    '" . addslashes($site['site_url']) . "');\n";
    $configContent .= "define('STEAM_API_KEY', '" . addslashes($site['steam_api_key']) . "');\n";
    $configContent .= "define('SITE_NAME',   '" . addslashes($site['site_name']) . "');\n\n";
    $configContent .= "define('UPLOAD_DIR',  __DIR__ . '/../uploads/');\n";
    $configContent .= "define('ASSETS_DIR',  __DIR__ . '/../public/assets/');\n\n";
    $configContent .= "// Payment gateways configured in admin panel\n\n";
    $configContent .= "define('RAIRITY_ORDER', [\n";
    $configContent .= "    'consumer', 'industrial', 'milspec', 'restricted',\n";
    $configContent .= "    'classified', 'covert', 'extraordinary', 'contraband'\n";
    $configContent .= "]);\n\n";
    $configContent .= "define('RAIRITY_COLORS', [\n";
    $configContent .= "    'consumer'      => '#b0c3d9',\n";
    $configContent .= "    'industrial'    => '#5e98d9',\n";
    $configContent .= "    'milspec'       => '#4b69ff',\n";
    $configContent .= "    'restricted'    => '#8847ff',\n";
    $configContent .= "    'classified'    => '#d32ce6',\n";
    $configContent .= "    'covert'        => '#eb4b4b',\n";
    $configContent .= "    'extraordinary' => '#e4ae39',\n";
    $configContent .= "    'contraband'    => '#de9b35'\n";
    $configContent .= "]);\n";
    
    return $configContent;
}

// =============================================================================
// HTML ШАБЛОНЫ
// =============================================================================

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DropForge — Установка</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #fff;
            padding: 2rem;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #e4ae39, #d32ce6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header p {
            color: #888;
        }
        
        .progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .progress::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #333;
            transform: translateY(-50%);
            z-index: 0;
        }
        
        .progress-step {
            position: relative;
            z-index: 1;
            text-align: center;
            flex: 1;
        }
        
        .progress-step__circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .progress-step--active .progress-step__circle {
            background: linear-gradient(135deg, #e4ae39, #d32ce6);
        }
        
        .progress-step--completed .progress-step__circle {
            background: #4CAF50;
        }
        
        .progress-step__label {
            font-size: 0.75rem;
            color: #888;
        }
        
        .progress-step--active .progress-step__label {
            color: #fff;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .card h2 {
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #ccc;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e4ae39;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #888;
            font-size: 0.85rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn--primary {
            background: linear-gradient(135deg, #e4ae39, #d32ce6);
            color: #fff;
        }
        
        .btn--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(228, 174, 57, 0.4);
        }
        
        .btn--secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        .btn--secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert--error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            color: #ffcdd2;
        }
        
        .alert--success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4CAF50;
            color: #c8e6c9;
        }
        
        .alert--warning {
            background: rgba(255, 152, 0, 0.2);
            border: 1px solid #ff9800;
            color: #ffe0b2;
        }
        
        .checklist {
            list-style: none;
        }
        
        .checklist__item {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .checklist__item:last-child {
            border-bottom: none;
        }
        
        .checklist__icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }
        
        .checklist__icon--ok {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        
        .checklist__icon--fail {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }
        
        .info-box {
            background: rgba(228, 174, 57, 0.1);
            border: 1px solid rgba(228, 174, 57, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-box__title {
            font-weight: 600;
            color: #e4ae39;
            margin-bottom: 0.5rem;
        }
        
        .credentials {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        
        .credentials__row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .credentials__row:last-child {
            border-bottom: none;
        }
        
        .credentials__label {
            color: #888;
        }
        
        .credentials__value {
            font-family: monospace;
            color: #4CAF50;
        }
        
        .code-block {
            background: rgba(0, 0, 0, 0.5);
            border-radius: 8px;
            padding: 1rem;
            overflow-x: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.875rem;
            color: #ccc;
            margin: 1rem 0;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-2 {
            margin-top: 2rem;
        }
        
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>DropForge Installer</h1>
            <p>Версия установщика: <?= $INSTALLER_VERSION ?></p>
        </div>
        
        <!-- ПРОГРЕСС -->
        <div class="progress">
            <?php 
            $stepKeys = array_keys($steps);
            $currentStepIndex = array_search($currentStep, $stepKeys);
            
            foreach ($steps as $key => $label): 
                $stepIndex = array_search($key, $stepKeys);
                $status = '';
                if ($stepIndex < $currentStepIndex) $status = 'completed';
                elseif ($stepIndex === $currentStepIndex) $status = 'active';
            ?>
                <div class="progress-step progress-step--<?= $status ?>">
                    <div class="progress-step__circle">
                        <?= $status === 'completed' ? '✓' : ($stepIndex + 1) ?>
                    </div>
                    <div class="progress-step__label"><?= $label ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- СООБЩЕНИЯ -->
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert--error"><?= e($error) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <?php foreach ($success as $msg): ?>
                <div class="alert alert--success"><?= e($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- КАРТОЧКА ШАГА -->
        <div class="card">
            <?php
            // =================================================================
            // ШАГ 1: ДОБРО ПОЖАЛОВАТЬ
            // =================================================================
            if ($currentStep === 'welcome'):
            ?>
                <h2>Добро пожаловать в DropForge! 🎉</h2>
                
                <div class="info-box">
                    <div class="info-box__title">📋 Что вас ожидает</div>
                    <p>Этот мастер поможет вам установить сайт DropForge на ваш VPS. Процесс включает:</p>
                    <ul style="margin-top: 0.5rem; margin-left: 1.5rem; color: #ccc;">
                        <li>Проверку требований сервера</li>
                        <li>Настройку базы данных MySQL</li>
                        <li>Создание всех необходимых таблиц</li>
                        <li>Настройку основных параметров сайта</li>
                        <li>Создание учётной записи администратора</li>
                    </ul>
                </div>
                
                <div class="info-box" style="background: rgba(76, 175, 80, 0.1); border-color: rgba(76, 175, 80, 0.3);">
                    <div class="info-box__title" style="color: #4CAF50;">⚡ Требования</div>
                    <ul style="margin-top: 0.5rem; margin-left: 1.5rem; color: #ccc;">
                        <li>PHP <?= $REQUIRED_PHP_VERSION ?> или выше</li>
                        <li>MySQL <?= $REQUIRED_MYSQL_VERSION ?> или выше / MariaDB 10.3+</li>
                        <li>Расширения: pdo_mysql, json, curl, session</li>
                        <li>Доступ на запись в директорию установки</li>
                    </ul>
                </div>
                
                <div class="btn-group">
                    <a href="?step=requirements" class="btn btn--primary">Начать установку →</a>
                </div>
                
            <?php
            // =================================================================
            // ШАГ 2: ПРОВЕРКА ТРЕБОВАНИЙ
            // =================================================================
            elseif ($currentStep === 'requirements'):
                $phpVersionOk = checkPHPVersion();
                $mysqlExtOk = checkMySQLExtension();
                $jsonExtOk = checkJSONExtension();
                $curlExtOk = checkCurlExtension();
                $sessionExtOk = checkSessionExtension();
                $writableOk = checkWritableDir(__DIR__);
                
                $allOk = $phpVersionOk && $mysqlExtOk && $jsonExtOk && $curlExtOk && $sessionExtOk && $writableOk;
            ?>
                <h2>Проверка требований 🔍</h2>
                
                <ul class="checklist">
                    <li class="checklist__item">
                        <span class="checklist__icon checklist__icon--<?= $phpVersionOk ? 'ok' : 'fail' ?>">
                            <?= $phpVersionOk ? '✓' : '✗' ?>
                        </span>
                        <span>PHP <?= $REQUIRED_PHP_VERSION ?>+ (ваша версия: <?= PHP_VERSION ?>)</span>
                    </li>
                    <li class="checklist__item">
                        <span class="checklist__icon checklist__icon--<?= $mysqlExtOk ? 'ok' : 'fail' ?>">
                            <?= $mysqlExtOk ? '✓' : '✗' ?>
                        </span>
                        <span>Расширение PDO MySQL</span>
                    </li>
                    <li class="checklist__item">
                        <span class="checklist__icon checklist__icon--<?= $jsonExtOk ? 'ok' : 'fail' ?>">
                            <?= $jsonExtOk ? '✓' : '✗' ?>
                        </span>
                        <span>Расширение JSON</span>
                    </li>
                    <li class="checklist__item">
                        <span class="checklist__icon checklist__icon--<?= $curlExtOk ? 'ok' : 'fail' ?>">
                            <?= $curlExtOk ? '✓' : '✗' ?>
                        </span>
                        <span>Расширение cURL</span>
                    </li>
                    <li class="checklist__item">
                        <span class="checklist__icon checklist__icon--<?= $sessionExtOk ? 'ok' : 'fail' ?>">
                            <?= $sessionExtOk ? '✓' : '✗' ?>
                        </span>
                        <span>Расширение Session</span>
                    </li>
                    <li class="checklist__item">
                        <span class="checklist__icon checklist__icon--<?= $writableOk ? 'ok' : 'fail' ?>">
                            <?= $writableOk ? '✓' : '✗' ?>
                        </span>
                        <span>Права на запись в директорию</span>
                    </li>
                </ul>
                
                <?php if ($allOk): ?>
                    <div class="alert alert--success mt-2">
                        Все требования выполнены! Можно продолжать установку.
                    </div>
                    <div class="btn-group">
                        <a href="?step=database" class="btn btn--primary">Продолжить →</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert--error mt-2">
                        Некоторые требования не выполнены. Пожалуйста, исправьте проблемы перед продолжением.
                    </div>
                <?php endif; ?>
                
            <?php
            // =================================================================
            // ШАГ 3: НАСТРОЙКА БАЗЫ ДАННЫХ
            // =================================================================
            elseif ($currentStep === 'database'):
            ?>
                <h2>Настройка базы данных 🗄️</h2>
                
                <div class="info-box">
                    <div class="info-box__title">ℹ️ Информация</div>
                    <p>Установщик создаст базу данных и подключит сайт. Выберите режим:</p>
                </div>
                
                <!-- Выбор режима -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <label style="cursor: pointer;">
                        <input type="radio" name="mode" value="simple" checked 
                               onchange="document.getElementById('advancedFields').style.display='none'; document.getElementById('simpleFields').style.display='block';"
                               style="margin-right: 0.5rem;">
                        <div style="border: 2px solid #e4ae39; border-radius: 8px; padding: 1rem; background: rgba(228, 174, 57, 0.1);">
                            <strong>🚀 Простой режим</strong><br>
                            <small style="color: #ccc;">Используем root — один шаг</small>
                        </div>
                    </label>
                    <label style="cursor: pointer;">
                        <input type="radio" name="mode" value="advanced"
                               onchange="document.getElementById('advancedFields').style.display='block'; document.getElementById('simpleFields').style.display='none';"
                               style="margin-right: 0.5rem;">
                        <div style="border: 2px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 1rem; background: rgba(255,255,255,0.05);">
                            <strong>🔐 Продвинутый режим</strong><br>
                            <small style="color: #ccc;">Создаём отдельного пользователя</small>
                        </div>
                    </label>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="database">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>MySQL хост</label>
                            <input type="text" name="db_host" value="<?= e($dbConfig['db_host'] ?? '127.0.0.1') ?>" required>
                            <small>Обычно 127.0.0.1 или localhost</small>
                        </div>
                        <div class="form-group">
                            <label>Порт</label>
                            <input type="text" name="db_port" value="<?= e($dbConfig['db_port'] ?? '3306') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Имя базы данных</label>
                        <input type="text" name="db_name" value="<?= e($dbConfig['db_name'] ?? 'dropforge') ?>" required>
                        <small>Придумайте имя для базы данных</small>
                    </div>
                    
                    <!-- Простой режим -->
                    <div id="simpleFields">
                        <hr style="border-color: rgba(255,255,255,0.1); margin: 1.5rem 0;">
                        <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">🔑 Root доступ к MySQL</h3>
                        <div class="form-group">
                            <label>Root пользователь</label>
                            <input type="text" name="db_root_user" value="<?= e($dbConfig['db_root_user'] ?? 'root') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Root пароль</label>
                            <input type="password" name="db_root_pass" value="<?= e($dbConfig['db_root_pass'] ?? '') ?>">
                            <small>Пароль root пользователя MySQL. Если пароль пустой — оставьте пустым.</small>
                        </div>
                    </div>
                    
                    <!-- Продвинутый режим -->
                    <div id="advancedFields" style="display: none;">
                        <hr style="border-color: rgba(255,255,255,0.1); margin: 1.5rem 0;">
                        <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">🔑 Root доступ (для создания БД)</h3>
                        <div class="form-group">
                            <label>Root пользователь</label>
                            <input type="text" name="db_root_user" value="<?= e($dbConfig['db_root_user'] ?? 'root') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Root пароль</label>
                            <input type="password" name="db_root_pass" value="<?= e($dbConfig['db_root_pass'] ?? '') ?>">
                        </div>
                        
                        <h3 style="font-size: 1.1rem; margin: 1.5rem 0 1rem;">👤 Новый пользователь БД</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Имя пользователя</label>
                                <input type="text" name="db_user" value="<?= e($dbConfig['db_user'] ?? 'dropforge') ?>">
                            </div>
                            <div class="form-group">
                                <label>Пароль</label>
                                <input type="text" name="db_pass" value="<?= e($dbConfig['db_pass'] ?? '') ?>">
                                <small>Оставьте пустым для генерации случайного</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <a href="?step=requirements" class="btn btn--secondary">← Назад</a>
                        <button type="submit" class="btn btn--primary">Создать базу данных →</button>
                    </div>
                </form>
                
            <?php
            // =================================================================
            // ШАГ 4: СОЗДАНИЕ ТАБЛИЦ
            // =================================================================
            elseif ($currentStep === 'tables'):
            ?>
                <h2>Создание таблиц 📊</h2>
                
                <p>Установщик создаст все необходимые таблицы в базе данных...</p>
                
                <form method="POST" id="tablesForm">
                    <input type="hidden" name="action" value="tables">
                    
                    <div class="info-box">
                        <div class="info-box__title">Будут созданы таблицы:</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem; margin-top: 0.5rem; font-size: 0.875rem; color: #ccc;">
                            <div>• users</div>
                            <div>• admin_users</div>
                            <div>• cases</div>
                            <div>• case_items</div>
                            <div>• categories</div>
                            <div>• inventory</div>
                            <div>• transactions</div>
                            <div>• payments</div>
                            <div>• pending_payments</div>
                            <div>• settings</div>
                            <div>• free_cases</div>
                            <div>• free_case_items</div>
                            <div>• daily_bonus_rewards</div>
                            <div>• battle_pass_seasons</div>
                            <div>• battle_pass_rewards</div>
                            <div>• promo_codes</div>
                            <div>• promo_code_uses</div>
                            <div>• live_wins</div>
                            <div>• support_tickets</div>
                            <div>• support_messages</div>
                            <div>• referrals</div>
                            <div>• withdraw_requests</div>
                            <div>• contract_items</div>
                            <div>• upgrade_games</div>
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn--primary">Создать таблицы →</button>
                    </div>
                </form>
                
            <?php
            // =================================================================
            // ШАГ 5: НАСТРОЙКИ САЙТА
            // =================================================================
            elseif ($currentStep === 'settings'):
            ?>
                <h2>Настройки сайта ⚙️</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="settings">
                    
                    <div class="form-group">
                        <label>Название сайта</label>
                        <input type="text" name="site_name" value="<?= e($siteConfig['site_name'] ?? 'DropForge') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>URL сайта</label>
                        <input type="url" name="site_url" value="<?= e($siteConfig['site_url'] ?? '') ?>" placeholder="https://example.com">
                        <small>Оставьте пустым для автоопределения</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Steam API Key</label>
                        <input type="text" name="steam_api_key" value="<?= e($siteConfig['steam_api_key'] ?? '') ?>" placeholder="Ваш Steam Web API Key">
                        <small>Получить можно на <a href="https://steamcommunity.com/dev/apikey" target="_blank" style="color: #e4ae39;">steamcommunity.com/dev/apikey</a></small>
                    </div>
                    
                    <div class="btn-group">
                        <a href="?step=tables" class="btn btn--secondary">← Назад</a>
                        <button type="submit" class="btn btn--primary">Сохранить →</button>
                    </div>
                </form>
                
            <?php
            // =================================================================
            // ШАГ 6: АДМИНИСТРАТОР
            // =================================================================
            elseif ($currentStep === 'admin'):
            ?>
                <h2>Создание администратора 👤</h2>
                
                <div class="info-box">
                    <div class="info-box__title">⚠️ Важно</div>
                    <p>Запомните или сохраните данные администратора. Они понадобятся для входа в панель управления.</p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="admin">
                    
                    <div class="form-group">
                        <label>Имя пользователя</label>
                        <input type="text" name="admin_username" value="<?= e($adminConfig['username'] ?? 'admin') ?>" required minlength="3">
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="admin_email" value="<?= e($adminConfig['email'] ?? '') ?>" placeholder="admin@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Пароль</label>
                        <input type="text" name="admin_password" value="<?= e($adminConfig['password'] ?? generatePassword(16)) ?>" required minlength="8">
                        <small>Минимум 8 символов</small>
                    </div>
                    
                    <div class="btn-group">
                        <a href="?step=settings" class="btn btn--secondary">← Назад</a>
                        <button type="submit" class="btn btn--primary">Создать администратора →</button>
                    </div>
                </form>
                
            <?php
            // =================================================================
            // ШАГ 7: ЗАВЕРШЕНИЕ
            // =================================================================
            elseif ($currentStep === 'finish'):
                $action = $_GET['action'] ?? '';
                $configGenerated = $_SESSION['config_generated'] ?? false;
                $configDownload = $_SESSION['config_download'] ?? null;
                $configPath = $_SESSION['config_path'] ?? null;
                
                if ($action === '' && !$configGenerated) {
                    // Автоматически генерируем config.php
                    echo '<script>window.location.href = "?step=finish&action=generate_config";</script>';
                    echo '<p>Генерация конфигурационного файла...</p>';
                    exit;
                }
            ?>
                <h2>Установка завершена! 🎉</h2>
                
                <div class="alert alert--success">
                    Сайт DropForge успешно установлен!
                </div>
                
                <?php if (isset($_SESSION['admin_config'])): ?>
                    <div class="credentials">
                        <div class="info-box__title">🔐 Данные администратора</div>
                        <div class="credentials__row">
                            <span class="credentials__label">Логин:</span>
                            <span class="credentials__value"><?= e($_SESSION['admin_config']['username']) ?></span>
                        </div>
                        <div class="credentials__row">
                            <span class="credentials__label">Пароль:</span>
                            <span class="credentials__value"><?= e($_SESSION['admin_config']['password']) ?></span>
                        </div>
                        <?php if (!empty($_SESSION['admin_config']['email'])): ?>
                        <div class="credentials__row">
                            <span class="credentials__label">Email:</span>
                            <span class="credentials__value"><?= e($_SESSION['admin_config']['email']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="alert alert--warning" style="margin-top: 1rem;">
                        ⚠️ <strong>Сохраните эти данные!</strong> Они понадобятся для входа в админ-панель.
                    </div>
                <?php endif; ?>
                
                <?php if ($action === 'config_auto'): ?>
                    <div class="alert alert--success">
                        ✅ Файл <code>config/config.php</code> успешно создан автоматически!
                        <?php if ($configPath): ?>
                            <br><small>Файл записан в: <code><?= e($configPath) ?></code></small>
                        <?php endif; ?>
                    </div>
                <?php elseif ($action === 'config_manual' || $configDownload): ?>
                    <div class="alert alert--warning">
                        ⚠️ Не удалось автоматически записать config.php (нет прав на запись). 
                        <strong>Скопируйте файл вручную:</strong>
                    </div>
                    
                    <div class="info-box">
                        <div class="info-box__title">📝 Инструкция (3 шага):</div>
                        <ol style="margin-left: 1.5rem; color: #ccc; margin-top: 0.5rem;">
                            <li>Скачайте файл <a href="javascript:void(0)" onclick="downloadConfig()" style="color: #e4ae39;">config.php ↓</a></li>
                            <li>Загрузите его в папку <code>config/</code> на вашем сервере</li>
                            <li>Или создайте файл <code>config/config.php</code> и вставьте содержимое ниже</li>
                        </ol>
                    </div>
                    
                    <div class="code-block"><?= e($configDownload) ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['db_config'])): ?>
                    <div class="credentials">
                        <div class="info-box__title">🗄️ Параметры базы данных</div>
                        <div class="credentials__row">
                            <span class="credentials__label">Хост:</span>
                            <span class="credentials__value"><?= e($_SESSION['db_config']['host']) ?></span>
                        </div>
                        <div class="credentials__row">
                            <span class="credentials__label">База данных:</span>
                            <span class="credentials__value"><?= e($_SESSION['db_config']['name']) ?></span>
                        </div>
                        <div class="credentials__row">
                            <span class="credentials__label">Пользователь:</span>
                            <span class="credentials__value"><?= e($_SESSION['db_config']['user']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="info-box" style="background: rgba(76, 175, 80, 0.1); border-color: rgba(76, 175, 80, 0.3);">
                    <div class="info-box__title" style="color: #4CAF50;">📁 Что делать дальше:</div>
                    <ol style="margin-left: 1.5rem; color: #ccc; margin-top: 0.5rem;">
                        <li>Убедитесь что <code>install.php</code> удалён (безопасность!)</li>
                        <li>Войдите в панель администратора: <code>/admin/index.php</code></li>
                        <li>Настройте платёжные системы в админ-панели</li>
                        <li>Добавьте кейсы и предметы</li>
                    </ol>
                </div>
                
                <div class="btn-group">
                    <a href="/admin/index.php" class="btn btn--primary">Перейти в админ-панель →</a>
                </div>
                
                <script>
                function downloadConfig() {
                    var content = <?= json_encode($configDownload ?? '') ?>;
                    var blob = new Blob([content], {type: 'text/php'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'config.php';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }
                </script>
                
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-2" style="color: #666; font-size: 0.875rem;">
            DropForge Installer v<?= $INSTALLER_VERSION ?> | © <?= date('Y') ?> DropForge
        </div>
    </div>
</body>
</html>
<?php
// Очистка сессии после завершения (опционально)
if ($currentStep === 'finish' && isset($_GET['action']) && $_GET['action'] === 'config_done') {
    // Можно очистить чувствительные данные из сессии
    // unset($_SESSION['admin_config']['password']);
}
?>
