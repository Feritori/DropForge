<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

// Включаем отображение ошибок для отладки (убрать на продакшене)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Live Feed API
if ($action === 'live_feed') {
    try {
        // Проверяем существование таблицы
        $tableCheck = db()->query("SHOW TABLES LIKE 'live_wins'")->fetch();
        if (!$tableCheck) {
            jsonResponse(['success' => true, 'wins' => []]);
            exit;
        }
        
        $stmt = db()->query("
            SELECT lw.*, c.name as case_name
            FROM live_wins lw
            LEFT JOIN cases c ON lw.case_id = c.id
            ORDER BY lw.created_at DESC
            LIMIT 50
        ");
        $wins = $stmt->fetchAll();
        
        // Добавляем цвета редкости
        foreach ($wins as &$w) {
            $w['rarity_color'] = RAIRITY_COLORS[$w['rarity']] ?? '#888';
        }
        unset($w);
        
        jsonResponse(['success' => true, 'wins' => $wins]);
    } catch (PDOException $e) {
        error_log("Live feed error: " . $e->getMessage());
        jsonResponse(['success' => true, 'wins' => []]);
    } catch (Exception $e) {
        error_log("Live feed error: " . $e->getMessage());
        jsonResponse(['success' => true, 'wins' => []]);
    }
}

// Recent wins для главной страницы (последние 5)
if ($action === 'recent_wins') {
    try {
        $tableCheck = db()->query("SHOW TABLES LIKE 'live_wins'")->fetch();
        if (!$tableCheck) {
            jsonResponse(['success' => true, 'wins' => []]);
            exit;
        }
        
        $stmt = db()->query("
            SELECT lw.*, c.name as case_name
            FROM live_wins lw
            LEFT JOIN cases c ON lw.case_id = c.id
            ORDER BY lw.created_at DESC
            LIMIT 5
        ");
        $wins = $stmt->fetchAll();
        
        foreach ($wins as &$w) {
            $w['rarity_color'] = RAIRITY_COLORS[$w['rarity']] ?? '#888';
            $w['time_ago'] = timeAgo(strtotime($w['created_at']));
        }
        unset($w);
        
        jsonResponse(['success' => true, 'wins' => $wins]);
    } catch (PDOException $e) {
        error_log("Recent wins error: " . $e->getMessage());
        jsonResponse(['success' => true, 'wins' => []]);
    }
}

// Поддержка: создать тикет
if ($action === 'ticket_create') {
    requireAuth();
    $user = getCurrentUser();
    
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($subject) || empty($message)) {
        jsonResponse(['success' => false, 'error' => 'Заполните все поля']);
    }
    
    if (!in_array($priority, ['low', 'medium', 'high'])) {
        $priority = 'medium';
    }
    
    try {
        $stmt = db()->prepare("
            INSERT INTO support_tickets (user_id, subject, message, priority)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user['id'], $subject, $message, $priority]);
        
        $ticketId = db()->lastInsertId();
        
        jsonResponse(['success' => true, 'ticket_id' => $ticketId]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Ошибка создания тикета: ' . $e->getMessage()]);
    }
}

// Поддержка: получить тикеты пользователя
if ($action === 'tickets_list') {
    requireAuth();
    $user = getCurrentUser();
    
    $stmt = db()->prepare("
        SELECT st.*, 
               (SELECT COUNT(*) FROM support_messages WHERE ticket_id = st.id) as message_count
        FROM support_tickets st
        WHERE st.user_id = ?
        ORDER BY st.updated_at DESC
    ");
    $stmt->execute([$user['id']]);
    $tickets = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'tickets' => $tickets]);
}

// Поддержка: получить сообщения тикета
if ($action === 'ticket_messages') {
    requireAuth();
    $user = getCurrentUser();
    
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    
    $stmt = db()->prepare("
        SELECT sm.*, u.username
        FROM support_messages sm
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE sm.ticket_id = ? AND sm.user_id = ?
        ORDER BY sm.created_at ASC
    ");
    $stmt->execute([$ticketId, $user['id']]);
    $messages = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'messages' => $messages]);
}

// Поддержка: отправить сообщение в тикет
if ($action === 'ticket_message') {
    requireAuth();
    $user = getCurrentUser();
    
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if (empty($message)) {
        jsonResponse(['success' => false, 'error' => 'Введите сообщение']);
    }
    
    try {
        $stmt = db()->prepare("
            INSERT INTO support_messages (ticket_id, user_id, message)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$ticketId, $user['id'], $message]);
        
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Ошибка отправки: ' . $e->getMessage()]);
    }
}

// Конвертер валют: получить текущий курс
if ($action === 'currency_info') {
    $rate = getUsdRubRate();
    $stmt = db()->query("SELECT updated_at FROM settings WHERE `key` = 'usd_rub_rate'");
    $row = $stmt->fetch();
    $date = $row ? date('d.m.Y H:i', strtotime($row['updated_at'])) : date('d.m.Y');
    
    jsonResponse([
        'success' => true,
        'rate' => $rate,
        'formatted' => number_format($rate, 2, '.', '.') . ' ₽',
        'date' => $date
    ]);
}

// Вспомогательная функция для времени
function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'только что';
    if ($diff < 3600) return floor($diff / 60) . ' мин. назад';
    if ($diff < 86400) return floor($diff / 3600) . ' ч. назад';
    return floor($diff / 86400) . ' д. назад';
}
