<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// Проверка: раздел отключён?
if (getSetting('inventory_enabled', '1') !== '1') {
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

$action = $data['action'] ?? '';

$db = db();

switch ($action) {
    case 'sell':
        $inventoryItemId = (int)($data['id'] ?? 0);

        $stmt = $db->prepare("SELECT * FROM user_inventory WHERE id = ? AND user_id = ? AND is_sold = 0");
        $stmt->execute([$inventoryItemId, $userId]);
        $item = $stmt->fetch();

        if (!$item) {
            jsonResponse(['success' => false, 'error' => 'Предмет не найден'], 404);
        }

        $price = (float)$item['price'];

        $stmt = $db->prepare("UPDATE user_inventory SET is_sold = 1 WHERE id = ?");
        $stmt->execute([$inventoryItemId]);

        addBalance($userId, $price, 'sell', "Продажа: {$item['item_name']}");

        jsonResponse(['success' => true, 'amount' => $price]);
        break;

    case 'sell_all':
        $stmt = $db->prepare("SELECT * FROM user_inventory WHERE user_id = ? AND is_sold = 0");
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll();

        if (empty($items)) {
            jsonResponse(['success' => false, 'error' => 'Инвентарь пуст'], 400);
        }

        $total = 0;
        $ids = [];
        foreach ($items as $item) {
            $total += (float)$item['price'];
            $ids[] = (int)$item['id'];
        }

        $idList = implode(',', $ids);
        $stmt = $db->prepare("UPDATE user_inventory SET is_sold = 1 WHERE id IN ($idList)");
        $stmt->execute();

        addBalance($userId, $total, 'sell', "Продажа всего инвентаря");

        jsonResponse(['success' => true, 'amount' => $total, 'count' => count($items)]);
        break;

    case 'list':
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 40;
        $offset = ($page - 1) * $perPage;

        $stmt = $db->prepare("SELECT * FROM user_inventory WHERE user_id = ? AND is_sold = 0 ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$userId, $perPage, $offset]);
        $items = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT COUNT(*) FROM user_inventory WHERE user_id = ? AND is_sold = 0");
        $stmt->execute([$userId]);
        $total = (int)$stmt->fetchColumn();

        jsonResponse(['success' => true, 'items' => $items, 'total' => $total, 'page' => $page]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
