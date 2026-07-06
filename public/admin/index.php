<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$section = $_GET['section'] ?? 'dashboard';

try {
    $stmt = db()->query("SELECT `key`, value FROM settings");
    $allSettings = [];
    while ($row = $stmt->fetch()) {
        $allSettings[$row['key']] = $row['value'];
    }
    
    $statsStmt = db()->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM cases) as total_cases,
            (SELECT SUM(balance) FROM users) as total_balance,
            (SELECT COUNT(*) FROM case_open_history) as total_opens,
            (SELECT SUM(amount) FROM transactions WHERE type = 'deposit') as total_deposits
    ");
    $statsStmt->execute();
    $stats = $statsStmt->fetch();
} catch (Exception $e) {
    error_log('Admin index error: ' . $e->getMessage());
    die('DB Error: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ — <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="/css/admin.css?v=<?= time() ?>">
</head>
<body>
    <header class="admin-header">
        <div class="admin-header__brand">
            <a href="/index.php" class="admin-header__logo">
                <span class="admin-header__logo-icon">⚡</span>
                <span class="admin-header__logo-text">DropForge <span style="font-weight:400;opacity:0.6;">Admin</span></span>
            </a>
        </div>
        <div class="admin-header__user">
            <span class="admin-header__username"><?= e($user['username']) ?></span>
            <a href="/api/auth?action=logout" class="admin-header__logout">Выйти</a>
        </div>
    </header>
        
    <div class="admin-layout">
        <nav class="admin-sidebar" id="adminSidebar">
            <?php
            $groups = [
                'Главная' => [['id' => 'dashboard', 'label' => 'Дашборд', 'icon' => '📊']],
                'Кейсы' => [
                    ['id' => 'cases', 'label' => 'Все кейсы', 'icon' => '📦'],
                    ['id' => 'case_items', 'label' => 'Предметы', 'icon' => '🎯'],
                    ['id' => 'categories', 'label' => 'Категории', 'icon' => '📁'],
                    ['id' => 'free_cases', 'label' => 'Бесплатные', 'icon' => '🎁']
                ],
                'Пользователи' => [['id' => 'users', 'label' => 'Пользователи', 'icon' => '👥']],
                'Журнал' => [
                    ['id' => 'transactions', 'label' => 'Транзакции', 'icon' => '💳'],
                    ['id' => 'history', 'label' => 'История', 'icon' => '📜'],
                    ['id' => 'referrals', 'label' => 'Рефералы', 'icon' => '🔗'],
                    ['id' => 'pending_payments', 'label' => 'Платежи', 'icon' => '⏳']
                ],
                'Настройки' => [
                    ['id' => 'settings', 'label' => 'Основные', 'icon' => '⚙️'],
                    ['id' => 'payment', 'label' => 'Платёжка', 'icon' => '💰']
                ],
                'Бонусы' => [
                    ['id' => 'daily_bonus', 'label' => 'Ежедневный', 'icon' => '📅'],
                    ['id' => 'battle_pass', 'label' => 'Battle Pass', 'icon' => '🏆']
                ]
            ];
            
            $catIndex = 0;
            foreach ($groups as $name => $items): 
                $catId = 'cat_' . $catIndex++;
                $itemIds = array_column($items, 'id');
                $isActive = in_array($section, $itemIds);
            ?>
                <div class="admin-sidebar__category <?= $isActive ? '' : 'collapsed' ?>" onclick="toggleCategory('<?= $catId ?>', event)">
                    <span class="admin-sidebar__category-title">
                        <?= e($name) ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                    <div id="<?= $catId ?>" class="admin-sidebar__links <?= $isActive ? 'visible' : 'hidden' ?>">
                        <?php foreach ($items as $item): ?>
                            <a href="?section=<?= e($item['id']) ?>" class="admin-sidebar__item <?= $section === $item['id'] ? 'active' : '' ?>">
                                <span class="admin-sidebar__item-icon"><?= $item['icon'] ?></span>
                                <?= e($item['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>

        <main class="admin-content">
<?php switch ($section):
    case 'dashboard': ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">Дашборд</h1>
                <p class="admin-page-header__subtitle">Обзор основных показателей вашего казино</p>
            </div>
            <div class="admin-page-header__actions">
                <button class="admin-btn admin-btn--secondary admin-btn--sm" onclick="window.location.reload()">
                    <span class="admin-btn__icon">🔄</span>
                    Обновить
                </button>
            </div>
        </div>
        
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon purple">👥</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Пользователей</div>
                    <div class="admin-stat-card__value"><?= number_format($stats['total_users']) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon green">📦</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Кейсов</div>
                    <div class="admin-stat-card__value"><?= number_format($stats['total_cases']) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon orange">💰</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Общий баланс</div>
                    <div class="admin-stat-card__value"><?= formatMoney($stats['total_balance'] ?? 0) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon blue">🎰</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Открытий</div>
                    <div class="admin-stat-card__value"><?= number_format($stats['total_opens']) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon pink">📈</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Депозиты</div>
                    <div class="admin-stat-card__value"><?= formatMoney($stats['total_deposits'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">🕐</span>
                    Последние открытия
                </div>
                <span class="admin-badge admin-badge--neutral">Всего: <?= number_format($stats['total_opens']) ?></span>
            </div>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Пользователь</th>
                            <th>Кейс</th>
                            <th>Предмет</th>
                            <th>Цена</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $historyStmt = db()->query("SELECT h.*, u.username FROM case_open_history h LEFT JOIN users u ON h.user_id = u.id ORDER BY h.created_at DESC LIMIT 20");
                        foreach ($historyStmt->fetchAll() as $r): 
                        ?>
                            <tr>
                                <td class="admin-table__cell-primary"><?= e($r['username'] ?? 'Unknown') ?></td>
                                <td class="admin-table__cell-muted">#<?= $r['case_id'] ?></td>
                                <td class="admin-table__cell-primary"><?= e($r['item_name']) ?></td>
                                <td class="admin-table__cell-success"><?= formatMoney($r['price']) ?></td>
                                <td class="admin-table__cell-muted"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php break;

    case 'cases':
        $cases = db()->query("SELECT c.*, cat.name as category_name, (SELECT COUNT(*) FROM case_items WHERE case_id = c.id) as items_count FROM cases c LEFT JOIN categories cat ON c.category_id = cat.id ORDER BY c.created_at DESC")->fetchAll();
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">Управление кейсами</h1>
                <p class="admin-page-header__subtitle">Создание и настройка кейсов для вашего казино</p>
            </div>
            <div class="admin-page-header__actions">
                <button class="admin-btn admin-btn--primary" onclick="openCaseModal()">
                    <span class="admin-btn__icon">➕</span>
                    Добавить кейс
                </button>
            </div>
        </div>

        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon purple">📦</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Всего кейсов</div>
                    <div class="admin-stat-card__value"><?= count($cases) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon green">✅</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Активных</div>
                    <div class="admin-stat-card__value"><?= count(array_filter($cases, fn($c) => $c['is_active'])) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon orange">📋</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Предметов</div>
                    <div class="admin-stat-card__value"><?= number_format(array_sum(array_column($cases, 'items_count'))) ?></div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Кейс</th>
                            <th>Цена</th>
                            <th>Категория</th>
                            <th>Предметов</th>
                            <th>Статус</th>
                            <th style="width:120px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cases as $c): ?>
                            <tr>
                                <td class="admin-table__cell-muted">#<?= $c['id'] ?></td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <?php if ($c['image_path']): ?>
                                            <img src="<?= e($c['image_path']) ?>" style="width:40px;height:30px;object-fit:contain;border-radius:4px;" alt="">
                                        <?php endif; ?>
                                        <span class="admin-table__cell-primary"><?= e($c['name']) ?></span>
                                    </div>
                                </td>
                                <td class="admin-table__cell-success"><?= formatMoney($c['price']) ?></td>
                                <td><?= e($c['category_name'] ?? '—') ?></td>
                                <td><span class="admin-badge admin-badge--info"><?= $c['items_count'] ?> шт</span></td>
                                <td>
                                    <?php if ($c['is_active']): ?>
                                        <span class="admin-badge admin-badge--success">Активен</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge--danger">Выкл</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <button class="admin-btn admin-btn--ghost admin-btn--sm" onclick="editCase(<?= $c['id'] ?>)" title="Редактировать">✏️</button>
                                        <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteCase(<?= $c['id'] ?>)" title="Удалить">🗑</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cases)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="admin-empty">
                                        <div class="admin-empty__icon">📦</div>
                                        <div class="admin-empty__title">Кейсов пока нет</div>
                                        <div class="admin-empty__text">Создайте первый кейс, чтобы начать</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php break;

    case 'case_items':
        $caseId = (int)($_GET['case_id'] ?? 0);
        $items = [];
        $selectedCase = null;
        if ($caseId) {
            $stmt = db()->prepare("SELECT * FROM cases WHERE id = ? LIMIT 1");
            $stmt->execute([$caseId]);
            $selectedCase = $stmt->fetch();
            
            $stmt = db()->prepare("SELECT * FROM case_items WHERE case_id = ? ORDER BY FIELD(rarity, 'consumer', 'industrial', 'milspec', 'restricted', 'classified', 'covert')");
            $stmt->execute([$caseId]);
            $items = $stmt->fetchAll();
        }
        $allCases = db()->query("SELECT id, name FROM cases ORDER BY name")->fetchAll();
        
        $rarityClasses = [
            'consumer' => 'consumer',
            'industrial' => 'industrial',
            'milspec' => 'milspec',
            'restricted' => 'restricted',
            'classified' => 'classified',
            'covert' => 'covert',
            'extraordinary' => 'extraordinary'
        ];
        $rarityLabels = [
            'consumer' => 'Ширпотреб',
            'industrial' => 'Промышленное',
            'milspec' => 'Армейское',
            'restricted' => 'Запрещённое',
            'classified' => 'Засекреченное',
            'covert' => 'Тайное',
            'extraordinary' => 'Внеобычное'
        ];
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">Управление предметами</h1>
                <p class="admin-page-header__subtitle">Наполнение кейсов предметами из Steam</p>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-card__body">
                <div class="admin-form-grid admin-form-grid--3" style="align-items:end;">
                    <div class="admin-form-group" style="margin:0;">
                        <label class="admin-form-label">Выберите кейс</label>
                        <select class="admin-form-control" onchange="if(this.value)location.href='?section=case_items&case_id='+this.value">
                            <option value="0">— Выберите кейс —</option>
                            <?php foreach ($allCases as $ac): ?>
                                <option value="<?= $ac['id'] ?>" <?= $ac['id'] == $caseId ? 'selected' : '' ?>><?= e($ac['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:flex;gap:0.5rem;">
                        <button class="admin-btn admin-btn--secondary" onclick="syncSteamItems()">
                            <span class="admin-btn__icon">🔄</span>
                            Синхронизировать
                        </button>
                        <button class="admin-btn admin-btn--secondary" onclick="updateSteamPrices()">
                            <span class="admin-btn__icon">💰</span>
                            Обновить цены
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($caseId && $selectedCase): ?>
        <div class="admin-card" style="border-left:3px solid var(--accent);">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">📦</span>
                    <?= e($selectedCase['name']) ?>
                </div>
                <button class="admin-btn admin-btn--primary admin-btn--sm" onclick="openSteamSearchModal(<?= $caseId ?>)">
                    <span class="admin-btn__icon">➕</span>
                    Добавить из Steam
                </button>
            </div>
            <div class="admin-card__body">
                <div class="flex gap-2" style="font-size:0.85rem;">
                    <span class="text-muted">Цена: <strong class="text-success"><?= formatMoney($selectedCase['price']) ?></strong></span>
                    <span class="text-muted">•</span>
                    <span class="text-muted">Предметов: <strong><?= count($items) ?></strong></span>
                </div>
            </div>
        </div>
        
        <?php if (empty($items)): ?>
        <div class="admin-card">
            <div class="admin-empty">
                <div class="admin-empty__icon">🎯</div>
                <div class="admin-empty__title">В кейсе пока нет предметов</div>
                <div class="admin-empty__text">Добавьте предметы из Steam или вручную</div>
                <button class="admin-btn admin-btn--primary" style="margin-top:1.5rem;" onclick="openSteamSearchModal(<?= $caseId ?>)">
                    <span class="admin-btn__icon">➕</span>
                    Добавить из Steam
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="admin-card">
            <div class="admin-card__header">
                <div class="admin-card__title">Предметы в кейсе</div>
                <span class="admin-badge admin-badge--neutral"><?= count($items) ?> шт</span>
            </div>
            <div class="admin-card__body">
                <div class="admin-case-items-grid">
                    <?php foreach ($items as $item): 
                        $rarity = strtolower($item['rarity'] ?? 'milspec');
                        $rarityClass = $rarityClasses[$rarity] ?? 'milspec';
                        $rarityLabel = $rarityLabels[$rarity] ?? $item['rarity'];
                        $imgUrl = getSteamItemImage($item['item_image'] ?? '', 'large');
                    ?>
                        <div class="admin-case-item-card">
                            <div class="admin-case-item-card__actions">
                                <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteCaseItem(<?= $item['id'] ?>, <?= $caseId ?>)" title="Удалить">🗑</button>
                            </div>
                            <img class="admin-case-item-card__image" src="<?= e($imgUrl) ?>" alt="<?= e($item['item_name']) ?>" 
                                onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><rect fill=%22%230d1117%22 width=%22100%22 height=%22100%22/><text x=%2250%22 y=%2255%22 text-anchor=%22middle%22 fill=%22%23484f58%22 font-size=%2212%22>Нет фото</text></svg>';">
                            <div class="admin-case-item-card__name"><?= e($item['item_name']) ?></div>
                            <div class="admin-case-item-card__rarity rarity-<?= $rarityClass ?>">● <?= e($rarityLabel) ?></div>
                            <div class="admin-case-item-card__price"><?= formatMoney($item['price']) ?></div>
                            <div class="admin-case-item-card__weight">Вес: <?= $item['weight'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php elseif (!$caseId): ?>
        <div class="admin-card">
            <div class="admin-empty">
                <div class="admin-empty__icon">📂</div>
                <div class="admin-empty__title">Кейс не выбран</div>
                <div class="admin-empty__text">Выберите кейс из списка выше</div>
            </div>
        </div>
        <?php endif; ?>
    <?php break;

    case 'categories':
        $cats = db()->query("SELECT c.*, COUNT(cases.id) as case_count FROM categories c LEFT JOIN cases ON c.id = cases.category_id GROUP BY c.id ORDER BY c.name")->fetchAll();
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">Категории</h1>
                <p class="admin-page-header__subtitle">Управление категориями кейсов</p>
            </div>
            <div class="admin-page-header__actions">
                <button class="admin-btn admin-btn--primary admin-btn--sm" onclick="addCategoryModal()">
                    <span class="admin-btn__icon">➕</span>
                    Добавить
                </button>
            </div>
        </div>
        
        <?php if (empty($cats)): ?>
        <div class="admin-card">
            <div class="admin-empty">
                <div class="admin-empty__icon">📁</div>
                <div class="admin-empty__title">Категорий пока нет</div>
                <div class="admin-empty__text">Создайте первую категорию для организации кейсов</div>
                <button class="admin-btn admin-btn--primary" style="margin-top:1.5rem;" onclick="addCategoryModal()">
                    <span class="admin-btn__icon">➕</span>
                    Добавить категорию
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon purple">📁</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Всего категорий</div>
                    <div class="admin-stat-card__value"><?= count($cats) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon green">📦</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Кейсов в категориях</div>
                    <div class="admin-stat-card__value"><?= number_format(array_sum(array_column($cats, 'case_count'))) ?></div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Название</th>
                            <th style="width:80px;">Иконка</th>
                            <th style="width:100px;">Цвет</th>
                            <th style="width:100px;">Кейсов</th>
                            <th style="width:80px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cats as $cat): ?>
                            <tr>
                                <td class="admin-table__cell-muted"><?= $cat['id'] ?></td>
                                <td class="admin-table__cell-primary"><strong><?= e($cat['name']) ?></strong></td>
                                <td style="font-size:1.5rem;text-align:center;"><?= e($cat['icon']) ?></td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <span style="display:inline-block;width:24px;height:24px;background:<?= e($cat['color']) ?>;border-radius:4px;border:1px solid var(--border);"></span>
                                        <code class="admin-table__cell-muted"><?= e($cat['color']) ?></code>
                                    </div>
                                </td>
                                <td><span class="admin-badge admin-badge--info"><?= $cat['case_count'] ?> шт</span></td>
                                <td>
                                    <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteCategory(<?= $cat['id'] ?>)" title="Удалить">🗑</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php break;

    case 'users':
        $page = (int)($_GET['page'] ?? 1);
        $search = trim($_GET['search'] ?? '');
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = '';
        $params = [];
        if ($search) {
            $where = 'WHERE u.username LIKE ? OR u.email LIKE ?';
            $params = ["%$search%", "%$search%"];
        }
        
        $stmt = db()->prepare("SELECT u.*, (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type = 'deposit') as total_deposit FROM users u $where ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $users = $stmt->fetchAll();
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">Пользователи</h1>
                <p class="admin-page-header__subtitle">Управление пользователями вашего казино</p>
            </div>
            <div class="admin-page-header__actions">
                <div class="admin-search-bar" style="margin:0;">
                    <input type="text" class="admin-form-control" id="userSearch" placeholder="Поиск..." value="<?= e($search) ?>" onkeyup="searchUsers()" style="max-width:250px;">
                    <button class="admin-btn admin-btn--primary admin-btn--sm" onclick="searchUsers()">🔍</button>
                </div>
            </div>
        </div>

        <?php if (empty($users)): ?>
        <div class="admin-card">
            <div class="admin-empty">
                <div class="admin-empty__icon">👥</div>
                <div class="admin-empty__title">Пользователи не найдены</div>
                <div class="admin-empty__text"><?= $search ? 'Попробуйте изменить запрос' : 'Регистраций пока нет' ?></div>
            </div>
        </div>
        <?php else: ?>
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon purple">👥</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Показано</div>
                    <div class="admin-stat-card__value"><?= count($users) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon green">💰</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Общий баланс</div>
                    <div class="admin-stat-card__value"><?= formatMoney(array_sum(array_column($users, 'balance'))) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon orange">📈</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Общие депозиты</div>
                    <div class="admin-stat-card__value"><?= formatMoney(array_sum(array_column($users, 'total_deposit'))) ?></div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Логин</th>
                            <th>Email</th>
                            <th style="width:120px;">Баланс</th>
                            <th style="width:120px;">Депозит</th>
                            <th style="width:100px;">Роль</th>
                            <th style="width:100px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody id="usersTable">
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="admin-table__cell-muted"><?= $u['id'] ?></td>
                                <td class="admin-table__cell-primary"><strong><?= e($u['username']) ?></strong></td>
                                <td class="admin-table__cell-muted"><?= e($u['email']) ?></td>
                                <td class="admin-table__cell-success"><?= formatMoney($u['balance']) ?></td>
                                <td class="admin-table__cell-muted"><?= formatMoney($u['total_deposit'] ?? 0) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="admin-badge admin-badge--info">👑 Admin</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge--success">👤 User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <button class="admin-btn admin-btn--ghost admin-btn--sm" onclick="editUser(<?= $u['id'] ?>)" title="Редактировать">✏️</button>
                                        <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteUser(<?= $u['id'] ?>)" title="Удалить">🗑</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php break;

    case 'transactions':
        $page = (int)($_GET['page'] ?? 1);
        $type = trim($_GET['type'] ?? '');
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $where = $type ? "WHERE t.type = ?" : '';
        $params = $type ? [$type] : [];
        $stmt = db()->prepare("SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON t.user_id = u.id $where ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">💰 Транзакции</h1>
                <p class="admin-page-header__subtitle">История всех операций</p>
            </div>
            <div class="admin-page-header__actions">
                <select class="admin-form-control" id="transType" onchange="loadTransactions()" style="max-width:180px;">
                    <option value="">Все типы</option>
                    <option value="deposit" <?= $type === 'deposit' ? 'selected' : '' ?>>Депозит</option>
                    <option value="case_open" <?= $type === 'case_open' ? 'selected' : '' ?>>Кейс</option>
                    <option value="referral_bonus" <?= $type === 'referral_bonus' ? 'selected' : '' ?>>Реферал</option>
                </select>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Пользователь</th>
                            <th>Тип</th>
                            <th>Сумма</th>
                            <th>Описание</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="admin-table__cell-muted"><?= $t['id'] ?></td>
                                <td class="admin-table__cell-primary"><?= e($t['username'] ?? 'Unknown') ?></td>
                                <td><span class="admin-badge admin-badge--info"><?= e($t['type']) ?></span></td>
                                <td class="<?= $t['amount'] >= 0 ? 'admin-table__cell-success' : 'text-danger' ?>"><?= formatMoney($t['amount']) ?></td>
                                <td class="admin-table__cell-muted"><?= e($t['description']) ?></td>
                                <td class="admin-table__cell-muted"><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php break;

    case 'history':
        $page = (int)($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $stmt = db()->prepare("SELECT h.*, u.username FROM case_open_history h LEFT JOIN users u ON h.user_id = u.id ORDER BY h.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute();
        $history = $stmt->fetchAll();
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">📜 История открытий</h1>
                <p class="admin-page-header__subtitle">Все открытия кейсов</p>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Пользователь</th>
                            <th>Кейс</th>
                            <th>Предмет</th>
                            <th>Цена</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td class="admin-table__cell-muted"><?= $h['id'] ?></td>
                                <td class="admin-table__cell-primary"><?= e($h['username'] ?? 'Unknown') ?></td>
                                <td class="admin-table__cell-muted">#<?= $h['case_id'] ?></td>
                                <td class="admin-table__cell-primary"><?= e($h['item_name']) ?></td>
                                <td class="admin-table__cell-success"><?= formatMoney($h['price']) ?></td>
                                <td class="admin-table__cell-muted"><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php break;

    case 'referrals':
        $stmt = db()->query("SELECT u.id, u.username, u.ref_code, (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as referral_count, (SELECT SUM(amount) FROM transactions t INNER JOIN users ru ON t.user_id = ru.id WHERE ru.referred_by = u.id AND t.type = 'deposit') as total_ref_deposit FROM users u WHERE u.ref_code IS NOT NULL AND u.ref_code != '' ORDER BY referral_count DESC LIMIT 50");
        $referrals = $stmt->fetchAll();
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">🔗 Рефералы</h1>
                <p class="admin-page-header__subtitle">Топ рефереров</p>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Логин</th>
                            <th>Код</th>
                            <th>Рефералов</th>
                            <th>Депозит</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrals as $r): ?>
                            <tr>
                                <td class="admin-table__cell-muted"><?= $r['id'] ?></td>
                                <td class="admin-table__cell-primary"><strong><?= e($r['username']) ?></strong></td>
                                <td><code class="admin-table__cell-muted"><?= e($r['ref_code']) ?></code></td>
                                <td><span class="admin-badge admin-badge--neutral"><?= $r['referral_count'] ?></span></td>
                                <td class="admin-table__cell-success"><?= formatMoney($r['total_ref_deposit'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php break;

    case 'free_cases':
        $freeCases = db()->query("SELECT * FROM free_cases ORDER BY sort_order")->fetchAll();
        $caseId = (int)($_GET['case_id'] ?? 0);
        $freeCaseItems = [];
        $selectedFreeCase = null;
        
        if ($caseId) {
            $stmt = db()->prepare("SELECT * FROM free_cases WHERE id = ? LIMIT 1");
            $stmt->execute([$caseId]);
            $selectedFreeCase = $stmt->fetch();
            
            $stmt = db()->prepare("SELECT * FROM free_case_items WHERE case_id = ? ORDER BY FIELD(rarity, 'consumer', 'industrial', 'milspec', 'restricted', 'classified', 'covert', 'extraordinary')");
            $stmt->execute([$caseId]);
            $freeCaseItems = $stmt->fetchAll();
        }
        
        // Редкости с цветами
        $rarityColors = [
            'consumer' => '#b0c3d9',
            'industrial' => '#5e98d9',
            'milspec' => '#4b69ff',
            'restricted' => '#8847ff',
            'classified' => '#d32ce6',
            'covert' => '#eb4b4b',
            'extraordinary' => '#e4ae39'
        ];
        $rarityLabels = [
            'consumer' => 'Ширпотреб',
            'industrial' => 'Промышленное',
            'milspec' => 'Армейское',
            'restricted' => 'Запрещённое',
            'classified' => 'Засекреченное',
            'covert' => 'Тайное',
            'extraordinary' => 'Внеобычное'
        ];
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">🎁 Бесплатные кейсы</h1>
                <p class="admin-page-header__subtitle">Управление бесплатными кейсами</p>
            </div>
            <div class="admin-page-header__actions">
                <button class="admin-btn admin-btn--primary admin-btn--sm" onclick="addFreeCaseModal()">
                    <span class="admin-btn__icon">➕</span>
                    Добавить кейс
                </button>
            </div>
        </div>
        
        <?php if (empty($freeCases)): ?>
        <div class="admin-card">
            <div class="admin-empty">
                <div class="admin-empty__icon">🎁</div>
                <div class="admin-empty__title">Бесплатных кейсов пока нет</div>
                <div class="admin-empty__text">Создайте первый бесплатный кейс</div>
                <button class="admin-btn admin-btn--primary" style="margin-top:1.5rem;" onclick="addFreeCaseModal()">
                    <span class="admin-btn__icon">➕</span>
                    Добавить кейс
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon purple">🎁</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Всего кейсов</div>
                    <div class="admin-stat-card__value"><?= count($freeCases) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon green">✅</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Активных</div>
                    <div class="admin-stat-card__value"><?= count(array_filter($freeCases, fn($c) => $c['is_active'])) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon orange">📦</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Предметов</div>
                    <div class="admin-stat-card__value"><?= count($freeCaseItems) ?></div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Название</th>
                            <th style="width:120px;">Мин. депозит</th>
                            <th style="width:100px;">Статус</th>
                            <th style="width:140px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($freeCases as $fc): ?>
                            <tr>
                                <td class="admin-table__cell-muted"><?= $fc['id'] ?></td>
                                <td>
                                    <a href="?section=free_cases&case_id=<?= $fc['id'] ?>" class="admin-table__cell-primary" style="text-decoration:none;"><?= e($fc['name']) ?></a>
                                </td>
                                <td class="admin-table__cell-success"><?= formatMoney($fc['min_deposit']) ?></td>
                                <td>
                                    <?php if ($fc['is_active']): ?>
                                        <span class="admin-badge admin-badge--success">Активен</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge--danger">Выкл</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <button class="admin-btn admin-btn--ghost admin-btn--sm" onclick="toggleFreeCase(<?= $fc['id'] ?>, <?= $fc['is_active'] ? '0' : '1' ?>)" title="<?= $fc['is_active'] ? 'Выключить' : 'Включить' ?>">
                                            <?= $fc['is_active'] ? '⏸️' : '▶️' ?>
                                        </button>
                                        <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteFreeCase(<?= $fc['id'] ?>)" title="Удалить">🗑</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($caseId && $selectedFreeCase): ?>
        <!-- Управление предметами кейса -->
        <div class="card-glow" style="border-left:3px solid var(--accent);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
                <div>
                    <h3 style="font-size:1.2rem;margin-bottom:0.3rem;"><?= e($selectedFreeCase['name']) ?></h3>
                    <p style="color:var(--text-muted);font-size:0.88rem;">
                        Предметов: <strong><?= count($freeCaseItems) ?></strong> • 
                        Мин. депозит: <strong style="color:var(--success);"><?= formatMoney($selectedFreeCase['min_deposit']) ?></strong>
                    </p>
                </div>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <button class="btn-glow btn-glow--primary btn-glow--sm" onclick="openFreeCaseSteamSearchModal(<?= $caseId ?>)">🎮 Добавить из Steam</button>
                    <button class="btn-glow btn-glow--outline btn-glow--sm" onclick="addFreeCaseItemModal(<?= $caseId ?>)">➕ Добавить вручную</button>
                </div>
            </div>
            
            <?php if (empty($freeCaseItems)): ?>
            <div class="empty-state">
                <div class="empty-state__icon">📦</div>
                <div class="empty-state__text">В кейсе пока нет предметов</div>
                <div class="empty-state__sub">Добавьте предметы, которые будут выпадать из этого кейса</div>
                <button class="btn-glow btn-glow--primary" style="margin-top:1.5rem;" onclick="addFreeCaseItemModal(<?= $caseId ?>)">➕ Добавить предмет</button>
            </div>
            <?php else: ?>
            <div class="case-items-grid">
                <?php foreach ($freeCaseItems as $item): 
                    $rarity = strtolower($item['rarity'] ?? 'milspec');
                    $color = $rarityColors[$rarity] ?? '#888';
                    $rarityLabel = $rarityLabels[$rarity] ?? $item['rarity'];
                    $imgUrl = getSteamItemImage($item['item_image'] ?? '', 'large');
                ?>
                    <div class="case-item-card">
                        <div class="case-item-card__actions">
                            <button class="btn-glow btn-glow--outline btn-glow--sm" onclick="editFreeCaseItem(<?= $item['id'] ?>, <?= $caseId ?>)" title="Редактировать">✏️</button>
                            <button class="btn-glow btn-glow--danger btn-glow--sm" onclick="deleteFreeCaseItem(<?= $item['id'] ?>, <?= $caseId ?>)" title="Удалить">🗑</button>
                        </div>
                        <img class="case-item-card__img" src="<?= e($imgUrl) ?>" alt="<?= e($item['item_name']) ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><rect fill=%22%231a1a2e%22 width=%22100%22 height=%22100%22/><text x=%2250%22 y=%2255%22 text-anchor=%22middle%22 fill=%22%23636e72%22 font-size=%2214%22>Нет фото</text></svg>';">
                        <div class="case-item-card__name"><?= e($item['item_name']) ?></div>
                        <div class="case-item-card__rarity" style="color:<?= $color ?>;">● <?= e($rarityLabel) ?></div>
                        <div class="case-item-card__price"><?= formatMoney($item['price']) ?></div>
                        <div class="case-item-card__weight">Вес: <?= $item['weight'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif (!$caseId): ?>
        <div class="card-glow">
            <div class="empty-state">
                <div class="empty-state__icon">📂</div>
                <div class="empty-state__text">Кейс не выбран</div>
                <div class="empty-state__sub">Выберите кейс из списка выше, чтобы управлять предметами</div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    <?php break;

    case 'users':
        $page = (int)($_GET['page'] ?? 1);
        $search = trim($_GET['search'] ?? '');
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = '';
        $params = [];
        if ($search) {
            $where = 'WHERE u.username LIKE ? OR u.email LIKE ?';
            $params = ["%$search%", "%$search%"];
        }
        
        $stmt = db()->prepare("SELECT u.*, (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type = 'deposit') as total_deposit FROM users u $where ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $users = $stmt->fetchAll();
    ?>
        <div class="section-header">
            <div>
                <h1>👥 Пользователи</h1>
                <p class="subtitle">Управление пользователями вашего казино</p>
            </div>
            <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <input type="text" class="admin-form-control-glow" id="userSearch" placeholder="Поиск по имени или email..." value="<?= e($search) ?>" onkeyup="searchUsers()" style="max-width:300px;">
                    <button class="btn-glow btn-glow--primary btn-glow--sm" onclick="searchUsers()">🔍</button>
                </div>
            </div>
        </div>

        <?php if (empty($users)): ?>
        <div class="card-glow">
            <div class="empty-state">
                <div class="empty-state__icon">👥</div>
                <div class="empty-state__text">Пользователи не найдены</div>
                <div class="empty-state__sub"><?= $search ? 'Попробуйте изменить поисковый запрос' : 'Регистраций пока нет' ?></div>
            </div>
        </div>
        <?php else: ?>
        <div class="stat-row">
            <div class="stat-mini">
                <div class="stat-mini__icon purple">👥</div>
                <div class="stat-mini__info">
                    <div class="stat-mini__label">Показано</div>
                    <div class="stat-mini__value"><?= count($users) ?></div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini__icon green">💰</div>
                <div class="stat-mini__info">
                    <div class="stat-mini__label">Общий баланс</div>
                    <div class="stat-mini__value"><?= formatMoney(array_sum(array_column($users, 'balance'))) ?></div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini__icon orange">📈</div>
                <div class="stat-mini__info">
                    <div class="stat-mini__label">Общие депозиты</div>
                    <div class="stat-mini__value"><?= formatMoney(array_sum(array_column($users, 'total_deposit'))) ?></div>
                </div>
            </div>
        </div>

        <div class="card-glow">
            <div class="table-glow">
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Логин</th>
                            <th>Email</th>
                            <th style="width:120px;">Баланс</th>
                            <th style="width:120px;">Депозит</th>
                            <th style="width:100px;">Роль</th>
                            <th style="width:100px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody id="usersTable">
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td style="color:var(--text-muted);"><?= $u['id'] ?></td>
                                <td><strong><?= e($u['username']) ?></strong></td>
                                <td style="color:var(--text-secondary);"><?= e($u['email']) ?></td>
                                <td style="color:var(--success);font-weight:600;"><?= formatMoney($u['balance']) ?></td>
                                <td><?= formatMoney($u['total_deposit'] ?? 0) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge-glow badge-glow--accent">👑 Admin</span>
                                    <?php else: ?>
                                        <span class="badge-glow badge-glow--success">👤 User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-glow btn-glow--outline btn-glow--sm" onclick="editUser(<?= $u['id'] ?>)" title="Редактировать">✏️</button>
                                    <button class="btn-glow btn-glow--danger btn-glow--sm" onclick="deleteUser(<?= $u['id'] ?>)" title="Удалить">🗑</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php break;

    case 'transactions':
        $page = (int)($_GET['page'] ?? 1);
        $type = trim($_GET['type'] ?? '');
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $where = $type ? "WHERE t.type = ?" : '';
        $params = $type ? [$type] : [];
        $stmt = db()->prepare("SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON t.user_id = u.id $where ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();
    ?>
        <h1 class="page-title">💰 Транзакции</h1>
        <div class="card">
            <div class="search-bar">
                <select class="admin-form-control" id="transType" onchange="loadTransactions()">
                    <option value="">Все типы</option>
                    <option value="deposit" <?= $type === 'deposit' ? 'selected' : '' ?>>Депозит</option>
                    <option value="case_open" <?= $type === 'case_open' ? 'selected' : '' ?>>Кейс</option>
                    <option value="referral_bonus" <?= $type === 'referral_bonus' ? 'selected' : '' ?>>Реферал</option>
                </select>
            </div>
            <div class="table-wrapper">
                <table><thead><tr><th>ID</th><th>Пользователь</th><th>Тип</th><th>Сумма</th><th>Описание</th><th>Дата</th></tr></thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <tr><td><?= $t['id'] ?></td><td><?= e($t['username'] ?? 'Unknown') ?></td>
                        <td><span class="admin-badge admin-badge--info"><?= e($t['type']) ?></span></td>
                        <td style="color:<?= $t['amount'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= formatMoney($t['amount']) ?></td>
                        <td><?= e($t['description']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
    <?php break;

    case 'history':
        $page = (int)($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $stmt = db()->prepare("SELECT h.*, u.username FROM case_open_history h LEFT JOIN users u ON h.user_id = u.id ORDER BY h.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute();
        $history = $stmt->fetchAll();
    ?>
        <h1 class="page-title">📜 История открытий</h1>
        <div class="card">
            <div class="table-wrapper">
                <table><thead><tr><th>ID</th><th>Пользователь</th><th>Кейс</th><th>Предмет</th><th>Цена</th><th>Дата</th></tr></thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr><td><?= $h['id'] ?></td><td><?= e($h['username'] ?? 'Unknown') ?></td><td><?= e($h['case_id']) ?></td><td><?= e($h['item_name']) ?></td><td style="color:var(--success);"><?= formatMoney($h['price']) ?></td><td><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
    <?php break;

    case 'settings': ?>
        <div class="section-header">
            <div>
                <h1>⚙️ Настройки</h1>
                <p class="subtitle">Управление параметрами вашего казино</p>
            </div>
            <button class="btn-glow btn-glow--outline btn-glow--sm" onclick="window.location.reload()">🔄 Обновить</button>
        </div>
        
        <!-- ОСНОВНЫЕ НАСТРОЙКИ -->
        <div class="settings-card">
            <div class="settings-card__header">
                <div class="settings-card__icon">🏪</div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:600;">Основные настройки</h3>
                    <p style="margin:0.25rem 0 0;color:var(--text-muted);font-size:0.85rem;">Название, описание и контакты поддержки</p>
                </div>
            </div>
            <div class="settings-card__body">
                <div class="settings-grid">
                    <div class="settings-field">
                        <label>Название сайта</label>
                        <input type="text" class="settings-input" id="setting_site_name" value="<?= e($allSettings['site_name'] ?? 'DropForge') ?>">
                        <span class="settings-hint">Название вашего казино, отображается в шапке</span>
                    </div>
                    <div class="settings-field">
                        <label>Описание</label>
                        <input type="text" class="settings-input" id="setting_site_description" value="<?= e($allSettings['site_description'] ?? '') ?>">
                        <span class="settings-hint">SEO описание для поисковых систем</span>
                    </div>
                    <div class="settings-field">
                        <label>Email поддержки</label>
                        <input type="email" class="settings-input" id="setting_support_email" value="<?= e($allSettings['support_email'] ?? 'support@dropforge.gg') ?>">
                    </div>
                    <div class="settings-field">
                        <label>Telegram поддержки</label>
                        <input type="text" class="settings-input" id="setting_support_telegram" value="<?= e($allSettings['support_telegram'] ?? '') ?>" placeholder="https://t.me/your_support">
                    </div>
                </div>
                <div class="settings-card__footer">
                    <button class="btn-glow btn-glow--success" onclick="saveSettings(['site_name','site_description','support_email','support_telegram'])">💾 Сохранить изменения</button>
                </div>
            </div>
        </div>

        <!-- СТРАНИЦЫ -->
        <div class="settings-card">
            <div class="settings-card__header">
                <div class="settings-card__icon">🌐</div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:600;">Управление страницами</h3>
                    <p style="margin:0.25rem 0 0;color:var(--text-muted);font-size:0.85rem;">Включение и отключение разделов сайта</p>
                </div>
            </div>
            <div class="settings-card__body">
                <div class="settings-toggles">
                    <?php
                    $pages = [
                        'upgrade_enabled' => '⚡ Upgrade',
                        'contract_enabled' => '🤝 Contract',
                        'battle_pass_enabled' => '⚔️ Battle Pass',
                        'daily_bonus_enabled' => '🎁 Daily Bonus',
                        'free_case_enabled' => '🎁 Free Case',
                        'referrals_enabled' => '🔗 Рефералы',
                        'inventory_enabled' => '🎒 Инвентарь',
                    ];
                    foreach ($pages as $k => $l): ?>
                        <label class="settings-toggle">
                            <input type="checkbox" <?= ($allSettings[$k] ?? '1') === '1' ? 'checked' : '' ?> onchange="togglePage('<?= $k ?>', this.checked)">
                            <span class="settings-toggle__track"></span>
                            <span class="settings-toggle__label"><?= $l ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- КЕЙСЫ -->
        <div class="settings-card">
            <div class="settings-card__header">
                <div class="settings-card__icon">📦</div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:600;">Настройки кейсов</h3>
                    <p style="margin:0.25rem 0 0;color:var(--text-muted);font-size:0.85rem;">Цены, количество и вероятности</p>
                </div>
            </div>
            <div class="settings-card__body">
                <div class="settings-grid">
                    <div class="settings-field">
                        <label>Мин. цена кейса ($)</label>
                        <input type="number" class="settings-input" id="setting_min_case_price" value="<?= e($allSettings['min_case_price'] ?? '0.50') ?>" step="0.01">
                    </div>
                    <div class="settings-field">
                        <label>Макс. цена кейса ($)</label>
                        <input type="number" class="settings-input" id="setting_max_case_price" value="<?= e($allSettings['max_case_price'] ?? '100.00') ?>" step="0.01">
                    </div>
                    <div class="settings-field">
                        <label>Цена продажи предмета (%)</label>
                        <input type="number" class="settings-input" id="setting_sell_price_percent" value="<?= e($allSettings['sell_price_percent'] ?? '70') ?>" step="1">
                        <span class="settings-hint">Процент от стоимости при продаже обратно</span>
                    </div>
                    <div class="settings-field">
                        <label>Макс. открытий за раз</label>
                        <input type="number" class="settings-input" id="setting_max_open_qty" value="<?= e($allSettings['max_open_qty'] ?? '10') ?>">
                    </div>
                    <div class="settings-field">
                        <label>Прозрачный рандом</label>
                        <select class="settings-input" id="setting_transparent_rig">
                            <option value="1" <?= ($allSettings['transparent_rig'] ?? '1') === '1' ? 'selected' : '' ?>>Вкл — показывать реальные шансы</option>
                            <option value="0" <?= ($allSettings['transparent_rig'] ?? '1') !== '1' ? 'selected' : '' ?>>Выкл — скрыть шансы</option>
                        </select>
                    </div>
                </div>
                <div class="settings-card__footer">
                    <button class="btn-glow btn-glow--success" onclick="saveSettings(['min_case_price','max_case_price','sell_price_percent','max_open_qty','transparent_rig'])">💾 Сохранить</button>
                </div>
            </div>
        </div>

        <!-- РЕФЕРАЛЬНАЯ СИСТЕМА -->
        <div class="settings-card">
            <div class="settings-card__header">
                <div class="settings-card__icon">🔗</div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:600;">Реферальная система</h3>
                    <p style="margin:0.25rem 0 0;color:var(--text-muted);font-size:0.85rem;">Комиссии и бонусы за приглашения</p>
                </div>
            </div>
            <div class="settings-card__body">
                <div class="settings-grid">
                    <div class="settings-field">
                        <label>Комиссия реферера (%)</label>
                        <input type="number" class="settings-input" id="setting_ref_commission" value="<?= e($allSettings['ref_commission'] ?? '5') ?>" step="0.1">
                        <span class="settings-hint">% от депозита реферала</span>
                    </div>
                    <div class="settings-field">
                        <label>Бонус первого депозита (%)</label>
                        <input type="number" class="settings-input" id="setting_first_deposit_bonus" value="<?= e($allSettings['first_deposit_bonus'] ?? '20') ?>" step="0.1">
                    </div>
                    <div class="settings-field">
                        <label>Бонус крипты (%)</label>
                        <input type="number" class="settings-input" id="setting_crypto_bonus" value="<?= e($allSettings['crypto_bonus'] ?? '5') ?>" step="0.1">
                        <span class="settings-hint">Дополнительный бонус за крипту</span>
                    </div>
                </div>
                <div class="settings-card__footer">
                    <button class="btn-glow btn-glow--success" onclick="saveSettings(['ref_commission','first_deposit_bonus','crypto_bonus'])">💾 Сохранить</button>
                </div>
            </div>
        </div>

        <!-- ВАЛЮТА И КУРС -->
        <div class="settings-card">
            <div class="settings-card__header">
                <div class="settings-card__icon">💱</div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:600;">Валюта и курс</h3>
                    <p style="margin:0.25rem 0 0;color:var(--text-muted);font-size:0.85rem;">Основная валюта и конвертация USD/RUB</p>
                </div>
            </div>
            <div class="settings-card__body">
                <div class="settings-grid">
                    <div class="settings-field">
                        <label>Основная валюта</label>
                        <select class="settings-input" id="setting_site_currency">
                            <option value="USD" <?= ($allSettings['site_currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>$ USD — Доллар</option>
                            <option value="RUB" <?= ($allSettings['site_currency'] ?? 'USD') === 'RUB' ? 'selected' : '' ?>>₽ RUB — Рубль</option>
                            <option value="KZT" <?= ($allSettings['site_currency'] ?? 'USD') === 'KZT' ? 'selected' : '' ?>>₸ KZT — Тенге</option>
                        </select>
                    </div>
                    <div class="settings-field">
                        <label>Символ валюты</label>
                        <input type="text" class="settings-input" id="setting_currency_symbol" value="<?= e($allSettings['currency_symbol'] ?? '$') ?>" maxlength="5">
                    </div>
                </div>
                
                <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:1rem;">
                        <div>
                            <label class="settings-toggle" style="display:inline-flex;">
                                <input type="checkbox" id="usdRubAuto" <?= ($allSettings['usd_rub_auto'] ?? '0') === '1' ? 'checked' : '' ?> onchange="toggleAutoRate()">
                                <span class="settings-toggle__track"></span>
                                <span class="settings-toggle__label">Авто-курс USD/RUB</span>
                            </label>
                            <span style="color:var(--success);font-weight:600;margin-left:1rem;" id="currentRate"><?= getUsdRubRateFormatted() ?></span>
                        </div>
                    </div>
                    
                    <div id="manualRateBlock" style="<?= ($allSettings['usd_rub_auto'] ?? '0') === '1' ? 'display:none;' : '' ?>;">
                        <div class="settings-grid" style="max-width:400px;">
                            <div class="settings-field">
                                <label>Ручной курс</label>
                                <input type="number" class="settings-input" id="manualUsdRubRate" value="<?= e($allSettings['usd_rub_rate'] ?? '90.00') ?>" step="0.01">
                            </div>
                        </div>
                        <div style="display:flex;gap:0.5rem;margin-top:1rem;">
                            <button class="btn-glow btn-glow--primary btn-glow--sm" onclick="saveManualRate()">💾 Сохранить курс</button>
                            <button class="btn-glow btn-glow--outline btn-glow--sm" onclick="updateAutoRate()">🔄 Обновить</button>
                        </div>
                    </div>
                    
                    <div style="display:flex;gap:0.5rem;margin-top:1rem;">
                        <button class="btn-glow btn-glow--success btn-glow--sm" onclick="saveSettings(['site_currency','currency_symbol'])">💾 Сохранить валюту</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ПЛАТЕЖНЫЕ СИСТЕМЫ -->
        <div class="settings-card">
            <div class="settings-card__header">
                <div class="settings-card__icon">💳</div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:600;">Платёжные системы</h3>
                    <p style="margin:0.25rem 0 0;color:var(--text-muted);font-size:0.85rem;">Настройка FreeKassa и YooMoney</p>
                </div>
            </div>
            <div class="settings-card__body">
                <div style="margin-bottom:2rem;padding-bottom:2rem;border-bottom:1px solid var(--border);">
                    <h4 style="margin:0 0 1rem 0;display:flex;align-items:center;gap:0.5rem;">
                        <span style="font-size:1.3rem;">💰</span> FreeKassa
                    </h4>
                    <div class="settings-grid">
                        <div class="settings-field">
                            <label>Merchant ID</label>
                            <input type="text" class="settings-input" id="fk_merchant_id" value="<?= e($allSettings['fk_merchant_id'] ?? '') ?>">
                        </div>
                        <div class="settings-field">
                            <label>Режим</label>
                            <select class="settings-input" id="fk_mode">
                                <option value="test" <?= ($allSettings['fk_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>🧪 Тест</option>
                                <option value="production" <?= ($allSettings['fk_mode'] ?? '') === 'production' ? 'selected' : '' ?>>🚀 Продакшн</option>
                            </select>
                        </div>
                        <div class="settings-field">
                            <label>Phrase 1</label>
                            <input type="text" class="settings-input" id="fk_phrase1" value="<?= e($allSettings['fk_phrase1'] ?? '') ?>">
                        </div>
                        <div class="settings-field">
                            <label>Phrase 2</label>
                            <input type="text" class="settings-input" id="fk_phrase2" value="<?= e($allSettings['fk_phrase2'] ?? '') ?>">
                        </div>
                    </div>
                    <button class="btn-glow btn-glow--success btn-glow--sm" onclick="saveFkSettings()">💾 Сохранить FreeKassa</button>
                </div>
                
                <div>
                    <h4 style="margin:0 0 1rem 0;display:flex;align-items:center;gap:0.5rem;">
                        <span style="font-size:1.3rem;">🏦</span> YooMoney
                    </h4>
                    <div class="settings-grid">
                        <div class="settings-field">
                            <label>ShopID</label>
                            <input type="text" class="settings-input" id="ym_shopid" value="<?= e($allSettings['ym_shopid'] ?? '') ?>">
                        </div>
                        <div class="settings-field">
                            <label>Логин (пароль)</label>
                            <input type="text" class="settings-input" id="ym_password" value="<?= e($allSettings['ym_password'] ?? '') ?>">
                        </div>
                        <div class="settings-field">
                            <label>Событие URL</label>
                            <input type="text" class="settings-input" id="ym_event_url" value="<?= e($allSettings['ym_event_url'] ?? SITE_URL . '/api/payment/ym_callback.php') ?>" placeholder="<?= SITE_URL ?>/api/payment/ym_callback.php">
                        </div>
                        <div class="settings-field">
                            <label>Режим</label>
                            <select class="settings-input" id="ym_mode">
                                <option value="test" <?= ($allSettings['ym_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>🧪 Тест</option>
                                <option value="production" <?= ($allSettings['ym_mode'] ?? '') === 'production' ? 'selected' : '' ?>>🚀 Продакшн</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn-glow btn-glow--success btn-glow--sm" onclick="saveYmSettings()">💾 Сохранить YooMoney</button>
                </div>
            </div>
        </div>

        <!-- STEAM -->
        <div class="settings-card">
            <div class="settings-card__header">
                <div class="settings-card__icon">🎮</div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:600;">Steam API</h3>
                    <p style="margin:0.25rem 0 0;color:var(--text-muted);font-size:0.85rem;">Ключ для интеграции с Steam</p>
                </div>
            </div>
            <div class="settings-card__body">
                <div class="settings-field">
                    <label>Steam Web API Key</label>
                    <input type="text" class="settings-input" id="setting_steam_api_key" value="<?= e($allSettings['steam_api_key'] ?? '') ?>" placeholder="Введите API ключ от Steam">
                    <span class="settings-hint">Получить на steamcommunity.com/my/apikey</span>
                </div>
                <div class="settings-card__footer">
                    <button class="btn-glow btn-glow--success" onclick="saveSteamApiKey()">💾 Сохранить ключ</button>
                </div>
            </div>
        </div>

        <!-- КАСТОМИЗАЦИЯ -->
        <div class="settings-card">
            <div class="settings-card__header">
                <div class="settings-card__icon">🎨</div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:600;">Кастомизация</h3>
                    <p style="margin:0.25rem 0 0;color:var(--text-muted);font-size:0.85rem;">CSS и HTML для изменения внешнего вида</p>
                </div>
            </div>
            <div class="settings-card__body">
                <div class="settings-field">
                    <label>Пользовательский CSS</label>
                    <textarea class="settings-input settings-textarea" id="setting_custom_css" rows="6" style="font-family:monospace;font-size:0.85rem;"><?= e($allSettings['custom_css'] ?? '') ?></textarea>
                </div>
                <div class="settings-field">
                    <label>HTML в футер</label>
                    <textarea class="settings-input settings-textarea" id="setting_footer_html" rows="4" style="font-family:monospace;font-size:0.85rem;"><?= e($allSettings['footer_html'] ?? '') ?></textarea>
                </div>
                <div class="settings-card__footer">
                    <button class="btn-glow btn-glow--success" onclick="saveSettings(['custom_css','footer_html'])">💾 Сохранить</button>
                </div>
            </div>
        </div>

        <!-- БЕЗОПАСНОСТЬ -->
        <div class="settings-card">
            <div class="settings-card__header">
                <div class="settings-card__icon">🔒</div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:600;">Безопасность</h3>
                    <p style="margin:0.25rem 0 0;color:var(--text-muted);font-size:0.85rem;">Регистрация и лимиты депозитов</p>
                </div>
            </div>
            <div class="settings-card__body">
                <div class="settings-grid">
                    <div class="settings-field">
                        <label>Регистрация</label>
                        <select class="settings-input" id="setting_registration_enabled">
                            <option value="1" <?= ($allSettings['registration_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>✅ Включена</option>
                            <option value="0" <?= ($allSettings['registration_enabled'] ?? '1') !== '1' ? 'selected' : '' ?>>❌ Выключена</option>
                        </select>
                    </div>
                    <div class="settings-field">
                        <label>Мин. сумма депозита ($)</label>
                        <input type="number" class="settings-input" id="setting_min_deposit" value="<?= e($allSettings['min_deposit'] ?? '1') ?>" step="0.01">
                    </div>
                    <div class="settings-field">
                        <label>Макс. сумма депозита ($)</label>
                        <input type="number" class="settings-input" id="setting_max_deposit" value="<?= e($allSettings['max_deposit'] ?? '10000') ?>" step="0.01">
                    </div>
                </div>
                <div class="settings-card__footer">
                    <button class="btn-glow btn-glow--success" onclick="saveSettings(['registration_enabled','min_deposit','max_deposit'])">💾 Сохранить</button>
                </div>
            </div>
        </div>
    <?php break;

    case 'referrals':
        $stmt = db()->query("SELECT u.id, u.username, u.ref_code, (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as referral_count, (SELECT SUM(amount) FROM transactions t INNER JOIN users ru ON t.user_id = ru.id WHERE ru.referred_by = u.id AND t.type = 'deposit') as total_ref_deposit FROM users u WHERE u.ref_code IS NOT NULL AND u.ref_code != '' ORDER BY referral_count DESC LIMIT 50");
        $referrals = $stmt->fetchAll();
    ?>
        <h1 class="page-title">🔗 Рефералы</h1>
        <div class="card">
            <div class="table-wrapper">
                <table><thead><tr><th>ID</th><th>Логин</th><th>Код</th><th>Рефералов</th><th>Депозит</th></tr></thead>
                <tbody>
                    <?php foreach ($referrals as $r): ?>
                        <tr><td><?= $r['id'] ?></td><td><strong><?= e($r['username']) ?></strong></td><td><code><?= e($r['ref_code']) ?></code></td><td><?= $r['referral_count'] ?></td><td><?= formatMoney($r['total_ref_deposit'] ?? 0) ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
    <?php break;

    case 'payment':
        $gateways = getPaymentGateways();
        $fkSettings = getFkSettings();
        $ymSettings = getYmSettings();
        $enotSettings = getEnotSettings();
    ?>

    <div class="admin-page-header">
        <div class="admin-page-header__title-group">
            <h1 class="admin-page-header__title">💳 Платёжные шлюзы</h1>
            <p class="admin-page-header__subtitle">Настройка способов оплаты для сайта</p>
        </div>
    </div>

    <div class="admin-info-box">
        <div class="admin-info-box__title">ℹ️ Важно</div>
        <div class="admin-info-box__text">По умолчанию все шлюзы <strong>выключены</strong>. Включите нужный шлюз и заполните обязательные поля. Пользователи видят только включённые и настроенные шлюзы.</div>
    </div>

    <div class="admin-payment-layout">
        <div class="admin-payment-nav">
            <?php foreach ($gateways as $key => $gw): 
                $isActive = $gw['enabled'];
                $isConfigured = false;
                
                if ($key === 'freekassa') {
                    $isConfigured = !empty($gw['settings']['fk_merchant_id']) && !empty($gw['settings']['fk_phrase1']);
                } elseif ($key === 'yoomoney') {
                    $isConfigured = !empty($gw['settings']['ym_shopid']) && !empty($gw['settings']['ym_password']);
                } elseif ($key === 'enot') {
                    $isConfigured = !empty($gw['settings']['enot_shop_id']) && !empty($gw['settings']['enot_secret_key']);
                }
                
                $statusLabel = $isActive ? ($isConfigured ? 'Готов' : 'Настройка') : 'Выкл';
                $statusClass = $isActive && $isConfigured ? 'active' : '';
            ?>
            <div class="admin-payment-nav-btn <?= $isActive ? 'active' : '' ?>" onclick="showPaymentPanel('<?= $key ?>')">
                <div class="admin-payment-nav-btn__left">
                    <div class="admin-payment-nav-btn__icon <?= $key ?>">
                        <?php if ($key === 'freekassa'): ?>🏧
                        <?php elseif ($key === 'yoomoney'): ?>💳
                        <?php elseif ($key === 'enot'): ?>🧾<?php endif; ?>
                    </div>
                    <span class="admin-payment-nav-btn__name"><?= e($gw['name']) ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="admin-payment-nav-btn__status <?= $statusClass ?>"><?= $statusLabel ?></span>
                    <label class="admin-toggle">
                        <input type="checkbox" id="toggle_<?= $key ?>" <?= $isActive ? 'checked' : '' ?> onclick="event.stopPropagation(); toggleGateway('<?= $key ?>', this.checked)">
                        <span class="admin-toggle__track"><span class="admin-toggle__thumb"></span></span>
                    </label>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="payment-panels">
            <!-- FreeKassa -->
            <div class="admin-payment-panel" id="panel_freekassa">
                <div class="admin-payment-panel__header">
                    <div class="admin-payment-panel__title">
                        <div class="admin-payment-panel__icon freekassa">🏧</div>
                        <div>
                            <h2 class="admin-payment-panel__heading">FreeKassa</h2>
                            <p class="admin-payment-panel__desc">Карты, СБП, электронные кошельки</p>
                        </div>
                    </div>
                    <span class="admin-badge <?= $gateways['freekassa']['enabled'] && !empty($fkSettings['fk_merchant_id']) ? 'admin-badge--success' : 'admin-badge--neutral' ?>">
                        <?= $gateways['freekassa']['enabled'] && !empty($fkSettings['fk_merchant_id']) ? '● Активен' : '○ Выключен' ?>
                    </span>
                </div>
                <div class="admin-payment-panel__body">
                    <div class="admin-payment-status-grid">
                        <div class="admin-payment-status-item">
                            <div class="admin-payment-status-item__value <?= $gateways['freekassa']['enabled'] ? 'success' : 'muted' ?>"><?= $gateways['freekassa']['enabled'] ? 'Да' : 'Нет' ?></div>
                            <div class="admin-payment-status-item__label">Включён</div>
                        </div>
                        <div class="admin-payment-status-item">
                            <div class="admin-payment-status-item__value <?= !empty($fkSettings['fk_merchant_id']) ? 'success' : 'error' ?>"><?= !empty($fkSettings['fk_merchant_id']) ? '✓' : '✕' ?></div>
                            <div class="admin-payment-status-item__label">Настроен</div>
                        </div>
                        <div class="admin-payment-status-item">
                            <div class="admin-payment-status-item__value"><?= e($fkSettings['fk_mode'] === 'production' ? 'Prod' : 'Test') ?></div>
                            <div class="admin-payment-status-item__label">Режим</div>
                        </div>
                    </div>

                    <ul class="admin-payment-checklist">
                        <li>
                            <span class="check <?= !empty($fkSettings['fk_merchant_id']) ? 'ok' : 'fail' ?>"><?= !empty($fkSettings['fk_merchant_id']) ? '✓' : '✕' ?></span>
                            <span>Merchant ID заполнен</span>
                        </li>
                        <li>
                            <span class="check <?= !empty($fkSettings['fk_phrase1']) ? 'ok' : 'fail' ?>"><?= !empty($fkSettings['fk_phrase1']) ? '✓' : '✕' ?></span>
                            <span>Секретный ключ (Phrase 1) заполнен</span>
                        </li>
                        <li>
                            <span class="check <?= !empty($fkSettings['fk_phrase2']) ? 'ok' : 'wait' ?>"><?= !empty($fkSettings['fk_phrase2']) ? '✓' : '○' ?></span>
                            <span>Phrase 2 для callback</span>
                        </li>
                    </ul>

                    <div class="admin-payment-form">
                        <div class="admin-payment-form__field">
                            <label class="admin-payment-form__label">Merchant ID<span class="req">*</span></label>
                            <input type="text" class="admin-form-control" id="fk_merchant_id" value="<?= e($fkSettings['fk_merchant_id'] ?? '') ?>" placeholder="1">
                            <div class="admin-payment-form__hint">ID из кабинета FreeKassa</div>
                        </div>
                        <div class="admin-payment-form__field">
                            <label class="admin-payment-form__label">Режим</label>
                            <select class="admin-form-control" id="fk_mode">
                                <option value="test" <?= ($fkSettings['fk_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Тест</option>
                                <option value="production" <?= ($fkSettings['fk_mode'] ?? '') === 'production' ? 'selected' : '' ?>>Продакшн</option>
                            </select>
                        </div>
                        <div class="admin-payment-form__field">
                            <label class="admin-payment-form__label">Phrase 1<span class="req">*</span></label>
                            <input type="password" class="admin-form-control" id="fk_phrase1" value="<?= e($fkSettings['fk_phrase1'] ?? '') ?>" placeholder="••••">
                            <div class="admin-payment-form__hint">Для подписи платежей</div>
                        </div>
                        <div class="admin-payment-form__field">
                            <label class="admin-payment-form__label">Phrase 2</label>
                            <input type="password" class="admin-form-control" id="fk_phrase2" value="<?= e($fkSettings['fk_phrase2'] ?? '') ?>" placeholder="••••">
                            <div class="admin-payment-form__hint">Для проверки callback</div>
                        </div>
                    </div>

                    <div class="admin-payment-form__actions">
                        <button class="admin-btn admin-btn--success" onclick="saveFkSettings()">Сохранить</button>
                        <button class="admin-btn admin-btn--ghost" onclick="window.open('https://merchant.freekassa.com/', '_blank')">Кабинет FreeKassa</button>
                    </div>
                </div>
            </div>

            <!-- YooMoney -->
            <div class="admin-payment-panel" id="panel_yoomoney">
                <div class="admin-payment-panel__header">
                    <div class="admin-payment-panel__title">
                        <div class="admin-payment-panel__icon yoomoney">💳</div>
                        <div>
                            <h2 class="admin-payment-panel__heading">YooMoney</h2>
                            <p class="admin-payment-panel__desc">Карты, СБП, ЮMoney</p>
                        </div>
                    </div>
                    <span class="admin-badge <?= $gateways['yoomoney']['enabled'] && !empty($ymSettings['ym_shopid']) ? 'admin-badge--success' : 'admin-badge--neutral' ?>">
                        <?= $gateways['yoomoney']['enabled'] && !empty($ymSettings['ym_shopid']) ? '● Активен' : '○ Выключен' ?>
                    </span>
                </div>
                <div class="admin-payment-panel__body">
                    <div class="admin-payment-status-grid">
                        <div class="admin-payment-status-item">
                            <div class="admin-payment-status-item__value <?= $gateways['yoomoney']['enabled'] ? 'success' : 'muted' ?>"><?= $gateways['yoomoney']['enabled'] ? 'Да' : 'Нет' ?></div>
                            <div class="admin-payment-status-item__label">Включён</div>
                        </div>
                        <div class="admin-payment-status-item">
                            <div class="admin-payment-status-item__value <?= !empty($ymSettings['ym_shopid']) ? 'success' : 'error' ?>"><?= !empty($ymSettings['ym_shopid']) ? '✓' : '✕' ?></div>
                            <div class="admin-payment-status-item__label">Настроен</div>
                        </div>
                        <div class="admin-payment-status-item">
                            <div class="admin-payment-status-item__value"><?= e($ymSettings['ym_mode'] === 'production' ? 'Prod' : 'Test') ?></div>
                            <div class="admin-payment-status-item__label">Режим</div>
                        </div>
                    </div>

                    <ul class="admin-payment-checklist">
                        <li>
                            <span class="check <?= !empty($ymSettings['ym_shopid']) ? 'ok' : 'fail' ?>"><?= !empty($ymSettings['ym_shopid']) ? '✓' : '✕' ?></span>
                            <span>ShopID заполнен</span>
                        </li>
                        <li>
                            <span class="check <?= !empty($ymSettings['ym_password']) ? 'ok' : 'fail' ?>"><?= !empty($ymSettings['ym_password']) ? '✓' : '✕' ?></span>
                            <span>Пароль API заполнен</span>
                        </li>
                        <li>
                            <span class="check <?= !empty($ymSettings['ym_event_url']) ? 'ok' : 'wait' ?>"><?= !empty($ymSettings['ym_event_url']) ? '✓' : '○' ?></span>
                            <span>Callback URL</span>
                        </li>
                    </ul>

                    <div class="admin-payment-form">
                        <div class="admin-payment-form__field">
                            <label class="admin-payment-form__label">ShopID<span class="req">*</span></label>
                            <input type="text" class="admin-form-control" id="ym_shopid" value="<?= e($ymSettings['ym_shopid'] ?? '') ?>" placeholder="44444">
                        </div>
                        <div class="admin-payment-form__field">
                            <label class="admin-payment-form__label">Режим</label>
                            <select class="admin-form-control" id="ym_mode">
                                <option value="test" <?= ($ymSettings['ym_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Тест</option>
                                <option value="production" <?= ($ymSettings['ym_mode'] ?? '') === 'production' ? 'selected' : '' ?>>Продакшн</option>
                            </select>
                        </div>
                        <div class="admin-payment-form__field admin-payment-form__field--full">
                            <label class="admin-payment-form__label">Пароль API<span class="req">*</span></label>
                            <input type="password" class="admin-form-control" id="ym_password" value="<?= e($ymSettings['ym_password'] ?? '') ?>" placeholder="••••">
                            <div class="admin-payment-form__hint">Из раздела API в кабинете ЮMoney</div>
                        </div>
                        <div class="admin-payment-form__field admin-payment-form__field--full">
                            <label class="admin-payment-form__label">Callback URL</label>
                            <input type="text" class="admin-form-control" id="ym_event_url" value="<?= e($ymSettings['ym_event_url'] ?? SITE_URL . '/api/payment/ym_callback.php') ?>" placeholder="<?= SITE_URL ?>/api/payment/ym_callback.php">
                        </div>
                    </div>

                    <div class="admin-payment-form__actions">
                        <button class="admin-btn admin-btn--success" onclick="saveYmSettings()">Сохранить</button>
                        <button class="admin-btn admin-btn--ghost" onclick="window.open('https://yoomoney.ru/', '_blank')">Кабинет YooMoney</button>
                    </div>
                </div>
            </div>

            <!-- enot.io -->
            <div class="admin-payment-panel" id="panel_enot">
                <div class="admin-payment-panel__header">
                    <div class="admin-payment-panel__title">
                        <div class="admin-payment-panel__icon enot">🧾</div>
                        <div>
                            <h2 class="admin-payment-panel__heading">enot.io</h2>
                            <p class="admin-payment-panel__desc">Карты, СБП, ЮMoney, Qiwi</p>
                        </div>
                    </div>
                    <span class="admin-badge <?= $gateways['enot']['enabled'] && !empty($enotSettings['enot_shop_id']) ? 'admin-badge--success' : 'admin-badge--neutral' ?>">
                        <?= $gateways['enot']['enabled'] && !empty($enotSettings['enot_shop_id']) ? '● Активен' : '○ Выключен' ?>
                    </span>
                </div>
                <div class="admin-payment-panel__body">
                    <div class="admin-payment-status-grid">
                        <div class="admin-payment-status-item">
                            <div class="admin-payment-status-item__value <?= $gateways['enot']['enabled'] ? 'success' : 'muted' ?>"><?= $gateways['enot']['enabled'] ? 'Да' : 'Нет' ?></div>
                            <div class="admin-payment-status-item__label">Включён</div>
                        </div>
                        <div class="admin-payment-status-item">
                            <div class="admin-payment-status-item__value <?= !empty($enotSettings['enot_shop_id']) ? 'success' : 'error' ?>"><?= !empty($enotSettings['enot_shop_id']) ? '✓' : '✕' ?></div>
                            <div class="admin-payment-status-item__label">Настроен</div>
                        </div>
                        <div class="admin-payment-status-item">
                            <div class="admin-payment-status-item__value"><?= e($enotSettings['enot_mode'] === 'production' ? 'Prod' : 'Test') ?></div>
                            <div class="admin-payment-status-item__label">Режим</div>
                        </div>
                    </div>

                    <ul class="admin-payment-checklist">
                        <li>
                            <span class="check <?= !empty($enotSettings['enot_shop_id']) ? 'ok' : 'fail' ?>"><?= !empty($enotSettings['enot_shop_id']) ? '✓' : '✕' ?></span>
                            <span>Shop ID заполнен</span>
                        </li>
                        <li>
                            <span class="check <?= !empty($enotSettings['enot_secret_key']) ? 'ok' : 'fail' ?>"><?= !empty($enotSettings['enot_secret_key']) ? '✓' : '✕' ?></span>
                            <span>Secret Key заполнен</span>
                        </li>
                    </ul>

                    <div class="admin-payment-form">
                        <div class="admin-payment-form__field">
                            <label class="admin-payment-form__label">Shop ID<span class="req">*</span></label>
                            <input type="text" class="admin-form-control" id="enot_shop_id" value="<?= e($enotSettings['enot_shop_id'] ?? '') ?>" placeholder="ваш_shop_id">
                        </div>
                        <div class="admin-payment-form__field">
                            <label class="admin-payment-form__label">Режим</label>
                            <select class="admin-form-control" id="enot_mode">
                                <option value="test" <?= ($enotSettings['enot_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Тест</option>
                                <option value="production" <?= ($enotSettings['enot_mode'] ?? '') === 'production' ? 'selected' : '' ?>>Продакшн</option>
                            </select>
                        </div>
                        <div class="admin-payment-form__field admin-payment-form__field--full">
                            <label class="admin-payment-form__label">Secret Key<span class="req">*</span></label>
                            <input type="password" class="admin-form-control" id="enot_secret_key" value="<?= e($enotSettings['enot_secret_key'] ?? '') ?>" placeholder="••••">
                            <div class="admin-payment-form__hint">Из кабинета enot.io</div>
                        </div>
                    </div>

                    <div class="admin-payment-form__actions">
                        <button class="admin-btn admin-btn--success" onclick="saveEnotSettings()">Сохранить</button>
                        <button class="admin-btn admin-btn--ghost" onclick="window.open('https://enot.io/', '_blank')">Кабинет enot.io</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php 
        $firstActive = null;
        foreach ($gateways as $key => $gw) {
            if ($gw['enabled']) {
                $firstActive = $key;
                break;
            }
        }
        if (!$firstActive) $firstActive = array_key_first($gateways);
        ?>
        showPaymentPanel('<?= $firstActive ?>');
    });

    function showPaymentPanel(key) {
        document.querySelectorAll('.admin-payment-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.admin-payment-nav-btn').forEach(c => c.classList.remove('active'));
        
        const panel = document.getElementById('panel_' + key);
        const card = document.querySelector('.admin-payment-nav-btn[onclick*="' + key + '"]');
        
        if (panel) panel.classList.add('active');
        if (card) card.classList.add('active');
    }

    function toggleGateway(key, enabled) {
        fetch('/admin/api.php?action=gateway_toggle', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ gateway: key, enabled: enabled ? 1 : 0 })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                notify('Шлюз ' + key + (enabled ? ' включён' : ' выключен'), 'success');
                showPaymentPanel(key);
                setTimeout(() => location.reload(), 800);
            } else {
                notify(d.error || 'Ошибка', 'error');
            }
        })
        .catch(() => notify('Ошибка соединения', 'error'));
    }

    function saveFkSettings() {
        const data = {
            fk_merchant_id: document.getElementById('fk_merchant_id').value,
            fk_phrase1: document.getElementById('fk_phrase1').value,
            fk_phrase2: document.getElementById('fk_phrase2').value,
            fk_mode: document.getElementById('fk_mode').value
        };

        if (!data.fk_merchant_id || !data.fk_phrase1) {
            notify('Заполните обязательные поля', 'error');
            return;
        }
        
        fetch('/admin/api.php?action=fk_settings_save', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                notify('Настройки FreeKassa сохранены', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                notify(d.error || 'Ошибка', 'error');
            }
        })
        .catch(() => notify('Ошибка соединения', 'error'));
    }

    function saveYmSettings() {
        const data = {
            ym_shopid: document.getElementById('ym_shopid').value,
            ym_password: document.getElementById('ym_password').value,
            ym_event_url: document.getElementById('ym_event_url').value,
            ym_mode: document.getElementById('ym_mode').value
        };

        if (!data.ym_shopid || !data.ym_password) {
            notify('Заполните обязательные поля', 'error');
            return;
        }
        
        fetch('/admin/api.php?action=ym_settings_save', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                notify('Настройки YooMoney сохранены', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                notify(d.error || 'Ошибка', 'error');
            }
        })
        .catch(() => notify('Ошибка соединения', 'error'));
    }

    function saveEnotSettings() {
        const data = {
            enot_shop_id: document.getElementById('enot_shop_id').value,
            enot_secret_key: document.getElementById('enot_secret_key').value,
            enot_mode: document.getElementById('enot_mode').value
        };

        if (!data.enot_shop_id || !data.enot_secret_key) {
            notify('Заполните обязательные поля', 'error');
            return;
        }
        
        fetch('/admin/api.php?action=enot_settings_save', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                notify('Настройки enot.io сохранены', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                notify(d.error || 'Ошибка', 'error');
            }
        })
        .catch(() => notify('Ошибка соединения', 'error'));
    }
    </script>
    <?php break;

    case 'daily_bonus':
        $stmt = db()->prepare("SELECT * FROM daily_bonus WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $dailyBonus = $stmt->fetch() ?: ['name' => 'Ежедневный бонус', 'cooldown_hours' => 24, 'is_active' => 1];
        $rewards = db()->query("SELECT * FROM daily_bonus_rewards ORDER BY weight DESC")->fetchAll();
        
        $balanceRewards = array_filter($rewards, fn($r) => $r['type'] === 'balance');
        $promoRewards = array_filter($rewards, fn($r) => $r['type'] === 'promo');
        $caseRewards = array_filter($rewards, fn($r) => $r['type'] === 'free_case');
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">🎁 Ежедневный бонус</h1>
                <p class="admin-page-header__subtitle">Настройка ежедневных наград</p>
            </div>
            <div class="admin-page-header__actions">
                <div class="flex gap-1">
                    <button class="admin-btn admin-btn--success admin-btn--sm" onclick="loadDefaultRewards()">⚡ Стандартные</button>
                    <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteAllDailyBonusRewards()">🗑 Очистить</button>
                    <button class="admin-btn admin-btn--primary admin-btn--sm" onclick="addDailyBonusRewardModal()">➕ Добавить</button>
                </div>
            </div>
        </div>
        
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon purple">🎁</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Всего наград</div>
                    <div class="admin-stat-card__value"><?= count($rewards) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon green">💰</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Баланс</div>
                    <div class="admin-stat-card__value"><?= count($balanceRewards) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon orange">🎟️</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Промокоды</div>
                    <div class="admin-stat-card__value"><?= count($promoRewards) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon blue">📦</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Кейсы</div>
                    <div class="admin-stat-card__value"><?= count($caseRewards) ?></div>
                </div>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">⚙️</span>
                    Параметры бонуса
                </div>
            </div>
            <div class="admin-card__body">
                <div class="admin-form-grid admin-form-grid--3">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Название</label>
                        <input type="text" class="admin-form-control" id="bonusName" value="<?= e($dailyBonus['name']) ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Кулдаун (часы)</label>
                        <input type="number" class="admin-form-control" id="bonusCooldown" value="<?= $dailyBonus['cooldown_hours'] ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Статус</label>
                        <select class="admin-form-control" id="bonusActive">
                            <option value="1" <?= $dailyBonus['is_active'] == 1 ? 'selected' : '' ?>>✅ Активен</option>
                            <option value="0" <?= $dailyBonus['is_active'] != 1 ? 'selected' : '' ?>>❌ Выключен</option>
                        </select>
                    </div>
                </div>
                <div class="admin-card__footer" style="margin:1rem -1.5rem -1.5rem -1.5rem;padding:1rem 1.5rem;">
                    <button class="admin-btn admin-btn--success" onclick="saveDailyBonus()">💾 Сохранить</button>
                </div>
            </div>
        </div>
        
        <div class="admin-info-box">
            <div class="admin-info-box__title">💡 Как работают веса?</div>
            <div class="admin-info-box__text">
                <strong>weight</strong> = вероятность выпадения. Чем больше число, тем чаще награда.<br>
                <span class="text-success">💰 Баланс</span> — начисляет деньги &nbsp;|&nbsp; 
                <span class="text-primary">🎟️ Промокод</span> — бонус к депозиту &nbsp;|&nbsp; 
                <span style="color:#74b9ff;">📦 Кейс</span> — бесплатные кейсы
            </div>
        </div>
        
        <?php if (!empty($balanceRewards)): ?>
        <div class="admin-card">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">💰</span>
                    Награды на баланс
                </div>
            </div>
            <div class="admin-card__body">
                <div class="admin-rewards-grid">
                    <?php foreach ($balanceRewards as $reward): ?>
                        <div class="admin-reward-card">
                            <div class="admin-reward-card__icon balance">💰</div>
                            <div class="admin-reward-card__content">
                                <div class="admin-reward-card__name"><?= e($reward['name']) ?></div>
                                <div class="admin-reward-card__desc"><?= number_format($reward['value'], 2) ?>$ на баланс</div>
                            </div>
                            <div class="admin-reward-card__meta">
                                <span class="admin-badge admin-badge--neutral">Вес: <?= $reward['weight'] ?></span>
                                <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteDailyBonusReward(<?= $reward['id'] ?>)">🗑</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($promoRewards)): ?>
        <div class="admin-card">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">🎟️</span>
                    Промокоды
                </div>
            </div>
            <div class="admin-card__body">
                <div class="admin-rewards-grid">
                    <?php foreach ($promoRewards as $reward): ?>
                        <div class="admin-reward-card">
                            <div class="admin-reward-card__icon promo">🎟️</div>
                            <div class="admin-reward-card__content">
                                <div class="admin-reward-card__name"><?= e($reward['name']) ?></div>
                                <div class="admin-reward-card__desc">+<?= $reward['value'] ?>% к депозиту</div>
                            </div>
                            <div class="admin-reward-card__meta">
                                <span class="admin-badge admin-badge--neutral">Вес: <?= $reward['weight'] ?></span>
                                <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteDailyBonusReward(<?= $reward['id'] ?>)">🗑</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($caseRewards)): ?>
        <div class="admin-card">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">📦</span>
                    Бесплатные кейсы
                </div>
            </div>
            <div class="admin-card__body">
                <div class="admin-rewards-grid">
                    <?php foreach ($caseRewards as $reward): ?>
                        <div class="admin-reward-card">
                            <div class="admin-reward-card__icon free_case">📦</div>
                            <div class="admin-reward-card__content">
                                <div class="admin-reward-card__name"><?= e($reward['name']) ?></div>
                                <div class="admin-reward-card__desc"><?= $reward['value'] ?> шт. кейсов</div>
                            </div>
                            <div class="admin-reward-card__meta">
                                <span class="admin-badge admin-badge--neutral">Вес: <?= $reward['weight'] ?></span>
                                <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteDailyBonusReward(<?= $reward['id'] ?>)">🗑</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (empty($rewards)): ?>
        <div class="admin-card">
            <div class="admin-empty">
                <div class="admin-empty__icon">🎁</div>
                <div class="admin-empty__title">Наград пока нет</div>
                <div class="admin-empty__text">Загрузите стандартные награды</div>
                <button class="admin-btn admin-btn--primary" style="margin-top:1.5rem;" onclick="loadDefaultRewards()">⚡ Загрузить стандартные</button>
            </div>
        </div>
        <?php endif; ?>
    <?php break;

    case 'battle_pass':
        $seasons = db()->query("SELECT * FROM battle_pass_seasons ORDER BY id DESC")->fetchAll();
        $activeSeason = null;
        foreach ($seasons as $s) { if ($s['is_active']) { $activeSeason = $s; break; } }
        $cases = db()->query("SELECT id, name FROM cases WHERE is_active = 1 ORDER BY name")->fetchAll();
        $bpTasks = [];
        if ($activeSeason) {
            $stmt = db()->prepare("SELECT * FROM battle_pass_tasks WHERE season_id = ? ORDER BY experience_reward DESC");
            $stmt->execute([$activeSeason['id']]);
            $bpTasks = $stmt->fetchAll();
        }
        $bpUsersCount = 0;
        if ($activeSeason) {
            $stmt = db()->prepare("SELECT COUNT(*) FROM user_battle_pass WHERE season_id = ?");
            $stmt->execute([$activeSeason['id']]);
            $bpUsersCount = (int)$stmt->fetchColumn();
        }
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">⚔️ Battle Pass</h1>
                <p class="admin-page-header__subtitle">Управление сезонами и наградами</p>
            </div>
        </div>
        
        <?php if ($activeSeason): ?>
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon purple">🏆</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Активный сезон</div>
                    <div class="admin-stat-card__value" style="font-size:1rem;"><?= e($activeSeason['name']) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon green">👥</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Участников</div>
                    <div class="admin-stat-card__value"><?= $bpUsersCount ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon orange">⭐</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Уровней</div>
                    <div class="admin-stat-card__value"><?= $activeSeason['max_level'] ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-card__icon blue">💰</div>
                <div class="admin-stat-card__content">
                    <div class="admin-stat-card__label">Цена Premium</div>
                    <div class="admin-stat-card__value"><?= number_format($activeSeason['price'], 2) ?>$</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="admin-card">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">📅</span>
                    Сезоны
                </div>
                <span class="admin-badge admin-badge--neutral"><?= count($seasons) ?></span>
            </div>
            <div class="admin-card__body">
                <div class="flex justify-between items-center" style="margin-bottom:1rem;">
                    <span class="text-muted">Все сезоны</span>
                    <button class="admin-btn admin-btn--primary admin-btn--sm" onclick="createBattlePassSeason()">➕ Новый сезон</button>
                </div>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th>Название</th>
                                <th>Цена</th>
                                <th>Уровней</th>
                                <th>Участников</th>
                                <th>Статус</th>
                                <th style="width:140px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($seasons as $s): 
                                $isActive = (bool)$s['is_active'];
                                $stmt = db()->prepare("SELECT COUNT(*) FROM user_battle_pass WHERE season_id = ?");
                                $stmt->execute([$s['id']]);
                                $usersCount = (int)$stmt->fetchColumn();
                            ?>
                                <tr>
                                    <td class="admin-table__cell-muted"><?= $s['id'] ?></td>
                                    <td class="admin-table__cell-primary"><strong><?= e($s['name']) ?></strong></td>
                                    <td class="admin-table__cell-success"><?= number_format($s['price'], 2) ?>$</td>
                                    <td class="admin-table__cell-muted"><?= $s['max_level'] ?></td>
                                    <td class="admin-table__cell-muted"><?= $usersCount ?></td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="admin-badge admin-badge--success">Активен</span>
                                        <?php else: ?>
                                            <span class="admin-badge admin-badge--neutral">Архив</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <?php if (!$isActive): ?>
                                                <button class="admin-btn admin-btn--success admin-btn--sm" onclick="activateSeason(<?= $s['id'] ?>)" title="Активировать">▶️</button>
                                            <?php endif; ?>
                                            <button class="admin-btn admin-btn--ghost admin-btn--sm" onclick="editSeason(<?= $s['id'] ?>, '<?= addslashes($s['name']) ?>', <?= $s['price'] ?>, <?= $s['max_level'] ?>)" title="Редактировать">✏️</button>
                                            <?php if (!$isActive && $usersCount == 0): ?>
                                                <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteSeason(<?= $s['id'] ?>)" title="Удалить">🗑</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if ($activeSeason): ?>
        <div class="admin-card" style="border-left:3px solid var(--accent);">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">🏆</span>
                    <?= e($activeSeason['name']) ?>
                </div>
                <button class="admin-btn admin-btn--ghost admin-btn--sm" onclick="resetBattlePass()">🔄 Сбросить</button>
            </div>
            <div class="admin-card__body">
                <div class="flex gap-2 text-muted" style="font-size:0.85rem;">
                    <span class="text-success"><?= number_format($activeSeason['price'], 2) ?>$</span>
                    <span>•</span>
                    <span><?= $activeSeason['max_level'] ?> уровней</span>
                    <span>•</span>
                    <span><?= $bpUsersCount ?> участников</span>
                </div>
            </div>
        </div>
        
        <?php
            $stmt = db()->prepare("SELECT * FROM battle_pass_rewards WHERE season_id = ? ORDER BY level ASC");
            $stmt->execute([$activeSeason['id']]);
            $bpRewards = $stmt->fetchAll();
        ?>
        <div class="admin-card">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">🎁</span>
                    Награды
                </div>
                <button class="admin-btn admin-btn--primary admin-btn--sm" onclick="addBPRewardModal(<?= $activeSeason['id'] ?>)">➕ Добавить</button>
            </div>
            <div class="admin-card__body">
                <?php if (empty($bpRewards)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty__icon">🎁</div>
                        <div class="admin-empty__title">Наград пока нет</div>
                        <div class="admin-empty__text">Добавьте награды для уровней</div>
                    </div>
                <?php else: ?>
                    <div class="admin-bp-rewards-list">
                        <?php foreach ($bpRewards as $reward): 
                            $caseName = '';
                            if ($reward['reward_type'] === 'case' && $reward['case_id']) {
                                $stmt2 = db()->prepare("SELECT name FROM cases WHERE id = ?");
                                $stmt2->execute([$reward['case_id']]);
                                $caseName = $stmt2->fetchColumn() ?: 'Удалён';
                            }
                            $rewardTooltip = '';
                            if ($reward['reward_type'] === 'balance') {
                                $rewardTooltip = number_format($reward['reward_value'], 2) . ' $ на баланс';
                            } elseif ($reward['reward_type'] === 'case') {
                                $rewardTooltip = 'Билет на кейс: ' . e($caseName);
                            } elseif ($reward['reward_type'] === 'promo') {
                                $rewardTooltip = '+' . number_format($reward['reward_value'], 0) . '% к депозиту';
                            }
                        ?>
                            <div class="admin-bp-reward-item">
                                <div class="admin-bp-reward-item__level">Ур. <?= $reward['level'] ?></div>
                                <div class="admin-bp-reward-item__content">
                                    <div class="admin-bp-reward-item__desc"><?= e($reward['reward_description']) ?></div>
                                    <div class="admin-bp-reward-item__meta">
                                        <?php if ($reward['reward_type'] === 'balance'): ?>
                                            <span class="text-success">💰 <?= $rewardTooltip ?></span>
                                        <?php elseif ($reward['reward_type'] === 'case'): ?>
                                            <span style="color:#74b9ff;">📦 <?= $rewardTooltip ?></span>
                                        <?php elseif ($reward['reward_type'] === 'promo'): ?>
                                            <span class="text-primary">🎟️ <?= $rewardTooltip ?></span>
                                        <?php endif; ?>
                                        <?= $reward['is_premium_only'] ? ' • <span class="admin-badge admin-badge--info">Premium</span>' : '' ?>
                                    </div>
                                </div>
                                <button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteBPReward(<?= $reward['id'] ?>)">🗑</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">✨</span>
                    Быстрые шаблоны заданий
                </div>
            </div>
            <div class="admin-card__body">
                <div class="flex flex-wrap gap-1" style="margin-bottom:1rem;">
                    <button class="admin-btn admin-btn--ghost admin-btn--sm" onclick="addBPTaskTemplate(<?= $activeSeason['id'] ?>, 'case_open')">📦 Открыть кейс</button>
                    <button class="admin-btn admin-btn--ghost admin-btn--sm" onclick="addBPTaskTemplate(<?= $activeSeason['id'] ?>, 'case_open_premium')">💎 Премиум кейс</button>
                    <button class="admin-btn admin-btn--ghost admin-btn--sm" onclick="addBPTaskTemplate(<?= $activeSeason['id'] ?>, 'deposit')">💰 Депозит</button>
                    <button class="admin-btn admin-btn--ghost admin-btn--sm" onclick="addBPTaskTemplate(<?= $activeSeason['id'] ?>, 'referral_invite')">👥 Реферал</button>
                    <button class="admin-btn admin-btn--ghost admin-btn--sm" onclick="addBPTaskTemplate(<?= $activeSeason['id'] ?>, 'daily_login')">📅 Вход</button>
                </div>
                <button class="admin-btn admin-btn--success admin-btn--sm" onclick="addBPTaskTemplates(<?= $activeSeason['id'] ?>)">➕ Добавить все шаблоны</button>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-card__header">
                <div class="admin-card__title">
                    <span class="admin-card__title-icon">📋</span>
                    Задания на опыт
                </div>
                <button class="admin-btn admin-btn--primary admin-btn--sm" onclick="addBPTaskModal(<?= $activeSeason['id'] ?>)">➕ Добавить</button>
            </div>
            <div class="admin-card__body">
                <?php if (empty($bpTasks)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty__icon">📋</div>
                        <div class="admin-empty__title">Заданий пока нет</div>
                        <div class="admin-empty__text">Используйте шаблоны или добавьте вручную</div>
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width:60px;">ID</th>
                                    <th>Описание</th>
                                    <th>Цель</th>
                                    <th>Награда</th>
                                    <th>Повтор</th>
                                    <th style="width:60px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bpTasks as $task): ?>
                                    <tr>
                                        <td class="admin-table__cell-muted"><?= $task['id'] ?></td>
                                        <td class="admin-table__cell-primary"><strong><?= e($task['task_description']) ?></strong></td>
                                        <td class="admin-table__cell-muted"><?= $task['target_value'] ?> раз</td>
                                        <td><span class="admin-badge admin-badge--info">+<?= $task['experience_reward'] ?> XP</span></td>
                                        <td class="admin-table__cell-muted"><?= $task['is_repeatable'] ? '✅ Да' : '❌ Нет' ?></td>
                                        <td><button class="admin-btn admin-btn--danger admin-btn--sm" onclick="deleteBPTask(<?= $task['id'] ?>)">🗑</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="admin-card">
            <div class="admin-empty">
                <div class="admin-empty__icon">⚔️</div>
                <div class="admin-empty__title">Нет активного сезона</div>
                <div class="admin-empty__text">Создайте или активируйте сезон</div>
            </div>
        </div>
        <?php endif; ?>
    <?php break;

    case 'pending_payments':
        $pendingPayments = db()->query("SELECT pp.*, u.username FROM pending_payments pp LEFT JOIN users u ON pp.user_id = u.id ORDER BY pp.created_at DESC LIMIT 50")->fetchAll();
    ?>
        <div class="admin-page-header">
            <div class="admin-page-header__title-group">
                <h1 class="admin-page-header__title">⏳ Ожидающие платежи</h1>
                <p class="admin-page-header__subtitle">Обработка ручных платежей</p>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Пользователь</th>
                            <th>Метод</th>
                            <th>Сумма USD</th>
                            <th>Сумма RUB</th>
                            <th>Статус</th>
                            <th>Дата</th>
                            <th style="width:120px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingPayments)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="admin-empty" style="padding:2rem;">
                                        <div class="admin-empty__icon">✅</div>
                                        <div class="admin-empty__title">Нет ожидающих платежей</div>
                                        <div class="admin-empty__text">Все платежи обработаны</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingPayments as $p): ?>
                                <tr>
                                    <td class="admin-table__cell-muted"><?= $p['id'] ?></td>
                                    <td class="admin-table__cell-primary"><?= e($p['username'] ?? 'Unknown') ?></td>
                                    <td><span class="admin-badge admin-badge--info"><?= e($p['payment_method']) ?></span></td>
                                    <td class="admin-table__cell-success"><?= number_format($p['amount_usd'], 2) ?>$</td>
                                    <td class="admin-table__cell-muted"><?= number_format($p['amount_rub'], 2) ?>₽</td>
                                    <td>
                                        <?php if ($p['status'] === 'completed'): ?>
                                            <span class="admin-badge admin-badge--success">Выполнен</span>
                                        <?php elseif ($p['status'] === 'pending'): ?>
                                            <span class="admin-badge admin-badge--warning">Ожидание</span>
                                        <?php else: ?>
                                            <span class="admin-badge admin-badge--neutral"><?= e($p['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="admin-table__cell-muted"><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></td>
                                    <td>
                                        <?php if ($p['status'] === 'pending'): ?>
                                            <button class="admin-btn admin-btn--success admin-btn--sm" onclick="processPayment('<?= e($p['order_id']) ?>')">✅ Обработать</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php break;

    default: ?>
        <h1 class="page-title">📌 <?= ucfirst($section) ?></h1>
        <p class="page-subtitle">Раздел в разработке</p>
        <div class="card"><p style="color:var(--text-muted);">В разработке</p></div>
    <?php break;
endswitch; ?>
        </div>
    </div>

    <div id="modalContainer"></div>

    <script>
        // Debug: функции загружены
        console.log('Admin JS loaded at', new Date().toISOString());
        
        // Глобальные функции
        window.toggleCategory = function(catId, e) {
            if (e) e.stopPropagation();
            const el = document.getElementById(catId);
            if (!el) return;
            const cat = el.previousElementSibling;
            if (!cat) return;
            
            const isHidden = el.classList.contains('hidden');
            
            if (isHidden) {
                el.classList.remove('hidden');
                el.classList.add('visible');
                cat.classList.remove('collapsed');
            } else {
                el.classList.remove('visible');
                el.classList.add('hidden');
                cat.classList.add('collapsed');
            }
        };

        // Auto-expand current section category on load
        document.addEventListener('DOMContentLoaded', () => {
            const activeLink = document.querySelector('.admin-sidebar__item.active');
            if (activeLink) {
                const cat = activeLink.closest('.admin-sidebar__links');
                if (cat) {
                    cat.classList.remove('hidden');
                    cat.classList.add('visible');
                    const catHeader = cat.previousElementSibling;
                    if (catHeader) catHeader.classList.remove('collapsed');
                }
            }
        });

        window.SITE_URL = '<?= SITE_URL ?>';
        
        window.notify = function(msg, type) {
            type = type || 'success';
            const n = document.createElement('div');
            n.className = 'admin-notify admin-notify--' + type;
            n.textContent = msg;
            document.body.appendChild(n);
            setTimeout(function() { n.remove(); }, 3000);
        };

        window.api = function(action, params) {
            params = params || {};
            var r = fetch('api.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:action,...params})});
            return r.then(function(response) {
                return response.text().then(function(text) {
                    if (!response.ok) {
                        console.error('API error ' + response.status + ':', text);
                        return {success:false, error: 'Server error ' + response.status};
                    }
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        console.error('JSON parse error:', text.substring(0, 200));
                        return {success:false, error: 'Invalid server response'};
                    }
                });
            }).catch(function(e) {
                console.error('Fetch error:', e);
                return {success:false, error:'Network error: ' + e.message};
            });
        };
        
        window.modal = function(html) {
            const container = document.getElementById('modalContainer');
            container.innerHTML = '<div class="admin-modal-overlay active" onclick="if(event.target===this)closeModal()"><div class="admin-modal">'+html+'</div></div>';
        };
        
        window.closeModal = function() { 
            document.getElementById('modalContainer').innerHTML = ''; 
        };
        
        // Закрытие модалок по ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Settings functions
        window.saveSettings = function(keys) {
            var s = {};
            for (var i = 0; i < keys.length; i++) {
                var k = keys[i];
                var el = document.getElementById('setting_' + k);
                if (el) {
                    s[k] = el.value;
                }
            }
            console.log('Saving settings:', s);
            window.api('settings_save', {settings: s}).then(function(d) {
                if (d.success) window.notify('✅ Сохранено', 'success');
                else window.notify('❌ ' + (d.error || 'Ошибка'), 'error');
            });
        };

        window.togglePage = function(key, enabled) {
            var cb = document.activeElement;
            // Если activeElement не чекбокс, ищем ближайший чекбокс
            if (!cb || cb.tagName !== 'INPUT' || cb.type !== 'checkbox') {
                cb = enabled && enabled.target ? enabled.target.closest('input[type="checkbox"]') : null;
            }
            // Fallback: ищем чекбокс в текущем контексте
            if (!cb) {
                try { cb = window.event ? window.event.target : null; } catch(e) {}
            }
            if (!cb) {
                console.warn('togglePage: checkbox not found for key', key);
                return;
            }
            cb.disabled = true;
            window.api('toggle_page', {key:key, enabled:enabled}).then(function(d) { 
                cb.disabled = false; 
                if(!d.success) cb.checked = !enabled; 
                window.notify(d.success ? (enabled?'✅ Вкл':'❌ Выкл') : '❌'+d.error, d.success?'success':'error'); 
            });
        };

        window.toggleAutoRate = function() {
            var cb = document.getElementById('usdRubAuto');
            var checked = cb.checked;
            document.getElementById('manualRateBlock').style.display = checked ? 'none' : 'block';
            window.api('toggle_auto_rate', {value:checked?'1':'0'}).then(function(d) { 
                if(!d.success) { 
                    cb.checked = !checked; 
                    document.getElementById('manualRateBlock').style.display = checked?'none':'block'; 
                    window.notify('❌'+d.error, 'error'); 
                }
            });
        };

        window.saveManualRate = function() {
            var rate = parseFloat(document.getElementById('manualUsdRubRate').value);
            if(isNaN(rate)||rate<=0) return window.notify('❌ Введите курс', 'error');
            window.api('save_usd_rub_rate', {rate:rate}).then(function(d) { 
                if(d.success) {
                    window.notify('✅ Сохранено', 'success');
                    setTimeout(function(){location.reload();}, 800); 
                }
                else window.notify('❌'+d.error, 'error');
            });
        };

        window.updateAutoRate = function() {
            var btn = document.activeElement;
            btn.disabled = true; 
            btn.textContent = '⏳';
            window.api('update_auto_rate').then(function(d) { 
                btn.disabled = false; 
                btn.textContent = '🔄'; 
                if(d.success) {
                    document.getElementById('currentRate').textContent = d.rate + ' ₽';
                    window.notify('✅ Обновлено', 'success');
                    setTimeout(function(){location.reload();},1000);
                } else window.notify('❌'+d.error, 'error');
            });
        };

        window.saveFkSettings = function() {
            window.api('fk_settings_save', {
                fk_merchant_id: document.getElementById('fk_merchant_id').value,
                fk_phrase1: document.getElementById('fk_phrase1').value,
                fk_phrase2: document.getElementById('fk_phrase2').value,
                fk_mode: document.getElementById('fk_mode').value
            }).then(function(d) {
                if (d.success) window.notify('✅ Сохранено', 'success');
                else window.notify('❌'+d.error, 'error');
            });
        };

        window.saveYmSettings = function() {
            window.api('ym_settings_save', {
                ym_shopid: document.getElementById('ym_shopid').value,
                ym_password: document.getElementById('ym_password').value,
                ym_event_url: document.getElementById('ym_event_url').value,
                ym_mode: document.getElementById('ym_mode').value
            }).then(function(d) {
                if (d.success) window.notify('✅ Сохранено', 'success');
                else window.notify('❌'+d.error, 'error');
            });
        };

        window.saveSteamApiKey = function() {
            var key = document.getElementById('setting_steam_api_key').value;
            if(!key.trim()) return window.notify('❌ Введите ключ', 'error');
            window.api('settings_save', {settings:{steam_api_key: key}}).then(function(d) { 
                if (d.success) window.notify('✅ Сохранено', 'success');
                else window.notify('❌'+d.error, 'error');
            });
        };

        // Battle Pass JS
        window.createBattlePassSeason = function() {
            var d = {
                name: document.getElementById('bpSeasonName').value,
                price: document.getElementById('bpSeasonPrice').value,
                max_level: document.getElementById('bpSeasonLevels').value
            };
            if(!d.name) return window.notify('❌ Введите название', 'error');
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Создать сезон? Название: ${d.name}, Цена: $${d.price}, Уровней: ${d.max_level}</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--success" onclick="confirmCreateSeason()">Создать</button>
                </div>
            `);
            window._pendingSeason = d;
        };

        window.confirmCreateSeason = function() {
            var d = window._pendingSeason;
            if (!d) return;
            window.api('battle_pass_season_create', d).then(function(r) {
                if (r.success) {
                    window.notify('✅ Создан', 'success');
                    location.reload();
                } else window.notify('❌'+r.error, 'error');
            }); 
        };

        window.activateSeason = function(id) {
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Активировать сезон? Текущий активный сезон будет деактивирован.</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--success" onclick="confirmActivateSeason(${id})">Активировать</button>
                </div>
            `);
            window._pendingActivateId = id;
        };
        
        window.confirmActivateSeason = function(id) {
            window.api('battle_pass_season_activate', {id:id}).then(function(r) {
                if (r.success) {
                    window.notify('✅ Сезон активирован', 'success');
                    location.reload();
                } else window.notify('❌'+r.error, 'error');
            });
        };
        
        window.editSeason = function(id, name, price, maxLevel) {
            window.modal(`
                <div class="admin-modal__title">✏️ Редактировать сезон</div>
                <div class="admin-form-group"><label>Название</label><input type="text" class="admin-form-control" id="editSeasonName" value="${name}"></div>
                <div class="admin-form-group"><label>Цена Premium ($)</label><input type="number" class="admin-form-control" id="editSeasonPrice" value="${price}" step="0.01"></div>
                <div class="admin-form-group"><label>Уровней</label><input type="number" class="admin-form-control" id="editSeasonLevels" value="${maxLevel}"></div>
                <div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="updateSeason(${id})">Сохранить</button></div>
            `);
        };
        
        window.updateSeason = function(id) {
            var name = document.getElementById('editSeasonName').value;
            var price = parseFloat(document.getElementById('editSeasonPrice').value);
            var maxLevel = parseInt(document.getElementById('editSeasonLevels').value);
            
            if (!name) return window.notify('❌ Введите название', 'error');
            if (price <= 0) return window.notify('❌ Неверная цена', 'error');
            if (maxLevel <= 0) return window.notify('❌ Неверное количество уровней', 'error');
            
            window.api('battle_pass_season_update', {id:id, name:name, price:price, max_level: maxLevel}).then(function(r) {
                if (r.success) {
                    window.notify('✅ Сохранено', 'success');
                    location.reload();
                } else window.notify('❌'+r.error, 'error');
            });
        };
        
        window.deleteSeason = function(id) {
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Удалить сезон? Это действие нельзя отменить.</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmDeleteSeason(${id})">Удалить</button>
                </div>
            `);
            window._pendingDeleteId = id;
        };
        
        window.confirmDeleteSeason = function(id) {
            window.api('battle_pass_season_delete', {id:id}).then(function(r) {
                if (r.success) {
                    window.notify('✅ Удалён', 'success');
                    location.reload();
                } else window.notify('❌'+r.error, 'error');
            });
        };
        
        window.resetBattlePass = function() {
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Сбросить Battle Pass? Все пользователи потеряют прогресс текущего сезона.</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmResetBattlePass()">Сбросить</button>
                </div>
            `);
        };
        
        window.confirmResetBattlePass = function() {
            window.api('battle_pass_season_reset').then(function(d) {
                if (d.success) window.notify('✅ Сброшено', 'success');
                else window.notify('❌'+d.error, 'error');
            });
        };
        
        // Cases
        window.openCaseModal = function() { 
            window.modal('<div class="admin-modal__title">➕ Кейс</div><div class="admin-form-group"><label>Название</label><input type="text" class="admin-form-control" id="caseName"></div><div class="admin-form-group"><label>Цена</label><input type="number" class="admin-form-control" id="casePrice" step="0.01"></div><div class="admin-form-group"><label>Описание</label><textarea class="admin-form-control" id="caseDesc"></textarea></div><div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="addCase()">Добавить</button></div>'); 
        };
        
        window.addCase = function() { 
            window.api('case_add', {name:document.getElementById('caseName').value, price:parseFloat(document.getElementById('casePrice').value), description:document.getElementById('caseDesc').value}).then(function(d) { 
                if (d.success) {
                    window.notify('✅ Создан');
                    location.reload();
                } else window.notify('❌'+d.error, 'error');
            }); 
        };
        
        window.editCase = function(id) { 
            window.api('case_get', {id:id}).then(function(d) { 
                if(!d.success) return; 
                var c=d.case; 
                window.modal('<div class="admin-modal__title">✏️ Кейс</div><div class="admin-form-group"><label>Название</label><input type="text" class="admin-form-control" id="editCaseName" value="'+c.name+'"></div><div class="admin-form-group"><label>Цена</label><input type="number" class="admin-form-control" id="editCasePrice" value="'+c.price+'" step="0.01"></div><div class="admin-form-group"><label>Описание</label><textarea class="admin-form-control" id="editCaseDesc">'+(c.description||'')+'</textarea></div><div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="updateCase('+id+')">Сохранить</button></div>'); 
            }); 
        };
        
        window.updateCase = function(id) { 
            window.api('case_edit', {id:id, name:document.getElementById('editCaseName').value, price:parseFloat(document.getElementById('editCasePrice').value), description:document.getElementById('editCaseDesc').value}).then(function(d) { 
                if (d.success) {
                    window.notify('✅ Обновлён');
                    location.reload();
                } else window.notify('❌'+d.error, 'error');
            }); 
        };
        
        window.deleteCase = function(id) { 
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Удалить кейс? Все предметы кейса будут удалены.</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmDeleteCase(${id})">Удалить</button>
                </div>
            `);
            window._pendingDeleteCaseId = id;
        };
        
        window.confirmDeleteCase = function(id) {
            window.api('case_delete', {id:id}).then(function(d) { 
                if (d.success) location.reload();
                else window.notify('❌'+d.error, 'error');
            });
        };
        
        // Case Items
        window.openSteamSearchModal = function(caseId) {
            window.api('steam_items_search', {q: '', limit: 100}).then(function(d) {
                if (!d.success) return window.notify('❌ Ошибка загрузки', 'error');
                var items = d.items;
                var html = '<div class="admin-modal__title">➕ Добавить из Steam</div>';
                html += '<div class="admin-form-group"><label>Поиск</label><input type="text" class="admin-form-control" id="steamSearch" placeholder="AK-47, AWP, Knife..." onkeyup="searchSteamItems(' + caseId + ')"></div>';
                html += '<div class="admin-form-group"><label>Редкость</label><select class="admin-form-control" id="steamRarity" onchange="searchSteamItems(' + caseId + ')"><option value="">Все</option><option>Consumer Grade</option><option>Industrial Grade</option><option>Milspec</option><option>Restricted</option><option>Classified</option><option>Covert</option><option>Extraordinary</option><option>Contraband</option></select></div>';
                html += '<div style="max-height:400px;overflow-y:auto;">';
                html += '<table style="width:100%;"><thead><tr><th>Картинка</th><th>Название</th><th>Редкость</th><th>Цена</th><th></th></tr></thead><tbody>';
                for (var i = 0; i < items.length; i++) {
                    var item = items[i];
                    html += '<tr>' +
                        '<td><img src="'+(item.icon_url || '')+'" style="width:50px;height:50px;object-fit:contain;background:#000;border-radius:4px;"></td>' +
                        '<td><strong>'+item.market_hash_name+'</strong><br><small style="color:var(--text-muted);">'+item.type+'</small></td>' +
                        '<td><span class="admin-badge admin-badge--info">'+item.rarity+'</span></td>' +
                        '<td>$'+item.price_usd+'</td>' +
                        '<td><button class="admin-btn admin-btn--success btn--sm" onclick="addSteamItem('+caseId+', \''+item.market_hash_name.replace(/'/g, "\\'")+'\', \''+item.rarity+'\', '+item.price_usd+')">➕</button></td>' +
                    '</tr>';
                }
                html += '</tbody></table></div>';
                html += '<div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Закрыть</button></div>';
                window.modal(html);
            });
        };
        
        window.searchSteamItems = function(caseId) {
            var query = document.getElementById('steamSearch').value;
            var rarity = document.getElementById('steamRarity').value;
            window.api('steam_items_search', {q: query, rarity: rarity, limit: 100}).then(function(d) {
                if (!d.success || !d.items.length) {
                    var tbody = document.querySelector('#modalContainer tbody');
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Ничего не найдено</td></tr>';
                    }
                    return;
                }
                var tbody = document.querySelector('#modalContainer tbody');
                if (!tbody) return;
                tbody.innerHTML = '';
                for (var i = 0; i < d.items.length; i++) {
                    var item = d.items[i];
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><img src="'+(item.icon_url || '')+'" style="width:50px;height:50px;object-fit:contain;background:#000;border-radius:4px;"></td><td><strong>'+item.market_hash_name+'</strong><br><small style="color:var(--text-muted);">'+item.type+'</small></td><td><span class="admin-badge admin-badge--info">'+item.rarity+'</span></td><td>$'+item.price_usd+'</td><td><button class="admin-btn admin-btn--success btn--sm" onclick="addSteamItem('+caseId+', \''+item.market_hash_name.replace(/'/g, "\\'")+'\', \''+item.rarity+'\', '+item.price_usd+')">➕</button></td>';
                    tbody.appendChild(tr);
                }
            });
        };
        
        window.addSteamItem = function(caseId, name, rarity, price) {
            window.api('case_item_add', {
                case_id: caseId,
                item_name: name,
                rarity: rarity.toLowerCase().replace(/ /g, '_'),
                price: price,
                weight: 1
            }).then(function(d) {
                if (d.success) {
                    window.notify('✅ Добавлен: ' + name);
                    location.reload();
                } else {
                    window.notify('❌ ' + d.error, 'error');
                }
            });
        };
        
        window.syncSteamItems = function() {
            window.modal('<div class="admin-modal__title">🔄 Синхронизация CS2</div><div id="syncProgress" style="margin:1rem 0;"><p style="color:var(--text-muted);">⏳ Инициализация...</p></div><div id="syncOutput" style="max-height:300px;overflow-y:auto;background:var(--bg-secondary);padding:1rem;border-radius:8px;font-family:monospace;font-size:0.85rem;color:var(--text-secondary);"></div>');
            
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'steam_sync'})
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var progressDiv = document.getElementById('syncProgress');
                var outputDiv = document.getElementById('syncOutput');
                
                if (d.success) {
                    progressDiv.innerHTML = '<p style="color:var(--success);">✅ Синхронизация завершена!</p>';
                    if (d.output) outputDiv.textContent = d.output;
                    setTimeout(function() { closeModal(); location.reload(); }, 2000);
                } else {
                    progressDiv.innerHTML = '<p style="color:var(--danger);">❌ Ошибка: ' + (d.error || 'Unknown') + '</p>';
                    if (d.output) outputDiv.textContent = d.output;
                }
            })
            .catch(function(e) {
                var progressDiv = document.getElementById('syncProgress');
                progressDiv.innerHTML = '<p style="color:var(--danger);">❌ Ошибка сети: ' + e.message + '</p>';
            });
        };
        
        window.updateSteamPrices = function() {
            window.modal(`
                <div class="admin-modal__title">💰 Обновление цен Steam</div>
                <div style="margin:1rem 0;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;">
                        <span style="color:var(--text-muted);">Прогресс:</span>
                        <span id="priceProgressText" style="color:var(--accent);">0%</span>
                    </div>
                    <div style="background:var(--bg-secondary);border-radius:8px;height:20px;overflow:hidden;">
                        <div id="priceProgressBar" style="background:linear-gradient(90deg,var(--accent),#a29bfe);height:100%;width:0%;transition:width 0.3s;"></div>
                    </div>
                </div>
                <div id="priceStatus" style="margin:1rem 0;font-size:0.9rem;color:var(--text-secondary);">⏳ Подготовка...</div>
                <div id="priceOutput" style="max-height:200px;overflow-y:auto;background:var(--bg-secondary);padding:1rem;border-radius:8px;font-family:monospace;font-size:0.8rem;color:var(--text-secondary);"></div>
                <div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()" id="priceCloseBtn" style="display:none;">Закрыть</button></div>
            `);
            
            let totalOffset = 0;
            let totalUpdated = 0;
            let totalNoPrice = 0;
            let totalSkipped = 0;
            let estimatedTotal = 5000; // Примерное количество предметов
            const batchSize = 50; // Маленький батч для веба
            
            function processBatch(offset) {
                fetch('api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'update_steam_prices',
                        batch_size: batchSize,
                        offset: offset
                    })
                })
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        document.getElementById('priceStatus').innerHTML = '<span style="color:var(--danger);">❌ Ошибка: ' + (d.error || 'Unknown') + '</span>';
                        document.getElementById('priceCloseBtn').style.display = 'inline-flex';
                        return;
                    }
                    
                    totalOffset += batchSize;
                    totalUpdated += d.updated || 0;
                    totalNoPrice += d.no_price || 0;
                    totalSkipped += d.skipped || 0;
                    
                    const progress = Math.min(100, Math.round((totalOffset / estimatedTotal) * 100));
                    document.getElementById('priceProgressBar').style.width = progress + '%';
                    document.getElementById('priceProgressText').textContent = progress + '%';
                    document.getElementById('priceStatus').innerHTML = 
                        '📦 Обработано: <strong>' + totalOffset + '</strong> | ' +
                        '✅ Обновлено: <strong>' + totalUpdated + '</strong> | ' +
                        '⚠️ Нет цены: <strong>' + totalNoPrice + '</strong>';
                    
                    // Добавляем логи
                    const outputDiv = document.getElementById('priceOutput');
                    if (d.logs && d.logs.length > 0) {
                        d.logs.forEach(log => {
                            if (log.startsWith('✅')) {
                                outputDiv.innerHTML += '<div style="color:var(--success);">' + log + '</div>';
                            } else if (log.startsWith('⚠️')) {
                                outputDiv.innerHTML += '<div style="color:var(--warning);">' + log + '</div>';
                            } else {
                                outputDiv.innerHTML += '<div>' + log + '</div>';
                            }
                        });
                        outputDiv.scrollTop = outputDiv.scrollHeight;
                    }
                    
                    if (d.finished) {
                        document.getElementById('priceStatus').innerHTML = '<span style="color:var(--success);">✅ Готово!</span>';
                        document.getElementById('priceCloseBtn').style.display = 'inline-flex';
                        setTimeout(() => { location.reload(); }, 2000);
                    } else {
                        // Следующий батч
                        setTimeout(() => processBatch(d.next_offset || totalOffset), 100);
                    }
                })
                .catch(e => {
                    document.getElementById('priceStatus').innerHTML = '<span style="color:var(--danger);">❌ Ошибка сети: ' + e.message + '</span>';
                    document.getElementById('priceCloseBtn').style.display = 'inline-flex';
                });
            }
            
            // Начинаем
            processBatch(0);
        };
        
        window.addItemModal = function(caseId) { 
            window.modal('<div class="admin-modal__title">➕ Предмет</div><div class="admin-form-group"><label>Название</label><input type="text" class="admin-form-control" id="itemName"></div><div class="admin-form-group"><label>Редкость</label><select class="admin-form-control" id="itemRarity"><option>consumer</option><option>industrial</option><option>milspec</option><option>restricted</option><option>classified</option><option>covert</option></select></div><div class="admin-form-group"><label>Цена</label><input type="number" class="admin-form-control" id="itemPrice" step="0.01"></div><div class="admin-form-group"><label>Вес</label><input type="number" class="admin-form-control" id="itemWeight" value="1"></div><div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="addCaseItem('+caseId+')">Добавить</button></div>'); 
        };
        
        window.addCaseItem = function(caseId) { 
            window.api('case_item_add', {case_id:caseId, item_name:document.getElementById('itemName').value, rarity:document.getElementById('itemRarity').value, price:parseFloat(document.getElementById('itemPrice').value), weight:parseInt(document.getElementById('itemWeight').value)}).then(function(d) { 
                if (d.success) {
                    window.notify('✅ Добавлен');
                    location.reload();
                } else window.notify('❌'+d.error, 'error');
            });
        };

        window.deleteCaseItem = function(id, caseId) { 
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Удалить предмет?</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmDeleteCaseItem(${id})">Удалить</button>
                </div>
            `);
            window._pendingDeleteItemId = id;
            window._pendingDeleteItemCaseId = caseId;
        };
        
        window.confirmDeleteCaseItem = function() {
            var id = window._pendingDeleteItemId;
            window.api('case_item_delete', {id:id}).then(function(d) { 
                if (d.success) location.reload();
                else window.notify('❌'+d.error, 'error');
            });
        };
        
        // Categories
        window.addCategoryModal = function() { 
            window.modal('<div class="admin-modal__title">➕ Категория</div><div class="admin-form-group"><label>Название</label><input type="text" class="admin-form-control" id="catName"></div><div class="admin-form-group"><label>Иконка</label><input type="text" class="admin-form-control" id="catIcon" value="📦"></div><div class="admin-form-group"><label>Цвет</label><input type="color" class="admin-form-control" id="catColor" value="#8338ec"></div><div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="addCategory()">Добавить</button></div>'); 
        };
        
        window.addCategory = function() { 
            window.api('category_add', {name:document.getElementById('catName').value, icon:document.getElementById('catIcon').value, color:document.getElementById('catColor').value}).then(function(d) { 
                if (d.success) {
                    window.notify('✅ Создана');
                    location.reload();
                } else window.notify('❌'+d.error, 'error');
            });
        };
        
        window.deleteCategory = function(id) { 
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Удалить категорию?</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmDeleteCategory(${id})">Удалить</button>
                </div>
            `);
            window._pendingDeleteCategoryId = id;
        };

        window.confirmDeleteCategory = function(id) {
            window.api('category_delete', {id:id}).then(function(d) { 
                if (d.success) location.reload();
                else window.notify('❌'+d.error, 'error');
            });
        };

        // Free Cases
        window.addFreeCaseModal = function() { 
            window.modal('<div class="admin-modal__title">➕ Бесплатный кейс</div><div class="admin-form-group"><label>Название</label><input type="text" class="admin-form-control" id="fcName"></div><div class="admin-form-group"><label>Мин. депозит</label><input type="number" class="admin-form-control" id="fcMinDeposit" step="0.01"></div><div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="addFreeCase()">Добавить</button></div>'); 
        };

        window.addFreeCase = function() { 
            window.api('add_free_case', {name:document.getElementById('fcName').value, min_deposit:parseFloat(document.getElementById('fcMinDeposit').value)}).then(function(d) { 
                if (d.success) {
                    window.notify('✅ Создан');
                    location.reload();
                } else window.notify('❌'+d.error, 'error');
            });
        };

        window.toggleFreeCase = function(id, active) { 
            window.api('toggle_free_case', {id:id, is_active:active}).then(function(d) { 
                if (d.success) location.reload();
                else window.notify('❌'+d.error, 'error');
            });
        };

        window.deleteFreeCase = function(id) { 
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Удалить бесплатный кейс?</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmDeleteFreeCase(${id})">Удалить</button>
                </div>
            `);
            window._pendingDeleteFreeCaseId = id;
        };

        window.confirmDeleteFreeCase = function(id) {
            window.api('delete_free_case', {id:id}).then(function(d) { 
                if (d.success) location.reload();
                else window.notify('❌'+d.error, 'error');
            });
        };
        
        // Free Case Items Management
        window.addFreeCaseItemModal = function(caseId) {
            window.modal(`
                <div class="admin-modal__title">➕ Добавить предмет</div>
                <div class="admin-form-group"><label>Название предмета</label><input type="text" class="admin-form-control" id="fcItemName" placeholder="AK-47 | Slate"></div>
                <div class="admin-form-group"><label>URL изображения</label><input type="text" class="admin-form-control" id="fcItemImage" placeholder="https://community.cloudflare.steamstatic.com/..."></div>
                <div class="admin-form-group"><label>Редкость</label>
                    <select class="admin-form-control" id="fcItemRarity">
                        <option value="consumer">Shabby (Ширпотреб)</option>
                        <option value="industrial">Workshop (Промышленное)</option>
                        <option value="milspec" selected>Military (Армейское)</option>
                        <option value="restricted">Restricted (Запрещённое)</option>
                        <option value="classified">Classified (Засекреченное)</option>
                        <option value="covert">Covert (Тайное)</option>
                        <option value="extraordinary">Extraordinary (Внеобычное)</option>
                    </select>
                </div>
                <div class="admin-form-group"><label>Цена ($)</label><input type="number" class="admin-form-control" id="fcItemPrice" step="0.01" value="0.01"></div>
                <div class="admin-form-group"><label>Вес (вероятность)</label><input type="number" class="admin-form-control" id="fcItemWeight" value="1" min="1"><small style="color:var(--text-muted);">Больше = чаще выпадает</small></div>
                <input type="hidden" id="fcItemCaseId" value="${caseId}">
                <div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="addFreeCaseItem()">Добавить</button></div>
            `);
        };

        window.addFreeCaseItem = function() {
            var caseId = parseInt(document.getElementById('fcItemCaseId').value);
            var name = document.getElementById('fcItemName').value.trim();
            var image = document.getElementById('fcItemImage').value.trim();
            var rarity = document.getElementById('fcItemRarity').value;
            var price = parseFloat(document.getElementById('fcItemPrice').value) || 0;
            var weight = parseInt(document.getElementById('fcItemWeight').value) || 1;
            
            if (!caseId || !name) return window.notify('❌ Заполните название', 'error');
            
            window.api('add_free_case_item', {
                case_id: caseId,
                item_name: name,
                item_image: image,
                rarity: rarity,
                price: price,
                weight: weight
            }).then(function(d) {
                if (d.success) {
                    window.notify('✅ Предмет добавлен', 'success');
                    closeModal();
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ '+d.error, 'error');
                }
            });
        };

        // Открытие модального окна поиска Steam предметов для бесплатных кейсов
        window.openFreeCaseSteamSearchModal = function(caseId) {
            // Сначала проверим несколько предметов
            window.api('steam_items_search', {q: '', limit: 10}).then(function(d) {
                if (!d.success) return window.notify('❌ Ошибка загрузки', 'error');
                
                console.log('=== ПРОВЕРКА ЦЕН В БД ===');
                d.items.forEach(function(item, i) {
                    console.log(i + ': ' + item.market_hash_name + ' | price_usd=' + item.price_usd + ' | type=' + typeof item.price_usd);
                });
                console.log('========================');
            });
            
            window.api('steam_items_search', {q: '', limit: 100}).then(function(d) {
                if (!d.success) return window.notify('❌ Ошибка загрузки', 'error');
                var items = d.items || [];
                
                if (items.length === 0) {
                    var html = '<div class="admin-modal__title">🎮 Добавить из Steam</div>';
                    html += '<div style="padding:2rem;text-align:center;color:var(--text-muted);">';
                    html += '<div style="font-size:3rem;margin-bottom:1rem;">⚠️</div>';
                    html += '<p style="font-size:1.1rem;margin-bottom:0.5rem;">База Steam предметов пуста</p>';
                    html += '<p style="font-size:0.9rem;">Сначала выполните синхронизацию предметов из Steam</p>';
                    html += '<button class="admin-btn admin-btn--success" onclick="syncSteamItems()" style="margin-top:1rem;">🔄 Синхронизировать</button>';
                    html += '</div>';
                    window.modal(html);
                    return;
                }
                
                var html = '<div class="admin-modal__title">🎮 Добавить из Steam</div>';
                
                // Быстрые кнопки добавления по цене
                html += '<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">';
                html += '<button class="admin-btn admin-btn--sm btn--success" onclick="addFreeCaseItemsByPriceRange(' + caseId + ', 0, 1)">➕ $0-1</button>';
                html += '<button class="admin-btn admin-btn--sm btn--success" onclick="addFreeCaseItemsByPriceRange(' + caseId + ', 1.01, 2)">➕ $1-2</button>';
                html += '<button class="admin-btn admin-btn--sm btn--success" onclick="addFreeCaseItemsByPriceRange(' + caseId + ', 2.01, 3)">➕ $2-3</button>';
                html += '<button class="admin-btn admin-btn--sm btn--success" onclick="addFreeCaseItemsByPriceRange(' + caseId + ', 3.01, 5)">➕ $3-5</button>';
                html += '<button class="admin-btn admin-btn--sm btn--success" onclick="addFreeCaseItemsByPriceRange(' + caseId + ', 5.01, 7)">➕ $5-7</button>';
                html += '<button class="admin-btn admin-btn--sm btn--success" onclick="addFreeCaseItemsByPriceRange(' + caseId + ', 7.01, 10)">➕ $7-10</button>';
                html += '</div>';
                
                html += '<div class="admin-form-group"><label>Поиск</label><input type="text" class="admin-form-control" id="freeCaseSteamSearch" placeholder="AK-47, AWP, Knife..." onkeyup="searchFreeCaseSteamItems(' + caseId + ')"></div>';
                html += '<div class="admin-form-group"><label>Редкость</label><select class="admin-form-control" id="freeCaseSteamRarity" onchange="searchFreeCaseSteamItems(' + caseId + ')"><option value="">Все</option><option>Consumer Grade</option><option>Industrial Grade</option><option>Mil-Spec</option><option>Restricted</option><option>Classified</option><option>Covert</option><option>Extraordinary</option><option>Contraband</option></select></div>';
                html += '<div style="max-height:400px;overflow-y:auto;">';
                html += '<table style="width:100%;"><thead><tr><th>Картинка</th><th>Название</th><th>Редкость</th><th>Цена</th><th>Вес</th><th></th></tr></thead><tbody>';
                for (var i = 0; i < items.length; i++) {
                    var item = items[i];
                    html += '<tr>' +
                        '<td><img src="'+(item.icon_url || '')+'" style="width:50px;height:50px;object-fit:contain;background:#000;border-radius:4px;"></td>' +
                        '<td><strong>'+item.market_hash_name+'</strong><br><small style="color:var(--text-muted);">'+item.type+'</small></td>' +
                        '<td><span class="admin-badge admin-badge--info">'+item.rarity+'</span></td>' +
                        '<td>$'+item.price_usd+'</td>' +
                        '<td><input type="number" id="weight_'+item.id+'" value="1" min="1" style="width:60px;padding:0.25rem;border-radius:4px;border:1px solid var(--border);background:var(--bg-secondary);color:var(--text-primary);"></td>' +
                        '<td><button class="admin-btn admin-btn--success btn--sm" onclick="addFreeCaseItemFromSteam('+caseId+', '+item.id+', \''+item.market_hash_name.replace(/'/g, "\\'")+'\', \''+item.rarity+'\', '+item.price_usd+')">➕</button></td>' +
                    '</tr>';
                }
                html += '</tbody></table></div>';
                html += '<div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Закрыть</button></div>';
                window.modal(html);
            });
        };

        // Массовое добавление предметов по диапазону цен
        window.addFreeCaseItemsByPriceRange = function(caseId, minPrice, maxPrice) {
            window.modal('<div class="admin-modal__title">⏳ Добавление предметов...</div><div id="bulkAddProgress" style="margin:1rem 0;"><p style="color:var(--text-muted);">⏳ Загрузка предметов...</p></div><div id="bulkAddOutput" style="max-height:300px;overflow-y:auto;background:var(--bg-secondary);padding:1rem;border-radius:8px;font-family:monospace;font-size:0.85rem;color:var(--text-secondary);"></div>');
            
            // Сначала получаем все предметы
            window.api('steam_items_search', {q: '', limit: 10000}).then(function(d) {
                if (!d.success) {
                    document.getElementById('bulkAddProgress').innerHTML = '<p style="color:var(--danger);">❌ Ошибка загрузки предметов</p>';
                    return;
                }
                
                console.log('Всего загружено:', d.items.length);
                
                // Фильтруем по цене с корректным парсингом
                var itemsToAdd = d.items.filter(function(item) {
                    // Гарантируем числовой тип
                    var price = Number(String(item.price_usd).replace(',', '.')) || 0;
                    var inRange = price >= minPrice && price <= maxPrice;
                    if (inRange) {
                        console.log('Найден предмет:', item.market_hash_name, '$' + price);
                    }
                    return inRange;
                });
                
                console.log('Диапазон:', minPrice, '-', maxPrice);
                console.log('Найдено в диапазоне:', itemsToAdd.length);
                
                if (itemsToAdd.length === 0) {
                    document.getElementById('bulkAddProgress').innerHTML = '<p style="color:var(--warning);">⚠️ Нет предметов в диапазоне $' + minPrice + ' - $' + maxPrice + '</p><p style="color:var(--text-muted);font-size:0.85rem;">Всего загружено: ' + d.items.length + '</p>';
                    return;
                }
                
                var progressDiv = document.getElementById('bulkAddProgress');
                var outputDiv = document.getElementById('bulkAddOutput');
                var added = 0;
                var skipped = 0;
                var errors = 0;
                
                // Функция для добавления одного предмета
                function addItem(index) {
                    if (index >= itemsToAdd.length) {
                        progressDiv.innerHTML = '<p style="color:var(--success);">✅ Готово!</p>';
                        outputDiv.innerHTML += '<div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);"><strong>Итог:</strong><br>✅ Добавлено: ' + added + '<br>⚠️ Пропущено: ' + skipped + '<br>❌ Ошибок: ' + errors + '</div>';
                        setTimeout(function() { closeModal(); location.reload(); }, 1500);
                        return;
                    }
                    
                    var item = itemsToAdd[index];
                    progressDiv.innerHTML = '<p style="color:var(--text-primary);">📦 Добавляем: ' + item.market_hash_name + ' ($' + item.price_usd + ') [' + (index + 1) + '/' + itemsToAdd.length + ']</p>';
                    
                    window.api('free_case_item_add_from_steam', {
                        case_id: caseId,
                        steam_item_id: item.id,
                        weight: 1
                    }).then(function(result) {
                        if (result.success) {
                            added++;
                            outputDiv.innerHTML += '<div style="color:var(--success);">✅ + ' + item.market_hash_name + '</div>';
                        } else {
                            if (result.error && result.error.includes('уже добавлен')) {
                                skipped++;
                                outputDiv.innerHTML += '<div style="color:var(--warning);">⚠️ Уже есть: ' + item.market_hash_name + '</div>';
                            } else {
                                errors++;
                                outputDiv.innerHTML += '<div style="color:var(--danger);">❌ Ошибка: ' + item.market_hash_name + ' (' + (result.error || 'Unknown') + ')</div>';
                            }
                        }
                        addItem(index + 1);
                    });
                }
                
                // Начинаем добавление
                addItem(0);
            });
        };

        // Поиск предметов для бесплатных кейсов
        window.searchFreeCaseSteamItems = function(caseId) {
            var query = document.getElementById('freeCaseSteamSearch').value;
            var rarity = document.getElementById('freeCaseSteamRarity').value;
            window.api('steam_items_search', {q: query, rarity: rarity, limit: 100}).then(function(d) {
                if (!d.success || !d.items.length) {
                    var tbody = document.querySelector('#modalContainer tbody');
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Ничего не найдено</td></tr>';
                    }
                    return;
                }
                var tbody = document.querySelector('#modalContainer tbody');
                if (!tbody) return;
                tbody.innerHTML = '';
                for (var i = 0; i < d.items.length; i++) {
                    var item = d.items[i];
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><img src="'+(item.icon_url || '')+'" style="width:50px;height:50px;object-fit:contain;background:#000;border-radius:4px;"></td><td><strong>'+item.market_hash_name+'</strong><br><small style="color:var(--text-muted);">'+item.type+'</small></td><td><span class="admin-badge admin-badge--info">'+item.rarity+'</span></td><td>$'+item.price_usd+'</td><td><input type="number" id="weight_'+item.id+'" value="1" min="1" style="width:60px;padding:0.25rem;border-radius:4px;border:1px solid var(--border);background:var(--bg-secondary);color:var(--text-primary);"></td><td><button class="admin-btn admin-btn--success btn--sm" onclick="addFreeCaseItemFromSteam('+caseId+', '+item.id+', \''+item.market_hash_name.replace(/'/g, "\\'")+'\', \''+item.rarity+'\', '+item.price_usd+')">➕</button></td>';
                    tbody.appendChild(tr);
                }
            });
        };

        // Добавление предмета из Steam в бесплатный кейс
        window.addFreeCaseItemFromSteam = function(caseId, steamItemId, name, rarity, price) {
            var weightInput = document.getElementById('weight_' + steamItemId);
            var weight = weightInput ? parseInt(weightInput.value) || 1 : 1;
            
            window.api('free_case_item_add_from_steam', {
                case_id: caseId,
                steam_item_id: steamItemId,
                weight: weight
            }).then(function(d) {
                if (d.success) {
                    window.notify('✅ Добавлен: ' + name, 'success');
                    closeModal();
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ ' + d.error, 'error');
                }
            });
        };

        window.editFreeCaseItem = function(itemId, caseId) {
            // Загружаем текущие данные предмета
            window.api('free_case_items_list', {case_id: caseId}).then(function(d) {
                var item = null;
                for (var i = 0; i < d.items.length; i++) {
                    if (d.items[i].id == itemId) {
                        item = d.items[i];
                        break;
                    }
                }
                if (!item) return window.notify('❌ Предмет не найден', 'error');
                
                window.modal(`
                    <div class="admin-modal__title">✏️ Редактировать предмет</div>
                    <div class="admin-form-group"><label>Название</label><input type="text" class="admin-form-control" id="editFcItemName" value="${item.item_name.replace(/"/g, '&quot;')}" disabled></div>
                    <div class="admin-form-group"><label>URL изображения</label><input type="text" class="admin-form-control" id="editFcItemImage" value="${(item.item_image || '').replace(/"/g, '&quot;')}" placeholder="https://community.cloudflare.steamstatic.com/..."></div>
                    <div class="admin-form-group"><label>Редкость</label>
                        <select class="admin-form-control" id="editFcItemRarity">
                            <option value="consumer" ${item.rarity === 'consumer' ? 'selected' : ''}>Shabby (Ширпотреб)</option>
                            <option value="industrial" ${item.rarity === 'industrial' ? 'selected' : ''}>Workshop (Промышленное)</option>
                            <option value="milspec" ${item.rarity === 'milspec' ? 'selected' : ''}>Military (Армейское)</option>
                            <option value="restricted" ${item.rarity === 'restricted' ? 'selected' : ''}>Restricted (Запрещённое)</option>
                            <option value="classified" ${item.rarity === 'classified' ? 'selected' : ''}>Classified (Засекреченное)</option>
                            <option value="covert" ${item.rarity === 'covert' ? 'selected' : ''}>Covert (Тайное)</option>
                            <option value="extraordinary" ${item.rarity === 'extraordinary' ? 'selected' : ''}>Extraordinary (Внеобычное)</option>
                        </select>
                    </div>
                    <div class="admin-form-group"><label>Цена ($)</label><input type="number" class="admin-form-control" id="editFcItemPrice" step="0.01" value="${item.price}"></div>
                    <div class="admin-form-group"><label>Вес (вероятность)</label><input type="number" class="admin-form-control" id="editFcItemWeight" value="${item.weight}" min="1"></div>
                    <div class="admin-form-group"><label>Активен</label><select class="admin-form-control" id="editFcItemActive"><option value="1" ${item.is_active ? 'selected' : ''}>Да</option><option value="0" ${!item.is_active ? 'selected' : ''}>Нет</option></select></div>
                    <input type="hidden" id="editFcItemId" value="${itemId}">
                    <input type="hidden" id="editFcItemCaseId" value="${caseId}">
                    <div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="updateFreeCaseItem()">Сохранить</button></div>
                `);
            });
        };

        window.updateFreeCaseItem = function() {
            var itemId = parseInt(document.getElementById('editFcItemId').value);
            var image = document.getElementById('editFcItemImage').value.trim();
            var rarity = document.getElementById('editFcItemRarity').value;
            var price = parseFloat(document.getElementById('editFcItemPrice').value) || 0;
            var weight = parseInt(document.getElementById('editFcItemWeight').value) || 1;
            var isActive = parseInt(document.getElementById('editFcItemActive').value);
            
            window.api('update_free_case_item', {
                id: itemId,
                item_image: image,
                rarity: rarity,
                price: price,
                weight: weight,
                is_active: isActive
            }).then(function(d) { 
                if (d.success) {
                    window.notify('✅ Предмет обновлён', 'success');
                    closeModal();
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ '+d.error, 'error');
                }
            });
        };

        window.deleteFreeCaseItem = function(itemId, caseId) {
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Удалить предмет из кейса?</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmDeleteFreeCaseItem(${itemId}, ${caseId})">Удалить</button>
                </div>
            `);
        };
        
        window.confirmDeleteFreeCaseItem = function(itemId, caseId) {
            window.api('delete_free_case_item', {id: itemId}).then(function(d) { 
                if (d.success) {
                    window.notify('✅ Предмет удалён', 'success');
                    closeModal();
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ '+d.error, 'error');
                }
            });
        };
        
        // Users
        window.searchUsers = function() { 
            window.location.href = '?section=users' + (document.getElementById('userSearch').value ? '&search='+encodeURIComponent(document.getElementById('userSearch').value) : ''); 
        };
        
        window.editUser = function(id) { 
            window.modal('<div class="admin-modal__title">✏️ Пользователь</div><div class="admin-form-group"><label>Баланс</label><input type="number" class="admin-form-control" id="editUserBalance" step="0.01"></div><div class="admin-form-group"><label>Роль</label><select class="admin-form-control" id="editUserRole"><option value="user">User</option><option value="admin">Admin</option></select></div><div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="updateUser('+id+')">Сохранить</button></div>'); 
        };
        
        window.updateUser = function(id) { 
            window.api('user_edit', {id:id, balance:parseFloat(document.getElementById('editUserBalance').value), role:document.getElementById('editUserRole').value}).then(function(d) { 
                if (d.success) {
                    window.notify('✅ Обновлён');
                    location.reload();
                } else window.notify('❌'+d.error, 'error');
            }); 
        };
        
        window.deleteUser = function(id) { 
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Удалить пользователя?</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmDeleteUser(${id})">Удалить</button>
                </div>
            `);
            window._pendingDeleteUserId = id;
        };
        
        window.confirmDeleteUser = function(id) {
            window.api('user_delete', {id:id}).then(function(d) { 
                if (d.success) location.reload();
                else window.notify('❌'+d.error, 'error');
            });
        };
        
        // Transactions
        window.loadTransactions = function() { 
            window.location.search = 'page=1&' + (document.getElementById('transType').value ? 'type='+document.getElementById('transType').value : ''); 
        };
        
        // Battle Pass
        window.addBPRewardModal = function(seasonId) {
            var casesList = <?= isset($cases) && !empty($cases) ? json_encode($cases) : '[]' ?>;
            var casesHtml = '<option value="">— Нет —</option>';
            for (var i = 0; i < casesList.length; i++) {
                var c = casesList[i];
                casesHtml += '<option value="'+c.id+'">'+c.name.replace(/"/g, '&quot;')+'</option>';
            }
            
            window.api('battle_pass_rewards_list', {season_id: seasonId}).then(function(d) {
                var maxLevel = 0;
                var rewards = d.rewards || [];
                for (var i = 0; i < rewards.length; i++) {
                    if (rewards[i].level > maxLevel) maxLevel = rewards[i].level;
                }
                var nextLevel = maxLevel + 1;
                
                window.modal(`
                    <div class="admin-modal__title">➕ Награда (ур. ${nextLevel})</div>
                    <div class="admin-form-group"><label>Уровень</label><input type="number" class="admin-form-control" id="bpRewardLevel" value="${nextLevel}"></div>
                    <div class="admin-form-group"><label>Тип</label>
                        <select class="admin-form-control" id="bpRewardType" onchange="toggleBpCaseField()">
                            <option value="balance">💰 Баланс</option>
                            <option value="case">📦 Кейс</option>
                            <option value="promo">🎁 Промокод (+% к пополнению)</option>
                        </select>
                    </div>
                    <div class="admin-form-group" id="bpCaseField" style="display:none;"><label>Выбери кейс</label>
                        <select class="admin-form-control" id="bpRewardCaseId">${casesHtml}</select>
                    </div>
                    <div class="admin-form-group"><label>Значение (fallback)</label><input type="text" class="admin-form-control" id="bpRewardValue" placeholder="0.00"></div>
                    <div class="admin-form-group"><label>Описание</label><input type="text" class="admin-form-control" id="bpRewardDesc" placeholder="Награда за уровень"></div>
                    <div class="admin-form-group"><label>Premium</label><input type="checkbox" id="bpRewardPremium"></div>
                    <div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="addBPReward(${seasonId})">Добавить</button></div>
                `);
            });
        };
        
        window.toggleBpCaseField = function() {
            var type = document.getElementById('bpRewardType').value;
            var caseField = document.getElementById('bpCaseField');
            var valueField = document.getElementById('bpRewardValue');
            var valueLabel = valueField.previousElementSibling;
            
            if (type === 'case') {
                caseField.style.display = 'block';
                valueLabel.textContent = 'Fallback (если кейс удалён)';
                valueField.placeholder = '0.00';
            } else if (type === 'promo') {
                caseField.style.display = 'none';
                valueLabel.textContent = 'Процент бонуса (%)';
                valueField.placeholder = '5';
            } else {
                caseField.style.display = 'none';
                valueLabel.textContent = 'Значение';
                valueField.placeholder = '0.00';
            }
        };
        
        window.addBPReward = function(seasonId) {
            var level = parseInt(document.getElementById('bpRewardLevel').value);
            var type = document.getElementById('bpRewardType').value;
            var value = document.getElementById('bpRewardValue').value;
            var desc = document.getElementById('bpRewardDesc').value;
            var premium = document.getElementById('bpRewardPremium').checked ? 1 : 0;
            var caseId = type === 'case' ? parseInt(document.getElementById('bpRewardCaseId').value) : 0;
            
            if (!level) return window.notify('❌ Укажите уровень', 'error');
            
            var params = { season_id: seasonId, level: level, reward_type: type, reward_value: value, reward_description: desc, is_premium_only: premium };
            if (type === 'case' && caseId) params.case_id = caseId;
            
            window.api('battle_pass_reward_add', params).then(function(d) { 
                if (d.success) {
                    window.notify('✅ Добавлена', 'success');
                    location.reload();
                } else window.notify('❌'+d.error, 'error');
            });
        };
        
        window.deleteBPReward = function(id) { 
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Удалить награду?</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmDeleteBPReward(${id})">Удалить</button>
                </div>
            `);
            window._pendingDeleteBPRewardId = id;
        };
        
        window.confirmDeleteBPReward = function(id) {
            window.api('battle_pass_reward_delete', {id:id}).then(function(d) { 
                if (d.success) location.reload();
                else window.notify('❌'+d.error, 'error');
            });
        };
        
        // Battle Pass Tasks
        window.addBPTaskModal = function(seasonId) {
            window.modal(`
                <div class="admin-modal__title">📋 Добавить задание</div>
                <div class="admin-form-group"><label>Описание</label><input type="text" class="admin-form-control" id="bpTaskDesc" placeholder="Например: Открыть кейс"></div>
                <div class="admin-form-group"><label>Цель (раз)</label><input type="number" class="admin-form-control" id="bpTaskTarget" value="1" min="1"></div>
                <div class="admin-form-group"><label>Награда XP</label><input type="number" class="admin-form-control" id="bpTaskXp" value="100" min="10" step="10"></div>
                <div class="admin-form-group"><label>Повторяемое</label><select class="admin-form-control" id="bpTaskRepeat"><option value="0">Нет</option><option value="1">Да</option></select></div>
                <div class="admin-form-group"><label>Тип задания</label>
                    <select class="admin-form-control" id="bpTaskType">
                        <option value="case_open">Открытие кейса</option>
                        <option value="case_open_premium">Открытие премиум кейса</option>
                        <option value="deposit">Депозит</option>
                        <option value="referral_invite">Приглашение реферала</option>
                        <option value="daily_login">Ежедневный вход</option>
                    </select>
                </div>
                <div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="addBPTask(${seasonId})">Добавить</button></div>
            `);
        };
        
        window.addBPTask = function(seasonId) {
            var desc = document.getElementById('bpTaskDesc').value;
            var target = parseInt(document.getElementById('bpTaskTarget').value);
            var xp = parseInt(document.getElementById('bpTaskXp').value);
            var repeat = parseInt(document.getElementById('bpTaskRepeat').value);
            var type = document.getElementById('bpTaskType').value;
            
            if (!desc || !target || !xp) return window.notify('❌ Заполните все поля', 'error');
            
            window.api('battle_pass_task_add', {
                season_id: seasonId,
                task_description: desc,
                target_value: target,
                experience_reward: xp,
                is_repeatable: repeat,
                task_type: type
            }).then(function(d) { 
                if (d.success) {
                    window.notify('✅ Задание добавлено', 'success');
                    location.reload();
                } else window.notify('❌'+d.error, 'error');
            });
        };

        window.addBPTaskTemplate = function(seasonId, type) {
            var templates = {
                case_open: {task_description: 'Открыть кейс', target_value: 1, experience_reward: 100, is_repeatable: 1, task_type: 'case_open'},
                case_open_premium: {task_description: 'Открыть премиум кейс', target_value: 1, experience_reward: 150, is_repeatable: 1, task_type: 'case_open_premium'},
                deposit: {task_description: 'Пополнить баланс', target_value: 1, experience_reward: 120, is_repeatable: 1, task_type: 'deposit'},
                referral_invite: {task_description: 'Пригласить реферала', target_value: 1, experience_reward: 200, is_repeatable: 1, task_type: 'referral_invite'},
                daily_login: {task_description: 'Войти в игру сегодня', target_value: 1, experience_reward: 80, is_repeatable: 1, task_type: 'daily_login'}
            };
            var template = templates[type];
            if (!template) return window.notify('❌ Неизвестный шаблон', 'error');
            template.season_id = seasonId;
            window.api('battle_pass_task_add', template).then(function(d) {
                if (d.success) {
                    window.notify('✅ Шаблон добавлен', 'success');
                    location.reload();
                } else {
                    window.notify('❌ ' + d.error, 'error');
                }
            });
        };

        window.addBPTaskTemplates = function(seasonId) {
            var tasks = [
                {task_description: 'Открыть кейс', target_value: 1, experience_reward: 100, is_repeatable: 1, task_type: 'case_open'},
                {task_description: 'Открыть премиум кейс', target_value: 1, experience_reward: 150, is_repeatable: 1, task_type: 'case_open_premium'},
                {task_description: 'Пополнить баланс', target_value: 1, experience_reward: 120, is_repeatable: 1, task_type: 'deposit'},
                {task_description: 'Пригласить реферала', target_value: 1, experience_reward: 200, is_repeatable: 1, task_type: 'referral_invite'},
                {task_description: 'Войти в игру сегодня', target_value: 1, experience_reward: 80, is_repeatable: 1, task_type: 'daily_login'}
            ];

            window.modal(`
                <div class="admin-modal__title">Добавить шаблонные задания</div>
                <p>Добавить сразу набор стандартных заданий для сезона?</p>
                <ul style="margin:0.75rem 0 1rem;padding-left:1.25rem;color:var(--text-secondary);">
                    <li>Открыть кейс</li>
                    <li>Открыть премиум кейс</li>
                    <li>Пополнить баланс</li>
                    <li>Пригласить реферала</li>
                    <li>Ежедневный вход</li>
                </ul>
                <div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="confirmAddBPTaskTemplates(${seasonId})">Добавить все</button></div>
            `);
            window._bpTaskTemplates = {season_id: seasonId, tasks: tasks};
        };

        window.confirmAddBPTaskTemplates = function(seasonId) {
            if (!window._bpTaskTemplates || window._bpTaskTemplates.season_id !== seasonId) return;
            window.api('battle_pass_task_bulk_add', window._bpTaskTemplates).then(function(d) {
                if (d.success) {
                    window.notify('✅ Шаблонные задания добавлены', 'success');
                    closeModal();
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ ' + d.error, 'error');
                }
            });
        };

        window.deleteBPTask = function(id) { 
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Удалить задание?</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmDeleteBPTask(${id})">Удалить</button>
                </div>
            `);
            window._pendingDeleteBPTaskId = id;
        };
        
        window.confirmDeleteBPTask = function(id) {
            window.api('battle_pass_task_delete', {id:id}).then(function(d) { 
                if (d.success) location.reload();
                else window.notify('❌'+d.error, 'error');
            });
        };
        
        // Проверка: все функции определены
        console.log('togglePage:', typeof window.togglePage);
        console.log('saveSettings:', typeof window.saveSettings);
        console.log('toggleAutoRate:', typeof window.toggleAutoRate);
        
        // Pending Payments
        window.processPayment = function(orderId) {
            window.modal(`
                <div class="admin-modal__title">Подтверждение</div>
                <p>Обработать платёж order_id: <code>${orderId}</code>?</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--success" onclick="confirmProcessPayment('${orderId.replace(/'/g, "\\'")}')">Обработать</button>
                </div>
            `);
        };
        
        window.confirmProcessPayment = function(orderId) {
            window.api('pending_payment_process', {order_id: orderId}).then(function(d) {
                if (d.success) {
                    window.notify('✅ Платёж обработан', 'success');
                    closeModal();
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ ' + d.error, 'error');
                }
            });
        };
        
        // Daily Bonus
        window.saveDailyBonus = function() {
            window.api('daily_bonus_save', {
                name: document.getElementById('bonusName').value,
                cooldown_hours: parseInt(document.getElementById('bonusCooldown').value),
                is_active: parseInt(document.getElementById('bonusActive').value)
            }).then(function(d) {
                if (d.success) {
                    window.notify('✅ Сохранено', 'success');
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ ' + d.error, 'error');
                }
            });
        };

        window.loadDefaultRewards = function() {
            window.modal(`
                <div class="admin-modal__title">⚡ Загрузить стандартные награды</div>
                <p style="color:var(--text-secondary); margin-bottom:1rem;">
                    Будет добавлено <strong>18 сбалансированных наград</strong> для ежедневного бонуса:<br>
                    • 11 наград баланса (от 0.10$ до 10.00$)<br>
                    • 5 промокодов (от 5% до 25% к депозиту)<br>
                    • 3 бесплатных кейса (1, 3, 5 шт)<br><br>
                    <span style="color:var(--accent);">⚠️ Текущие награды будут удалены!</span>
                </p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--success" onclick="confirmLoadDefaultRewards()">Загрузить</button>
                </div>
            `);
        };

        window.confirmLoadDefaultRewards = function() {
            window.api('daily_bonus_load_defaults', {}).then(function(d) {
                if (d.success) {
                    window.notify('✅ Загружено ' + d.count + ' наград', 'success');
                    closeModal();
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ ' + d.error, 'error');
                }
            });
        };

        window.addDailyBonusRewardModal = function() {
            window.modal(`
                <div class="admin-modal__title">➕ Новая награда</div>
                <div class="admin-form-group"><label>Название</label><input type="text" class="admin-form-control" id="dailyRewardName" placeholder="Например: Мелочь"></div>
                <div class="admin-form-group"><label>Тип</label><select class="admin-form-control" id="dailyRewardType" onchange="updateDailyRewardPlaceholder()"><option value="balance">💰 Баланс</option><option value="promo">🎁 Промокод (+% к депозиту)</option><option value="free_case">📦 Бесплатный кейс</option></select></div>
                <div class="admin-form-group"><label>Значение</label><input type="text" class="admin-form-control" id="dailyRewardValue" placeholder="Сумма в $ или процент"></div>
                <div class="admin-form-group"><label>Вес (вероятность)</label><input type="number" class="admin-form-control" id="dailyRewardWeight" value="100" min="1"><small style="color:var(--text-muted);">Больше = чаще выпадает</small></div>
                <div class="admin-modal__footer"><button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button><button class="admin-btn admin-btn--success" onclick="addDailyBonusReward()">Добавить</button></div>
            `);
        };

        window.updateDailyRewardPlaceholder = function() {
            var type = document.getElementById('dailyRewardType').value;
            var valueInput = document.getElementById('dailyRewardValue');
            var label = valueInput.previousElementSibling;
            
            if (type === 'balance') {
                label.textContent = 'Сумма в $';
                valueInput.placeholder = '0.50';
            } else if (type === 'promo') {
                label.textContent = 'Процент бонуса (%)';
                valueInput.placeholder = '10';
            } else {
                label.textContent = 'Количество кейсов';
                valueInput.placeholder = '1';
            }
        };

        window.addDailyBonusReward = function() {
            var name = document.getElementById('dailyRewardName').value.trim();
            var type = document.getElementById('dailyRewardType').value;
            var value = document.getElementById('dailyRewardValue').value.trim();
            var weight = parseInt(document.getElementById('dailyRewardWeight').value, 10) || 100;

            if (!name || !value) return window.notify('❌ Заполните название и значение', 'error');

            window.api('daily_bonus_reward_add', {
                name: name,
                type: type,
                value: value,
                weight: weight
            }).then(function(d) {
                if (d.success) {
                    window.notify('✅ Награда добавлена', 'success');
                    closeModal();
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ ' + d.error, 'error');
                }
            });
        };

        window.deleteAllDailyBonusRewards = function() {
            window.modal(`
                <div class="admin-modal__title">Удалить все награды</div>
                <p>Вы точно хотите удалить все награды ежедневного бонуса?</p>
                <div class="admin-modal__footer">
                    <button class="admin-btn admin-btn--ghost" onclick="closeModal()">Отмена</button>
                    <button class="admin-btn admin-btn--danger" onclick="confirmDeleteAllDailyBonusRewards()">Удалить все</button>
                </div>
            `);
        };

        window.confirmDeleteAllDailyBonusRewards = function() {
            window.api('daily_bonus_rewards_delete_all', {}).then(function(d) {
                if (d.success) {
                    window.notify('✅ Все награды удалены', 'success');
                    closeModal();
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ ' + d.error, 'error');
                }
            });
        };

        window.deleteDailyBonusReward = function(id) {
            if (!confirm('Удалить награду?')) return;
            window.api('daily_bonus_reward_delete', {id: id}).then(function(d) {
                if (d.success) {
                    window.notify('✅ Удалено', 'success');
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    window.notify('❌ ' + d.error, 'error');
                }
            });
        };
    </script>
    
    <script>
    // Инициализация при загрузке
    document.addEventListener('DOMContentLoaded', function() {
        // Авто-скрытие уведомлений
        const notifyEls = document.querySelectorAll('.admin-notify');
        notifyEls.forEach(function(el) {
            setTimeout(function() {
                el.style.opacity = '0';
                el.style.transform = 'translateX(100%)';
                setTimeout(function() { el.remove(); }, 300);
            }, 3000);
        });
    });
    </script>
</body>
</html>
