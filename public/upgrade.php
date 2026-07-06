<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';
requireAuth();

// Проверка: страница отключена?
if (getSetting('upgrade_enabled', '1') !== '1') {
    redirect('/index.php');
}

$user = getCurrentUser();

$stmt = db()->prepare("SELECT * FROM user_inventory WHERE user_id = ? AND is_sold = 0 ORDER BY price DESC");
$stmt->execute([$user['id']]);
$inventory = $stmt->fetchAll();

$stmt = db()->prepare("SELECT DISTINCT item_name, item_image, rarity, price FROM case_items WHERE weight > 0 ORDER BY price DESC");
$stmt->execute();
$allItems = $stmt->fetchAll();

$stmt = db()->prepare("SELECT value FROM settings WHERE `key` = 'upgrade_loss_rate'");
$stmt->execute();
$setting = $stmt->fetch();
$upgradeLossRate = (float)($setting['value'] ?? 66.00);
?>

<style>
.upgrade-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.upgrade-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 0 1rem;
}

.topbar-tabs {
    display: flex;
    gap: 1rem;
}

.topbar-tab {
    padding: 0.5rem 1.5rem;
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
}

.topbar-tab.active {
    color: #fff;
}

.topbar-coefficient {
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-weight: 600;
}

.upgrade-main {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 3rem;
    margin-bottom: 2rem;
}

.upgrade-side {
    flex: 1;
    max-width: 300px;
    text-align: center;
}

.upgrade-platform-box {
    position: relative;
    height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.2s;
}

.upgrade-platform-box:hover {
    transform: translateY(-5px);
}

.platform-top {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 180px;
    height: 40px;
    background: linear-gradient(180deg, #2a2a3a, #1a1a2a);
    border-radius: 10px 10px 0 0;
    border: 1px solid #3a3a4a;
    border-bottom: none;
}

.platform-beam {
    position: absolute;
    top: 40px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: calc(100% - 80px);
    background: linear-gradient(180deg, rgba(131, 56, 236, 0.6), rgba(131, 56, 236, 0.1));
    box-shadow: 0 0 20px rgba(131, 56, 236, 0.4);
}

.platform-content-area {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 160px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.platform-content-area img {
    width: 140px;
    height: 100px;
    object-fit: contain;
    filter: drop-shadow(0 0 15px rgba(131, 56, 236, 0.5));
}

.platform-placeholder {
    color: var(--text-muted);
    font-size: 0.85rem;
    text-align: center;
    line-height: 1.4;
}

.platform-bottom {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 200px;
    height: 50px;
    background: radial-gradient(ellipse, rgba(131, 56, 236, 0.3), transparent);
    border-radius: 50%;
    border: 1px solid rgba(131, 56, 236, 0.3);
}

.upgrade-item-info {
    margin-top: 1rem;
}

.upgrade-item-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.25rem;
}

.upgrade-item-name {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.upgrade-tower-container {
    position: relative;
    width: 200px;
    height: 350px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.tower-top {
    width: 160px;
    height: 35px;
    background: linear-gradient(180deg, #2a2a3a, #1a1a2a);
    border-radius: 10px 10px 0 0;
    border: 1px solid #3a3a4a;
    border-bottom: none;
    margin-bottom: -1px;
}

.tower-status {
    text-align: center;
    margin: 0.5rem 0;
    min-height: 24px;
}

.tower-status-value {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
}

.tower-status-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.tower-status-fail {
    color: #ef4444;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 1rem;
}

.tower-body {
    position: relative;
    width: 120px;
    height: 200px;
    background: linear-gradient(180deg, rgba(131, 56, 236, 0.1), rgba(131, 56, 236, 0.05));
    border: 2px solid rgba(131, 56, 236, 0.3);
    border-radius: 10px;
    overflow: hidden;
}

.tower-fill {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 0%;
    background: linear-gradient(180deg, rgba(131, 56, 236, 0.6), rgba(74, 222, 128, 0.8));
    transition: height 1s ease;
}

.tower-fill.success {
    background: linear-gradient(180deg, rgba(74, 222, 128, 0.8), rgba(74, 222, 128, 0.4));
}

.tower-fill.fail {
    background: linear-gradient(180deg, rgba(239, 68, 68, 0.8), rgba(239, 68, 68, 0.4));
}

.tower-marker {
    position: absolute;
    left: 0;
    right: 0;
    height: 2px;
    background: #fbbf24;
    box-shadow: 0 0 10px rgba(251, 191, 36, 0.5);
    transition: bottom 1s ease;
}

.tower-bottom {
    width: 160px;
    height: 35px;
    background: linear-gradient(180deg, #1a1a2a, #2a2a3a);
    border-radius: 0 0 10px 10px;
    border: 1px solid #3a3a4a;
    border-top: none;
    margin-top: -1px;
}

.tower-result {
    text-align: center;
    margin-top: 1rem;
    min-height: 60px;
}

.tower-result-text {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.tower-result-btn {
    padding: 0.75rem 2rem;
    background: linear-gradient(135deg, #4ade80, #22c55e);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: none;
}

.tower-result-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(74, 222, 128, 0.4);
}

.upgrade-action {
    text-align: center;
    margin: 2rem 0;
}

.upgrade-btn-main {
    padding: 1rem 4rem;
    font-size: 1.25rem;
    font-weight: 800;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #fff;
    background: linear-gradient(135deg, #8338ec, #a855f7);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 0 30px rgba(131, 56, 236, 0.5);
}

.upgrade-btn-main:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 0 50px rgba(131, 56, 236, 0.8);
}

.upgrade-btn-main:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.upgrade-bottom {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-top: 2rem;
}

.upgrade-bottom-panel {
    background: var(--bg-card);
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 1rem;
    max-height: 400px;
    display: flex;
    flex-direction: column;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.panel-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
}

.panel-search {
    position: relative;
}

.panel-search input {
    padding: 0.4rem 2rem 0.4rem 0.75rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.8rem;
    width: 150px;
}

.panel-search input:focus {
    outline: none;
    border-color: var(--accent);
}

.panel-search span {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.8rem;
    opacity: 0.5;
}

.panel-items {
    flex: 1;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 0.5rem;
}

.panel-item {
    background: var(--bg-secondary);
    border-radius: 8px;
    padding: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.panel-item:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}

.panel-item img {
    width: 100%;
    height: 60px;
    object-fit: contain;
    margin-bottom: 0.25rem;
}

.panel-item-name {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.15rem;
}

.panel-item-price {
    font-size: 0.65rem;
    color: var(--success);
    font-weight: 600;
}

.panel-item-rarity {
    height: 2px;
    margin-top: 0.25rem;
}

.panel-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
    font-size: 0.85rem;
    line-height: 1.6;
}

.panel-empty a {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.5rem 1.5rem;
    background: linear-gradient(135deg, #4ade80, #22c55e);
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    border-radius: 6px;
}

.upgrade-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(5, 8, 20, 0.95);
    z-index: 200;
    align-items: center;
    justify-content: center;
}

.upgrade-modal-overlay.active {
    display: flex;
}

.upgrade-modal {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 2rem;
    max-width: 700px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
}

.upgrade-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
}

.upgrade-modal h2 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.upgrade-modal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
}

.upgrade-modal-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    background: var(--bg-secondary);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.upgrade-modal-item:hover {
    background: var(--bg-hover);
    transform: translateX(5px);
}

.upgrade-modal-item img {
    width: 50px;
    height: 40px;
    object-fit: contain;
}

.upgrade-modal-item-info div:first-child {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
}

.upgrade-modal-item-info small {
    font-size: 0.7rem;
    color: var(--success);
}

@media (max-width: 900px) {
    .upgrade-main {
        flex-direction: column;
        gap: 2rem;
    }
    
    .upgrade-bottom {
        grid-template-columns: 1fr;
    }
    
    .upgrade-tower-container {
        transform: rotate(90deg);
        margin: 2rem 0;
    }
}
</style>

<div class="upgrade-wrapper">
    <div class="upgrade-main">
        <div class="upgrade-side">
            <div class="upgrade-platform-box" id="yourPlatform" onclick="openInventoryModal()">
                <div class="platform-top"></div>
                <div class="platform-beam"></div>
                <div class="platform-content-area" id="yourContent">
                    <div class="platform-placeholder">Выберите<br>предмет</div>
                </div>
                <div class="platform-bottom"></div>
            </div>
            <div class="upgrade-item-info" id="yourInfo" style="display:none;">
                <div class="upgrade-item-price" id="yourPrice"></div>
                <div class="upgrade-item-name" id="yourName"></div>
            </div>
        </div>

        <div class="upgrade-tower-container">
            <div class="tower-top"></div>
            <div class="tower-status" id="towerStatus"></div>
            <div class="tower-body">
                <div class="tower-fill" id="towerFill"></div>
                <div class="tower-marker" id="towerMarker" style="bottom: 0%;"></div>
            </div>
            <div class="tower-bottom"></div>
            <div class="tower-result" id="towerResult"></div>
        </div>

        <div class="upgrade-side">
            <div class="upgrade-platform-box" id="targetPlatform" onclick="openTargetModal()">
                <div class="platform-top"></div>
                <div class="platform-beam"></div>
                <div class="platform-content-area" id="targetContent">
                    <div class="platform-placeholder">Выберите<br>цель</div>
                </div>
                <div class="platform-bottom"></div>
            </div>
            <div class="upgrade-item-info" id="targetInfo" style="display:none;">
                <div class="upgrade-item-price" id="targetPrice"></div>
                <div class="upgrade-item-name" id="targetName"></div>
            </div>
        </div>
    </div>

    <div class="upgrade-action">
        <button class="upgrade-btn-main" id="upgradeBtn" onclick="doUpgrade()" disabled>УЛУЧШИТЬ</button>
    </div>

    <div class="upgrade-bottom">
        <div class="upgrade-bottom-panel">
            <div class="panel-header">
                <div class="panel-title">Мои предметы</div>
                <div class="panel-search">
                    <input type="text" placeholder="Поиск..." id="inventorySearch" onkeyup="filterInventory()">
                    <span>&#128269;</span>
                </div>
            </div>
            <div class="panel-items" id="inventoryList">
                <?php if (empty($inventory)): ?>
                    <div class="panel-empty">
                        У вас нет предметов<br>
                        <a href="/cases.php">ОТКРОЙТЕ КЕЙС</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($inventory as $item): ?>
                        <div class="panel-item" data-name="<?= e(strtolower($item['item_name'])) ?>" onclick="selectYourItem(<?= e(json_encode($item)) ?>)">
                            <img src="<?= getSteamItemImage($item['item_image'], 'medium', $item['item_name']) ?>" alt="">
                            <div class="panel-item-name"><?= e($item['item_name']) ?></div>
                            <div class="panel-item-price"><?= formatMoney($item['price']) ?></div>
                            <div class="panel-item-rarity" style="background:<?= RAIRITY_COLORS[$item['rarity']] ?>"></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="upgrade-bottom-panel">
            <div class="panel-header">
                <div class="panel-title">Скины</div>
                <div class="panel-search">
                    <input type="text" placeholder="Поиск..." id="targetSearch" onkeyup="filterTargets()">
                    <span>&#128269;</span>
                </div>
            </div>
            <div class="panel-items" id="itemsList">
                <?php foreach ($allItems as $item): ?>
                    <div class="panel-item target-item" data-name="<?= e(strtolower($item['item_name'])) ?>" onclick="selectTargetItem(<?= e(json_encode($item)) ?>)">
                        <img src="<?= getSteamItemImage($item['item_image'], 'medium') ?>" alt="">
                        <div class="panel-item-name"><?= e($item['item_name']) ?></div>
                        <div class="panel-item-price"><?= formatMoney($item['price']) ?></div>
                        <div class="panel-item-rarity" style="background:<?= RAIRITY_COLORS[$item['rarity']] ?>"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="upgrade-modal-overlay" id="inventoryModal" onclick="if(event.target===this)closeInventoryModal()">
    <div class="upgrade-modal">
        <button class="upgrade-modal-close" onclick="closeInventoryModal()">&times;</button>
        <h2>Выберите предмет</h2>
        <div class="upgrade-modal-grid">
            <?php foreach ($inventory as $item): ?>
                <div class="upgrade-modal-item" onclick="selectYourItem(<?= e(json_encode($item)) ?>)">
                    <img src="<?= getSteamItemImage($item['item_image'], 'medium') ?>" alt="">
                    <div class="upgrade-modal-item-info">
                        <div><?= e($item['item_name']) ?></div>
                        <small><?= formatMoney($item['price']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="upgrade-modal-overlay" id="targetModal" onclick="if(event.target===this)closeTargetModal()">
    <div class="upgrade-modal">
        <button class="upgrade-modal-close" onclick="closeTargetModal()">&times;</button>
        <h2>Цель улучшения</h2>
        <input type="text" class="form-control" placeholder="Поиск..." onkeyup="filterTargetModal(this.value)" style="margin-bottom:1rem;">
        <div class="upgrade-modal-grid" id="targetModalGrid">
            <?php foreach ($allItems as $item): ?>
                <div class="upgrade-modal-item" onclick="selectTargetItem(<?= e(json_encode($item)) ?>)">
                    <img src="<?= getSteamItemImage($item['item_image'], 'medium') ?>" alt="">
                    <div class="upgrade-modal-item-info">
                        <div><?= e($item['item_name']) ?></div>
                        <small><?= formatMoney($item['price']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
const YOUR_INVENTORY = <?= json_encode($inventory) ?>;
const ALL_TARGETS = <?= json_encode($allItems) ?>;
const UPGRADE_LOSS_RATE = <?= $upgradeLossRate ?>;
const RAIRITY_COLORS = <?= json_encode(RAIRITY_COLORS) ?>;
</script>
<script src="/js/upgrade.js"></script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

