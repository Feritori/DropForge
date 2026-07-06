<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// Считываем JSON-тело
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Если JSON пустой или невалидный
if ($rawInput === '' || $rawInput === null || !is_array($data)) {
    jsonResponse(['success' => false, 'error' => 'Invalid JSON'], 400);
}

$caseId = (int)($data['case_id'] ?? 0);
$userId = $_SESSION['user_id'] ?? 0;
$qty = (int)($data['qty'] ?? 1);

if (!$userId) {
    jsonResponse(['success' => false, 'error' => 'Не авторизован'], 401);
}

if (!$caseId) {
    jsonResponse(['success' => false, 'error' => 'Не указан кейс'], 400);
}

if ($qty < 1 || $qty > 10) {
    $qty = 1;
}

try {
    $result = openMultipleCases($caseId, $userId, $qty);
    jsonResponse($result);
} catch (Exception $e) {
    error_log('Case API Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
