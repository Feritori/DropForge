<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
requireAuth();

// Проверка: раздел отключён?
if (getSetting('daily_bonus_enabled', '1') !== '1') {
    jsonResponse(['success' => false, 'error' => 'Раздел временно недоступен']);
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$user = getCurrentUser();

try {
    // Получить настройки бонуса
    if ($action === 'get') {
        $stmt = db()->prepare("SELECT * FROM daily_bonus WHERE id = 1 AND is_active = 1 LIMIT 1");
        $stmt->execute();
        $bonus = $stmt->fetch();
        
        if (!$bonus) {
            jsonResponse(['success' => false, 'error' => 'Daily bonus not available']);
        }
        
        // Проверить когда пользователь последний раз получал бонус
        $stmt = db()->prepare("
            SELECT claimed_at FROM user_daily_bonus 
            WHERE user_id = ? 
            ORDER BY claimed_at DESC LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $lastClaim = $stmt->fetch();
        
        $canClaim = false;
        $timeUntilNext = 0;
        
        if (!$lastClaim) {
            $canClaim = true;
        } else {
            $lastTime = strtotime($lastClaim['claimed_at']);
            $now = time();
            $diff = $now - $lastTime;
            
            if ($diff >= $bonus['cooldown_hours'] * 3600) {
                $canClaim = true;
            } else {
                $timeUntilNext = ($bonus['cooldown_hours'] * 3600) - $diff;
            }
        }
        
        jsonResponse([
            'success' => true,
            'bonus' => $bonus,
            'can_claim' => $canClaim,
            'time_until_next' => $timeUntilNext,
            'last_claimed' => $lastClaim['claimed_at'] ?? null
        ]);
    }
    
    //_claim бонус
    if ($action === 'claim') {
        $stmt = db()->prepare("SELECT * FROM daily_bonus WHERE id = 1 AND is_active = 1 LIMIT 1");
        $stmt->execute();
        $bonus = $stmt->fetch();
        
        if (!$bonus) {
            jsonResponse(['success' => false, 'error' => 'Daily bonus not available']);
        }
        
        // Проверить кулдаун
        $stmt = db()->prepare("
            SELECT claimed_at FROM user_daily_bonus 
            WHERE user_id = ? 
            ORDER BY claimed_at DESC LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $lastClaim = $stmt->fetch();
        
        if ($lastClaim) {
            $lastTime = strtotime($lastClaim['claimed_at']);
            $now = time();
            $diff = $now - $lastTime;
            
            if ($diff < $bonus['cooldown_hours'] * 3600) {
                jsonResponse(['success' => false, 'error' => "Подождите ещё " . ceil(($bonus['cooldown_hours'] * 3600 - $diff) / 60) . " минут"]);
            }
        }
        
        // Рассчитать награду
        $rewardAmount = 0;
        if ($bonus['bonus_type'] === 'fixed') {
            $rewardAmount = (float)$bonus['bonus_amount'];
        } elseif ($bonus['bonus_type'] === 'percentage') {
            $rewardAmount = $user['balance'] * ((float)$bonus['bonus_amount'] / 100);
        }
        
        if ($rewardAmount <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid reward amount']);
        }
        
        // Начислить баланс
        addBalance($user['id'], $rewardAmount, 'daily_bonus', 'Ежедневный бонус');
        
        // Записать в историю
        $stmt = db()->prepare("INSERT INTO user_daily_bonus (user_id, bonus_id, amount) VALUES (?, 1, ?)");
        $stmt->execute([$user['id'], $rewardAmount]);
        
        jsonResponse([
            'success' => true,
            'amount' => $rewardAmount,
            'new_balance' => getCurrentUser()['balance']
        ]);
    }
    
    jsonResponse(['success' => false, 'error' => 'Unknown action']);
} catch (Exception $e) {
    error_log('Daily bonus error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Server error']);
}
