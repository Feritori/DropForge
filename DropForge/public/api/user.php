<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Invalid method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'] ?? 0;

if (!$userId) {
    jsonResponse(['success' => false, 'error' => 'Не авторизован'], 401);
}

$action = $data['action'] ?? '';

try {
    $db = db();
    
    switch ($action) {
        case 'create_ref_code':
            $code = trim($data['code'] ?? '');
            
            if (empty($code)) {
                jsonResponse(['success' => false, 'error' => 'Введите код'], 400);
            }
            
            if (!preg_match('/^[A-Z0-9]{3,10}$/', $code)) {
                jsonResponse(['success' => false, 'error' => 'Код должен содержать 3-10 символов (A-Z, 0-9)'], 400);
            }
            
            // Check if code already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE ref_code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Этот код уже занят'], 400);
            }
            
            // Check if user already has ref code
            $stmt = $db->prepare("SELECT ref_code FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && $user['ref_code']) {
                jsonResponse(['success' => false, 'error' => 'У вас уже есть реферальный код'], 400);
            }
            
            // Create ref code
            $stmt = $db->prepare("UPDATE users SET ref_code = ? WHERE id = ?");
            $stmt->execute([$code, $userId]);
            
            jsonResponse(['success' => true, 'code' => $code]);
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
    }
    
} catch (Exception $e) {
    error_log('User API Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
