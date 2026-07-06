<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';

// Проверка: страница отключена?
if (getSetting('free_case_enabled', '1') !== '1') {
    redirect('/index.php');
}

$caseId = (int)($_GET['id'] ?? 0);
if (!$caseId) {
    header('Location: /index.php');
    exit;
}

// Get free case
$stmt = db()->prepare("SELECT * FROM free_cases WHERE id = ? AND is_active = 1");
$stmt->execute([$caseId]);
$case = $stmt->fetch();

if (!$case) {
    header('Location: /index.php');
    exit;
}

// Get case items
$stmt = db()->prepare("SELECT * FROM free_case_items WHERE case_id = ? AND weight > 0 ORDER BY weight DESC");
$stmt->execute([$caseId]);
$items = $stmt->fetchAll();

$user = getCurrentUser();
$userDeposit = $user ? getUserDepositLast24h($user['id']) : 0;
$canOpen = $user ? $userDeposit >= (float)$case['min_deposit'] : false;
?>

<h1 style="margin-bottom:0.5rem;"><?= e($case['name']) ?></h1>
<p style="color:var(--text-secondary); margin-bottom:1.5rem;"><?= e($case['description'] ?? '') ?></p>

<div style="display:flex; flex-wrap:wrap; gap:2rem;">
    <!-- LEFT: Open -->
    <div style="flex:1; min-width:300px;">
        <!-- DEPOSIT INFO -->
        <div style="background:var(--bg-card); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; border:2px solid <?= $canOpen ? '#4CAF50' : '#ff4444' ?>;">
            <div style="font-size:1.1rem; font-weight:700; margin-bottom:1rem;">
                <?= $canOpen ? '✅ Доступно для открытия' : '🔒 Требуется депозит' ?>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
                <span style="color:var(--text-muted);">Требуемый депозит:</span>
                <span style="font-weight:600; color:var(--success);">$<?= number_format($case['min_deposit'], 2) ?></span>
            </div>
            <?php if ($user): ?>
                <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
                    <span style="color:var(--text-muted);">Ваш депозит (24ч):</span>
                    <span style="font-weight:600;"><?= $userDeposit >= $case['min_deposit'] ? '✅' : '❌' ?> $<?= number_format($userDeposit, 2) ?></span>
                </div>
                <div style="background:var(--bg-tertiary); border-radius:8px; height:10px; overflow:hidden;">
                    <div style="width:<?= min(100, ($userDeposit / $case['min_deposit']) * 100) ?>%; background:<?= $canOpen ? '#4CAF50' : '#ff4444' ?>; height:100%;"></div>
                </div>
            <?php else: ?>
                <a href="/login.php" class="btn btn--primary btn--sm" style="margin-top:1rem;">Войти чтобы проверить</a>
            <?php endif; ?>
        </div>

        <!-- OPEN BUTTON -->
        <div style="text-align:center; margin:2rem 0;">
            <button class="btn btn--primary btn--lg" id="openFreeCaseBtn" onclick="openFreeCase()" <?= !$canOpen ? 'disabled' : '' ?> style="<?= !$canOpen ? 'opacity:0.5; cursor:not-allowed;' : '' ?>">
                <?= $user ? 'Открыть бесплатно' : 'Войти чтобы открыть' ?>
            </button>
            <?php if ($user && !$canOpen): ?>
                <p style="color:var(--text-muted); margin-top:1rem; font-size:0.9rem;">
                    Пополните баланс на $<?= number_format($case['min_deposit'] - $userDeposit, 2) ?> чтобы открыть кейс
                </p>
                <a href="/deposits.php" class="btn btn--success btn--sm" style="margin-top:0.5rem;">Пополнить баланс</a>
            <?php endif; ?>
        </div>

        <!-- WIN DISPLAY -->
        <div id="winDisplay" style="display:none; text-align:center; padding:2rem; background:var(--bg-card); border-radius:12px; margin-top:1.5rem;">
            <img id="winImage" src="" style="width:220px; height:180px; object-fit:contain; margin-bottom:1rem;">
            <h3 id="winName" style="margin-bottom:0.5rem; font-size:1.3rem;"></h3>
            <span id="winRarity" class="badge"></span>
            <div id="winPrice" style="color:var(--success); font-weight:700; font-size:1.4rem; margin-top:0.75rem;"></div>
            <div style="margin-top:1.5rem; display:flex; gap:0.75rem; justify-content:center;">
                <button class="btn btn--success btn--sm" onclick="sellWin()">Продать</button>
                <button class="btn btn--outline btn--sm" onclick="closeWin()">В инвентарь</button>
            </div>
        </div>
    </div>

    <!-- RIGHT: Items preview -->
    <div style="flex:1; min-width:300px;">
        <h3 style="margin-bottom:1rem;">Содержимое кейса</h3>
        <div style="display:flex; flex-direction:column; gap:0.5rem; max-height:600px; overflow-y:auto;">
            <?php foreach ($items as $item): ?>
                <div style="display:flex; align-items:center; gap:0.75rem; padding:0.75rem; background:var(--bg-card); border-radius:8px; border-left:4px solid <?= RAIRITY_COLORS[$item['rarity']] ?? '#888' ?>;">
                    <?php if (!empty($item['item_image'])): ?>
                        <img src="<?= e($item['item_image']) ?>" style="width:60px; height:50px; object-fit:contain;" alt="">
                    <?php else: ?>
                        <div style="width:60px; height:50px; background:var(--bg-tertiary); border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:1.5rem;">🔫</div>
                    <?php endif; ?>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:0.9rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= e($item['item_name']) ?></div>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= rarityLabel($item['rarity']) ?></div>
                    </div>
                    <div style="color:var(--success); font-weight:700; font-size:1rem;"><?= formatMoney($item['price']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<script>
    const caseData = ' . json_encode([
        "id" => $case["id"],
        "name" => $case["name"],
        "items" => array_map(function($i) {
            return [
                "id" => $i["id"],
                "name" => $i["item_name"],
                "image" => $i["item_image"] ?: "/assets/images/default-case.png",
                "rarity" => $i["rarity"],
                "price" => (float)$i["price"],
                "weight" => (int)$i["weight"]
            ];
        }, $items)
    ]) . ';
    
    let currentWinItem = null;
    
    function openFreeCase() {
        const btn = document.getElementById("openFreeCaseBtn");
        btn.disabled = true;
        btn.textContent = "Открываем...";
        
        fetch("/api/free_cases.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "open", case_id: caseData.id })
        })
        .then(async r => {
            const text = await r.text();
            if (!text) throw new Error("Пустой ответ от сервера");
            return JSON.parse(text);
        })
        .then(data => {
            if (data.success) {
                showWin(data.item);
                currentWinItem = data.item;
            } else {
                Modal.alert('Ошибка', data.error || 'Не удалось открыть бонус', '❌');
                btn.disabled = false;
                btn.textContent = "Открыть бесплатно";
            }
        })
        .catch(err => {
            Modal.alert('Ошибка', err.message, '❌');
            btn.disabled = false;
            btn.textContent = "Открыть бесплатно";
        });
    }
    
    function showWin(item) {
        const winDisplay = document.getElementById("winDisplay");
        const winImage = document.getElementById("winImage");
        const winName = document.getElementById("winName");
        const winRarity = document.getElementById("winRarity");
        const winPrice = document.getElementById("winPrice");
        const btn = document.getElementById("openFreeCaseBtn");
        
        winImage.src = item.image;
        winName.textContent = item.name;
        winRarity.className = "badge badge--" + item.rarity;
        winRarity.textContent = rarityLabel(item.rarity);
        winPrice.textContent = formatMoney(item.price);
        winDisplay.style.display = "block";
        
        btn.style.display = "none";
        winDisplay.scrollIntoView({ behavior: "smooth" });
    }
    
    function sellWin() {
        if (!currentWinItem) return;
        
        fetch("/api/inventory.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "sell", item_id: currentWinItem.id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Modal.alert('✅ Продано', 'Предмет продан за ' + formatMoney(data.amount), '💰');
                location.reload();
            } else {
                Modal.alert('Ошибка', data.error || 'Не удалось продать', '❌');
            }
        });
    }
    
    function closeWin() {
        if (!currentWinItem) return;
        location.href = "/inventory.php";
    }
    
    function formatMoney(amount) {
        const currency = window.SITE_CURRENCY || 'USD';
        const symbol = window.CURRENCY_SYMBOL || '$';
        
        if (currency === 'RUB' || currency === 'KZT') {
            return Math.round(amount).toLocaleString('ru-RU') + ' ' + symbol;
        } else {
            return parseFloat(amount).toFixed(2) + ' ' + symbol;
        }
    }
    
    function rarityLabel(rarity) {
        const labels = {
            "consumer": "Shabby",
            "industrial": "Workshop", 
            "milspec": "Military",
            "restricted": "Restricted",
            "classified": "Classified",
            "covert": "Covert",
            "extraordinary": "Extraordinary",
            "contraband": "Contraband"
        };
        return labels[rarity] || rarity;
    }
</script>';
?>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
