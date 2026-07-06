<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log_php.txt');

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../includes/functions.php';
    requireAdmin();
} catch (Exception $e) {
    error_log('Auth error: ' . $e->getMessage());
    exit(json_encode(['success' => false, 'error' => 'Auth failed: ' . $e->getMessage()]));
}

$isJson = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
$isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;

if ($isJson) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_GET['action'] ?? '';
    $data = $input;
} else {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $data = $_POST;
}

try {
    $db = db();
} catch (Exception $e) {
    error_log('DB connection error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'DB failed: ' . $e->getMessage()], 500);
}

// ========== MAIN SWITCH ==========
try {
switch ($action) {
    // ========== CATEGORIES ==========
    case 'categories_list':
        $stmt = $db->query("SELECT c.*, COUNT(cases.id) as case_count FROM categories c LEFT JOIN cases ON c.id = cases.category_id GROUP BY c.id ORDER BY c.name");
        jsonResponse(['success' => true, 'categories' => $stmt->fetchAll()]);
        break;
    case 'category_add':
        $name = trim($data['name'] ?? '');
        $color = trim($data['color'] ?? '#8338ec');
        $icon = trim($data['icon'] ?? '📦');
        if (!$name) jsonResponse(['success' => false, 'error' => 'Укажите название'], 400);
        $stmt = $db->prepare("INSERT INTO categories (name, color, icon) VALUES (?, ?, ?)");
        $stmt->execute([$name, $color, $icon]);
        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId()]);
        break;
    case 'category_delete':
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    // ========== CASES ==========
    case 'cases_list':
        $stmt = $db->query("SELECT c.*, COUNT(ci.id) as items_count FROM cases c LEFT JOIN case_items ci ON c.id = ci.case_id GROUP BY c.id ORDER BY c.created_at DESC");
        jsonResponse(['success' => true, 'cases' => $stmt->fetchAll()]);
        break;
    case 'case_add':
        $name = trim($data['name'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $desc = trim($data['description'] ?? '');
        $image = trim($data['image_path'] ?? '');
        $active = (int)($data['is_active'] ?? 1);
        $categoryId = (int)($data['category_id'] ?? 0);
        if (!$name || $price <= 0) jsonResponse(['success' => false, 'error' => 'Некорректные данные'], 400);
        $stmt = $db->prepare("INSERT INTO cases (name, description, image_path, price, is_active, category_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $desc, $image, $price, $active, $categoryId]);
        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId()]);
        break;
    case 'case_toggle':
        $id = (int)($data['id'] ?? 0);
        $active = (int)($data['is_active'] ?? 0);
        $stmt = $db->prepare("UPDATE cases SET is_active = ? WHERE id = ?");
        $stmt->execute([$active, $id]);
        jsonResponse(['success' => true]);
        break;
    case 'case_edit':
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $desc = trim($data['description'] ?? '');
        $category = (int)($data['category_id'] ?? 0) ?: null;
        $active = (int)($data['is_active'] ?? 1);
        $imagePath = trim($data['image_path'] ?? '');
        if (!$id || !$name || $price <= 0) jsonResponse(['success' => false, 'error' => 'Некорректные данные'], 400);
        $stmt = $db->prepare("UPDATE cases SET name=?, price=?, description=?, category_id=?, is_active=?, image_path=? WHERE id=?");
        $stmt->execute([$name, $price, $desc, $category, $active, $imagePath, $id]);
        jsonResponse(['success' => true]);
        break;
    case 'case_delete':
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM cases WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
        break;
    case 'case_get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);
        $stmt = $db->prepare("SELECT * FROM cases WHERE id = ?");
        $stmt->execute([$id]);
        $case = $stmt->fetch();
        if (!$case) jsonResponse(['success' => false, 'error' => 'Case not found'], 404);
        jsonResponse(['success' => true, 'case' => $case]);
        break;

    // ========== CASE ITEMS ==========
    case 'case_items_list':
        $caseId = (int)($_GET['case_id'] ?? 0);
        if (!$caseId) jsonResponse(['success' => true, 'items' => []]);
        $stmt = $db->prepare("SELECT * FROM case_items WHERE case_id = ? ORDER BY FIELD(rarity, 'consumer', 'industrial', 'milspec', 'restricted', 'classified', 'covert', 'extraordinary', 'contraband')");
        $stmt->execute([$caseId]);
        jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
        break;
    case 'case_item_add':
        $caseId = (int)($data['case_id'] ?? 0);
        $name = trim($data['item_name'] ?? '');
        $image = trim($data['item_image'] ?? '');
        $rarity = trim($data['rarity'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $weight = (int)($data['weight'] ?? 1);
        if (!$caseId || !$name || !$rarity) jsonResponse(['success' => false, 'error' => 'Некорректные данные'], 400);
        if (empty($image)) $image = autoFillItemImage($name);
        $stmt = $db->prepare("INSERT INTO case_items (case_id, item_name, item_image, rarity, price, weight) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$caseId, $name, $image, $rarity, $price, $weight]);
        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId(), 'image' => $image]);
        break;
    case 'case_item_delete':
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM case_items WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    // ========== STEAM ITEMS DATABASE ==========
    case 'steam_sync':
        // Подключаем файл синхронизации
        $syncFile = __DIR__ . '/sync_cs2_items.php';
        if (!file_exists($syncFile)) {
            jsonResponse(['success' => false, 'error' => 'Файл синхронизации не найден'], 404);
        }
        
        // Устанавливаем флаг API режима
        $_SERVER['HTTP_X_API_MODE'] = 'true';
        
        // Запускаем синхронизацию
        ob_start();
        
        try {
            // Подключаем функции
            require_once __DIR__ . '/../../includes/functions.php';
            require_once $syncFile;
            
            $output = ob_get_clean();
            
            jsonResponse([
                'success' => true,
                'message' => 'Синхронизация завершена',
                'output' => $output
            ]);
        } catch (Exception $e) {
            $output = ob_get_clean() ?: '';
            jsonResponse(['success' => false, 'error' => $e->getMessage(), 'output' => $output]);
        }
        break;

    case 'steam_items_search':
        $query = trim($data['q'] ?? $_GET['q'] ?? '');
        $rarity = trim($data['rarity'] ?? $_GET['rarity'] ?? '');
        $type = trim($data['type'] ?? $_GET['type'] ?? '');
        $isGraduated = isset($data['graduated']) ? (int)$data['graduated'] : (isset($_GET['graduated']) ? (int)$_GET['graduated'] : -1);
        $limit = min((int)($data['limit'] ?? $_GET['limit'] ?? 5000), 10000);
        
        // Проверяем существование таблицы
        $stmtCheck = $db->query("SHOW TABLES LIKE 'steam_items'");
        if (!$stmtCheck->fetch()) {
            jsonResponse(['success' => true, 'items' => [], 'message' => 'Таблица steam_items не создана. Запустите sync_cs2_items.php сначала.']);
            break;
        }
        
        $where = [];
        $params = [];
        
        if ($query) {
            $where[] = "market_hash_name LIKE ?";
            $params[] = "%$query%";
        }
        if ($rarity) {
            $where[] = "rarity = ?";
            $params[] = $rarity;
        }
        if ($type) {
            $where[] = "type = ?";
            $params[] = $type;
        }
        if ($isGraduated >= 0) {
            $where[] = "is_graduated = ?";
            $params[] = $isGraduated;
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM steam_items $whereClause ORDER BY price_usd DESC LIMIT $limit";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
        break;

    // ========== USERS ==========
    case 'user_get':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'user' => $stmt->fetch()]);
        break;
    case 'user_edit':
        $id = (int)($data['id'] ?? 0);
        $balance = (float)($data['balance'] ?? 0);
        $role = trim($data['role'] ?? 'user');
        if (!in_array($role, ['user', 'admin'])) $role = 'user';
        $stmt = $db->prepare("UPDATE users SET balance = ?, role = ? WHERE id = ?");
        $stmt->execute([$balance, $role, $id]);
        jsonResponse(['success' => true]);
        break;
    case 'user_delete':
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
        break;
    case 'users_list':
        $page = (int)($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');
        $where = '';
        $params = [];
        if ($search) {
            $where = 'WHERE u.username LIKE ? OR u.email LIKE ?';
            $params = ["%$search%", "%$search%"];
        }
        $stmt = $db->prepare("SELECT u.*, (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type = 'deposit') as total_deposit FROM users u $where ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        $stmt = $db->prepare("SELECT COUNT(*) FROM users u $where");
        $stmt->execute($params);
        jsonResponse(['success' => true, 'users' => $users, 'total' => (int)$stmt->fetchColumn(), 'page' => $page, 'limit' => $limit]);
        break;

    // ========== TRANSACTIONS ==========
    case 'transactions_list':
        $page = (int)($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $type = trim($_GET['type'] ?? '');
        $where = $type ? "WHERE t.type = ?" : '';
        $params = $type ? [$type] : [];
        $stmt = $db->prepare("SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON t.user_id = u.id $where ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        jsonResponse(['success' => true, 'transactions' => $stmt->fetchAll(), 'page' => $page, 'limit' => $limit]);
        break;

    // ========== HISTORY ==========
    case 'history_list':
        $page = (int)($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $stmt = $db->prepare("SELECT h.*, u.username FROM case_open_history h LEFT JOIN users u ON h.user_id = u.id ORDER BY h.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute();
        jsonResponse(['success' => true, 'history' => $stmt->fetchAll(), 'page' => $page, 'limit' => $limit]);
        break;

    // ========== REFERRALS ==========
    case 'referrals_list':
        $stmt = $db->query("SELECT u.id, u.username, u.ref_code, (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as referral_count FROM users u WHERE u.ref_code IS NOT NULL AND u.ref_code != '' ORDER BY referral_count DESC LIMIT 50");
        jsonResponse(['success' => true, 'referrals' => $stmt->fetchAll()]);
        break;

    // ========== SETTINGS ==========
    case 'settings_save':
        $settings = $data['settings'] ?? [];
        if (empty($settings)) {
            error_log('settings_save: empty settings, data=' . json_encode($data));
            jsonResponse(['success' => false, 'error' => 'No settings provided']);
            break;
        }
        error_log('settings_save: ' . json_encode($settings));
        $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        foreach ($settings as $key => $value) {
            try {
                $stmt->execute([$key, $value]);
                error_log("  Saved $key = $value");
            } catch (Exception $e) {
                error_log("  Failed to save $key: " . $e->getMessage());
            }
        }
        resetSettingsCache();
        jsonResponse(['success' => true]);
        break;
    case 'toggle_page':
        $key = trim($data['key'] ?? '');
        $enabled = $data['enabled'] ? '1' : '0';
        if (!$key) jsonResponse(['success' => false, 'error' => 'Invalid key'], 400);
        $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute([$key, $enabled]);
        resetSettingsCache();
        jsonResponse(['success' => true]);
        break;
    case 'toggle_auto_rate':
        $value = trim($data['value'] ?? '0');
        error_log('toggle_auto_rate: ' . $value);
        $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES ('usd_rub_auto', ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$value, $value]);
        resetSettingsCache();
        jsonResponse(['success' => true]);
        break;
    case 'save_usd_rub_rate':
        $rate = (float)($data['rate'] ?? 0);
        if ($rate <= 0) jsonResponse(['success' => false, 'error' => 'Неверный курс'], 400);
        error_log('save_usd_rub_rate: ' . $rate);
        $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES ('usd_rub_rate', ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$rate, $rate]);
        resetSettingsCache();
        jsonResponse(['success' => true]);
        break;
    case 'update_auto_rate':
        // Получаем курс USD/RUB из API ЦБ РФ
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
            $cbData = json_decode($response, true);
            if (isset($cbData['Valute']['USD']['Value'])) {
                $rate = (float)$cbData['Valute']['USD']['Value'];
                $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES ('usd_rub_rate', ?) ON DUPLICATE KEY UPDATE value = ?");
                $stmt->execute([$rate, $rate]);
                resetSettingsCache();
                jsonResponse(['success' => true, 'rate' => $rate]);
            } else {
                jsonResponse(['success' => false, 'error' => 'Не удалось получить курс из API']);
            }
        } else {
            jsonResponse(['success' => false, 'error' => 'Ошибка соединения с API ЦБ РФ']);
        }
        break;
    case 'save_steam_api_key':
        error_log('save_steam_api_key data: ' . json_encode($data));
        $key = trim($data['key'] ?? '');
        if (!$key) jsonResponse(['success' => false, 'error' => 'Введите ключ'], 400);
        $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES ('steam_api_key', ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$key, $key]);
        resetSettingsCache();
        jsonResponse(['success' => true]);
        break;
    case 'fk_settings_save':
        error_log('fk_settings_save data: ' . json_encode($data));
        $settings = [
            'fk_merchant_id' => trim($data['fk_merchant_id'] ?? ''),
            'fk_phrase1' => trim($data['fk_phrase1'] ?? ''),
            'fk_phrase2' => trim($data['fk_phrase2'] ?? ''),
            'fk_mode' => in_array($data['fk_mode'] ?? '', ['test', 'production']) ? $data['fk_mode'] : 'test'
        ];
        foreach ($settings as $key => $value) {
            if ($value === '' && $key !== 'fk_merchant_id') continue;
            $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        resetSettingsCache();
        jsonResponse(['success' => true]);
        break;
    case 'ym_settings_save':
        error_log('ym_settings_save data: ' . json_encode($data));
        $settings = [
            'ym_shopid' => trim($data['ym_shopid'] ?? ''),
            'ym_password' => trim($data['ym_password'] ?? ''),
            'ym_event_url' => trim($data['ym_event_url'] ?? ''),
            'ym_mode' => in_array($data['ym_mode'] ?? '', ['test', 'production']) ? $data['ym_mode'] : 'test'
        ];
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        resetSettingsCache();
        jsonResponse(['success' => true]);
        break;

    // ========== ENOT.IO SETTINGS ==========
    case 'enot_settings_save':
        error_log('enot_settings_save data: ' . json_encode($data));
        $settings = [
            'enot_shop_id' => trim($data['enot_shop_id'] ?? ''),
            'enot_secret_key' => trim($data['enot_secret_key'] ?? ''),
            'enot_mode' => in_array($data['enot_mode'] ?? '', ['test', 'production']) ? $data['enot_mode'] : 'test'
        ];
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        resetSettingsCache();
        jsonResponse(['success' => true]);
        break;

    // ========== PAYMENT GATEWAYS MANAGEMENT ==========
    case 'gateways_list':
        $gateways = getPaymentGateways();
        jsonResponse(['success' => true, 'gateways' => $gateways]);
        break;
    case 'gateway_toggle':
        $gateway = trim($data['gateway'] ?? '');
        $enabled = (int)($data['enabled'] ?? 0);
        if (!in_array($gateway, ['freekassa', 'yoomoney', 'enot'])) {
            jsonResponse(['success' => false, 'error' => 'Неверный шлюз'], 400);
        }
        $key = 'payment_' . $gateway . '_enabled';
        $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$key, $enabled ? '1' : '0', $enabled ? '1' : '0']);
        resetSettingsCache();
        jsonResponse(['success' => true, 'enabled' => $enabled]);
        break;

    // ========== PENDING PAYMENTS ==========
    case 'pending_payments_list':
        $status = trim($data['status'] ?? 'pending');
        if ($status === 'all') {
            $stmt = $db->query("SELECT pp.*, u.username FROM pending_payments pp LEFT JOIN users u ON pp.user_id = u.id ORDER BY pp.created_at DESC LIMIT 50");
        } else {
            $stmt = $db->prepare("SELECT pp.*, u.username FROM pending_payments pp LEFT JOIN users u ON pp.user_id = u.id WHERE pp.status = ? ORDER BY pp.created_at DESC LIMIT 50");
            $stmt->execute([$status]);
        }
        jsonResponse(['success' => true, 'pending_payments' => $stmt->fetchAll()]);
        break;
    case 'pending_payment_process':
        $orderId = trim($data['order_id'] ?? '');
        if (!$orderId) jsonResponse(['success' => false, 'error' => 'Не указан order_id'], 400);

        // Получаем pending payment
        $stmt = $db->prepare("SELECT * FROM pending_payments WHERE order_id = ? AND status = 'pending'");
        $stmt->execute([$orderId]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            jsonResponse(['success' => false, 'error' => 'Платёж не найден или уже обработан']);
            break;
        }
        
        $userId = (int)$payment['user_id'];
        $amountUsd = (float)$payment['amount_usd'];
        
        try {
            $db->beginTransaction();
            
            // Начисляем баланс
            $stmt = $db->prepare("UPDATE users SET balance = balance + ?, first_deposit = 0 WHERE id = ?");
            $stmt->execute([$amountUsd, $userId]);
            
            // Записываем транзакцию
            $desc = "Ручное начисление: ЮMoney (order: $orderId)";
            $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, description, order_id) VALUES (?, 'deposit', ?, ?, ?)");
            $stmt->execute([$userId, $amountUsd, $desc, $orderId]);
            
            // Обновляем pending payment
            $stmt = $db->prepare("UPDATE pending_payments SET status = 'completed', transaction_id = LAST_INSERT_ID() WHERE order_id = ?");
            $stmt->execute([$orderId]);
            
            $db->commit();
            
            jsonResponse(['success' => true, 'message' => 'Платёж обработан']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
        break;

    // ========== ADD FUNDS ==========
    case 'add_funds':
        $userId = (int)($data['user_id'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);
        if ($userId && $amount > 0) {
            addBalance($userId, $amount, 'deposit', 'Admin: добавление средств');
            jsonResponse(['success' => true]);
        }
        jsonResponse(['success' => false, 'error' => 'Invalid data'], 400);
        break;

    // ========== DAILY BONUS ==========
    case 'daily_bonus_save':
        $name = trim($data['name'] ?? 'Ежедневный бонус');
        $cooldown = (int)($data['cooldown_hours'] ?? 24);
        $isActive = (int)($data['is_active'] ?? 1);
        
        $stmt = $db->prepare("INSERT INTO daily_bonus (id, name, cooldown_hours, is_active) VALUES (1, ?, ?, ?) ON DUPLICATE KEY UPDATE name=?, cooldown_hours=?, is_active=?");
        $stmt->execute([$name, $cooldown, $isActive, $name, $cooldown, $isActive]);
        jsonResponse(['success' => true]);
        break;
    case 'daily_bonus_reward_add':
        $name = trim($data['name'] ?? '');
        $type = trim($data['type'] ?? 'balance');
        $value = trim($data['value'] ?? '');
        $weight = (int)($data['weight'] ?? 100);
        
        // Валидация типа
        $validTypes = ['balance', 'case', 'promo', 'free_case'];
        if (!in_array($type, $validTypes)) {
            $type = 'balance';
        }
        
        if (!$name) {
            jsonResponse(['success' => false, 'error' => 'Укажите название'], 400);
        }
        
        $stmt = $db->prepare("INSERT INTO daily_bonus_rewards (name, type, value, weight) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $type, $value, $weight]);
        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId()]);
        break;
    case 'daily_bonus_reward_delete':
        $stmt = $db->prepare("DELETE FROM daily_bonus_rewards WHERE id = ?");
        $stmt->execute([(int)$data['id']]);
        jsonResponse(['success' => true]);
        break;
    case 'daily_bonus_rewards_bulk_add':
        $rewards = $data['rewards'] ?? [];
        if (!is_array($rewards) || empty($rewards)) {
            jsonResponse(['success' => false, 'error' => 'Некорректные данные'], 400);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO daily_bonus_rewards (name, type, value, weight) VALUES (?, ?, ?, ?)");
            foreach ($rewards as $reward) {
                $name = trim($reward['name'] ?? '');
                $type = trim($reward['type'] ?? 'balance');
                $value = trim($reward['value'] ?? '');
                $weight = (int)($reward['weight'] ?? 100);
                if (!$name || $value === '') {
                    continue;
                }
                $stmt->execute([$name, $type, $value, $weight]);
            }
            $db->commit();
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Ошибка при добавлении наград: ' . $e->getMessage()], 500);
        }
        break;
    case 'daily_bonus_load_defaults':
        // Загрузка стандартных наград для ежедневного бонуса
        $defaultRewards = [
            // Баланс — маленькие суммы (частые)
            ['name' => 'Мелочь', 'type' => 'balance', 'value' => '0.10', 'weight' => 300],
            ['name' => 'На кофе', 'type' => 'balance', 'value' => '0.25', 'weight' => 250],
            ['name' => 'Немного', 'type' => 'balance', 'value' => '0.50', 'weight' => 200],
            ['name' => 'Бонус', 'type' => 'balance', 'value' => '0.75', 'weight' => 150],
            ['name' => 'Удача', 'type' => 'balance', 'value' => '1.00', 'weight' => 120],
            
            // Баланс — средние суммы (реже)
            ['name' => 'Средний бонус', 'type' => 'balance', 'value' => '1.50', 'weight' => 80],
            ['name' => 'Хороший улов', 'type' => 'balance', 'value' => '2.00', 'weight' => 60],
            ['name' => 'Приятно', 'type' => 'balance', 'value' => '2.50', 'weight' => 45],
            ['name' => 'Отлично', 'type' => 'balance', 'value' => '3.00', 'weight' => 30],
            
            // Баланс — большие суммы (редко)
            ['name' => 'Джекпот дня', 'type' => 'balance', 'value' => '5.00', 'weight' => 15],
            ['name' => 'Супер бонус', 'type' => 'balance', 'value' => '7.50', 'weight' => 8],
            ['name' => 'Мега приз', 'type' => 'balance', 'value' => '10.00', 'weight' => 3],
            
            // Промокоды — бонус к депозиту
            ['name' => 'Мини бонус', 'type' => 'promo', 'value' => '5', 'weight' => 200],
            ['name' => 'Стандарт', 'type' => 'promo', 'value' => '10', 'weight' => 150],
            ['name' => 'Большой бонус', 'type' => 'promo', 'value' => '15', 'weight' => 100],
            ['name' => 'Супер бонус', 'type' => 'promo', 'value' => '20', 'weight' => 50],
            ['name' => 'Ультра бонус', 'type' => 'promo', 'value' => '25', 'weight' => 20],
            
            // Бесплатные кейсы
            ['name' => 'Бесплатный кейс', 'type' => 'free_case', 'value' => '1', 'weight' => 100],
            ['name' => 'Пакет кейсов', 'type' => 'free_case', 'value' => '3', 'weight' => 60],
            ['name' => 'Большой пакет', 'type' => 'free_case', 'value' => '5', 'weight' => 30],
        ];

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO daily_bonus_rewards (name, type, value, weight) VALUES (?, ?, ?, ?)");
            foreach ($defaultRewards as $reward) {
                $stmt->execute([
                    $reward['name'],
                    $reward['type'],
                    $reward['value'],
                    $reward['weight']
                ]);
            }
            $db->commit();
            jsonResponse(['success' => true, 'count' => count($defaultRewards)]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()], 500);
        }
        break;
    case 'daily_bonus_rewards_delete_all':
        $db->exec("DELETE FROM daily_bonus_rewards");
        jsonResponse(['success' => true]);
        break;
    case 'daily_bonus_claim':
        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            jsonResponse(['success' => false, 'error' => 'Не авторизован'], 401);
        }

        $stmt = $db->prepare("SELECT * FROM daily_bonus WHERE id = 1 AND is_active = 1 LIMIT 1");
        $stmt->execute();
        $bonus = $stmt->fetch();
        if (!$bonus) {
            jsonResponse(['success' => false, 'error' => 'Ежедневный бонус недоступен']);
        }
        
        $stmt = $db->prepare("SELECT claimed_at FROM user_daily_bonus WHERE user_id = ? ORDER BY claimed_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $lastClaim = $stmt->fetch();
        if ($lastClaim) {
            $lastTime = strtotime($lastClaim['claimed_at']);
            $diff = time() - $lastTime;
            if ($diff < 86400) {
                jsonResponse(['success' => false, 'error' => 'Подождите ещё ' . ceil((86400 - $diff) / 60) . ' минут']);
            }
        }
        
        $stmt = $db->prepare("SELECT * FROM daily_bonus_rewards WHERE is_active = 1 ORDER BY weight DESC");
        $stmt->execute();
        $rewards = $stmt->fetchAll();
        if (empty($rewards)) {
            jsonResponse(['success' => false, 'error' => 'Нет доступных наград']);
        }
        
        $totalWeight = array_sum(array_column($rewards, 'weight'));
        $random = mt_rand(1, $totalWeight);
        $current = 0;
        $selected = end($rewards);
        foreach ($rewards as $reward) {
            $current += $reward['weight'];
            if ($random <= $current) {
                $selected = $reward;
                break;
            }
        }
        
        $rewardAmount = 0;
        $rewardText = $selected['name'];
        if ($selected['type'] === 'balance' && !empty($selected['value'])) {
            $rewardAmount = (float)$selected['value'];
            addBalance($userId, $rewardAmount, 'daily_bonus', 'Ежедневный бонус');
        }
        
        $stmt = $db->prepare("INSERT INTO user_daily_bonus (user_id, bonus_id, amount) VALUES (?, 1, ?)");
        $stmt->execute([$userId, $rewardAmount]);
        
        jsonResponse(['success' => true, 'reward' => ['text' => $rewardText, 'amount' => $rewardAmount]]);
        break;

    // ========== BATTLE PASS ==========
    case 'battle_pass_season_create':
        $stmt = $db->prepare("INSERT INTO battle_pass_seasons (name, price, max_level, start_date, end_date, is_active) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 0)");
        $stmt->execute([$data['name'] ?? '', $data['price'] ?? 299, $data['max_level'] ?? 50, $data['duration'] ?? 30]);
        jsonResponse(['success' => true]);
        break;
    case 'battle_pass_season_activate':
        $seasonId = (int)($data['id'] ?? 0);
        if (!$seasonId) jsonResponse(['success' => false, 'error' => 'Неверный ID'], 400);

        // Проверяем существует ли сезон
        $stmt = $db->prepare("SELECT id FROM battle_pass_seasons WHERE id = ?");
        $stmt->execute([$seasonId]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'error' => 'Сезон не найден'], 404);
        }
        
        $db->query("UPDATE battle_pass_seasons SET is_active = 0");
        $stmt = $db->prepare("UPDATE battle_pass_seasons SET is_active = 1 WHERE id = ?");
        $stmt->execute([$seasonId]);
        jsonResponse(['success' => true]);
        break;
    case 'battle_pass_season_update':
        $seasonId = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $maxLevel = (int)($data['max_level'] ?? 0);
        
        if (!$seasonId || !$name || $price <= 0 || $maxLevel <= 0) {
            jsonResponse(['success' => false, 'error' => 'Некорректные данные'], 400);
        }
        
        $stmt = $db->prepare("UPDATE battle_pass_seasons SET name = ?, price = ?, max_level = ? WHERE id = ?");
        $stmt->execute([$name, $price, $maxLevel, $seasonId]);
        jsonResponse(['success' => true]);
        break;
    case 'battle_pass_season_delete':
        $seasonId = (int)($data['id'] ?? 0);
        if (!$seasonId) jsonResponse(['success' => false, 'error' => 'Неверный ID'], 400);
        
        // Проверяем что сезон не активен
        $stmt = $db->prepare("SELECT is_active FROM battle_pass_seasons WHERE id = ?");
        $stmt->execute([$seasonId]);
        $season = $stmt->fetch();
        if (!$season) {
            jsonResponse(['success' => false, 'error' => 'Сезон не найден'], 404);
        }
        if ($season['is_active']) {
            jsonResponse(['success' => false, 'error' => 'Нельзя удалить активный сезон. Сначала деактивируйте его.'], 400);
        }
        
        // Проверяем что нет пользователей
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_battle_pass WHERE season_id = ?");
        $stmt->execute([$seasonId]);
        $usersCount = (int)$stmt->fetchColumn();
        if ($usersCount > 0) {
            jsonResponse(['success' => false, 'error' => 'Нельзя удалить сезон с пользователями (' . $usersCount . ' чел.)'], 400);
        }
        
        $stmt = $db->prepare("DELETE FROM battle_pass_seasons WHERE id = ?");
        $stmt->execute([$seasonId]);
        jsonResponse(['success' => true]);
        break;
    case 'battle_pass_season_reset':
        $stmt = $db->query("SELECT id FROM battle_pass_seasons WHERE is_active = 1 LIMIT 1");
        $season = $stmt->fetch();
        if (!$season) jsonResponse(['success' => false, 'error' => 'Нет активного сезона'], 400);
        $db->prepare("DELETE FROM user_battle_pass WHERE season_id = ?")->execute([$season['id']]);
        jsonResponse(['success' => true]);
        break;
    case 'battle_pass_reward_add':
        $seasonId = (int)($data['season_id'] ?? 0);
        $level = (int)($data['level'] ?? 0);
        $rewardType = trim($data['reward_type'] ?? 'balance');
        $rewardValue = $data['reward_value'] ?? '0';
        $rewardValue = $rewardValue === '' ? '0' : trim($rewardValue);
        $rewardDesc = trim($data['reward_description'] ?? '');
        $premiumOnly = (int)($data['is_premium_only'] ?? 0);
        $caseId = (int)($data['case_id'] ?? 0);

        if (!$seasonId || !$level || !$rewardType) {
            jsonResponse(['success' => false, 'error' => 'Укажите уровень, название и тип награды'], 400);
        }
        
        // Разрешаем несколько наград на один уровень
        // Проверяем только что это не точная копия существующей награды
        $stmt = $db->prepare("SELECT id FROM battle_pass_rewards WHERE season_id = ? AND level = ? AND reward_type = ? AND reward_value = ? AND reward_description = ?");
        $stmt->execute([$seasonId, $level, $rewardType, $rewardValue, $rewardDesc]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'error' => 'Такая награда уже существует на этом уровне'], 400);
        }
        
        // case_id = 0 → NULL (нет привязки к кейсу)
        $caseIdValue = $caseId > 0 ? $caseId : null;
        
        $stmt = $db->prepare("INSERT INTO battle_pass_rewards (season_id, level, reward_type, reward_value, reward_description, is_premium_only, case_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$seasonId, $level, $rewardType, $rewardValue, $rewardDesc, $premiumOnly, $caseIdValue]);
        jsonResponse(['success' => true]);
        break;
    case 'battle_pass_reward_delete':
        $stmt = $db->prepare("DELETE FROM battle_pass_rewards WHERE id = ?");
        $stmt->execute([(int)$data['id']]);
        jsonResponse(['success' => true]);
        break;
    case 'battle_pass_rewards_list':
        $stmt = $db->prepare("SELECT * FROM battle_pass_rewards WHERE season_id = ? ORDER BY level ASC");
        $stmt->execute([(int)$_GET['season_id']]);
        jsonResponse(['success' => true, 'rewards' => $stmt->fetchAll()]);
        break;
    case 'battle_pass_task_add':
        $seasonId = (int)($data['season_id'] ?? 0);
        $desc = trim($data['task_description'] ?? '');
        $target = (int)($data['target_value'] ?? 1);
        $xp = (int)($data['experience_reward'] ?? 100);
        $repeat = (int)($data['is_repeatable'] ?? 0);
        $type = trim($data['task_type'] ?? 'case_open');

        if (!$seasonId || !$desc || !$target || !$xp) {
            jsonResponse(['success' => false, 'error' => 'Заполните все поля'], 400);
        }
        
        $stmt = $db->prepare("INSERT INTO battle_pass_tasks (season_id, task_type, task_description, target_value, experience_reward, is_repeatable) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$seasonId, $type, $desc, $target, $xp, $repeat]);
        jsonResponse(['success' => true]);
        break;
    case 'battle_pass_task_bulk_add':
        $seasonId = (int)($data['season_id'] ?? 0);
        $tasks = $data['tasks'] ?? [];
        if (!$seasonId || !is_array($tasks) || empty($tasks)) {
            jsonResponse(['success' => false, 'error' => 'Некорректные данные'], 400);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO battle_pass_tasks (season_id, task_type, task_description, target_value, experience_reward, is_repeatable) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($tasks as $task) {
                $desc = trim($task['task_description'] ?? '');
                $target = (int)($task['target_value'] ?? 1);
                $xp = (int)($task['experience_reward'] ?? 100);
                $repeat = (int)($task['is_repeatable'] ?? 0);
                $type = trim($task['task_type'] ?? 'case_open');
                if (!$desc || !$target || !$xp) {
                    continue;
                }
                $stmt->execute([$seasonId, $type, $desc, $target, $xp, $repeat]);
            }
            $db->commit();
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Ошибка при создании заданий: ' . $e->getMessage()], 500);
        }
        break;
    case 'battle_pass_task_delete':
        $stmt = $db->prepare("DELETE FROM battle_pass_tasks WHERE id = ?");
        $stmt->execute([(int)$data['id']]);
        jsonResponse(['success' => true]);
        break;

    // ========== FREE CASES ==========
    case 'free_cases_list':
        $stmt = $db->query("SELECT * FROM free_cases ORDER BY sort_order");
        jsonResponse(['success' => true, 'cases' => $stmt->fetchAll()]);
        break;
    case 'free_case_items_list':
        $caseId = (int)($_GET['case_id'] ?? 0);
        if (!$caseId) jsonResponse(['success' => true, 'items' => []]);
        $stmt = $db->prepare("SELECT * FROM free_case_items WHERE case_id = ? ORDER BY FIELD(rarity, 'consumer', 'industrial', 'milspec', 'restricted', 'classified', 'covert', 'extraordinary')");
        $stmt->execute([$caseId]);
        jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
        break;
    case 'add_free_case':
        $stmt = $db->prepare("INSERT INTO free_cases (name, min_deposit, sort_order) VALUES (?, ?, ?)");
        $stmt->execute([$data['name'] ?? '', (float)($data['min_deposit'] ?? 0), (int)($data['sort_order'] ?? 0)]);
        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId()]);
        break;
    case 'toggle_free_case':
        $stmt = $db->prepare("UPDATE free_cases SET is_active = ? WHERE id = ?");
        $stmt->execute([(int)$data['is_active'], (int)$data['id']]);
        jsonResponse(['success' => true]);
        break;
    case 'delete_free_case':
        $stmt = $db->prepare("DELETE FROM free_cases WHERE id = ?");
        $stmt->execute([(int)$data['id']]);
        jsonResponse(['success' => true]);
        break;
    case 'add_free_case_item':
        $caseId = (int)($data['case_id'] ?? 0);
        $name = trim($data['item_name'] ?? '');
        $image = trim($data['item_image'] ?? '');
        $rarity = trim($data['rarity'] ?? 'milspec');
        $price = (float)($data['price'] ?? 0);
        $weight = (int)($data['weight'] ?? 1);
        if (!$caseId || !$name || !$rarity) jsonResponse(['success' => false, 'error' => 'Некорректные данные'], 400);
        if (empty($image)) $image = autoFillItemImage($name);
        $stmt = $db->prepare("INSERT INTO free_case_items (case_id, item_name, item_image, rarity, price, weight) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$caseId, $name, $image, $rarity, $price, $weight]);
        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId(), 'image' => $image]);
        break;
    case 'update_free_case_item':
        $itemId = (int)($data['id'] ?? 0);
        $image = trim($data['item_image'] ?? '');
        $rarity = trim($data['rarity'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $weight = (int)($data['weight'] ?? 1);
        $isActive = (int)($data['is_active'] ?? 1);
        if (!$itemId) jsonResponse(['success' => false, 'error' => 'Неверный ID'], 400);
        $stmt = $db->prepare("UPDATE free_case_items SET item_image = ?, rarity = ?, price = ?, weight = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$image, $rarity, $price, $weight, $isActive, $itemId]);
        jsonResponse(['success' => true, 'image' => $image]);
        break;
    case 'delete_free_case_item':
        $stmt = $db->prepare("DELETE FROM free_case_items WHERE id = ?");
        $stmt->execute([(int)$data['id']]);
        jsonResponse(['success' => true]);
        break;
    case 'free_case_item_add_from_steam':
        $caseId = (int)($data['case_id'] ?? 0);
        $steamItemId = (int)($data['steam_item_id'] ?? 0);
        $weight = (int)($data['weight'] ?? 1);

        if (!$caseId || !$steamItemId) {
            jsonResponse(['success' => false, 'error' => 'Некорректные данные'], 400);
            break;
        }
        
        // Получаем предмет из steam_items
        $stmt = $db->prepare("SELECT * FROM steam_items WHERE id = ? LIMIT 1");
        $stmt->execute([$steamItemId]);
        $steamItem = $stmt->fetch();
        
        if (!$steamItem) {
            jsonResponse(['success' => false, 'error' => 'Предмет не найден в базе Steam'], 404);
            break;
        }
        
        // Проверяем что предмет уже не добавлен
        $stmt = $db->prepare("SELECT id FROM free_case_items WHERE case_id = ? AND item_name = ?");
        $stmt->execute([$caseId, $steamItem['market_hash_name']]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'error' => 'Этот предмет уже добавлен в кейс'], 400);
            break;
        }
        
        // Преобразуем rarity из формата Steam во внутренний формат
        $rarityMap = [
            'Consumer Grade' => 'consumer',
            'Industrial Grade' => 'industrial',
            'Mil-Spec' => 'milspec',
            'Restricted' => 'restricted',
            'Classified' => 'classified',
            'Covert' => 'covert',
            'Extraordinary' => 'extraordinary',
            'Contraband' => 'contraband',
        ];
        $rarity = $steamItem['rarity'] ?? 'milspec';
        $rarityLower = strtolower($rarity);
        
        // Проверяем точное совпадение
        $normalizedRarity = $rarityMap[$rarity] ?? null;
        
        // Если не найдено, пробуем частичное совпадение
        if (!$normalizedRarity) {
            foreach ($rarityMap as $steamRarity => $internalRarity) {
                if (stripos($rarity, $steamRarity) !== false || stripos($steamRarity, $rarity) !== false) {
                    $normalizedRarity = $internalRarity;
                    break;
                }
            }
        }
        
        // Если всё ещё не найдено, используем milspec по умолчанию
        if (!$normalizedRarity) {
            $normalizedRarity = 'milspec';
        }
        
        // Добавляем предмет в free_case_items
        $stmt = $db->prepare("INSERT INTO free_case_items (case_id, item_name, item_image, rarity, price, weight) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $caseId,
            $steamItem['market_hash_name'],
            $steamItem['icon_url'],
            $normalizedRarity,
            $steamItem['price_usd'],
            $weight
        ]);
        
        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId()]);
        break;
    case 'update_steam_prices':
        // Batch-режим для веба
        $batchSize = (int)($data['batch_size'] ?? 100);
        $offset = (int)($data['offset'] ?? 0);
        
        $scriptPath = __DIR__ . '/update_steam_prices.php';
        if (!file_exists($scriptPath)) {
            jsonResponse(['success' => false, 'error' => 'Скрипт не найден']);
            break;
        }
        
        // Выполняем скрипт с параметрами
        $url = 'http://' . $_SERVER['HTTP_HOST'] . '/admin/update_steam_prices.php?batch=' . $batchSize . '&offset=' . $offset;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                jsonResponse($result);
            } else {
                jsonResponse(['success' => false, 'error' => 'Ошибка скрипта', 'details' => $result]);
            }
        } else {
            jsonResponse(['success' => false, 'error' => 'HTTP ' . $httpCode]);
        }
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action: ' . $action], 400);
} // end switch
} catch (Exception $e) {
    error_log('API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
?>
