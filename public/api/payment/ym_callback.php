<?php
/**
 * YooMoney Callback Handler
 * Обработка уведомлений от ЮMoney
 */
require_once __DIR__ . '/../../includes/functions.php';

// Получаем параметры от ЮMoney
$ymMode = isset($_GET['mode']) ? $_GET['mode'] : 'production';

$operationId   = $_POST['operation_id'] ?? $_GET['operation_id'] ?? '';
$amount         = $_POST['amount'] ?? $_GET['amount'] ?? '';
$currency        = $_POST['currency'] ?? $_GET['currency'] ?? 'RUB';
$timestamp       = $_POST['datetime'] ?? $_GET['datetime'] ?? '';
$shopId          = $_POST['shop_id'] ?? $_GET['shop_id'] ?? '';
$signatureValue  = $_POST['sign_value'] ?? $_GET['sign_value'] ?? '';
$label           = $_POST['label'] ?? $_GET['label'] ?? '';

// Получаем пароль из настроек
$ymSettings = getYmSettings();
$password = $ymSettings['ym_password'];

if (empty($label) || empty($signatureValue)) {
    error_log('YooMoney callback: missing label or signature');
    exit('FAIL');
}

// Проверяем подпись
$signString = "$operationId|$amount|$currency|$timestamp|$shopId|$label|$password";
$expectedSign = strtoupper(md5($signString));

if ($signatureValue !== $expectedSign) {
    error_log('YooMoney callback: invalid signature. Got: ' . $signatureValue . ', Expected: ' . $expectedSign);
    exit('FAIL');
}

// Парсим label (user_id:order_id)
$parts = explode(':', $label);
if (count($parts) !== 2) {
    error_log('YooMoney callback: invalid label format: ' . $label);
    exit('FAIL');
}

$userId = (int)$parts[0];
$orderId = $parts[1];

if (!$userId) {
    error_log('YooMoney callback: invalid user_id: ' . $userId);
    exit('FAIL');
}

// Проверяем что платёж ещё не обработан
$db = db();
try {
    $stmt = $db->prepare("SELECT id, amount_rub FROM pending_payments WHERE order_id = ? AND status != 'completed'");
    $stmt->execute([$orderId]);
    $payment = $stmt->fetch();
} catch (Exception $e) {
    // Таблица может не существовать
    $payment = null;
}

if (!$payment) {
    // Уже обработан или не найден
    error_log('YooMoney callback: payment not found or already completed. order_id=' . $orderId);
    exit('OK');
}

// Конвертируем RUB в USD
$usdRate = getUsdRubRate();

$amountRub = (float)$amount;
$amountUsd = $amountRub / $usdRate;

// Начисляем баланс
try {
    $db->beginTransaction();
    
    // Проверяем first deposit bonus
    $firstDepositBonus = 0;
    $stmt = $db->prepare("SELECT first_deposit FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['first_deposit'] == 1) {
        $bonusPercent = 20.00;
        $firstDepositBonus = $amountUsd * ($bonusPercent / 100);
        $amountUsd += $firstDepositBonus;
    }
    
    // Начисляем
    $stmt = $db->prepare("UPDATE users SET balance = balance + ?, first_deposit = 0 WHERE id = ?");
    $stmt->execute([$amountUsd, $userId]);
    
    // Записываем транзакцию
    $desc = "Пополнение через ЮMoney (order: $orderId)";
    if ($firstDepositBonus > 0) {
        $desc .= " (+20% first deposit bonus)";
    }
    
    $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, description, order_id) VALUES (?, 'deposit', ?, ?, ?)");
    $stmt->execute([$userId, $amountUsd, $desc, $orderId]);
    
    // Обновляем pending payment
    $stmt = $db->prepare("UPDATE pending_payments SET status = 'completed', transaction_id = LAST_INSERT_ID() WHERE order_id = ?");
    $stmt->execute([$orderId]);
    
    $db->commit();
    
    error_log('YooMoney callback: payment completed. user_id=' . $userId . ' amount_usd=' . $amountUsd);
    echo 'OK';
} catch (Exception $e) {
    $db->rollBack();
    error_log('YooMoney callback error: ' . $e->getMessage());
    echo 'FAIL';
}
?>
