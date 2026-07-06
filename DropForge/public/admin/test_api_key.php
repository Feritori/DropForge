<?php
/**
 * Тест Steam API ключа
 */

$apiKey = 'F4079FAFACBF691AA299B49429430713';
$appId = 730; // CS2

echo "🧪 Тест Steam API ключа...\n";
echo "Ключ: " . substr($apiKey, 0, 8) . "...\n";
echo "AppID: $appId\n\n";

// Тест 1: GetItemsList
echo "📦 Тест 1: GetItemsList...\n";
$url = "https://api.steampowered.com/IEconItems_$appId/GetItemsList/v1/?key=$apiKey&language=english";
echo "URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "Error: $error\n";
}

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    if (isset($data['result']) && is_array($data['result'])) {
        echo "✅ GetItemsList работает!\n";
        echo "Предметов: " . count($data['result']) . "\n";
        echo "\nПервые 3 предмета:\n";
        $count = 0;
        foreach ($data['result'] as $item) {
            if ($count >= 3) break;
            echo "  - " . ($item['name'] ?? 'Unknown') . " (" . ($item['type'] ?? 'Unknown') . ")\n";
            $count++;
        }
    } else {
        echo "❌ Неверный формат ответа\n";
        echo "Ответ: " . substr($response, 0, 200) . "\n";
    }
} else {
    echo "❌ GetItemsList не работает\n";
    echo "Ответ: " . substr($response ?? '', 0, 500) . "\n";
}

echo "\n";

// Тест 2: Market Price History (альтернативный метод)
echo "📦 Тест 2: Market Listings...\n";
$testItem = urlencode("AK-47 | Slate");
$url2 = "https://steamcommunity.com/market/listings/730/$testItem";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0');

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP Code: $httpCode2\n";
if ($httpCode2 === 200) {
    echo "✅ Market Listings работает!\n";
    if (strpos($response2, 'icon_url') !== false || strpos($response2, 'market_hash_name') !== false) {
        echo "✅ Найдены данные о предметах\n";
    }
} else {
    echo "❌ Market Listings не работает\n";
}

echo "\n✅ Тестирование завершено\n";
