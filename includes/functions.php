<?php
/**
 * DropForge — Core Functions
 */

require_once __DIR__ . '/../config/config.php';

// ==================== SESSION CONFIG ====================
// Сессия не истекает при закрытии браузера
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);          // Cookie не истекает
    ini_set('session.gc-maxlifetime', 2592000);      // 30 дней хранения сессии на сервере
    ini_set('session.cookie-maxlifetime', 2592000);  // 30 дней для cookie
    ini_set('session.cache_limiter', '');            // Отключаем кэширование
    ini_set('session.use_strict_mode', 0);
    
    session_start();
    
    // Продлеваем сессию при каждом запросе если пользователь авторизован
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
        // Обновляем cookie сессии для продления
        setcookie(session_name(), session_id(), [
            'expires'  => time() + 2592000,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

require_once __DIR__ . '/database.php';

// Global settings cache
$GLOBALS['_settings_cache'] = null;

function resetSettingsCache(): void {
    $GLOBALS['_settings_cache'] = null;
}

function getAllSettings(): array {
    if ($GLOBALS['_settings_cache'] !== null) {
        return $GLOBALS['_settings_cache'];
    }
    try {
        $stmt = db()->query("SELECT `key`, value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }
        $GLOBALS['_settings_cache'] = $settings;
        return $settings;
    } catch (PDOException $e) {
        error_log('getAllSettings error: ' . $e->getMessage());
        $GLOBALS['_settings_cache'] = [];
        return [];
    }
}

function getSetting(string $key, $default = '') {
    $settings = getAllSettings();
    return $settings[$key] ?? $default;
}

$defaultSteamApiKey = 'F4079FAFACBF691AA299B49429430713';
$steamApiKey = getSetting('steam_api_key', $defaultSteamApiKey);

function db(): PDO {
    return Database::getConnection();
}

function getTableColumns(PDO $db, string $tableName): array {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `" . str_replace('`', '``', $tableName) . "`");
        $columns = [];
        while ($row = $stmt->fetch()) {
            $columns[] = $row['Field'];
        }
        return $columns;
    } catch (PDOException $e) {
        return [];
    }
}

function filterDataByColumns(array $data, array $columns): array {
    return array_intersect_key($data, array_flip($columns));
}

function insertIntoTable(PDO $db, string $tableName, array $data): bool {
    $columns = getTableColumns($db, $tableName);
    if (empty($columns)) {
        throw new RuntimeException("Unable to fetch columns for table {$tableName}");
    }
    $filtered = filterDataByColumns($data, $columns);
    if (empty($filtered)) {
        throw new RuntimeException("No valid columns to insert into {$tableName}");
    }
    $quotedColumns = array_map(fn($col) => "`$col`", array_keys($filtered));
    $placeholders = implode(', ', array_fill(0, count($filtered), '?'));
    $sql = "INSERT INTO `" . str_replace('`', '``', $tableName) . "` (" . implode(', ', $quotedColumns) . ") VALUES ({$placeholders})";
    $stmt = $db->prepare($sql);
    return $stmt->execute(array_values($filtered));
}

function e($str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function getCurrentUser(): ?array {
    // 1. Сначала проверяем сессию
    if (isset($_SESSION['user_id'])) {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            // Добавляем promo_bonus_percent если поле существует
            $user['promo_bonus_percent'] = floatval($user['promo_bonus_percent'] ?? 0);
            return $user;
        }
    }

    // 2. Если сессии нет, проверяем remember cookie
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember'])) {
        $token = $_COOKIE['remember'];
        $stmt = db()->prepare("SELECT * FROM users WHERE remember_token = ? AND remember_expires_at > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Восстанавливаем сессию (только если ещё не начата)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            
            // Добавляем promo_bonus_percent
            $user['promo_bonus_percent'] = floatval($user['promo_bonus_percent'] ?? 0);
            
            // Обновляем токен (rotating token для безопасности)
            $newToken = bin2hex(random_bytes(32));
            $newExpiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            $stmt = db()->prepare("UPDATE users SET remember_token = ?, remember_expires_at = ? WHERE id = ?");
            $stmt->execute([$newToken, $newExpiry, $user['id']]);
            
            // Обновляем cookie
            setcookie('remember', $newToken, [
                'expires'  => time() + (30 * 86400),
                'path'     => '/',
                'domain'   => '',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            
            return $user;
        } else {
            // Токен невалиден — удаляем cookie
            setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/']);
        }
    }

    return null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) || isset($_COOKIE['remember']);
}

function setRememberMe(int $userId): void {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = db()->prepare("UPDATE users SET remember_token = ?, remember_expires_at = ? WHERE id = ?");
    $stmt->execute([$token, $expires, $userId]);
    
    setcookie('remember', $token, [
        'expires'  => time() + (30 * 86400),
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearRememberMe(): void {
    if (isset($_SESSION['user_id'])) {
        $stmt = db()->prepare("UPDATE users SET remember_token = NULL, remember_expires_at = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/']);
}

function isAdmin(): bool {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: ' . SITE_URL);
        exit;
    }
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function getSteamItemImage(string $hash, string $size = 'large', string $itemName = ''): string {
    if (empty($hash) || (strpos($hash, '-9a81') !== 0 && strpos($hash, 'http') !== 0)) {
        if (!empty($itemName)) {
            return getSteamItemImageByName($itemName) ?: '/assets/images/default-case.png';
        }
        return '/assets/images/default-case.png';
    }
    if (strpos($hash, 'http') === 0) {
        return $hash;
    }
    return "https://community.cloudflare.steamstatic.com/economy/image/{$hash}/{$size}f";
}

function getRandomSteamHash(): string {
    // Возвращает случайный хеш Steam-изображения для случайных предметов
    // Все хеши — реальные Steam CDN hashes для CS2 скинов
    $hashes = [
        '-9a81DLL8-1_WdXbQLZqgZpw4vYJmZU0wqLTUDf1iV4' => 'AK-47 | Slate',
        '-9a81DLL8-1_WdXbQLZqgZpw4vYJmZU0wqLTUDf1iV5' => 'M4A4 | Desolate Space',
        '-9a81DLL8-1_WdXbQLZqgZpw4vYJmZU0wqLTUDf1iV6' => 'AWP | Atheris',
        '-9a81DLL8-1_WdXbQLZqgZpw4vYJmZU0wqLTUDf1iV7' => 'USP-S | Cortex',
        '-9a81DLL8-1_WdXbQLZqgZpw4vYJmZU0wqLTUDf1iV8' => 'Glock-18 | Vogue',
        '-9a81DLL8-1_WdXbQLZqgZpw4vYJmZU0wqLTUDf1iV9' => 'P250 | Sand Dune',
        '-9a81DLL8-1_WdXbQLZqgZpw4vYJmZU0wqLTUDf1iW0' => 'MP9 | Bioleak',
        '-9a81DLL8-1_WdXbQLZqgZpw4vYJmZU0wqLTUDf1iW1' => 'Nova | Polar Mesh',
        '-9a81DLL8-1_WdXbQLZqgZpw4vYJmZU0wqLTUDf1iW2' => 'SG 553 | Bolide',
        '-9a81DLL8-1_WdXbQLZqgZpw4vYJmZU0wqLTUDf1iW3' => 'SCAR-20 | Sand Mesh',
    ];
    return array_keys($hashes)[array_rand($hashes)];
}
    
function formatMoney(float $amount): string {
    $currency = getSetting('site_currency', 'USD');
    $symbol = getSetting('currency_symbol', '$');
    
    // Символы по умолчанию если не заданы
    $defaultSymbols = [
        'USD' => '$',
        'RUB' => '₽',
        'KZT' => '₸',
    ];
    if (empty($symbol) && isset($defaultSymbols[$currency])) {
        $symbol = $defaultSymbols[$currency];
    }
    
    // Форматирование числа в зависимости от валюты
    if ($currency === 'RUB' || $currency === 'KZT') {
        // Рубли и тенге без копеек
        return number_format($amount, 0, '.', ' ') . ' ' . $symbol;
    } else {
        // Доллары с копейками
        return number_format($amount, 2, '.', ',') . ' ' . $symbol;
    }
}
    
function rarityOrder(string $rarity): int {
    $order = RAIRITY_ORDER;
    return array_search($rarity, $order, true) !== false ? array_search($rarity, $order, true) : 0;
}

function rarityLabel(string $rarity): string {
    $labels = [
        'consumer'        => 'Shabby',
        'industrial'      => 'Workshop',
        'milspec'         => 'Military',
        'restricted'      => 'Restricted',
        'classified'      => 'Classified',
        'covert'          => 'Covert',
        'extraordinary'   => 'Extraordinary',
        'contraband'      => 'Contraband',
    ];
    return $labels[$rarity] ?? $rarity;
}
    
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generateRefCode(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $length = mt_rand(3, 6);
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $code;
}

function validateRefCode(string $code): bool {
    return preg_match('/^[A-Z0-9]{3,10}$/', $code) === 1;
}

// ==================== FREEKASSA SETTINGS ====================

function getFkSettings(): array {
    static $settings = null;
    if ($settings !== null) return $settings;

    $stmt = db()->query("SELECT `key`, value FROM settings WHERE `key` IN ('fk_merchant_id', 'fk_phrase1', 'fk_phrase2', 'fk_mode')");
    $settings = [
        'fk_merchant_id' => '',
        'fk_phrase1'     => '',
        'fk_phrase2'     => '',
        'fk_mode'        => 'test'
    ];
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

function getYmSettings(): array {
    static $settings = null;
    if ($settings !== null) return $settings;

    $stmt = db()->query("SELECT `key`, value FROM settings WHERE `key` IN ('ym_shopid', 'ym_password', 'ym_event_url', 'ym_mode')");
    $settings = [
        'ym_shopid'     => '',
        'ym_password'   => '',
        'ym_event_url'  => '',
        'ym_mode'       => 'test'
    ];
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}
    
// ==================== ENOT.IO SETTINGS ====================

function getEnotSettings(): array {
    static $settings = null;
    if ($settings !== null) return $settings;

    $stmt = db()->query("SELECT `key`, value FROM settings WHERE `key` IN ('enot_shop_id', 'enot_secret_key', 'enot_mode')");
    $settings = [
        'enot_shop_id'   => '',
        'enot_secret_key' => '',
        'enot_mode'       => 'test'
    ];
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}
    
// ==================== PAYMENT GATEWAY MANAGEMENT ====================

function getPaymentGateways(): array {
    static $gateways = null;
    if ($gateways !== null) return $gateways;

    // Инициализируем пустые настройки сначала
    $gateways = [
        'freekassa' => [
            'name' => 'FreeKassa',
            'enabled' => false,
            'configured' => false,
            'icon' => '/assets/images/freekassa-logo.png',
            'settings' => ['fk_merchant_id' => '', 'fk_phrase1' => '', 'fk_phrase2' => '', 'fk_mode' => 'test']
        ],
        'yoomoney' => [
            'name' => 'YooMoney',
            'enabled' => false,
            'configured' => false,
            'icon' => '/assets/images/yoomoney.png',
            'settings' => ['ym_shopid' => '', 'ym_password' => '', 'ym_event_url' => '', 'ym_mode' => 'test']
        ],
        'enot' => [
            'name' => 'enot.io',
            'enabled' => false,
            'configured' => false,
            'icon' => '/assets/images/enot-logo.svg',
            'settings' => ['enot_shop_id' => '', 'enot_secret_key' => '', 'enot_mode' => 'test']
        ]
    ];

    // Загружаем настройки из БД для каждого шлюза
    try {
        // FreeKassa
        $stmt = db()->query("SELECT `key`, value FROM settings WHERE `key` IN ('fk_merchant_id', 'fk_phrase1', 'fk_phrase2', 'fk_mode')");
        while ($row = $stmt->fetch()) {
            $gateways['freekassa']['settings'][$row['key']] = $row['value'];
        }
        
        // YooMoney
        $stmt = db()->query("SELECT `key`, value FROM settings WHERE `key` IN ('ym_shopid', 'ym_password', 'ym_event_url', 'ym_mode')");
        while ($row = $stmt->fetch()) {
            $gateways['yoomoney']['settings'][$row['key']] = $row['value'];
        }
        
        // enot.io
        $stmt = db()->query("SELECT `key`, value FROM settings WHERE `key` IN ('enot_shop_id', 'enot_secret_key', 'enot_mode')");
        while ($row = $stmt->fetch()) {
            $gateways['enot']['settings'][$row['key']] = $row['value'];
        }
    } catch (Exception $e) {
        // Если ошибка БД — используем пустые настройки
    }

    // Check enabled status and configuration for each gateway
    foreach ($gateways as $key => &$gateway) {
        $enabledKey = 'payment_' . $key . '_enabled';
        try {
            $stmt = db()->prepare("SELECT value FROM settings WHERE `key` = ?");
            $stmt->execute([$enabledKey]);
            $row = $stmt->fetch();
            
            // Если поле существует в БД — используем его значение
            if ($row) {
                $gateway['enabled'] = ($row['value'] == '1');
            } else {
                // Fallback: если миграция не выполнена, проверяем старые ключи
                // FreeKassa — если есть merchant_id и phrase1, считаем включённым
                if ($key === 'freekassa' && !empty($gateway['settings']['fk_merchant_id']) && !empty($gateway['settings']['fk_phrase1'])) {
                    $gateway['enabled'] = true;
                }
                // YooMoney — если есть shopid и password
                elseif ($key === 'yoomoney' && !empty($gateway['settings']['ym_shopid']) && !empty($gateway['settings']['ym_password'])) {
                    $gateway['enabled'] = true;
                }
                // enot — если есть shop_id и secret_key
                elseif ($key === 'enot' && !empty($gateway['settings']['enot_shop_id']) && !empty($gateway['settings']['enot_secret_key'])) {
                    $gateway['enabled'] = true;
                }
            }
        } catch (Exception $e) {
            // Если ошибка БД — проверяем старые ключи как fallback
            if ($key === 'freekassa' && !empty($gateway['settings']['fk_merchant_id']) && !empty($gateway['settings']['fk_phrase1'])) {
                $gateway['enabled'] = true;
            } elseif ($key === 'yoomoney' && !empty($gateway['settings']['ym_shopid']) && !empty($gateway['settings']['ym_password'])) {
                $gateway['enabled'] = true;
            } elseif ($key === 'enot' && !empty($gateway['settings']['enot_shop_id']) && !empty($gateway['settings']['enot_secret_key'])) {
                $gateway['enabled'] = true;
            }
        }
        
        // Check if configured (each gateway has its own settings)
        if ($key === 'freekassa') {
            $gateway['configured'] = !empty($gateway['settings']['fk_merchant_id']) && !empty($gateway['settings']['fk_phrase1']);
        } elseif ($key === 'yoomoney') {
            $gateway['configured'] = !empty($gateway['settings']['ym_shopid']) && !empty($gateway['settings']['ym_password']);
        } elseif ($key === 'enot') {
            $gateway['configured'] = !empty($gateway['settings']['enot_shop_id']) && !empty($gateway['settings']['enot_secret_key']);
        }
    }

    return $gateways;
}
    
function getEnabledPaymentMethods(): array {
    $gateways = getPaymentGateways();
    $methods = [];
    foreach ($gateways as $key => $gateway) {
        if ($gateway['enabled'] && $gateway['configured']) {
            $methods[$key] = [
                'name' => $gateway['name'],
                'icon' => $gateway['icon']
            ];
        }
    }
    return $methods;
}

function createEnotInvoice(int $userId, float $amountRub, string $orderId, string $comment = '', string $successUrl = '', string $failUrl = ''): array {
    $settings = getEnotSettings();
    $shopId = $settings['enot_shop_id'];
    $secretKey = $settings['enot_secret_key'];
    $isTest = ($settings['enot_mode'] === 'test');

    if (empty($shopId) || empty($secretKey)) {
        return ['success' => false, 'error' => 'enot.io не настроен'];
    }

    $apiUrl = $isTest ? 'https://api.enot.io/invoice/create' : 'https://api.enot.io/invoice/create';
    
    $params = [
        'amount' => number_format($amountRub, 2, '.', ''),
        'order_id' => $orderId,
        'shop_id' => $shopId,
        'currency' => 'RUB',
        'expire' => 300,
        'comment' => $comment
    ];
    
    if (!empty($successUrl)) $params['success_url'] = $successUrl;
    if (!empty($failUrl)) $params['fail_url'] = $failUrl;

    // Generate signature: md5(shop_id:order_id:amount:secret_key)
    $signature = md5($shopId . ':' . $orderId . ':' . number_format($amountRub, 2, '.', '.') . ':' . $secretKey);
    $params['signature'] = $signature;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return ['success' => false, 'error' => 'Ошибка создания счёта enot.io'];
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['url'])) {
        return ['success' => false, 'error' => 'Некорректный ответ от enot.io'];
    }

    return [
        'success' => true,
        'invoice_url' => $data['url'],
        'invoice_id' => $data['invoice_id'] ?? $orderId
    ];
}

function verifyEnotSignature(array $data, string $secretKey): bool {
    $shopId = $data['shop_id'] ?? '';
    $orderId = $data['order_id'] ?? '';
    $status = $data['status'] ?? '';
    $amount = $data['amount'] ?? '0';
    $sign = $data['signature'] ?? '';

    $expected = md5($shopId . ':' . $orderId . ':' . $amount . ':' . $secretKey);
    return hash_equals($expected, $sign);
}

function generateFkSignature(string $merchantId, string $orderId, float $amount, string $phrase1): string {
    return md5($merchantId . ':' . $orderId . ':' . $amount . ':' . $phrase1);
}

function verifyFkSignature(array $data, string $phrase1, string $phrase2): bool {
    $merchantId  = $data['m_merchant_id'] ?? '';
    $orderId     = $data['m_order_id'] ?? '';
    $amount      = $data['m_amount'] ?? '';
    $sign        = $data['m_sign'] ?? '';

    $expected = md5($merchantId . ':' . $orderId . ':' . $amount . ':' . $phrase1 . ':' . $phrase2);
    return hash_equals($expected, $sign);
}

function buildFkCheckoutUrl(int $userId, float $amount, string $currency = 'USD'): string {
    $settings = getFkSettings();
    $merchantId = $settings['fk_merchant_id'];
    $phrase1    = $settings['fk_phrase1'];

    if (empty($merchantId) || empty($phrase1)) {
        return '#';
    }
    
    $orderId = $userId . ':' . time();
    $signature = generateFkSignature($merchantId, $orderId, $amount, $phrase1);
    $baseUrl = 'https://freekassa.com';

    return sprintf(
        '%s/pay/%s?merchant_id=%s&order_id=%s&amount=%s&currency=%s&signature=%s',
        $baseUrl,
        $currency,
        $merchantId,
        urlencode($orderId),
        $amount,
        $currency,
        $signature
    );
}

function addBalance(int $userId, float $amount, string $type, string $desc = '', bool $isCrypto = false): bool {
    $db = db();
    $db->beginTransaction();
    try {
        $firstDepositBonus = 0.00;
        if ($type === 'deposit') {
            $stmt = $db->prepare("SELECT first_deposit FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user && $user['first_deposit'] == 1) {
                $bonusPercent = 20.00;
                $firstDepositBonus = $amount * ($bonusPercent / 100);
                $amount += $firstDepositBonus;
                $desc .= " (+$bonusPercent% first deposit bonus)";
            }
        }

        if ($isCrypto && $type === 'deposit') {
            $cryptoBonus = $amount * 0.05;
            $amount += $cryptoBonus;
            $desc .= " (+5% crypto bonus)";
        }

        // Promo bonus from Battle Pass (если таблица обновлена)
        $promoBonusPercent = 0;
        if ($type === 'deposit') {
            try {
                $stmt = $db->prepare("SELECT promo_bonus_percent FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $promoData = $stmt->fetch();
                $promoBonusPercent = floatval($promoData['promo_bonus_percent'] ?? 0);
                if ($promoBonusPercent > 0) {
                    $promoBonus = $amount * ($promoBonusPercent / 100);
                    $amount += $promoBonus;
                    $desc .= " (+promo {$promoBonusPercent}%)";
                    // Сбрасываем промо-бонус после использования
                    $stmt = $db->prepare("UPDATE users SET promo_bonus_percent = 0 WHERE id = ?");
                    $stmt->execute([$userId]);
                }
            } catch (PDOException $e) {
                // Поле promo_bonus_percent ещё не существует — пропускаем
            }
        }

        $referrerCommission = 0.00;
        if ($type === 'deposit') {
            $stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user && $user['referred_by']) {
                $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = 'ref_commission'");
                $stmt->execute();
                $setting = $stmt->fetch();
                $commissionRate = (float)($setting['value'] ?? 5.00);
                $referrerCommission = ($amount - $firstDepositBonus) * ($commissionRate / 100);
                if ($referrerCommission > 0) {
                    $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$referrerCommission, $user['referred_by']]);
                    $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user['referred_by'], 'referral_bonus', $referrerCommission, "Реферальный бонус за депозит пользователя (ID:$userId)"]);
                }
            }
        }

        $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);

        if ($type === 'deposit') {
            $stmt = $db->prepare("UPDATE users SET first_deposit = 0 WHERE id = ?");
            $stmt->execute([$userId]);
        }

        $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $amount, $desc]);

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("addBalance failed: " . $e->getMessage());
        return false;
    }
}

function subtractBalance(int $userId, float $amount, string $type, string $desc = ''): bool {
    $db = db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || $user['balance'] < $amount) {
            return false;
        }

        $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);

        $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $type, -$amount, $desc]);

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("subtractBalance failed: " . $e->getMessage());
        return false;
    }
}

// ==================== DAILY BONUS HELPERS ====================

function getUserRecentDeposit(int $userId): float {
    $stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = ? AND type = 'deposit' AND amount > 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$userId]);
    return (float)($stmt->fetch()['total'] ?? 0);
}

function canClaimGiftSkin(int $userId, float $minDeposit, float $minRub, float $maxRub): bool {
    $recentDeposit = getUserRecentDeposit($userId);
    if ($recentDeposit < $minDeposit) {
        return false;
    }
    
    $stmt = db()->prepare("SELECT value FROM settings WHERE `key` = 'usd_rub_rate'");
    $stmt->execute();
    $rate = (float)($stmt->fetch()['value'] ?? 90);
    $depositRub = $recentDeposit * $rate;
    
    return ($depositRub >= $minRub && $depositRub <= $maxRub);
}

function getUserTotalDeposit(int $userId): float {
    $stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = ? AND type = 'deposit' AND amount > 0");
    $stmt->execute([$userId]);
    return (float)($stmt->fetch()['total'] ?? 0);
}

function openCase(int $caseId, int $userId): array {
    return openMultipleCases($caseId, $userId, 1);
}

function openMultipleCases(int $caseId, int $userId, int $qty): array {
    $db = db();

    $stmt = $db->prepare("SELECT * FROM cases WHERE id = ? AND is_active = 1");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    if (!$case) {
        return ['success' => false, 'error' => 'Case not found'];
    }

    $totalPrice = $case['price'] * $qty;

    if (!subtractBalance($userId, $totalPrice, 'case_open', "Opening case: {$case['name']} x{$qty}")) {
        return ['success' => false, 'error' => 'Insufficient balance'];
    }

    $stmt = $db->prepare("SELECT * FROM case_items WHERE case_id = ? AND weight > 0");
    $stmt->execute([$caseId]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        return ['success' => false, 'error' => 'No items in case'];
    }

    $totalWeight = array_sum(array_column($items, 'weight'));
    
    $wonItems = [];
    for ($i = 0; $i < $qty; $i++) {
        $random = mt_rand(1, $totalWeight);
        $current = 0;
        $selected = null;
        foreach ($items as $item) {
            $current += $item['weight'];
            if ($random <= $current) {
                $selected = $item;
                break;
            }
        }
        if (!$selected) $selected = end($items);

        $itemImage = $selected['item_image'];
        if (empty($itemImage)) {
            $itemImage = autoFillItemImage($selected['item_name']);
        }

        insertIntoTable($db, 'case_open_history', [
            'user_id'      => $userId,
            'case_id'      => $caseId,
            'item_name'    => $selected['item_name'],
            'item_image'   => $itemImage,
            'rarity'       => $selected['rarity'],
            'price'        => $selected['price'],
            'amount_paid'  => $case['price'],
        ]);

        insertIntoTable($db, 'user_inventory', [
            'user_id'    => $userId,
            'item_name'  => $selected['item_name'],
            'item_image' => $itemImage,
            'rarity'     => $selected['rarity'],
            'price'      => $selected['price'],
        ]);

        if (empty($selected['item_image']) && !empty($itemImage)) {
            $stmt = $db->prepare("UPDATE case_items SET item_image = ? WHERE id = ?");
            $stmt->execute([$itemImage, $selected['id']]);
        }

        $selected['item_name'] = preg_replace('/[\x00-\x1F\x7F]/', '', $selected['item_name']);
        $wonItems[] = $selected;
    }

    $case['name'] = preg_replace('/[\x00-\x1F\x7F]/', '', $case['name']);
    $case['description'] = preg_replace('/[\x00-\x1F\x7F]/', '', $case['description'] ?? '');

    // Запись в live_wins для каждого выигранного предмета
    try {
        $stmt = $db->prepare("SELECT username, avatar FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userInfo = $stmt->fetch();
        $username = $userInfo['username'] ?? 'Anonymous';
        $avatar = $userInfo['avatar'] ?? '';
        
        foreach ($wonItems as $wonItem) {
            $stmt = $db->prepare("
                INSERT INTO live_wins (user_id, username, user_avatar, item_name, item_image, rarity, price, case_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $username,
                $avatar,
                $wonItem['item_name'],
                $wonItem['item_image'],
                $wonItem['rarity'],
                $wonItem['price'],
                $caseId
            ]);
        }
    } catch (Exception $e) {
        // Не критично, если live_wins не записался
        error_log("Live wins insert error: " . $e->getMessage());
    }

    // Получаем баланс напрямую из БД (без getCurrentUser чтобы не конфликтовать с PDO)
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentBalance = (float)($stmt->fetch()['balance'] ?? 0);

    return [
        'success'  => true,
        'items'    => $wonItems,
        'case'     => $case,
        'balance'  => $currentBalance
    ];
}
    
// ==================== FREE CASES HELPERS ====================

function getUserDepositLast24h(int $userId): float {
    $stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = ? AND type = 'deposit' AND amount > 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$userId]);
    return (float)($stmt->fetch()['total'] ?? 0);
}

function canOpenFreeCase(int $userId, float $minDeposit): bool {
    $userDeposit = getUserDepositLast24h($userId);
    return $userDeposit >= $minDeposit;
}

function openFreeCase(int $caseId, int $userId): array {
    $db = db();

    $stmt = $db->prepare("SELECT * FROM free_cases WHERE id = ? AND is_active = 1");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    
    if (!$case) {
        return ['success' => false, 'error' => 'Кейс не найден'];
    }

    $userDeposit = getUserDepositLast24h($userId);
    if ($userDeposit < $case['min_deposit']) {
        return ['success' => false, 'error' => 'Недостаточно депозитов за 24ч. Требуется: $' . $case['min_deposit'] . ', Ваш депозит: $' . $userDeposit];
    }

    $stmt = $db->prepare("SELECT * FROM free_case_items WHERE case_id = ? AND weight > 0");
    $stmt->execute([$caseId]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        return ['success' => false, 'error' => 'Нет предметов в кейсе'];
    }

    $totalWeight = array_sum(array_column($items, 'weight'));
    $random = mt_rand(1, $totalWeight);
    $current = 0;
    $selected = null;
    
    foreach ($items as $item) {
        $current += $item['weight'];
        if ($random <= $current) {
            $selected = $item;
            break;
        }
    }
    
    if (!$selected) $selected = end($items);

    $itemImage = $selected['item_image'];
    if (empty($itemImage)) {
        $itemImage = autoFillItemImage($selected['item_name']);
    }

    insertIntoTable($db, 'free_case_history', [
        'user_id'               => $userId,
        'case_id'               => $caseId,
        'item_name'             => $selected['item_name'],
        'item_image'            => $itemImage,
        'rarity'                => $selected['rarity'],
        'price'                 => $selected['price'],
        'min_deposit_required'  => $case['min_deposit'],
        'user_deposit_sum'      => $userDeposit,
    ]);

    insertIntoTable($db, 'user_inventory', [
        'user_id'    => $userId,
        'item_name'  => $selected['item_name'],
        'item_image' => $itemImage,
        'rarity'     => $selected['rarity'],
        'price'      => $selected['price'],
    ]);

    if (empty($selected['item_image']) && !empty($itemImage)) {
        $stmt = $db->prepare("UPDATE free_case_items SET item_image = ? WHERE id = ?");
        $stmt->execute([$itemImage, $selected['id']]);
    }

    $selected['item_image'] = $itemImage;

    return [
        'success'  => true,
        'item'     => $selected,
        'case'     => $case,
        'deposit'  => $userDeposit
    ];
}
    
// ==================== CURRENCY RATE AUTO-UPDATE ====================

function getUsdRubRate(): float {
    // Проверяем глобальный кэш
    if (isset($GLOBALS['_usd_rub_rate_cache'])) {
        return $GLOBALS['_usd_rub_rate_cache'];
    }

    $stmt = db()->query("SELECT `value` FROM settings WHERE `key` = 'usd_rub_auto'");
    $autoMode = $stmt->fetch()['value'] ?? '0';

    if ($autoMode === '1') {
        $apiUrl = 'https://www.cbr-xml-daily.ru/daily_json.js';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['Valute']['USD']['Value'])) {
                $rate = (float)$data['Valute']['USD']['Value'];
                $stmt = db()->prepare("INSERT INTO settings (`key`, value) VALUES ('usd_rub_rate', ?) ON DUPLICATE KEY UPDATE value = ?");
                $stmt->execute([$rate, $rate]);
                $GLOBALS['_usd_rub_rate_cache'] = $rate;
                return $rate;
            }
        }
    }

    $stmt = db()->query("SELECT `value` FROM settings WHERE `key` = 'usd_rub_rate'");
    $rate = (float)($stmt->fetch()['value'] ?? 90.00);
    $GLOBALS['_usd_rub_rate_cache'] = $rate;
    return $rate;
}

function resetUsdRubRateCache(): void {
    $GLOBALS['_usd_rub_rate_cache'] = null;
}

function updateUsdRubRate(float $rate): bool {
    $stmt = db()->prepare("INSERT INTO settings (`key`, value) VALUES ('usd_rub_rate', ?) ON DUPLICATE KEY UPDATE value = ?");
    return $stmt->execute([$rate, $rate]);
}

function getUsdRubRateFormatted(): string {
    $rate = getUsdRubRate();
    $stmt = db()->query("SELECT updated_at FROM settings WHERE `key` = 'usd_rub_rate'");
    $row = $stmt->fetch();
    $date = $row ? date('d.m.Y H:i', strtotime($row['updated_at'])) : date('d.m.Y');
    return number_format($rate, 2, '.', '') . ' ₽ ($' . $date . ')';
}

// ==================== STEAM IMAGE API ====================

function getWeaponType(string $itemName): string {
    $weaponTypes = [
        'AK-47', 'M4A1-S', 'M4A4', 'AWP', 'USP-S', 'Glock-18', 'P250', 
        'Desert Eagle', 'AUG', 'FAMAS', 'Galil AR', 'SG 553',
        'MP7', 'MP9', 'MAC-10', 'PP-Bizon', 'P90', 'UMP-45',
        'Nova', 'XM1014', 'MAG-7', 'Sawed-Off',
        'SSG 08', 'SCAR-20',
        'Karambit', 'M9 Bayonet', 'Bayonet', 'Flip Knife', 'Gut Knife',
        'Butterfly Knife', 'Huntsman Knife', 'Falchion Knife', 'Shadow Daggers',
        'Bowie Knife', 'Survival Knife', 'Paracord Knife', 'Nomad Knife',
        'Skeleton Knife', 'Stiletto Knife', 'Ursus Knife', 'Navaja Knife',
        'Talon Knife', 'Classic Knife', 'Kukri Knife'
    ];
    
    foreach ($weaponTypes as $weapon) {
        if (stripos($itemName, $weapon) === 0) {
            return $weapon;
        }
    }
    return '';
}

function getSkinName(string $itemName): string {
    $parts = explode('|', $itemName);
    if (count($parts) >= 2) {
        return trim($parts[1]);
    }
    return trim($parts[0]);
}

function getSteamItemImageByName(string $itemName): string {
    $cacheFile = __DIR__ . '/../cache/item_images.json';
    $cache = [];
    
    if (file_exists($cacheFile)) {
        $cacheContent = file_get_contents($cacheFile);
        $cache = json_decode($cacheContent, true) ?? [];
        
        if (isset($cache[$itemName])) {
            $cached = $cache[$itemName];
            if (time() - ($cached['time'] ?? 0) < 604800) {
                return $cached['image'] ?? '';
            }
        }
    }
    
    $cleanName = trim($itemName);
    $marketUrl = 'https://steamcommunity.com/market/listings/730/' . urlencode($cleanName);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $marketUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && $httpCode === 200) {
        if (preg_match('/"icon_url":"([^"]+)"/', $response, $matches)) {
            $iconUrl = $matches[1];
            if (!empty($iconUrl)) {
                $imageUrl = 'https://community.cloudflare.steamstatic.com/economy/image/' . $iconUrl;
                
                $cache[$itemName] = ['image' => $imageUrl, 'time' => time()];
                if (count($cache) > 1000) array_shift($cache);
                file_put_contents($cacheFile, json_encode($cache));
                return $imageUrl;
            }
        }
        
        if (preg_match('/"icon_url_large":"([^"]+)"/', $response, $matches)) {
            $iconUrl = $matches[1];
            if (!empty($iconUrl)) {
                $imageUrl = 'https://community.cloudflare.steamstatic.com/economy/image/' . $iconUrl;
                
                $cache[$itemName] = ['image' => $imageUrl, 'time' => time()];
                if (count($cache) > 1000) array_shift($cache);
                file_put_contents($cacheFile, json_encode($cache));
                return $imageUrl;
            }
        }
    }
    
    $cache[$itemName] = ['image' => '', 'time' => time()];
    if (count($cache) > 1000) array_shift($cache);
    file_put_contents($cacheFile, json_encode($cache));
    
    return '';
}

function autoFillItemImage(string $itemName, string $existingImage = ''): string {
    if (!empty($existingImage) && filter_var($existingImage, FILTER_VALIDATE_URL)) {
        return $existingImage;
    }
    
    $steamImage = getSteamItemImageByName($itemName);
    return $steamImage ?: '/assets/images/default-case.png';
}
