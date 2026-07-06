<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test Steam Community Market API
$testNames = [
    'AK-47 | Redline',
    'AWP | Dragon Lore',
    '★ Karambit | Fade',
    'M4A4 | Howl',
    'Desert Eagle | Blaze'
];

foreach ($testNames as $name) {
    echo "Тест: $name\n";
    
    $marketUrl = 'https://steamcommunity.com/market/listings/730/' . urlencode($name);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $marketUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "  HTTP: $httpCode\n";
    
    if ($response && $httpCode === 200) {
        if (preg_match('/"icon_url":"([^"]+)"/', $response, $matches)) {
            echo "  ✅ Icon URL: " . substr($matches[1], 0, 50) . "...\n";
        } else {
            echo "  ❌ Не найден icon_url\n";
        }
    } else {
        echo "  ❌ Ошибка: " . ($response ? 'HTTP ' . $httpCode : 'Нет ответа') . "\n";
    }
    
    echo "\n";
}
?>
