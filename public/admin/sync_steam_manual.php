<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/database.php';

try {
    $db = Database::getConnection();
    
    // Получаем список предметов из Steam API
    $apiUrl = 'https://steamcommunity.com/market/priceoverview/?currency=1&appid=730&market_hash_name=';
    
    // Получаем список всех предметов CS2 через API
    $itemsUrl = 'https://api.steampowered.com/IGreatGameService_730_1/GetItemDefinitions/v1/?key=placeholder';
    
    // Используем CSGO Backpack v3 (новый endpoint)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.csgobackpack.net/v3/getItemsList/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Error: " . ($error ?: 'none') . "\n";
    echo "Response length: " . strlen($response) . "\n";
    
    if ($httpCode !== 200 || !$response) {
        echo "\n❌ Ошибка загрузки\n";
        exit(1);
    }
    
    $data = json_decode($response, true);
    if (!isset($data['items']) || !is_array($data['items'])) {
        echo "\n❌ Неверный формат данных\n";
        echo "Response: " . substr($response, 0, 500) . "\n";
        exit(1);
    }
    
    $total = count($data['items']);
    echo "\n✅ Найдено предметов: $total\n";
    echo "Очистка таблицы...\n";
    
    $db->exec("TRUNCATE TABLE steam_items");
    
    $stmt = $db->prepare("INSERT INTO steam_items (market_hash_name, type, rarity, icon_url, price_usd, is_graduated) VALUES (?, ?, ?, ?, ?, ?)");
    
    $added = 0;
    foreach ($data['items'] as $item) {
        $name = $item['name'] ?? '';
        $type = $item['type'] ?? '';
        $rarity = $item['rarity'] ?? '';
        $icon = $item['image'] ?? '';
        $price = $item['price_usd'] ?? 0;
        
        if (empty($name)) continue;
        
        // Определяем graduated
        $isGraduated = stripos($name, 'Knife') !== false || 
                       stripos($name, 'Gloves') !== false || 
                       stripos($name, 'Bayonet') !== false ||
                       stripos($name, 'Huntsman') !== false ||
                       stripos($name, 'Butterfly') !== false;
        
        $stmt->execute([
            $name,
            $type,
            $rarity,
            $icon,
            $price,
            $isGraduated ? 1 : 0
        ]);
        $added++;
        
        if ($added % 500 == 0) {
            echo "Добавлено: $added / $total\n";
        }
    }
    
    echo "\n✅ Готово! Добавлено: $added предметов\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>
