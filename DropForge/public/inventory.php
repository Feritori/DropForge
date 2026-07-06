<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';
requireAuth();

// Проверка: страница отключена?
if (getSetting('inventory_enabled', '1') !== '1') {
    redirect('/index.php');
}

$user = getCurrentUser();

$page = (int)($_GET['page'] ?? 1);
$perPage = 40;
$offset = ($page - 1) * $perPage;

// Total count
$stmt = db()->prepare("SELECT COUNT(*) FROM user_inventory WHERE user_id = ? AND is_sold = 0");
$stmt->execute([$user['id']]);
$totalItems = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($totalItems / $perPage));

// Items
$stmt = db()->prepare("SELECT * FROM user_inventory WHERE user_id = ? AND is_sold = 0 ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$user['id'], $perPage, $offset]);
$items = $stmt->fetchAll();

$totalValue = 0;
foreach ($items as $item) $totalValue += (float)$item['price'];
?>

<h1>📦 Инвентарь</h1>
<div style="display:flex; justify-content:space-between; align-items:center; margin:0.5rem 0 1.5rem; flex-wrap:wrap; gap:1rem;">
    <p style="color:var(--text-secondary);">
        Предметов: <strong><?= $totalItems ?></strong> · 
        Общая стоимость: <strong style="color:var(--success);"><?= formatMoney($totalValue) ?></strong>
    </p>
    <div style="display:flex; gap:0.5rem;">
        <button class="btn btn--danger btn--sm" onclick="sellAll()">🗑 Продать всё</button>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="empty-state__icon">📦</div>
        <div class="empty-state__text">Инвентарь пуст. Открой кейс, чтобы получить скины!</div>
        <a href="/cases.php" class="btn btn--primary" style="margin-top:1rem;">Перейти к кейсам</a>
    </div>
<?php else: ?>
    <div class="inventory-grid">
        <?php foreach ($items as $item): ?>
            <div class="inv-item" data-id="<?= $item['id'] ?>" data-price="<?= $item['price'] ?>" data-name="<?= e($item['item_name']) ?>">
                <img src="<?= getSteamItemImage($item['item_image'], 'medium') ?>" class="inv-item__image" alt="">
                <div class="inv-item__info">
                    <div class="inv-item__name"><?= e($item['item_name']) ?></div>
                    <div class="inv-item__price"><?= formatMoney($item['price']) ?></div>
                </div>
                <div class="inv-item__rarity-bar" style="background:<?= RAIRITY_COLORS[$item['rarity']] ?>"></div>
                <div style="display:flex; gap:0.25rem; padding:0 0.5rem 0.5rem;">
                    <button class="btn btn--success btn--sm" style="flex:1; padding:0.3rem;" onclick="sellItem(<?= $item['id'] ?>)">💰</button>
                    <button class="btn btn--outline btn--sm" style="flex:1; padding:0.3rem;" onclick="useItem(<?= $item['id'] ?>)">⬆️</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- PAGINATION -->
    <div style="display:flex; justify-content:center; gap:0.5rem; margin-top:2rem;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?>" class="btn btn--sm <?= $p === $page ? 'btn--primary' : 'btn--outline' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<script src="/js/inventory.js"></script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

