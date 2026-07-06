<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

// Проверка: раздел отключён?
if (getSetting('battle_pass_enabled', '1') !== '1') {
    jsonResponse(['success' => false, 'error' => 'Раздел временно недоступен']);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'error' => 'Не авторизован'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$userId = $_SESSION['user_id'];
$db = db();

switch ($action) {
    case 'buy':
        // Get active season
        $stmt = $db->prepare("SELECT * FROM battle_pass_seasons WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $season = $stmt->fetch();
        
        if (!$season) {
            jsonResponse(['success' => false, 'error' => 'Сезон не активен']);
        }
        
        // Check if already premium
        $stmt = $db->prepare("SELECT * FROM user_battle_pass WHERE user_id = ? AND season_id = ? AND is_premium = 1");
        $stmt->execute([$userId, $season['id']]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'error' => 'У вас уже есть Premium']);
        }
        
        $price = (float)$season['price'];
        
        // Check balance
        $user = getCurrentUser();
        if ($user['balance'] < $price) {
            jsonResponse(['success' => false, 'error' => 'Недостаточно средств. Требуется: '.number_format($price, 2).'$']);
        }
        
        // Deduct balance
        if (!subtractBalance($userId, $price, 'battle_pass', "Покупка Battle Pass: {$season['name']}")) {
            jsonResponse(['success' => false, 'error' => 'Ошибка списания средств']);
        }
        
        // Create or update battle pass record
        $stmt = $db->prepare("INSERT INTO user_battle_pass (user_id, season_id, is_premium, purchased_at) VALUES (?, ?, 1, NOW()) 
                              ON DUPLICATE KEY UPDATE is_premium = 1, purchased_at = NOW()");
        $stmt->execute([$userId, $season['id']]);
        
        jsonResponse(['success' => true]);
        break;
        
    case 'claim':
        $rewardId = (int)($data['reward_id'] ?? 0);
        
        // Get reward
        $stmt = $db->prepare("SELECT * FROM battle_pass_rewards WHERE id = ?");
        $stmt->execute([$rewardId]);
        $reward = $stmt->fetch();
        
        if (!$reward) {
            jsonResponse(['success' => false, 'error' => 'Награда не найдена']);
        }
        
        // Check if already claimed
        $stmt = $db->prepare("SELECT * FROM user_battle_pass_claims WHERE user_id = ? AND reward_id = ?");
        $stmt->execute([$userId, $rewardId]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'error' => 'Награда уже получена']);
        }
        
        // Check user's level
        $stmt = $db->prepare("SELECT * FROM user_battle_pass WHERE user_id = ? AND season_id = ?");
        $stmt->execute([$userId, $reward['season_id']]);
        $userBP = $stmt->fetch();
        
        if (!$userBP || $userBP['current_level'] < $reward['level']) {
            jsonResponse(['success' => false, 'error' => 'Недостаточный уровень']);
        }
        
        // Check premium requirement
        if ($reward['is_premium_only'] && !$userBP['is_premium']) {
            jsonResponse(['success' => false, 'error' => 'Требуется Premium Battle Pass']);
        }
        
        // Grant reward
        $db->beginTransaction();
        try {
            // Mark as claimed
            $stmt = $db->prepare("INSERT INTO user_battle_pass_claims (user_id, season_id, reward_id) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $reward['season_id'], $rewardId]);
            
            $promoCode = null;
            
            // Give reward based on type
            switch ($reward['reward_type']) {
                case 'balance':
                    $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$reward['reward_value'], $userId]);
                    break;
                    
                case 'case':
                    // Выдаём билет на кейс
                    if (!empty($reward['case_id'])) {
                        $stmt = $db->prepare("SELECT id, name, price FROM cases WHERE id = ? AND is_active = 1 LIMIT 1");
                        $stmt->execute([$reward['case_id']]);
                        $case = $stmt->fetch();
                        if ($case) {
                            $stmt = $db->prepare("INSERT INTO user_inventory (user_id, item_name, item_image, rarity, price) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$userId, "🎫 Билет на кейс: {$case['name']}", '', 'milspec', $case['price']]);
                        }
                    } else {
                        // Fallback: баланс
                        $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$reward['reward_value'] ?? 0, $userId]);
                    }
                    break;

                case 'promo':
                    // Генерируем промокод
                    $bonusPercent = floatval($reward['reward_value']);
                    $code = 'BP-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                    
                    $stmt = $db->prepare("INSERT INTO bp_promo_codes (user_id, season_id, reward_id, code, bonus_percent, created_at, expires_at, used) VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 48 HOUR), 0)");
                    $stmt->execute([$userId, $reward['season_id'], $rewardId, $code, $bonusPercent]);
                    
                    $promoCode = $code;
                    break;
            }
            
            $db->commit();
            
            $response = ['success' => true];
            if ($promoCode) {
                $response['promo_code'] = $promoCode;
                $response['bonus_percent'] = $bonusPercent;
                $response['expires_in_hours'] = 48;
            }
            jsonResponse($response);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Ошибка получения награды: ' . $e->getMessage()]);
        }
        break;
        
    case 'add_xp':
        $taskId = (int)($data['task_id'] ?? 0);
        $xpAmount = (int)($data['xp'] ?? 0);
        
        if (!$taskId || !$xpAmount) {
            jsonResponse(['success' => false, 'error' => 'Некорректные данные']);
        }
        
        // Get task
        $stmt = $db->prepare("SELECT * FROM battle_pass_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        
        if (!$task) {
            jsonResponse(['success' => false, 'error' => 'Задание не найдено']);
        }
        
        // Get active season
        $stmt = $db->prepare("SELECT * FROM battle_pass_seasons WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $season = $stmt->fetch();
        
        if (!$season) {
            jsonResponse(['success' => false, 'error' => 'Сезон не активен']);
        }
        
        // Update task progress
        $stmt = $db->prepare("SELECT * FROM user_battle_pass_tasks WHERE user_id = ? AND task_id = ? AND season_id = ?");
        $stmt->execute([$userId, $taskId, $season['id']]);
        $userTask = $stmt->fetch();
        
        if (!$userTask) {
            // Create task record
            $stmt = $db->prepare("INSERT INTO user_battle_pass_tasks (user_id, task_id, season_id, progress) VALUES (?, ?, ?, 1)");
            $stmt->execute([$userId, $taskId, $season['id']]);
            $progress = 1;
        } else {
            if ($userTask['completed'] && !$task['is_repeatable']) {
                jsonResponse(['success' => false, 'error' => 'Задание уже выполнено']);
            }
            
            $progress = ($userTask['progress'] ?? 0) + 1;
            $completed = $progress >= $task['target_value'] ? 1 : 0;
            
            $stmt = $db->prepare("UPDATE user_battle_pass_tasks SET progress = ?, completed = ?, completed_at = ? WHERE user_id = ? AND task_id = ? AND season_id = ?");
            $stmt->execute([$progress, $completed, $completed ? 'NOW()' : null, $userId, $taskId, $season['id']]);
        }
        
        // Add XP if task completed
        if ($progress >= $task['target_value']) {
            $stmt = $db->prepare("UPDATE user_battle_pass SET experience = experience + ? WHERE user_id = ? AND season_id = ?");
            $stmt->execute([$task['experience_reward'], $userId, $season['id']]);
            
            // Check for level up
            $stmt = $db->prepare("SELECT * FROM user_battle_pass WHERE user_id = ? AND season_id = ?");
            $stmt->execute([$userId, $season['id']]);
            $userBP = $stmt->fetch();
            
            if ($userBP) {
                $xpPerLevel = 250;
                $newLevel = floor($userBP['experience'] / $xpPerLevel) + 1;
                if ($newLevel > $userBP['current_level'] && $newLevel <= $season['max_level']) {
                    $stmt = $db->prepare("UPDATE user_battle_pass SET current_level = ? WHERE user_id = ? AND season_id = ?");
                    $stmt->execute([$newLevel, $userId, $season['id']]);
                }
            }
        }
        
        jsonResponse(['success' => true, 'progress' => $progress]);
        break;
        
    case 'claim_level':
        $level = (int)($data['level'] ?? 0);
        
        if (!$level) {
            jsonResponse(['success' => false, 'error' => 'Некорректный уровень']);
        }
        
        // Get active season
        $stmt = $db->prepare("SELECT * FROM battle_pass_seasons WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $season = $stmt->fetch();
        
        if (!$season) {
            jsonResponse(['success' => false, 'error' => 'Сезон не активен']);
        }
        
        // Check user's level
        $stmt = $db->prepare("SELECT * FROM user_battle_pass WHERE user_id = ? AND season_id = ?");
        $stmt->execute([$userId, $season['id']]);
        $userBP = $stmt->fetch();
        
        if (!$userBP || $userBP['current_level'] < $level) {
            jsonResponse(['success' => false, 'error' => 'Недостаточный уровень']);
        }
        
        // Get all rewards for this level
        $stmt = $db->prepare("SELECT * FROM battle_pass_rewards WHERE season_id = ? AND level = ?");
        $stmt->execute([$season['id'], $level]);
        $rewards = $stmt->fetchAll();
        
        if (empty($rewards)) {
            jsonResponse(['success' => false, 'error' => 'Нет наград на этом уровне']);
        }
        
        $db->beginTransaction();
        try {
            $promoCodes = [];
            
            foreach ($rewards as $reward) {
                // Check if already claimed
                $stmt = $db->prepare("SELECT * FROM user_battle_pass_claims WHERE user_id = ? AND reward_id = ?");
                $stmt->execute([$userId, $reward['id']]);
                if ($stmt->fetch()) {
                    continue; // Уже получено, пропускаем
                }
                
                // Check premium requirement
                if ($reward['is_premium_only'] && !$userBP['is_premium']) {
                    continue; // Требуется Premium, пропускаем
                }
                
                // Mark as claimed
                $stmt = $db->prepare("INSERT INTO user_battle_pass_claims (user_id, season_id, reward_id) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $season['id'], $reward['id']]);
                
                // Give reward based on type
                switch ($reward['reward_type']) {
                    case 'balance':
                        $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$reward['reward_value'], $userId]);
                        break;
                        
                    case 'case':
                        if (!empty($reward['case_id'])) {
                            $stmt = $db->prepare("SELECT id, name, price FROM cases WHERE id = ? AND is_active = 1 LIMIT 1");
                            $stmt->execute([$reward['case_id']]);
                            $case = $stmt->fetch();
                            if ($case) {
                                $stmt = $db->prepare("INSERT INTO user_inventory (user_id, item_name, item_image, rarity, price) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$userId, "🎫 Билет на кейс: {$case['name']}", '', 'milspec', $case['price']]);
                            }
                        }
                        break;
                        
                    case 'promo':
                        $bonusPercent = floatval($reward['reward_value']);
                        $code = 'BP-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                        
                        $stmt = $db->prepare("INSERT INTO bp_promo_codes (user_id, season_id, reward_id, code, bonus_percent, created_at, expires_at, used) VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 48 HOUR), 0)");
                        $stmt->execute([$userId, $season['id'], $reward['id'], $code, $bonusPercent]);
                        
                        $promoCodes[] = ['code' => $code, 'bonus_percent' => $bonusPercent];
                        break;
                }
            }
            
            $db->commit();
            
            $response = ['success' => true];
            if (!empty($promoCodes)) {
                $response['promo_codes'] = $promoCodes;
            }
            jsonResponse($response);
            
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Ошибка получения наград: ' . $e->getMessage()]);
        }
        break;
        
    case 'redeem_promo':
        $code = trim($data['code'] ?? '');
        
        if (!$code) {
            jsonResponse(['success' => false, 'error' => 'Введите промокод'], 400);
        }
        
        // Проверяем промокод
        $stmt = $db->prepare("SELECT * FROM bp_promo_codes WHERE code = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$code]);
        $promo = $stmt->fetch();
        
        if (!$promo) {
            jsonResponse(['success' => false, 'error' => 'Промокод не найден или истёк'], 400);
        }
        
        // Проверяем что промокод для этого пользователя
        if ($promo['user_id'] != $userId) {
            jsonResponse(['success' => false, 'error' => 'Этот промокод не ваш'], 400);
        }
        
        // Применяем бонус
        $bonusPercent = floatval($promo['bonus_percent']);
        
        $db->beginTransaction();
        try {
            // Применяем бонус
            $stmt = $db->prepare("UPDATE users SET promo_bonus_percent = promo_bonus_percent + ? WHERE id = ?");
            $stmt->execute([$bonusPercent, $userId]);
            
            // Помечаем как использованный
            $stmt = $db->prepare("UPDATE bp_promo_codes SET used = 1 WHERE id = ?");
            $stmt->execute([$promo['id']]);
            
            $db->commit();
            
            jsonResponse([
                'success' => true,
                'bonus_percent' => $bonusPercent,
                'message' => "Бонус +{$bonusPercent}% к пополнению активирован!"
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Ошибка активации: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_promo_codes':
        $stmt = $db->prepare("SELECT p.*, r.reward_description FROM bp_promo_codes p 
                              LEFT JOIN battle_pass_rewards r ON p.reward_id = r.id 
                              WHERE p.user_id = ? AND p.season_id = (SELECT id FROM battle_pass_seasons WHERE is_active = 1 LIMIT 1)
                              ORDER BY p.created_at DESC");
        $stmt->execute([$userId]);
        $codes = $stmt->fetchAll();
        
        jsonResponse(['success' => true, 'codes' => $codes]);
        break;
        
    default:
        jsonResponse(['success' => false, 'error' => 'Неизвестное действие']);
}
