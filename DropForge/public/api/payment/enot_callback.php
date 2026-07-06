<?php
// enot.io callback handler
require_once __DIR__ . '/../../includes/functions.php';

$data = $_POST;

$settings = getEnotSettings();
$secretKey = $settings['enot_secret_key'];

// Если secret key не настроен — пропускаем проверку подписи (fallback)
if (!empty($secretKey)) {
    // Verify signature
    if (!verifyEnotSignature($data, $secretKey)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        exit;
    }
}

$shopId      = $data['shop_id'] ?? '';
$orderId     = $data['order_id'] ?? '';
$status      = $data['status'] ?? '';
$amount      = (float)($data['amount'] ?? 0);
$currency    = $data['currency'] ?? 'RUB';

// Parse user_id from order_id (format: user_id:timestamp)
$parts = explode(':', $orderId);
if (count($parts) < 2) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order_id format']);
    exit;
}
$userId = (int)$parts[0];

if ($userId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user_id']);
    exit;
}

// Get USD/RUB rate
$usdRate = getUsdRubRate();

// Convert RUB to USD
$amountUsd = $amount / $usdRate;

// Get stored amount from pending_payments
try {
    $stmt = db()->prepare("SELECT payment_method, amount_usd, amount_rub FROM pending_payments WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $payment = $stmt->fetch();
    if ($payment) {
        if ($payment['amount_usd']) {
            $amountUsd = (float)$payment['amount_usd'];
        }
        // Удаляем запись
        $stmt = db()->prepare("DELETE FROM pending_payments WHERE order_id = ?");
        $stmt->execute([$orderId]);
    }
} catch (Exception $e) {
    // Таблица может не существовать
}

// Status 'paid' = success
if ($status === 'paid') {
    addBalance($userId, $amountUsd, 'deposit', "Пополнение через enot.io (ID: $orderId)");
    echo json_encode(['status' => 'ok']);
    exit;
}

echo json_encode(['status' => 'pending']);
exit;
