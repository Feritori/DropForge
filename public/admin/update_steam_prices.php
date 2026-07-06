 <?php
/**
 * DropForge — Обновление реальных цен Steam
 * Берёт актуальные рыночные цены из Steam Community Market
 * Поддержка batch-режима для веба
 */

$isApiMode = isset($_SERVER['HTTP_X_API_MODE']) && $_SERVER['HTTP_X_API_MODE'] === 'true';
$batchMode = isset($_GET['batch']) && isset($_GET['offset']);
$batchSize = (int)($_GET['batch'] ?? 100);
$offset = (int)($_GET['offset'] ?? 0);

if ($batchMode) {
    ini_set('max_execution_time', '120');
    set_time_limit(120);
} else {
    ini_set('max_execution_time', '900');
    set_time_limit(900);
}

if ($isApiMode || $batchMode) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    $db = Database::getConnection();
    if (!$isApiMode && !$batchMode) echo "✅ Подключено к БД\n\n";
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

function getSteamMarketPrice(string $marketHashName): ?float {
    $marketUrl = 'https://steamcommunity.com/market/priceoverview/?appid=730&currency=1&market_hash_name=' . urlencode($marketHashName);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $marketUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3, // Уменьшаем timeout
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest'
        ],
        CURLOPT_CONNECTTIMEOUT => 2 // Быстрый коннект
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['success']) || !$data['success']) {
        return null;
    }
    
    if (isset($data['lowest_price'])) {
        $priceStr = $data['lowest_price'];
        $priceStr = preg_replace('/[^\d.,]/', '', $priceStr);
        $priceStr = str_replace(',', '', $priceStr);
        
        $price = (float)$priceStr;
        if ($price > 0) {
            return $price;
        }
    }
    
    return null;
}

function logMsg($msg) {
    global $isApiMode, $batchMode;
    if ($isApiMode || $batchMode) {
        static $logs = [];
        $logs[] = $msg;
        $GLOBALS['_sync_logs'] = $logs;
    } else {
        echo $msg . "\n";
        if (ob_get_level() > 0) {
            ob_flush();
            flush();
        }
    }
}

// ===== Основная функция =====
function updatePrices(PDO $db, int $batchSize = 100, int $offset = 0): array {
    global $batchMode;
    
    logMsg("🔄 Обновление цен Steam Market...");
    
    // Получаем общее количество предметов
    $stmt = $db->query("SELECT COUNT(*) as c FROM steam_items");
    $total = (int)$stmt->fetch()['c'];
    
    logMsg("📦 Всего предметов: $total");
    logMsg("📦 Batch: $batchSize, Offset: $offset");
    
    // Получаем предметы батчем
    $stmt = $db->prepare("SELECT id, market_hash_name, price_usd, is_graduated FROM steam_items ORDER BY is_graduated DESC, rarity DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    if (empty($items)) {
        logMsg("✅ Обработка завершена");
        return ['success' => true, 'updated' => 0, 'no_price' => 0, 'finished' => true];
    }
    
    logMsg("📦 Обрабатываем: " . count($items) . " предметов");
    
    $updated = 0;
    $skipped = 0;
    $noPrice = 0;
    
    $stmt = $db->prepare("UPDATE steam_items SET price_usd = ? WHERE id = ?");
    
    foreach ($items as $i => $item) {
        $marketName = $item['market_hash_name'];
        
        // Пропускаем очень дешёвые предметы (стикеры без названия скина)
        if (stripos($marketName, 'sticker') !== false && stripos($marketName, '|') === false) {
            $skipped++;
            continue;
        }
        
        // Пропускаем ножи и перчатки - у них и так примерно верные цены
        if ($item['is_graduated']) {
            $skipped++;
            continue;
        }
        
        $price = getSteamMarketPrice($marketName);
        
        if ($price !== null && $price > 0) {
            $stmt->execute([$price, $item['id']]);
            $updated++;
        } else {
            $noPrice++;
        }
        
        // Прогресс каждые 100 предметов
        if (($i + 1) % 100 === 0) {
            logMsg("📈 Прогресс: " . ($i + 1) . "/" . count($items) . " | ✅ $updated | ⚠️ $noPrice | ⏭️ $skipped");
        }
        
        // Убрали usleep для скорости - Steam API быстрый
    }
    
    $hasMore = ($offset + $batchSize) < $total;
    
    logMsg("\n" . str_repeat('=', 50));
    logMsg("📊 Batch обновлён: $updated");
    logMsg("⚠️ Нет цены: $noPrice");
    logMsg("⏭️ Пропущено: $skipped");
    logMsg("📈 Продолжить: " . ($hasMore ? 'Да' : 'Нет'));
    logMsg(str_repeat('=', 50));
    
    return [
        'success' => true,
        'updated' => $updated,
        'no_price' => $noPrice,
        'skipped' => $skipped,
        'finished' => !$hasMore,
        'next_offset' => $hasMore ? $offset + $batchSize : null
    ];
}

// ===== MAIN =====
try {
    if ($batchMode) {
        // Batch-режим для веба
        $result = updatePrices($db, $batchSize, $offset);
        
        echo json_encode([
            'success' => $result['success'],
            'updated' => $result['updated'],
            'no_price' => $result['no_price'],
            'skipped' => $result['skipped'],
            'finished' => $result['finished'],
            'next_offset' => $result['next_offset'],
            'logs' => $GLOBALS['_sync_logs'] ?? []
        ]);
    } else {
        // Полное обновление через CLI
        $result = updatePrices($db, 5000, 0);
        
        if ($isApiMode) {
            echo json_encode([
                'success' => $result['success'],
                'updated' => $result['updated'],
                'no_price' => $result['no_price'],
                'output' => implode("\n", $GLOBALS['_sync_logs'] ?? [])
            ]);
        } else {
            echo "\n🎉 Готово!\n";
        }
    }
} catch (Exception $e) {
    if ($isApiMode || $batchMode) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        echo "\n❌ Ошибка: " . $e->getMessage() . "\n";
    }
    exit(1);
}
