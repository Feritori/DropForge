<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// Проверка: раздел отключён?
if (getSetting('contract_enabled', '1') !== '1') {
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

$uAction = $data['action'] ?? '';

$db = db();

switch ($uAction) {
    case 'add_item':
        // Add item to contract session
        $inventoryItemId = (int)($data['inventory_item_id'] ?? 0);

        $stmt = $db->prepare("SELECT * FROM user_inventory WHERE id = ? AND user_id = ? AND is_sold = 0");
        $stmt->execute([$inventoryItemId, $userId]);
        $item = $stmt->fetch();

        if (!$item) {
            jsonResponse(['success' => false, 'error' => 'Предмет не найден'], 404);
        }

        // Store in session
        if (!isset($_SESSION['contract_items'])) {
            $_SESSION['contract_items'] = [];
        }

        if (in_array($inventoryItemId, $_SESSION['contract_items'])) {
            jsonResponse(['success' => false, 'error' => 'Предмет уже добавлен'], 400);
        }

        if (count($_SESSION['contract_items']) >= 10) {
            jsonResponse(['success' => false, 'error' => 'Максимум 10 предметов'], 400);
        }

        $_SESSION['contract_items'][] = $inventoryItemId;

        // Recalculate
        $items = [];
        $rarityCounts = [];
        $totalValue = 0;
        foreach ($_SESSION['contract_items'] as $invId) {
            $stmt = $db->prepare("SELECT * FROM user_inventory WHERE id = ?");
            $stmt->execute([$invId]);
            $it = $stmt->fetch();
            if ($it) {
                $items[] = $it;
                $r = $it['rarity'];
                $rarityCounts[$r] = ($rarityCounts[$r] ?? 0) + 1;
                $totalValue += (float)$it['price'];
            }
        }

        // Check if all same rarity
        $isSameRarity = count(array_unique(array_column($items, 'rarity'))) === 1;

        // Determine target
        $targetName = '';
        $targetImage = '';
        $targetRarity = '';
        $targetPrice = 0;
        $multiplier = 1.0;

        if ($isSameRarity && count($items) === 10) {
            $mostRarity = $items[0]['rarity'];
            $nextRarityIdx = (array_search($mostRarity, RAIRITY_ORDER) ?? -1) + 1;
            if ($nextRarityIdx < count(RAIRITY_ORDER)) {
                $targetRarity = RAIRITY_ORDER[$nextRarityIdx];
                $targetName = rarityLabel($targetRarity) . ' Random';
                $targetImage = '';
                // Price: total * random multiplier (0.85 to 1.30) - rigged toward lower
                $randMult = 0.85 + (mt_rand(0, 4500) / 1000); // 0.85 to 1.30, weighted lower
                $targetPrice = $totalValue * $randMult;
                $multiplier = $randMult;
            }
        }

        jsonResponse([
            'success'    => true,
            'items'      => array_map(fn($i) => [
                'id' => $i['id'], 'name' => $i['item_name'], 'image' => $i['item_image'],
                'rarity' => $i['rarity'], 'price' => (float)$i['price']
            ], $items),
            'total'      => $totalValue,
            'count'      => count($items),
            'isSameRarity' => $isSameRarity,
            'canSign'    => $isSameRarity && count($items) === 10,
            'target'     => [
                'name' => $targetName, 'image' => $targetImage,
                'rarity' => $targetRarity, 'price' => round($targetPrice, 2),
                'multiplier' => round($multiplier, 2)
            ]
        ]);
        break;

    case 'remove_item':
        $inventoryItemId = (int)($data['inventory_item_id'] ?? 0);
        if (isset($_SESSION['contract_items'])) {
            $key = array_search($inventoryItemId, $_SESSION['contract_items']);
            if ($key !== false) {
                unset($_SESSION['contract_items'][$key]);
                $_SESSION['contract_items'] = array_values($_SESSION['contract_items']);
            }
        }
        jsonResponse(['success' => true]);
        break;

    case 'clear':
        $_SESSION['contract_items'] = [];
        jsonResponse(['success' => true]);
        break;

    case 'sign':
        if (!isset($_SESSION['contract_items']) || count($_SESSION['contract_items']) < 10) {
            jsonResponse(['success' => false, 'error' => 'Нужно 10 предметов'], 400);
        }

        $db->beginTransaction();
        try {
            $items = [];
            $rarityCounts = [];
            $totalValue = 0;
            $invIds = [];

            foreach ($_SESSION['contract_items'] as $invId) {
                $stmt = $db->prepare("SELECT * FROM user_inventory WHERE id = ? AND user_id = ? AND is_sold = 0");
                $stmt->execute([$invId, $userId]);
                $it = $stmt->fetch();
                if ($it) {
                    $items[] = $it;
                    $r = $it['rarity'];
                    $rarityCounts[$r] = ($rarityCounts[$r] ?? 0) + 1;
                    $totalValue += (float)$it['price'];
                    $invIds[] = $invId;
                }
            }

            $isSameRarity = count(array_unique(array_column($items, 'rarity'))) === 1;

            if (!$isSameRarity) {
                $db->rollBack();
                jsonResponse(['success' => false, 'error' => 'Все предметы должны быть одной редкости'], 400);
            }

            $mostRarity = $items[0]['rarity'];
            $nextRarityIdx = (array_search($mostRarity, RAIRITY_ORDER) ?? -1) + 1;

            if ($nextRarityIdx >= count(RAIRITY_ORDER)) {
                $db->rollBack();
                jsonResponse(['success' => false, 'error' => 'Нет редкости выше'], 400);
            }

            $targetRarity = RAIRITY_ORDER[$nextRarityIdx];

            // Random multiplier: rigged toward lower values (0.85 to 1.30)
            // Using a distribution that favors lower multipliers
            $rand = mt_rand(1, 100);
            if ($rand <= 30) {
                $mult = 0.85 + ($rand / 100) * 0.15; // 0.85-0.95 (30%)
            } elseif ($rand <= 60) {
                $mult = 0.95 + (($rand - 30) / 100) * 0.15; // 0.95-1.05 (30%)
            } elseif ($rand <= 85) {
                $mult = 1.05 + (($rand - 60) / 100) * 0.15; // 1.05-1.15 (25%)
            } else {
                $mult = 1.15 + (($rand - 85) / 100) * 0.15; // 1.15-1.30 (15%)
            }

            $targetPrice = $totalValue * $mult;

            // Remove all items
            $invIdList = implode(',', array_map(fn($id) => (int)$id, $invIds));
            $stmt = $db->prepare("UPDATE user_inventory SET is_sold = 1 WHERE id IN ($invIdList)");
            $stmt->execute();

            // Add reward item
            $targetName = rarityLabel($targetRarity) . ' Random #' . time();
            $targetImage = getRandomSteamHash();

            $stmt = $db->prepare("INSERT INTO user_inventory (user_id, item_name, item_image, rarity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $targetName, $targetImage, $targetRarity, round($targetPrice, 2)]);

            // Transaction record
            $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, 'contract', -$totalValue, "Контракт: {$mostRarity} → {$targetRarity}"]);

            // Clear session
            $_SESSION['contract_items'] = [];

            $db->commit();

            jsonResponse([
                'success'   => true,
                'won'       => true,
                'target'    => [
                    'name'    => $targetName,
                    'image'   => $targetImage,
                    'rarity'  => $targetRarity,
                    'price'   => round($targetPrice, 2),
                    'multiplier' => round($mult, 2)
                ],
                'totalValue' => $totalValue
            ]);
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Contract failed: " . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Ошибка сервера'], 500);
        }
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
