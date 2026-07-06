<?php
/**
 * Free Cases API
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';

// Проверка: раздел отключён?
if (getSetting('free_case_enabled', '1') !== '1') {
    jsonResponse(['success' => false, 'error' => 'Раздел временно недоступен']);
}

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Необходимо авторизоваться'], 401);
}

$user = getCurrentUser();
$action = $_POST['action'] ?? '';

if ($action === 'open') {
    $caseId = (int)($_POST['case_id'] ?? 0);
    if (!$caseId) {
        jsonResponse(['success' => false, 'error' => 'Неверный ID кейса']);
    }

    $result = openFreeCase($caseId, $user['id']);
    jsonResponse($result);
}

if ($action === 'list') {
    $stmt = db()->query("SELECT * FROM free_cases WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC");
    $cases = $stmt->fetchAll();
    
    $casesWithAccess = array_map(function($case) use ($user) {
        $canOpen = canOpenFreeCase($user['id'], (float)$case['min_deposit']);
        $userDeposit = getUserDepositLast24h($user['id']);
        return [
            'id' => (int)$case['id'],
            'name' => $case['name'],
            'description' => $case['description'],
            'image_path' => $case['image_path'],
            'min_deposit' => (float)$case['min_deposit'],
            'can_open' => $canOpen,
            'user_deposit' => $userDeposit,
            'progress_percent' => $case['min_deposit'] > 0 ? min(100, ($userDeposit / $case['min_deposit']) * 100) : 0
        ];
    }, $cases);
    
    jsonResponse(['success' => true, 'cases' => $casesWithAccess]);
}

jsonResponse(['success' => false, 'error' => 'Неверное действие']);
