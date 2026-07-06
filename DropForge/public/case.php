<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';

$caseId = (int)($_GET['id'] ?? 0);
if (!$caseId) {
    header('Location: /index.php');
    exit;
}

// Get case
$stmt = db()->prepare("SELECT c.*, cat.name as category_name FROM cases c LEFT JOIN categories cat ON c.category_id = cat.id WHERE c.id = ? AND c.is_active = 1");
$stmt->execute([$caseId]);
$case = $stmt->fetch();

if (!$case) {
    header('Location: /index.php');
    exit;
}

// Get case items
$stmt = db()->prepare("SELECT * FROM case_items WHERE case_id = ? AND weight > 0 ORDER BY FIELD(rarity, 'consumer', 'industrial', 'milspec', 'restricted', 'classified', 'covert', 'extraordinary', 'contraband')");
$stmt->execute([$caseId]);
$items = $stmt->fetchAll();

$casePrice = (float)$case['price'];
$user = getCurrentUser();
$userBalance = $user ? (float)$user['balance'] : 0;
?>

<!-- CASE PAGE -->
<div style="display:flex; flex-wrap:wrap; gap:2rem;">
    <!-- LEFT: Roulette + Open -->
    <div style="flex:1; min-width:300px;">
        <h1 style="margin-bottom:0.5rem;"><?= e($case['name']) ?></h1>
        <p style="color:var(--text-secondary); margin-bottom:1.5rem;"><?= e($case['description'] ?? '') ?></p>

        <!-- ROULETTE -->
        <div class="roulette-container" id="rouletteContainer">
            <div class="roulette-container__marker"></div>
            <div class="roulette-track" id="rouletteTrack"></div>
        </div>

        <!-- QUANTITY SELECTOR -->
        <div style="display:flex; align-items:center; justify-content:center; gap:0.5rem; margin:1rem 0;">
            <button class="btn btn--outline btn--sm qty-btn active" onclick="setQty(1)" data-qty="1">x1</button>
            <button class="btn btn--outline btn--sm qty-btn" onclick="setQty(3)" data-qty="3">x3</button>
            <button class="btn btn--outline btn--sm qty-btn" onclick="setQty(5)" data-qty="5">x5</button>
        </div>

        <!-- OPEN BUTTON -->
        <div style="text-align:center; margin:1.5rem 0;">
            <div style="font-size:1.5rem; font-weight:700; color:var(--success); margin-bottom:1rem;">
                <span id="totalPrice"><?= formatMoney($casePrice) ?></span>
            </div>
            <button class="btn btn--primary btn--lg" id="openCaseBtn" onclick="openCase()">
                Открыть кейс
            </button>
            <p id="balanceInfo" style="margin-top:0.75rem; color:var(--text-muted); font-size:0.85rem;">
                Ваш баланс: <?= $user ? formatMoney($userBalance) : 'Необходима авторизация' ?>
            </p>
            <a href="/deposits.php" id="depositLink" style="display:none; color:var(--accent); font-weight:600; text-decoration:underline; cursor:pointer; margin-top:0.5rem;">
                Пополнить баланс
            </a>
        </div>

        <!-- WIN DISPLAY -->
        <div id="winDisplay" style="display:none; text-align:center; padding:1.5rem; background:var(--bg-card); border-radius:12px; margin-top:1rem;">
            <img id="winImage" src="" style="width:200px; height:160px; object-fit:contain; margin-bottom:1rem;">
            <h3 id="winName" style="margin-bottom:0.25rem;"></h3>
            <span id="winRarity" class="badge"></span>
            <div id="winPrice" style="color:var(--success); font-weight:700; font-size:1.2rem; margin-top:0.5rem;"></div>
            <div style="margin-top:1rem; display:flex; gap:0.75rem; justify-content:center;">
                <button class="btn btn--success btn--sm" onclick="sellWin()">Продать</button>
                <button class="btn btn--outline btn--sm" onclick="closeWin()">В инвентарь</button>
            </div>
        </div>
    </div>

    <!-- RIGHT: Items preview -->
    <div style="flex:1; min-width:300px;">
        <h3 style="margin-bottom:1rem;">Содержимое кейса</h3>
        <div style="display:flex; flex-direction:column; gap:0.5rem; max-height:500px; overflow-y:auto;">
            <?php foreach ($items as $item): ?>
                <div style="display:flex; align-items:center; gap:0.75rem; padding:0.6rem; background:var(--bg-card); border-radius:8px; border-left:3px solid <?= RAIRITY_COLORS[$item['rarity']] ?? '#888' ?>;">
                    <?php if (!empty($item['item_image'])): ?>
                        <img src="<?= e($item['item_image']) ?>" style="width:50px; height:40px; object-fit:contain;" alt="">
                    <?php else: ?>
                        <div style="width:50px; height:40px; background:var(--bg-tertiary); border-radius:4px; display:flex; align-items:center; justify-content:center;">📦</div>
                    <?php endif; ?>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:0.85rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= e($item['item_name']) ?></div>
                    </div>
                    <div style="color:var(--success); font-weight:600; font-size:0.85rem; white-space:nowrap;"><?= formatMoney($item['price']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$rairityColorsJson = json_encode(RAIRITY_COLORS);
$caseDataJson = json_encode([
    "id" => $case["id"],
    "name" => $case["name"],
    "price" => $casePrice,
    "items" => array_map(function($i) {
        return [
            "id" => $i["id"],
            "name" => $i["item_name"],
            "image" => $i["item_image"],
            "rarity" => $i["rarity"],
            "price" => (float)$i["price"],
            "weight" => (int)$i["weight"]
        ];
    }, $items)
]);
?>

<script src="/js/cases.js"></script>
<script>
    const RAIRITY_COLORS = <?= $rairityColorsJson ?>;
    const caseData = <?= $caseDataJson ?>;
    const casePrice = <?= $casePrice ?>;
    let currentQty = 1;
    let userBalance = <?= $userBalance ?>;
    
    function setQty(qty) {
        currentQty = qty;
        document.querySelectorAll(".qty-btn").forEach(btn => {
            btn.classList.toggle("active", parseInt(btn.dataset.qty) === qty);
        });
        const totalPrice = casePrice * qty;
        document.getElementById("totalPrice").textContent = formatMoney(totalPrice);
        checkBalance();
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
    
    function checkBalance() {
        const totalPrice = casePrice * currentQty;
        const openBtn = document.getElementById("openCaseBtn");
        const depositLink = document.getElementById("depositLink");
        
        if (userBalance < totalPrice) {
            openBtn.style.display = "none";
            depositLink.style.display = "inline-block";
        } else {
            openBtn.style.display = "inline-flex";
            depositLink.style.display = "none";
        }
    }
        
    window.openCase = function() {
        if (isSpinning) return;
        if (!USER_ID) {
            return notify("Необходимо авторизоваться", "error");
        }
        checkBalance();
        const totalPrice = casePrice * currentQty;
        if (userBalance < totalPrice) {
            return notify("Недостаточно средств", "error");
        }
        
        isSpinning = true;
        document.getElementById("openCaseBtn").disabled = true;
        document.getElementById("winDisplay").style.display = "none";
        
        fetch(SITE_URL + "/api/case.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ case_id: caseData.id, qty: currentQty })
        })
        .then(async r => {
            const text = await r.text();
            if (!text) {
                throw new Error("Пустой ответ от сервера");
            }
            return JSON.parse(text);
        })
        .then(data => {
            if (data.success) {
                if (Array.isArray(data.items)) {
                    showMultipleWins(data.items);
                } else {
                    showWin(data.items || data.item);
                }
                userBalance = data.balance || userBalance;
                document.getElementById("balanceInfo").textContent = "Ваш баланс: " + formatMoney(userBalance);
                checkBalance();
            } else {
                notify(data.error || "Ошибка", "error");
                document.getElementById("openCaseBtn").disabled = false;
            }
            isSpinning = false;
        })
        .catch(err => {
            notify("Ошибка: " + err.message, "error");
            isSpinning = false;
            document.getElementById("openCaseBtn").disabled = false;
        });
    };
    
    function showMultipleWins(items) {
        const winDisplay = document.getElementById("winDisplay");
        const winImage = document.getElementById("winImage");
        const winName = document.getElementById("winName");
        const winRarity = document.getElementById("winRarity");
        const winPrice = document.getElementById("winPrice");
        
        if (items.length === 1) {
            showWin(items[0]);
            return;
        }
        
        const lastItem = items[items.length - 1];
        winImage.src = lastItem.item_image || "/assets/images/default-case.png";
        winName.textContent = lastItem.item_name;
        winRarity.className = "badge badge--" + lastItem.rarity;
        winRarity.textContent = lastItem.rarity.charAt(0).toUpperCase() + lastItem.rarity.slice(1);
        winPrice.textContent = formatMoney(lastItem.price);
        
        let html = '<div style="margin-top:1rem; text-align:left;">';
        html += '<div style="font-weight:600; margin-bottom:0.5rem;">Все выигрыши:</div>';
        items.forEach(item => {
            const color = RAIRITY_COLORS[item.rarity] || "#888";
            html += '<div style="display:flex; align-items:center; gap:0.5rem; padding:0.5rem; background:var(--bg-tertiary); border-radius:6px; margin-bottom:0.25rem; border-left:3px solid ' + color + ';">';
            html += '<img src="' + (item.item_image || "/assets/images/default-case.png") + '" style="width:40px; height:30px; object-fit:contain;">';
            html += '<div style="flex:1;"><div style="font-size:0.85rem; font-weight:600;">' + item.item_name + '</div>';
            html += '<div style="font-size:0.75rem; color:var(--success);">' + formatMoney(item.price) + '</div></div></div>';
        });
        html += '</div>';
        
        winDisplay.innerHTML = html + winDisplay.innerHTML;
        winDisplay.style.display = "block";
    }
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
