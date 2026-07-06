<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';
requireAuth();

// Проверка: страница отключена?
if (getSetting('contract_enabled', '1') !== '1') {
    redirect('/index.php');
}

$user = getCurrentUser();

$stmt = db()->prepare("SELECT * FROM user_inventory WHERE user_id = ? AND is_sold = 0 ORDER BY price DESC");
$stmt->execute([$user['id']]);
$inventory = $stmt->fetchAll();

$selectedItems = $_SESSION['contract_items'] ?? [];
$rarityCounts = [];
$totalValue = 0;

if (!empty($selectedItems)) {
    foreach ($selectedItems as $invId) {
        foreach ($inventory as $item) {
            if ($item['id'] == $invId) {
                $r = $item['rarity'];
                $rarityCounts[$r] = ($rarityCounts[$r] ?? 0) + 1;
                $totalValue += (float)$item['price'];
                break;
            }
        }
    }
}

$selectedRarity = null;
if (!empty($rarityCounts)) {
    $mostCommon = array_keys($rarityCounts, max($rarityCounts))[0];
    $nextRarityIdx = (array_search($mostCommon, RAIRITY_ORDER) ?? -1) + 1;
    if ($nextRarityIdx < count(RAIRITY_ORDER)) {
        $selectedRarity = RAIRITY_ORDER[$nextRarityIdx];
    }
}
?>

<style>
.contract-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.contract-top {
    text-align: center;
    margin-bottom: 2rem;
    position: relative;
}

.contract-cards {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    perspective: 1000px;
}

.contract-card {
    width: 120px;
    height: 160px;
    background: linear-gradient(135deg, #1a1a2e, #16213e);
    border: 2px solid var(--border);
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.3s;
    cursor: pointer;
}

.contract-card.filled {
    border-color: var(--accent);
    box-shadow: 0 0 20px rgba(131, 56, 236, 0.3);
}

.contract-card img {
    width: 80px;
    height: 60px;
    object-fit: contain;
    margin-bottom: 0.5rem;
}

.contract-card-name {
    font-size: 0.65rem;
    color: var(--text-primary);
    text-align: center;
    padding: 0 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100px;
}

.contract-card-price {
    font-size: 0.7rem;
    color: var(--success);
    font-weight: 600;
    margin-top: 0.25rem;
}

.contract-card-rarity {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.contract-card .check {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 20px;
    height: 20px;
    background: #4ade80;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    color: #fff;
    font-weight: 700;
}

.contract-info-bar {
    display: flex;
    justify-content: center;
    gap: 2rem;
    align-items: center;
    margin-bottom: 1rem;
}

.contract-count {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
}

.contract-total {
    font-size: 1rem;
    font-weight: 700;
    color: var(--success);
}

.contract-sign-btn {
    padding: 0.75rem 3rem;
    background: linear-gradient(135deg, #4ade80, #22c55e);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.contract-sign-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(74, 222, 128, 0.4);
}

.contract-sign-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.contract-result-info {
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.contract-bottom {
    margin-top: 2rem;
}

.contract-bottom-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.contract-bottom-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
}

.contract-bottom-search {
    position: relative;
}

.contract-bottom-search input {
    padding: 0.4rem 2rem 0.4rem 0.75rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.8rem;
    width: 150px;
}

.contract-bottom-search input:focus {
    outline: none;
    border-color: var(--accent);
}

.contract-bottom-search span {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.8rem;
    opacity: 0.5;
}

.contract-items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.75rem;
    max-height: 400px;
    overflow-y: auto;
}

.contract-item-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.contract-item-card:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}

.contract-item-card.selected {
    border-color: #4ade80;
    box-shadow: 0 0 10px rgba(74, 222, 128, 0.3);
}

.contract-item-card img {
    width: 100%;
    height: 70px;
    object-fit: contain;
    margin-bottom: 0.25rem;
}

.contract-item-card-name {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.15rem;
}

.contract-item-card-price {
    font-size: 0.65rem;
    color: var(--success);
    font-weight: 600;
}

.contract-item-card-rarity {
    height: 2px;
    margin-top: 0.25rem;
}

.contract-item-card .check {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 18px;
    height: 18px;
    background: #4ade80;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    color: #fff;
    font-weight: 700;
}

.contract-item-card.selected .check {
    display: flex;
}

.contract-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(5, 8, 20, 0.95);
    z-index: 200;
    align-items: center;
    justify-content: center;
}

.contract-modal-overlay.active {
    display: flex;
}

.contract-modal {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 2rem;
    max-width: 800px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
}

.contract-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
}

.contract-modal h2 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.contract-modal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
}

.contract-modal-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    background: var(--bg-secondary);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.contract-modal-item:hover {
    background: var(--bg-hover);
    transform: translateX(5px);
}

.contract-modal-item img {
    width: 50px;
    height: 40px;
    object-fit: contain;
}

.contract-modal-item-info div:first-child {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
}

.contract-modal-item-info small {
    font-size: 0.7rem;
    color: var(--success);
}

@media (max-width: 768px) {
    .contract-cards {
        flex-wrap: wrap;
    }
    
    .contract-card {
        width: 100px;
        height: 140px;
    }
    
    .contract-info-bar {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

<div class="contract-wrapper">
    <div class="contract-top">
        <div class="contract-cards" id="contractCards">
            <?php 
            // Создаём карту ID предмета для быстрого доступа
            $itemMap = [];
            foreach ($inventory as $invItem) {
                $itemMap[$invItem['id']] = $invItem;
            }
            ?>
            <?php for ($i = 0; $i < 10; $i++): ?>
                <?php 
                $invId = $selectedItems[$i] ?? null;
                $item = $invId && isset($itemMap[$invId]) ? $itemMap[$invId] : null;
                $rarityColor = $item ? (RAIRITY_COLORS[$item['rarity']] ?? '#888') : 'var(--border)';
                ?>
                <div class="contract-card" id="card-<?= $i ?>" onclick="openContractModal()">
                    <div class="contract-card-rarity" style="background:<?= $rarityColor ?>"></div>
                    <?php if ($item): ?>
                        <img src="<?= getSteamItemImage($item['item_image'], 'medium', $item['item_name']) ?>" alt="">
                        <div class="contract-card-name"><?= e($item['item_name']) ?></div>
                        <div class="contract-card-price">₽ <?= number_format($item['price'] * 90, 0, '.', ' ') ?></div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
        
        <div class="contract-info-bar">
            <div class="contract-count"><span id="selectedCount">0</span> / 10</div>
            <button class="contract-sign-btn" id="signBtn" onclick="signContract()" disabled>СОЗДАТЬ КОНТРАКТ</button>
            <div class="contract-total">₽ <span id="totalRub">0</span></div>
        </div>
        
        <div class="contract-result-info" id="resultInfo">
            В результате вы получите предмет стоимостью от <span id="minResult">—</span> до <span id="maxResult">—</span> ₽
        </div>
    </div>

    <div class="contract-bottom">
        <div class="contract-bottom-header">
            <div class="contract-bottom-title">Доступные для контракта предметы</div>
            <div class="contract-bottom-search">
                <input type="text" placeholder="Поиск..." id="contractSearch" onkeyup="filterContractItems()">
                <span>&#128269;</span>
            </div>
        </div>
        <div class="contract-items-grid" id="contractItemsGrid">
            <?php foreach ($inventory as $item): ?>
                <div class="contract-item-card" data-name="<?= e(strtolower($item['item_name'])) ?>" data-id="<?= $item['id'] ?>" onclick="toggleContractItem(<?= $item['id'] ?>)">
                    <div class="check">✓</div>
                    <img src="<?= getSteamItemImage($item['item_image'], 'medium', $item['item_name']) ?>" alt="">
                    <div class="contract-item-card-name"><?= e($item['item_name']) ?></div>
                    <div class="contract-item-card-price">₽ <?= number_format($item['price'] * 90, 0, '.', ' ') ?></div>
                    <div class="contract-item-card-rarity" style="background:<?= RAIRITY_COLORS[$item['rarity']] ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="contract-modal-overlay" id="contractModal" onclick="if(event.target===this)closeContractModal()">
    <div class="contract-modal">
        <button class="contract-modal-close" onclick="closeContractModal()">&times;</button>
        <h2>Добавить предмет</h2>
        <input type="text" class="form-control" placeholder="Поиск..." onkeyup="filterModalItems(this.value)" style="margin-bottom:1rem;">
        <div class="contract-modal-grid" id="contractModalGrid">
            <?php foreach ($inventory as $item): ?>
                <div class="contract-modal-item" onclick="toggleContractItem(<?= $item['id'] ?>)">
                    <img src="<?= getSteamItemImage($item['item_image'], 'medium', $item['item_name']) ?>" alt="">
                    <div class="contract-modal-item-info">
                        <div><?= e($item['item_name']) ?></div>
                        <small>₽ <?= number_format($item['price'] * 90, 0, '.', ' ') ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
const CONTRACT_INVENTORY = <?= json_encode($inventory) ?>;
const SELECTED_IDS = <?= json_encode($selectedItems) ?>;
const TOTAL_VALUE = <?= $totalValue ?>;
</script>
<script src="/js/contract.js"></script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

