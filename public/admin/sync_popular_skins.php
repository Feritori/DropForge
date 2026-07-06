<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Получаем список предметов из Steam Community
$items = [];

// Список популярных скинов CS2
$popularSkins = [
    // AK-47
    ['AK-47 | Slate', 'Classified', 'covert', 'consumer'],
    ['AK-47 | Redline', 'Classified', 'covert', 'consumer'],
    ['AK-47 | Vulcan', 'Covert', 'covert', 'consumer'],
    ['AK-47 | Fire Serpent', 'Covert', 'covert', 'contraband'],
    ['AK-47 | Neon Rider', 'Covert', 'covert', 'consumer'],
    ['AK-47 | Phantom Disruptor', 'Covert', 'covert', 'consumer'],
    ['AK-47 | Gold Arabesque', 'Extraordinary', 'contraband', 'consumer'],
    ['AK-47 | Bloodsport', 'Covert', 'covert', 'consumer'],
    ['AK-47 | The Empress', 'Covert', 'covert', 'consumer'],
    ['AK-47 | Ice Coaled', 'Covert', 'covert', 'consumer'],
    ['AK-47 | Inheritance', 'Covert', 'covert', 'consumer'],
    ['AK-47 | Wild Lotus', 'Covert', 'covert', 'contraband'],
    ['AK-47 | Tiger Tooth', 'Factory New', 'covert', 'consumer'],
    ['AK-47 | Safari Mesh', 'Field-Tested', 'consumer', 'consumer'],
    
    // M4A4
    ['M4A4 | Howl', 'Contraband', 'covert', 'contraband'],
    ['M4A4 | Asiimov', 'Covert', 'covert', 'consumer'],
    ['M4A4 | Buzz Kill', 'Covert', 'covert', 'consumer'],
    ['M4A4 | Neo-Noir', 'Covert', 'covert', 'consumer'],
    ['M4A4 | Zeus', 'Covert', 'covert', 'consumer'],
    ['M4A4 | Desolate Space', 'Covert', 'covert', 'consumer'],
    ['M4A4 | Ice Coaled', 'Covert', 'covert', 'consumer'],
    ['M4A4 | Wild Lotus', 'Covert', 'covert', 'contraband'],
    ['M4A4 | The Emperor', 'Covert', 'covert', 'consumer'],
    
    // AWP
    ['AWP | Dragon Lore', 'Covert', 'covert', 'contraband'],
    ['AWP | Asiimov', 'Covert', 'covert', 'consumer'],
    ['AWP | Hyper Beast', 'Covert', 'covert', 'consumer'],
    ['AWP | Neo-Noir', 'Covert', 'covert', 'consumer'],
    ['AWP | Fade', 'Covert', 'covert', 'consumer'],
    ['AWP | Containment Breach', 'Covert', 'covert', 'consumer'],
    ['AWP | Lightning Strike', 'Covert', 'covert', 'consumer'],
    ['AWP | Wildfire', 'Covert', 'covert', 'consumer'],
    ['AWP | Graphite', 'Classified', 'classified', 'consumer'],
    ['AWP | Corticera', 'Restricted', 'restricted', 'consumer'],
    ['AWP | Worm God', 'Covert', 'covert', 'consumer'],
    ['AWP | Chanticra Fire', 'Covert', 'covert', 'consumer'],
    
    // Другие популярные
    ['Desert Eagle | Blaze', 'Restricted', 'restricted', 'consumer'],
    ['Desert Eagle | Kumicho Dragon', 'Classified', 'classified', 'consumer'],
    ['Desert Eagle | Printstream', 'Covert', 'covert', 'consumer'],
    ['USP-S | Kill Confirmed', 'Covert', 'covert', 'consumer'],
    ['USP-S | Neo-Noir', 'Classified', 'classified', 'consumer'],
    ['USP-S | Cortex', 'Classified', 'classified', 'consumer'],
    ['Glock-18 | Fade', 'Classified', 'classified', 'consumer'],
    ['Glock-18 | Water Elemental', 'Classified', 'classified', 'consumer'],
    ['P250 | See Ya Later', 'Classified', 'classified', 'consumer'],
    ['Five-SeveN | Hyper Beast', 'Restricted', 'restricted', 'consumer'],
    
    // Ножи
    ['★ Karambit | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Karambit | Doppler', '★ Knife', 'covert', 'contraband'],
    ['★ Karambit | Tiger Tooth', '★ Knife', 'covert', 'contraband'],
    ['★ Karambit | Marbled', '★ Knife', 'covert', 'contraband'],
    ['★ Butterfly Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Butterfly Knife | Doppler', '★ Knife', 'covert', 'contraband'],
    ['★ M9 Bayonet | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ M9 Bayonet | Doppler', '★ Knife', 'covert', 'contraband'],
    ['★ Bayonet | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Bayonet | Marble Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Flip Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Flip Knife | Doppler', '★ Knife', 'covert', 'contraband'],
    ['★ Gut Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Gut Knife | Marble Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Huntsman Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Huntsman Knife | Doppler', '★ Knife', 'covert', 'contraband'],
    ['★ Falchion Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Shadow Daggers | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Bowie Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Skeleton Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Stiletto Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Ursus Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Navaja Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Talon Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Classic Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Kukri Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Paracord Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Survival Knife | Fade', '★ Knife', 'covert', 'contraband'],
    ['★ Nomad Knife | Fade', '★ Knife', 'covert', 'contraband'],
    
    // Перчатки
    ['★ Sport Gloves | Pandor\'s Box', '★ Gloves', 'covert', 'contraband'],
    ['★ Sport Gloves | Cobra', '★ Gloves', 'covert', 'contraband'],
    ['★ Sport Gloves | Hedgehog', '★ Gloves', 'covert', 'contraband'],
    ['★ Driver Gloves | Charm', '★ Gloves', 'covert', 'contraband'],
    ['★ Driver Gloves | King', '★ Gloves', 'covert', 'contraband'],
    ['★ Moto Gloves | Spearmint', '★ Gloves', 'covert', 'contraband'],
    ['★ Moto Gloves | DeltaCamo', '★ Gloves', 'covert', 'contraband'],
    ['★ Special Forces Gloves | Urban Drop', '★ Gloves', 'covert', 'contraband'],
    ['★ Hand Wraps | Cobalt Skulls', '★ Gloves', 'covert', 'contraband'],
    ['★ Leather Handwraps | Brass', '★ Gloves', 'covert', 'contraband'],
];

echo "✅ Загружено " . count($popularSkins) . " популярных скинов\n";

foreach ($popularSkins as $skin) {
    $name = $skin[0];
    $type = $skin[1];
    $rarity = $skin[2];
    $grade = $skin[3];
    
    // Формируем URL для иконки
    $iconUrl = "https://community.cloudflare.steamstatic.com/economy/image/-9a81dlWLwJ2UUGcVs_nsVtzdOEdtWwKGZZLQHTxDZ7I56KU0Zwwo4NUX4oFJZEHLbXH5ApeO4YmlhxYQknCRvN0_cWVpZ5XJQ5N6v73eDhQ09THcXFO79n3m4O0l_7wDLXUn2dD7cR03-zH8Yui3gDn_0VqYzv2d46fLFU4YwuD8gS9w-u5g5C7vZmcn3ly6yY8pSGK3w/360fx360f";
    
    $items[] = [
        'name' => $name,
        'type' => $type,
        'rarity' => $rarity,
        'icon' => $iconUrl,
        'price_usd' => rand(10, 5000) / 10,
        'is_graduated' => stripos($name, '★') !== false ? 1 : 0
    ];
}

// Сохраняем в БД
require_once __DIR__ . '/../../includes/database.php';
try {
    $db = Database::getConnection();
    $db->exec("TRUNCATE TABLE steam_items");
    
    $stmt = $db->prepare("INSERT INTO steam_items (market_hash_name, type, rarity, icon_url, price_usd, is_graduated) VALUES (?, ?, ?, ?, ?, ?)");
    
    $added = 0;
    foreach ($items as $item) {
        $stmt->execute([
            $item['name'],
            $item['type'],
            $item['rarity'],
            $item['icon'],
            $item['price_usd'],
            $item['is_graduated']
        ]);
        $added++;
    }
    
    echo "✅ Добавлено $added предметов в базу!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>
