<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';
requireAuth();

$user = getCurrentUser();

// Проверяем статус оплаты (после редиректа от ЮMoney)
$status = $_GET['status'] ?? '';
$statusMessage = '';
if ($status === 'success') {
    $statusMessage = '<div style="background:rgba(0,230,118,0.1); border-radius:12px; padding:1.5rem; border:1px solid rgba(0,230,118,0.3); margin-bottom:2rem; text-align:center;"><div style="font-size:2rem; margin-bottom:0.5rem;">✅</div><div style="font-weight:700; color:var(--success); font-size:1.1rem;">Платёж прошёл успешно!</div><div style="color:var(--text-secondary); font-size:0.9rem; margin-top:0.5rem;">Баланс обновится в течение нескольких минут. Если этого не произошло, обратитесь в поддержку.</div></div>';
} elseif ($status === 'failed') {
    $statusMessage = '<div style="background:rgba(255,82,82,0.1); border-radius:12px; padding:1.5rem; border:1px solid rgba(255,82,82,0.3); margin-bottom:2rem; text-align:center;"><div style="font-size:2rem; margin-bottom:0.5rem;">❌</div><div style="font-weight:700; color:var(--danger); font-size:1.1rem;">Платёж не прошёл</div><div style="color:var(--text-secondary); font-size:0.9rem; margin-top:0.5rem;">Если средства были списаны, обратитесь в поддержку с номером операции.</div></div>';
}

// Get USD/RUB rate from database or API
$stmt = db()->prepare("SELECT value FROM settings WHERE `key` = 'usd_rub_rate'");
$stmt->execute();
$rateRow = $stmt->fetch();
$usdRate = (float)($rateRow['value'] ?? 90.00);

// Get enabled payment methods via API
$paymentMethods = [];
$methodsResult = @file_get_contents(SITE_URL . '/api/payment/methods.php');
if ($methodsResult) {
    $methodsData = json_decode($methodsResult, true);
    if ($methodsData && $methodsData['success']) {
        $paymentMethods = $methodsData['methods'] ?? [];
    }
}

// Get promo codes history via API
$promoCodes = [];
$promoResult = @file_get_contents(SITE_URL . '/api/battle_pass.php?action=get_promo_codes');
if ($promoResult) {
    $promoData = json_decode($promoResult, true);
    if ($promoData && $promoData['success']) {
        $promoCodes = $promoData['codes'] ?? [];
    }
}

// Текущий бонус от Battle Pass
$currentBonus = floatval($user['promo_bonus_percent'] ?? 0);
?>

<!-- Header Tabs -->
<div style="display:flex; gap:2rem; margin-bottom:2rem; border-bottom:2px solid var(--border); padding-bottom:1rem;">
    <a href="/deposits.php" style="color:var(--accent); font-weight:700; font-size:1.1rem; text-decoration:none; display:flex; align-items:center; gap:0.5rem;">
        <span style="font-size:1.2rem;">💰</span> Пополнить
    </a>
    <a href="/transactions.php" style="color:var(--text-muted); font-weight:500; font-size:1.1rem; text-decoration:none; display:flex; align-items:center; gap:0.5rem;">
        <span style="font-size:1.2rem;">📋</span> История
    </a>
</div>

<div style="display:grid; grid-template-columns: 1fr 380px; gap:2rem;">

    <?php if ($statusMessage): ?>
    <?= $statusMessage ?>
    <?php endif; ?>
    
<!-- Left: Payment Form -->
<div style="background:var(--bg-card); border-radius:16px; padding:2rem; border:1px solid var(--border);">
    
    <!-- Promo Code Activation -->
    <div style="margin-bottom:2rem;">
        <label style="display:block; margin-bottom:0.5rem; color:var(--text-secondary); font-size:0.9rem;">🎁 Промокод</label>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="promoInput" class="form-control" placeholder="Введите промокод" style="flex:1; text-transform:uppercase; font-family:monospace; letter-spacing:1px;">
            <button class="btn btn--primary" onclick="applyPromo()" id="promoBtn">Активировать</button>
        </div>
        <div id="promoMessage" style="margin-top:0.75rem;"></div>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-top:0.5rem;">
            Введите промокод для получения бонуса к пополнению. Бонус действует только на следующую оплату.
        </p>
    </div>

    <!-- My Promo Codes History -->
    <?php if (!empty($promoCodes)): ?>
    <div style="margin-bottom:2rem; padding:1rem; background:var(--bg-tertiary); border-radius:12px;">
        <div style="font-weight:600; margin-bottom:0.75rem; color:var(--text-secondary);">📋 Мои промокоды</div>
        <div style="display:flex; flex-direction:column; gap:0.5rem;" id="promoHistoryList">
            <!-- JS will populate -->
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Amount -->
    <div style="margin-bottom:2rem;">
        <label style="display:block; margin-bottom:0.5rem; color:var(--text-secondary); font-size:0.9rem;">Введите сумму ($)</label>
        <div style="position:relative;">
            <input type="number" id="depositAmount" class="form-control" placeholder="0.00" min="1" step="0.01" style="padding-right:60px; font-size:1.5rem; font-weight:700;" oninput="updateBalance()">
            <span style="position:absolute; right:15px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:1.2rem;">$</span>
        </div>
        <div style="background:var(--bg-tertiary); border-radius:8px; padding:0.75rem 1rem; margin-top:0.75rem; display:flex; justify-content:space-between; align-items:center;">
            <span style="color:var(--text-muted); font-size:0.85rem;">К оплате в рублях:</span>
            <span style="font-weight:700; color:var(--accent);" id="rubAmount">0.00 ₽</span>
        </div>
        <div style="display:flex; gap:0.5rem; margin-top:0.75rem; flex-wrap:wrap;">
            <button class="btn btn--outline btn--sm" onclick="setAmount(5)">$5</button>
            <button class="btn btn--outline btn--sm" onclick="setAmount(10)">$10</button>
            <button class="btn btn--outline btn--sm" onclick="setAmount(25)">$25</button>
            <button class="btn btn--outline btn--sm" onclick="setAmount(50)">$50</button>
            <button class="btn btn--outline btn--sm" onclick="setAmount(100)">$100</button>
        </div>
        <p style="color:var(--text-muted); font-size:0.8rem; margin-top:0.5rem;">
            Курс: 1$ = <span id="usdRate"><?= number_format($usdRate, 2) ?></span>₽ (обновляется автоматически)
        </p>
    </div>

    <!-- Payment Methods -->
    <div style="margin-bottom:2rem;">
        <label style="display:block; margin-bottom:0.75rem; color:var(--text-secondary); font-size:0.9rem;">Способ оплаты</label>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:0.75rem;" id="paymentMethodsGrid">
            <div style="color:var(--text-muted);font-size:0.9rem;padding:1rem;">Загрузка...</div>
        </div>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-top:0.5rem;" id="paymentMethodsHint">
            Выберите доступный способ оплаты. Если ни один метод не отображается, обратитесь к администратору.
        </p>
    </div>

    <!-- Info -->
    <div style="background:rgba(0,230,118,0.1); border-radius:12px; padding:1rem; border:1px solid rgba(0,230,118,0.3);">
        <div style="display:flex; align-items:center; gap:0.5rem; color:var(--success); font-weight:600; margin-bottom:0.5rem;">
            <span>✓</span> Моментальное пополнение
        </div>
        <p style="color:var(--text-secondary); font-size:0.85rem; margin:0;">
            Баланс пополняется мгновенно. Если этого не произошло в течение часа, обратитесь в поддержку, указав номер транзакции.
        </p>
    </div>
</div>

<!-- Right: Summary -->
<div style="background:var(--bg-card); border-radius:16px; padding:2rem; border:1px solid var(--border);">
    
    <!-- Balance -->
    <div style="text-align:center; margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:1px solid var(--border);">
        <div style="color:var(--text-muted); font-size:0.85rem; margin-bottom:0.5rem;">Ваш баланс</div>
        <div style="font-size:2.5rem; font-weight:800; color:var(--success);"><?= formatMoney($user['balance']) ?></div>
    </div>

    <!-- Summary -->
    <div style="margin-bottom:2rem;">
        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
            <span style="color:var(--text-secondary);">Сумма</span>
            <span style="font-weight:600;" id="summaryAmount">0.00 $</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem; <?php echo ($user['promo_bonus_percent'] ?? 0) > 0 ? '' : 'display:none;' ?>" id="promoBonusRow">
            <span style="color:var(--text-secondary);">Бонус</span>
            <span style="font-weight:600; color:var(--success);" id="summaryBonus">+0.00 $</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem; display:none;" id="cryptoBonusRow">
            <span style="color:var(--text-secondary);">Бонус за крипту +5%</span>
            <span style="font-weight:600; color:var(--accent);" id="summaryCrypto">+0.00 $</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem; <?php echo $user['first_deposit'] == 1 ? '' : 'display:none;' ?>" id="firstDepositRow">
            <span style="color:var(--text-secondary);">Первое пополнение +20%</span>
            <span style="font-weight:600; color:var(--accent);" id="summaryFirst">+0.00 $</span>
        </div>
        <div style="display:flex; justify-content:space-between; padding-top:0.75rem; border-top:2px solid var(--border); margin-top:0.75rem;">
            <span style="font-weight:700; font-size:1.1rem;">Итого на баланс</span>
            <span style="font-weight:800; font-size:1.3rem; color:var(--success);" id="summaryTotal">0.00 $</span>
        </div>
    </div>

    <!-- First Deposit Bonus -->
    <?php if ($user['first_deposit'] == 1): ?>
    <div style="background:rgba(255,165,0,0.1); border-radius:12px; padding:1rem; border:1px solid rgba(255,165,0,0.3); text-align:center; margin-bottom:2rem;">
        <div style="font-size:1.5rem; margin-bottom:0.5rem;">🎁</div>
        <div style="font-weight:700; color:var(--accent);">Бонус +20%</div>
        <div style="color:var(--text-secondary); font-size:0.85rem;">На первое пополнение!</div>
    </div>
    <?php endif; ?>
    
    <!-- Pay Button -->
    <button class="btn btn--success btn--lg" onclick="createPayment()" style="width:100%; font-size:1.1rem; padding:1rem;" id="payBtn">
        💳 Пополнить
    </button>

    <p style="text-align:center; margin-top:1rem; color:var(--text-muted); font-size:0.8rem;">
        Нажимая "Пополнить", вы принимаете <a href="#" style="color:var(--accent);">пользовательское соглашение</a>
    </p>
</div>

</div>

<script>
const USD_RATE = <?= $usdRate ?>;
let selectedMethod = '';
const isFirstDeposit = <?= $user['first_deposit'] == 1 ? 'true' : 'false' ?>;
const userPromoBonus = <?= floatval($user['promo_bonus_percent'] ?? 0) ?>;
const hasUserPromo = userPromoBonus > 0;
const promoCodes = <?= json_encode($promoCodes) ?>;

// Рендерим доступные платёжные методы
function renderPaymentMethods(methods) {
    const grid = document.getElementById('paymentMethodsGrid');
    const hint = document.getElementById('paymentMethodsHint');
    
    if (!methods || methods.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1;color:var(--text-muted);font-size:0.9rem;padding:1rem;text-align:center;">Способы оплаты временно недоступны. Обратитесь к администратору.</div>';
        hint.style.display = 'none';
        return;
    }
    
    hint.style.display = 'none';
    grid.innerHTML = methods.map(m => {
        const imgSrc = m.icon.endsWith('.svg') ? m.icon : m.icon;
        return `
        <div class="payment-method" data-method="${m.key}" onclick="selectPayment(this)" style="background:var(--bg-tertiary); border:2px solid var(--border); border-radius:12px; padding:1rem; cursor:pointer; text-align:center; transition:all .2s;">
            <img src="${imgSrc}" style="width:60px; height:auto; margin-bottom:0.5rem;" alt="${e(m.name)}">
            <div style="font-size:0.85rem; font-weight:600;">${e(m.name)}</div>
        </div>
    `;
    }).join('');
    
    // Select first method by default
    if (methods.length > 0) {
        const firstMethod = grid.querySelector('.payment-method');
        if (firstMethod) selectPayment(firstMethod);
    }
}

function e(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Загружаем методы оплаты при старте
fetch('/api/payment/methods.php')
    .then(r => r.json())
    .then(d => {
        if (d.success && d.methods) {
            renderPaymentMethods(d.methods);
        } else {
            renderPaymentMethods([]);
        }
    })
    .catch(() => renderPaymentMethods([]));

// Рендерим историю промокодов
function renderPromoHistory() {
    const container = document.getElementById('promoHistoryList');
    if (!container || promoCodes.length === 0) return;
    
    container.innerHTML = promoCodes.map(promo => {
        const isExpired = new Date(promo.expires_at) < new Date();
        const isActive = promo.used == 0 && !isExpired;
        return `
            <div style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem; background:<?= $isActive ? 'rgba(0,230,118,0.05)' : 'var(--bg-secondary)' ?>; border-radius:8px; border:1px solid <?= $isActive ? 'rgba(0,230,118,0.2)' : 'var(--border)' ?>;">
                <div>
                    <div style="font-family:monospace; font-size:0.95rem; font-weight:700; letter-spacing:1px; color:<?= $isActive ? 'var(--success)' : 'var(--text-muted)' ?>">
                        ${promo.code}
                    </div>
                    <div style="font-size:0.8rem; color:var(--text-secondary);">
                        +${parseFloat(promo.bonus_percent).toFixed(2)}% к пополнению
                    </div>
                </div>
                <div>
                    ${promo.used 
                        ? '<span class="badge badge--success">Использован</span>' 
                        : isExpired 
                            ? '<span class="badge badge--danger">Истёк</span>' 
                            : '<span class="badge badge--accent">Активен</span>'
                    }
                </div>
            </div>
        `;
    }).join('');
}

renderPromoHistory();

function applyPromo() {
    const code = document.getElementById('promoInput').value.trim().toUpperCase();
    if (!code) return notify('❌ Введите промокод', 'error');
    
    const btn = document.getElementById('promoBtn');
    const msgDiv = document.getElementById('promoMessage');
    btn.disabled = true;
    btn.textContent = '...';
    msgDiv.innerHTML = '<div style="padding:0.75rem; border-radius:8px; background:rgba(255,165,0,0.1); color:var(--accent);">⏳ Проверка...</div>';

    fetch('/api/battle_pass.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'redeem_promo', code: code })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            msgDiv.innerHTML = '<div style="padding:0.75rem; border-radius:8px; background:rgba(0,230,118,0.1); color:var(--success); font-weight:600;">✅ ' + d.message + '</div>';
            document.getElementById('promoInput').value = '';
            // Обновляем бонус на странице
            setTimeout(() => location.reload(), 1500);
        } else {
            msgDiv.innerHTML = '<div style="padding:0.75rem; border-radius:8px; background:rgba(255,82,82,0.1); color:var(--danger); font-weight:600;">❌ ' + d.error + '</div>';
            btn.disabled = false;
            btn.textContent = 'Активировать';
        }
    })
    .catch(() => {
        msgDiv.innerHTML = '<div style="padding:0.75rem; border-radius:8px; background:rgba(255,82,82,0.1); color:var(--danger); font-weight:600;">❌ Ошибка соединения</div>';
        btn.disabled = false;
        btn.textContent = 'Активировать';
    });
}

function selectPayment(el) {
    document.querySelectorAll('.payment-method').forEach(m => {
        m.style.borderColor = 'var(--border)';
        m.style.background = 'var(--bg-tertiary)';
    });
    el.style.borderColor = 'var(--accent)';
    el.style.background = 'rgba(255,165,0,0.1)';
    selectedMethod = el.dataset.method;
    updateBalance();
}

function setAmount(amount) {
    document.getElementById('depositAmount').value = amount;
    updateBalance();
}

function updateBalance() {
    const amountUsd = parseFloat(document.getElementById('depositAmount').value) || 0;
    const amountRub = amountUsd * USD_RATE;
    
    document.getElementById('rubAmount').textContent = amountRub.toFixed(2) + ' ₽';
    document.getElementById('summaryAmount').textContent = amountUsd.toFixed(2) + ' $';
    
    let bonus = 0;
    if (hasUserPromo) {
        bonus = amountUsd * (userPromoBonus / 100);
        document.getElementById('summaryBonus').parentElement.style.display = 'flex';
        document.getElementById('summaryBonus').parentElement.querySelector('span:first-child').textContent = 'Бонус BP +' + userPromoBonus.toFixed(2) + '%';
        document.getElementById('summaryBonus').textContent = '+' + bonus.toFixed(2) + ' $';
    } else {
        document.getElementById('summaryBonus').parentElement.style.display = 'none';
    }
    
    let cryptoBonus = 0;
    if (selectedMethod === 'crypto') {
        cryptoBonus = amountUsd * 0.05;
        document.getElementById('summaryCrypto').parentElement.style.display = 'flex';
        document.getElementById('summaryCrypto').textContent = '+' + cryptoBonus.toFixed(2) + ' $';
    } else {
        document.getElementById('summaryCrypto').parentElement.style.display = 'none';
    }
    
    let firstDeposit = 0;
    if (isFirstDeposit) {
        firstDeposit = amountUsd * 0.20;
        document.getElementById('summaryFirst').parentElement.style.display = 'flex';
        document.getElementById('summaryFirst').textContent = '+' + firstDeposit.toFixed(2) + ' $';
    } else {
        document.getElementById('summaryFirst').parentElement.style.display = 'none';
    }
    
    const total = amountUsd + bonus + cryptoBonus + firstDeposit;
    document.getElementById('summaryTotal').textContent = total.toFixed(2) + ' $';
}

function createPayment() {
    const amountUsd = parseFloat(document.getElementById('depositAmount').value);
    
    if (!amountUsd || amountUsd < 1) {
        return notify('Минимальная сумма: 1$', 'error');
    }

    if (!selectedMethod) {
        return notify('Выберите способ оплаты', 'error');
    }

    const btn = document.getElementById('payBtn');
    btn.textContent = 'Обработка...';
    btn.disabled = true;

    fetch('/api/payment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ 
            amount: amountUsd, 
            currency: 'USD',
            amount_rub: amountUsd * USD_RATE,
            method: selectedMethod 
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (d.payment_type === 'yoomoney') {
                // YooMoney — отправляем форму
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'https://yoomoney.ru/quickpay/confirm';
                
                const fields = [
                    { name: 'receiver', value: d.receiver },
                    { name: 'label', value: d.label },
                    { name: 'quickpay-form', value: 'button' },
                    { name: 'sum', value: d.sum },
                    { name: 'paymentType', value: 'AC' },
                    { name: 'successURL', value: d.success_url },
                    { name: 'failURL', value: d.fail_url || d.success_url.replace('success', 'failed') }
                ];
                
                fields.forEach(f => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = f.name;
                    input.value = f.value;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            } else if (d.checkout_url) {
                // FreeKassa, enot.io — редирект
                window.location.href = d.checkout_url;
            } else {
                notify('Ошибка: нет URL для оплаты', 'error');
                btn.textContent = '💳 Пополнить';
                btn.disabled = false;
            }
        } else {
            notify(d.error || 'Ошибка создания счёта', 'error');
            btn.textContent = '💳 Пополнить';
            btn.disabled = false;
        }
    })
    .catch(() => {
        notify('Ошибка соединения', 'error');
        btn.textContent = '💳 Пополнить';
        btn.disabled = false;
    });
}

// Select first method by default (handled by renderPaymentMethods)
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>