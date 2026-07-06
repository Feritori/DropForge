<?php
/**
 * DropForge — CS2 Full Items Sync v3.0
 * Оптимизировано: batch insert, увеличен таймаут
 */

$isApiMode = isset($_SERVER['HTTP_X_API_MODE']) && $_SERVER['HTTP_X_API_MODE'] === 'true';

// Увеличиваем лимиты
ini_set('max_execution_time', '600');
set_time_limit(600);
ignore_user_abort(true); // продолжаем даже при разрыве соединения

if ($isApiMode) {
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
    if (!$isApiMode) {
        echo "✅ Подключено к БД: " . DB_NAME . "\n\n";
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

function logMsg($msg) {
    global $isApiMode;
    if ($isApiMode) {
        static $logs = [];
        $logs[] = $msg;
        $GLOBALS['_sync_logs'] = $logs;
    } else {
        echo $msg . "\n";
        flush();
        ob_flush();
    }
}

// ===== Создание таблицы =====
function ensureTable(PDO $db): void {
    logMsg("🔧 Проверка таблицы steam_items...");
    $db->exec("
        CREATE TABLE IF NOT EXISTS steam_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            market_hash_name VARCHAR(255) NOT NULL,
            type VARCHAR(100),
            category_weapon VARCHAR(50),
            category_item_type VARCHAR(50),
            rarity VARCHAR(50),
            quality VARCHAR(50),
            icon_url TEXT,
            icon_url_large TEXT,
            classroom VARCHAR(100),
            is_graduated TINYINT(1) DEFAULT 0,
            price_usd DECIMAL(10,2) DEFAULT 0.03,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_name (market_hash_name),
            INDEX idx_rarity (rarity),
            INDEX idx_graduated (is_graduated),
            INDEX idx_weapon (category_weapon)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    logMsg("✅ Таблица готова");
}

// ===== Batch insert =====
function batchInsert(PDO $db, array $rows, int $batchSize = 500): int {
    if (empty($rows)) return 0;
    
    $total = 0;
    $columns = ['market_hash_name','type','category_weapon','category_item_type','rarity','quality','icon_url','icon_url_large','classroom','is_graduated','price_usd'];
    
    for ($i = 0; $i < count($rows); $i += $batchSize) {
        $batch = array_slice($rows, $i, $batchSize);
        $values = [];
        $params = [];
        
        foreach ($batch as $row) {
            $values[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            $params = array_merge($params, array_values($row));
        }
        
        $sql = "INSERT INTO steam_items (" . implode(',', $columns) . ") VALUES " . implode(',', $values);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $total += count($batch);
    }
    
    return $total;
}

// ===== Основная синхронизация =====
function syncFromByMykelApi(PDO $db): array {
    logMsg("📦 ByMykel CS:GO API sync...");
    
    ensureTable($db);
    
    // Загружаем все API
    $apiUrls = [
        'skins'    => 'https://raw.githubusercontent.com/ByMykel/CSGO-API/main/public/api/en/skins.json',
        'stickers' => 'https://raw.githubusercontent.com/ByMykel/CSGO-API/main/public/api/en/stickers.json',
        'graffitis'=> 'https://raw.githubusercontent.com/ByMykel/CSGO-API/main/public/api/en/graffiti.json',
        'agents'   => 'https://raw.githubusercontent.com/ByMykel/CSGO-API/main/public/api/en/agents.json',
        'charms'   => 'https://raw.githubusercontent.com/ByMykel/CSGO-API/main/public/api/en/keychains.json',
    ];
    
    $allData = [];
    foreach ($apiUrls as $key => $url) {
        logMsg("🌐 Загрузка: $key...");
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'DropForge',
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code === 200 && $resp) {
            $data = json_decode($resp, true);
            $allData[$key] = is_array($data) ? $data : [];
            logMsg("✅ $key: " . count($allData[$key]) . " предметов");
        } else {
            logMsg("⚠️ $key: ошибка HTTP $code");
            $allData[$key] = [];
        }
    }
    
    $totalSkins = count($allData['skins']);
    if ($totalSkins === 0) {
        logMsg("⚠️ Нет скинов. Fallback...");
        return syncFromLocalDatabase($db);
    }
    
    logMsg("🗑️ Очистка таблицы...");
    $db->exec("TRUNCATE TABLE steam_items");
    logMsg("✅ Очищено");
    
    // ===== Сбор batch данных =====
    $batch = [];
    $stats = ['skins'=>0,'knives'=>0,'gloves'=>0,'stickers'=>0,'graffitis'=>0,'agents'=>0,'charms'=>0,'other'=>0,'with_images'=>0,'errors'=>0];
    
    $categorize = function($name) {
        if (preg_match('/★|Karambit|Butterfly|Bayonet/', $name)) return [1, 'knife', 'graduated'];
        if (stripos($name, 'Glove') !== false) return [1, 'gloves', 'graduated'];
        if (stripos($name, 'Sticker') !== false) return [0, 'sticker', 'decal'];
        if (stripos($name, 'Graffiti') !== false) return [0, 'graffiti', 'decal'];
        if (stripos($name, 'Charm') !== false || stripos($name, 'Keychain') !== false) return [0, 'charm', 'csgo_item'];
        if (stripos($name, 'Agent') !== false) return [0, 'agent', 'character'];
        return [0, 'weapon', 'skin'];
    };
    
    $rarity = function($item) {
        return $item['rarity']['name'] ?? $item['rarity'] ?? 'normal';
    };
    
    $price = function($r, $g) {
        if (preg_match('/Covert|Extraordinary|Contraband/', $r)) return rand(50,5000)/100;
        if (preg_match('/Classified|Relic/', $r)) return rand(10,500)/100;
        if (preg_match('/Restricted/', $r)) return rand(2,50)/100;
        if ($g) return rand(50,1000)/100;
        return rand(1,10)/100;
    };
    
    // СКИНЫ
    logMsg("⚙️  Обработка скинов...");
    foreach ($allData['skins'] as $skin) {
        try {
            $weaponName = $skin['weapon']['name'] ?? '';
            $patternName = $skin['pattern']['name'] ?? '';
            $wearName = $skin['wear']['name'] ?? '';
            
            if (!empty($weaponName) && !empty($patternName)) {
                $marketName = "$weaponName | $patternName";
                if (!empty($wearName)) $marketName .= " ($wearName)";
            } else {
                $marketName = $skin['name'] ?? "CS2 Item";
            }
            
            [$grad, $catW, $catI] = $categorize($marketName);
            $r = $rarity($skin);
            $p = $price($r, $grad);
            $icon = $skin['image'] ?? '';
            
            $batch[] = [
                $marketName, 'Skin', $catW, $catI, $r,
                $skin['paint_index'] ?? '', $icon, $icon, $weaponName,
                $grad, $p
            ];
            
            if ($catW === 'knife') $stats['knives']++;
            elseif ($catW === 'gloves') $stats['gloves']++;
            elseif ($catW === 'weapon') $stats['skins']++;
            else $stats['other']++;
            
            if (!empty($icon)) $stats['with_images']++;
            
            if (count($batch) >= 500) {
                batchInsert($db, $batch);
                $processed = array_sum($stats);
                logMsg("📈 Скины: $processed / $totalSkins");
                $batch = [];
            }
        } catch (Exception $e) {
            $stats['errors']++;
        }
    }
    
    // СТИКЕРЫ
    logMsg("⚙️  Обработка стикеров...");
    foreach ($allData['stickers'] as $item) {
        if (!empty($item['name'])) {
            $batch[] = [$item['name'], 'Sticker', 'sticker', 'decal', $rarity($item), '', $item['image']??'', $item['image']??'', '', 0, rand(1,100)/100];
            $stats['stickers']++;
        }
        if (count($batch) >= 500) {
            batchInsert($db, $batch);
            $batch = [];
        }
    }
    
    // ГРАФФИТИ
    logMsg("⚙️  Обработка граффити...");
    foreach ($allData['graffitis'] as $item) {
        if (!empty($item['name'])) {
            $batch[] = [$item['name'], 'Graffiti', 'graffiti', 'decal', $rarity($item), '', $item['image']??'', $item['image']??'', '', 0, rand(1,50)/100];
            $stats['graffitis']++;
        }
        if (count($batch) >= 500) {
            batchInsert($db, $batch);
            $batch = [];
        }
    }
    
    // АГЕНТЫ
    logMsg("⚙️  Обработка агентов...");
    foreach ($allData['agents'] as $item) {
        if (!empty($item['name'])) {
            $batch[] = [$item['name'], 'Agent', 'agent', 'character', $rarity($item), '', $item['image']??'', $item['image']??'', '', 0, rand(5,200)/100];
            $stats['agents']++;
        }
        if (count($batch) >= 500) {
            batchInsert($db, $batch);
            $batch = [];
        }
    }
    
    // ШАРМЫ
    logMsg("⚙️  Обработка шармов...");
    foreach ($allData['charms'] as $item) {
        if (!empty($item['name'])) {
            $batch[] = [$item['name'], 'Charm', 'charm', 'csgo_item', $rarity($item), '', $item['image']??'', $item['image']??'', '', 0, rand(1,50)/100];
            $stats['charms']++;
        }
        if (count($batch) >= 500) {
            batchInsert($db, $batch);
            $batch = [];
        }
    }
    
    // Финальный батч
    if (!empty($batch)) {
        batchInsert($db, $batch);
    }
    
    $total = array_sum($stats);
    
    logMsg("\n" . str_repeat('=', 50));
    logMsg("✅ Синхронизация завершена!");
    logMsg("📊 Всего в БД: $total предметов");
    logMsg("   Скины: {$stats['skins']} | Ножи: {$stats['knives']} | Перчатки: {$stats['gloves']}");
    logMsg("   Стикеры: {$stats['stickers']} | Граффити: {$stats['graffitis']}");
    logMsg("   Агенты: {$stats['agents']} | Шармы: {$stats['charms']}");
    logMsg("   Ошибки: {$stats['errors']}");
    logMsg(str_repeat('=', 50));
    
    return ['success' => true, 'processed' => $total, 'stats' => $stats];
}

// ===== Fallback =====
function syncFromLocalDatabase(PDO $db): array {
    logMsg("📦 Fallback: локальная база...");
    
    ensureTable($db);
    $db->exec("TRUNCATE TABLE steam_items");
    
    $items = [
        ['AK-47 | Slate','weapon','Classified','covert'],
        ['AK-47 | Fire Serpent','weapon','Covert','covert'],
        ['AK-47 | Asiimov','weapon','Covert','covert'],
        ['M4A4 | Howl','weapon','Contraband','covert'],
        ['AWP | Dragon Lore','weapon','Covert','covert'],
        ['AWP | Asiimov','weapon','Covert','covert'],
        ['Desert Eagle | Blaze','weapon','Restricted','restricted'],
        ['★ Karambit | Fade','knife','★ Knife','covert'],
        ['★ Karambit | Doppler','knife','★ Knife','covert'],
        ['★ Butterfly Knife | Fade','knife','★ Knife','covert'],
        ['★ M9 Bayonet | Fade','knife','★ Knife','covert'],
        ['★ Sport Gloves | Vice','gloves','★ Gloves','covert'],
        ['★ Driver Gloves | King Snake','gloves','★ Gloves','covert'],
    ];
    
    $icon = "https://community.cloudflare.steamstatic.com/economy/image/-9a81dlWLwJ2UUGcVs_nsVtzdOEdtWwKGZZLQHTxDZ7I56KU0Zwwo4NUX4oFJZEHLbXH5ApeO4YmlhxYQknCRvN0_cWVpZ5XJQ5N6v73eDhQ09THcXFO79n3m4O0l_7wDLXUn2dD7cR03-zH8Yui3gDn_0VqYzv2d46fLFU4YwuD8gS9w-u5g5C7vZmcn3ly6yY8pSGK3w/360fx360f";
    
    $batch = [];
    foreach ($items as $s) {
        $grad = ($s[0] === 'knife' || $s[0] === 'gloves') ? 1 : 0;
        $price = $grad ? rand(5000,50000)/100 : rand(100,5000)/100;
        $batch[] = [$s[0], $s[1], $s[0], $grad ? 'graduated' : 'skin', $s[2], '', $icon, $icon, '', $grad, $price];
    }
    
    batchInsert($db, $batch);
    
    logMsg("✅ Fallback: " . count($items) . " предметов");
    return ['success' => true, 'processed' => count($items)];
}

// ===== MAIN =====
function main() {
    global $db, $isApiMode;
    
    if (!$isApiMode) {
        echo "\n╔═══════════════════════════════════════════════════╗\n";
        echo "║   DropForge — CS2 Items Sync v3.0 (Optimized)    ║\n";
        echo "╚═══════════════════════════════════════════════════╝\n\n";
        echo "⚡ Batch insert • max_time: 600s\n\n";
    }
    
    $result = syncFromByMykelApi($db);
    
    if ($result['success']) {
        if (!$isApiMode) echo "\n🎉 Готово!\n";
        
        try {
            $count = $db->query("SELECT COUNT(*) as c FROM steam_items")->fetch()['c'];
            if (!$isApiMode) echo "📦 В БД: $count предметов\n";
            $result['count'] = $count;
        } catch (Exception $e) {}
        
        if ($isApiMode) {
            echo json_encode(['success'=>true,'message'=>'Done','output'=>implode("\n",$GLOBALS['_sync_logs']??[]),'count'=>$result['count']??0]);
        }
    } else {
        if ($isApiMode) {
            echo json_encode(['success'=>false,'error'=>$result['error']??'Unknown','output'=>$GLOBALS['_sync_logs']??[]]);
        } else {
            echo "\n❌ Ошибка: " . ($result['error'] ?? 'Unknown') . "\n";
        }
        exit(1);
    }
}

main();
