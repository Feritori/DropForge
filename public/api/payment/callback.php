<?php
require_once __DIR__ . '/../../includes/functions.php';

// FreeKassa IPN callback
$data = $_POST;
$settings = getFkSettings();
$phrase1 = $settings['fk_phrase1'];
$phrase2 = $settings['fk_phrase2'];

// Verify signature
if (!verifyFkSignature($data, $phrase1, $phrase2)) {
    http_response_code(403);
    echo 'FAIL';
    exit;
}

$merchant_id  = (int)($data['m_merchant_id'] ?? 0);
$order_id     = $data['m_order_id'] ?? '';
$amountRub    = (float)($data['m_amount'] ?? 0);  // Amount in RUB from FreeKassa
$currency     = strtoupper($data['m_currency'] ?? 'RUB');
$status       = $data['m_payment_no'] ?? '';

// Parse user_id from order_id
$parts = explode(':', $order_id);
if (count($parts) < 2) {
    echo 'FAIL';
    exit;
}
$userId = (int)$parts[0];

if ($userId <= 0) {
    echo 'FAIL';
    exit;
}

// Get USD/RUB rate
$stmt = db()->prepare("SELECT value FROM settings WHERE `key` = 'usd_rub_rate'");
$stmt->execute();
$rateRow = $stmt->fetch();
$usdRate = (float)($rateRow['value'] ?? 90.00);

// Convert RUB to USD
$amountUsd = $amountRub / $usdRate;

// Проверяем, была ли оплата криптой
$isCrypto = false;
$pendingUsd = null;
try {
    $stmt = db()->prepare("SELECT payment_method, amount_usd, amount_rub FROM pending_payments WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $payment = $stmt->fetch();
    if ($payment) {
        if ($payment['payment_method'] === 'crypto') {
            $isCrypto = true;
        }
        // Use stored USD amount if available
        if ($payment['amount_usd']) {
            $amountUsd = (float)$payment['amount_usd'];
        }
        // Удаляем запись после обработки
        $stmt = db()->prepare("DELETE FROM pending_payments WHERE order_id = ?");
        $stmt->execute([$order_id]);
    }
} catch (Exception $e) {
    // Таблица может не существовать
}

// Status 000 = success
if ($status === '000' || $status === 'SUCCESS') {
    addBalance($userId, $amountUsd, 'deposit', "Пополнение через FreeKassa (ID: $order_id)", $isCrypto);
}

echo 'OK';
exit;
