<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Invalid method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'] ?? 0;
$amount = (float)($data['amount'] ?? 0);
$currency = strtoupper($data['currency'] ?? 'USD');
$paymentMethod = $data['method'] ?? '';
$amountRub = (float)($data['amount_rub'] ?? 0);

// Get USD/RUB rate from settings
$stmt = db()->prepare("SELECT value FROM settings WHERE `key` = 'usd_rub_rate'");
$stmt->execute();
$rateRow = $stmt->fetch();
$usdRate = (float)($rateRow['value'] ?? 90.00);

// Convert USD to RUB for FreeKassa
if ($currency === 'USD') {
    $amountRub = $amount * $usdRate;
} else {
    $amountRub = $amount;
}

if (!$userId || $amount < 1) {
    jsonResponse(['success' => false, 'error' => 'Минимум 1$'], 400);
}

// Apply promo code if active
$promoBonus = 0;
$activePromo = $_SESSION['active_promo'] ?? null;
if ($activePromo && $activePromo['min_amount'] <= $amount) {
    $promoBonus = $amount * ($activePromo['bonus_percent'] / 100);
    
    // Log promo usage
    $stmt = db()->prepare("INSERT INTO user_promo_usage (user_id, promo_code_id, bonus_amount) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $activePromo['id'], $promoBonus]);
    
    // Increment uses count
    $stmt = db()->prepare("UPDATE promo_codes SET uses_count = uses_count + 1 WHERE id = ?");
    $stmt->execute([$activePromo['id']]);
    
    // Remove from session
    unset($_SESSION['active_promo']);
}

// Crypto bonus +5%
$cryptoBonus = 0;
if ($paymentMethod === 'crypto') {
    $cryptoBonus = $amount * 0.05;
}

// Total in USD
$totalUsd = $amount + $promoBonus + $cryptoBonus;
$totalRub = $totalUsd * $usdRate;

// Get available payment methods
$gateways = getPaymentGateways();

// Get FK settings from database (used for signature)
$settings = getFkSettings();
$merchantId = $settings['fk_merchant_id'];
$phrase1    = $settings['fk_phrase1'];

// Get YooMoney settings
$ymSettings = getYmSettings();
$ymShopId   = $ymSettings['ym_shopid'];
$ymPassword = $ymSettings['ym_password'];
$ymMode     = $ymSettings['ym_mode'];

// YooMoney payment
if ($paymentMethod === 'yoomoney') {
    if (!$gateways['yoomoney']['enabled'] || !$gateways['yoomoney']['configured']) {
        jsonResponse(['success' => false, 'error' => 'ЮMoney недоступен. Обратитесь к администратору.'], 503);
    }

    $orderId = $userId . ':' . time();
    
    // Сохраняем pending payment
    try {
        $stmt = db()->prepare("INSERT INTO pending_payments (user_id, order_id, payment_method, amount_usd, amount_rub) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $orderId, 'yoomoney', $totalUsd, $totalRub]);
    } catch (Exception $e) {
        // ignore
    }

    jsonResponse([
        'success' => true,
        'payment_type' => 'yoomoney',
        'receiver' => $ymShopId,
        'label' => $orderId,
        'sum' => number_format($totalRub, 2, '.', ''),
        'success_url' => SITE_URL . '/deposits.php?status=success',
        'fail_url' => SITE_URL . '/deposits.php?status=failed',
    ]);
}

// enot.io payment
if ($paymentMethod === 'enot') {
    if (!$gateways['enot']['enabled'] || !$gateways['enot']['configured']) {
        jsonResponse(['success' => false, 'error' => 'enot.io недоступен. Обратитесь к администратору.'], 503);
    }

    $orderId = $userId . ':' . time();
    
    // Сохраняем pending payment
    try {
        $stmt = db()->prepare("INSERT INTO pending_payments (user_id, order_id, payment_method, amount_usd, amount_rub) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $orderId, 'enot', $totalUsd, $totalRub]);
    } catch (Exception $e) {
        // ignore
    }

    $result = createEnotInvoice(
        $userId,
        $totalRub,
        $orderId,
        'Пополнение баланса',
        SITE_URL . '/deposits.php?status=success',
        SITE_URL . '/deposits.php?status=failed'
    );

    if (!$result['success']) {
        jsonResponse(['success' => false, 'error' => $result['error']], 500);
    }

    jsonResponse([
        'success' => true,
        'checkout_url' => $result['invoice_url']
    ]);
}

// FreeKassa payment (default)
if (!$gateways['freekassa']['enabled'] || !$gateways['freekassa']['configured']) {
    jsonResponse(['success' => false, 'error' => 'Платёжная система не настроена. Обратитесь к администратору.'], 503);
}

// Build checkout URL - FreeKassa works with RUB
$orderId = $userId . ':' . time();
$signature = generateFkSignature($merchantId, $orderId, $totalRub, $phrase1);
$baseUrl = 'https://freekassa.com';

$checkoutUrl = sprintf(
    '%s/pay/RUB?merchant_id=%s&order_id=%s&amount=%s&currency=RUB&signature=%s',
    $baseUrl,
    $merchantId,
    urlencode($orderId),
    number_format($totalRub, 2, '.', ''),
    $signature
);

// Сохраняем метод оплаты для callback
try {
    $stmt = db()->prepare("INSERT INTO pending_payments (user_id, order_id, payment_method, amount_usd, amount_rub) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $orderId, $paymentMethod, $totalUsd, $totalRub]);
} catch (Exception $e) {
    // Таблица может не существовать на старых базах, игнорируем
}

jsonResponse([
    'success' => true, 
    'checkout_url' => $checkoutUrl,
    'promo_bonus' => $promoBonus,
    'crypto_bonus' => $cryptoBonus,
    'total_amount' => $totalUsd,
    'total_rub' => $totalRub
]);
