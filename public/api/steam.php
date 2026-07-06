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

requireAdmin();

$action = $data['action'] ?? '';

try {
    $db = db();
    
    switch ($action) {
        case 'get_steam_api_key':
            $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'steam_api_key'");
            $stmt->execute();
            $key = $stmt->fetchColumn() ?? '';
            jsonResponse(['success' => true, 'key' => $key]);
            break;
            
        case 'save_steam_api_key':
            $key = trim($data['key'] ?? '');
            $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES ('steam_api_key', ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$key, $key]);
            jsonResponse(['success' => true]);
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
    }
    
} catch (Exception $e) {
    error_log('Steam API Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
