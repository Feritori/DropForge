<?php
// Простой API для получения доступных методов оплаты
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';

try {
    $gateways = getPaymentGateways();
    $methods = [];

    if (isset($gateways['freekassa']) && $gateways['freekassa']['enabled'] && $gateways['freekassa']['configured']) {
        $methods[] = ['key' => 'freekassa', 'name' => 'FreeKassa', 'icon' => '/assets/images/freekassa-logo.png'];
    }
    
    if (isset($gateways['yoomoney']) && $gateways['yoomoney']['enabled'] && $gateways['yoomoney']['configured']) {
        $methods[] = ['key' => 'yoomoney', 'name' => 'YooMoney', 'icon' => '/assets/images/yoomoney.png'];
    }
    
    if (isset($gateways['enot']) && $gateways['enot']['enabled'] && $gateways['enot']['configured']) {
        $methods[] = ['key' => 'enot', 'name' => 'enot.io', 'icon' => '/assets/images/enot-logo.svg'];
    }

    echo json_encode(['success' => true, 'methods' => $methods]);
    
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'methods' => []]);
}
