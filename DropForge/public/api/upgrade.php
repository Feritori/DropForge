<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// Проверка: раздел отключён?
if (getSetting('upgrade_enabled', '1') !== '1') {
    jsonResponse(['success' => false, 'error' => 'Раздел временно недоступен']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Invalid method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'] ?? 0;

if (!$userId) {
    jsonResponse(['success' => false, 'error' => 'Не авторизован'], 401);
}

$inventoryItemId = (int)($data['inventory_item_id'] ?? 0);
$targetItemName  = trim($data['target_item_name'] ?? '');
$targetItemImage = trim($data['target_item_image'] ?? '');
$targetRarity    = trim($data['target_rarity'] ?? '');
$targetPrice     = (float)($data['target_price'] ?? 0);

if (!$inventoryItemId || !$targetItemName) {
    jsonResponse(['success' => false, 'error' => 'Не указаны данные'], 400);
}

$db = db();
$db->beginTransaction();
try {
    // Get user's item
    $stmt = $db->prepare("SELECT * FROM user_inventory WHERE id = ? AND user_id = ? AND is_sold = 0 FOR UPDATE");
    $stmt->execute([$inventoryItemId, $userId]);
    $item = $stmt->fetch();

    if (!$item) {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => 'Предмет не найден'], 404);
    }

    $itemPrice = (float)$item['price'];
    $targetPriceCalc = (float)$targetPrice;

    if ($targetPriceCalc <= $itemPrice) {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => 'Цель должна быть дороже вашего предмета'], 400);
    }

    // Calculate chance: (your_price / target_price) * 100 - 5% house edge
    $baseChance = ($itemPrice / $targetPriceCalc) * 100;
    $houseEdge = 5.00; // 5% fixed house edge
    $calculatedChance = max($baseChance - $houseEdge, 1.00);

    // Get upgrade loss rate setting (default 66%)
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'upgrade_loss_rate'");
    $stmt->execute();
    $setting = $stmt->fetch();
    $upgradeLossRate = (float)($setting['value'] ?? 66.00);

    // Apply loss rate: real_chance = calculated_chance * (1 - loss_rate/100)
    // If loss_rate = 66, then success multiplier = 0.34
    $successMultiplier = 1.0 - ($upgradeLossRate / 100.0);
    $realChance = min($calculatedChance * $successMultiplier, 95.00);
    $realChance = max($realChance, 1.00);

    // Roll: 1-100
    $roll = mt_rand(1, 10000) / 100; // от 0.01 до 100.00
    $isWon = $roll <= $realChance;

    // Remove old item
    $stmt = $db->prepare("UPDATE user_inventory SET is_sold = 1 WHERE id = ?");
    $stmt->execute([$inventoryItemId]);

    if ($isWon) {
        // Add new item to inventory
        $stmt = $db->prepare("INSERT INTO user_inventory (user_id, item_name, item_image, rarity, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $targetItemName, $targetItemImage, $targetRarity, $targetPriceCalc]);

        $db->commit();
        jsonResponse([
            'success'    => true,
            'won'        => true,
            'item'       => [
                'name'    => $targetItemName,
                'image'   => $targetItemImage,
                'rarity'  => $targetRarity,
                'price'   => $targetPriceCalc,
            ],
            'chance'     => round($realChance, 2),
            'baseChance' => round($calculatedChance, 2),
            'balance'    => (float)getCurrentUser()['balance']
        ]);
    } else {
        $db->commit();
        jsonResponse([
            'success'    => true,
            'won'        => false,
            'chance'     => round($realChance, 2),
            'baseChance' => round($calculatedChance, 2),
            'balance'    => (float)getCurrentUser()['balance']
        ]);
    }
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Upgrade failed: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Ошибка сервера'], 500);
}
