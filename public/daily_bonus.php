<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';
requireAuth();

// Проверка: страница отключена?
if (getSetting('daily_bonus_enabled', '1') !== '1') {
    redirect('/index.php');
}

$user = getCurrentUser();
if (!$user) {
    header('Location: /login.php');
    exit;
}

// Check when user last claimed bonus
$stmt = db()->prepare("SELECT claimed_at FROM user_daily_bonus WHERE user_id = ? ORDER BY claimed_at DESC LIMIT 1");
$stmt->execute([$user['id']]);
$lastClaim = $stmt->fetch();

$canClaimDaily = false;
$timeUntilNext = 0;

if (!$lastClaim) {
    $canClaimDaily = true;
} else {
    $lastTime = strtotime($lastClaim['claimed_at']);
    $now = time();
    $diff = $now - $lastTime;
    if ($diff >= 86400) { // 24 hours
        $canClaimDaily = true;
    } else {
        $timeUntilNext = 86400 - $diff;
    }
}

// Get user's bonus history
$stmt = db()->prepare("
    SELECT ub.amount, ub.claimed_at, ub.bonus_id
    FROM user_daily_bonus ub
    WHERE ub.user_id = ?
    ORDER BY ub.claimed_at DESC
    LIMIT 20
");
$stmt->execute([$user['id']]);
$bonusHistory = $stmt->fetchAll();

// Get user's total deposit for skin conditions
$stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) as total_deposit FROM transactions WHERE user_id = ? AND type = 'deposit' AND amount > 0");
$stmt->execute([$user['id']]);
$totalDeposit = (float)($stmt->fetch()['total_deposit'] ?? 0);

// Get min deposit timestamp
$stmt = db()->prepare("SELECT MAX(created_at) as last_deposit_time FROM transactions WHERE user_id = ? AND type = 'deposit' AND amount > 0");
$stmt->execute([$user['id']]);
$lastDepositTime = $stmt->fetch()['last_deposit_time'] ?? null;

$depositWithin24h = false;
if ($lastDepositTime) {
    $depositAge = time() - strtotime($lastDepositTime);
    $depositWithin24h = ($depositAge < 86400);
}
?>

<div class="daily-bonus-page">
    <h1 class="page-title">🎁 Ежедневный бонус</h1>
    
    <div class="daily-bonus-main">
        <!-- Bonus Card -->
        <div class="daily-bonus-hero">
            <div class="daily-bonus-hero__header">
                <h2><?= e($dailyBonus['name']) ?></h2>
                <p style="color: var(--text-secondary); margin-top: 0.5rem;"><?= e($dailyBonus['description']) ?></p>
            </div>
            
            <div class="daily-bonus-hero__content">
                <div class="daily-bonus-hero__image">
                    <img src="/assets/images/freebonus.png" alt="Daily Bonus" style="width: 20rem; margin-bottom: -4rem;">
                </div>
                
                <div class="daily-bonus-hero__info">
                    <div class="bonus-info-grid">
                        <div class="bonus-info-item">
                            <div class="bonus-info-label">Награда</div>
                            <div class="bonus-info-value" style="color: var(--success);">
                                Случайная награда
                            </div>
                        </div>
                        <div class="bonus-info-item">
                            <div class="bonus-info-label">Кулдаун</div>
                            <div class="bonus-info-value">24 часов</div>
                        </div>
                    </div>
                    
                    <?php if ($canClaimDaily): ?>
                        <button class="btn btn--primary btn--lg daily-bonus-hero__btn" onclick="claimDailyBonus()" id="claimBonusBtn">
                            🎁 ОТКРЫТЬ БОНУС
                        </button>
                    <?php else: ?>
                        <div class="daily-bonus-hero__cooldown">
                            <div style="color: var(--text-muted); margin-bottom: 0.5rem;">Следующий бонус доступен через:</div>
                            <div class="daily-bonus-hero__timer" id="bonusTimer" data-seconds="<?= $timeUntilNext ?>">
                                <?= floor($timeUntilNext / 3600) ?>ч <?= floor(($timeUntilNext % 3600) / 60) ?>м <?= $timeUntilNext % 60 ?>с
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Bonus History -->
        <div class="daily-bonus-history">
            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                📊 История получения бонусов
            </h3>
            
            <?php if (!empty($bonusHistory)): ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Сумма</th>
                                <th>Тип</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bonusHistory as $history): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($history['claimed_at'])) ?></td>
                                    <td style="color: var(--success); font-weight: 600;"><?= formatMoney($history['amount']) ?></td>
                                    <td>Ежедневный бонус</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🎁</div>
                    <div>Вы ещё не получали ежедневный бонус</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.daily-bonus-page {
    max-width: 1000px;
    margin: 0 auto;
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.daily-bonus-main {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.daily-bonus-hero {
    background: linear-gradient(180deg, #1a1040 0%, var(--bg-card) 100%);
    border-radius: 16px;
    border: 1px solid rgba(138, 43, 226, 0.3);
    overflow: hidden;
    box-shadow: 0 4px 30px rgba(138, 43, 226, 0.15);
}

.daily-bonus-hero__header {
    padding: 1.5rem;
    background: rgba(138, 43, 226, 0.1);
    border-bottom: 1px solid rgba(138, 43, 226, 0.2);
}

.daily-bonus-hero__header h2 {
    font-size: 1.5rem;
    font-weight: 700;
}

.daily-bonus-hero__content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    padding: 2rem;
}

.daily-bonus-hero__image {
    display: flex;
    align-items: center;
    justify-content: center;
}

.daily-bonus-hero__image img {
    max-width: 250px;
    height: auto;
    filter: drop-shadow(0 0 20px rgba(138, 43, 226, 0.4));
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.daily-bonus-hero__info {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.bonus-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 2rem;
}

.bonus-info-item {
    background: var(--bg-tertiary);
    padding: 1rem;
    border-radius: 10px;
    border: 1px solid var(--border);
    text-align: center;
}

.bonus-info-label {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
}

.bonus-info-value {
    font-size: 1.2rem;
    font-weight: 700;
}

.daily-bonus-hero__btn {
    width: 100%;
    background: linear-gradient(135deg, #8a2be2, #a855f7);
    border: none;
    padding: 1.2rem;
    font-weight: 700;
    font-size: 1.1rem;
    letter-spacing: 1px;
    box-shadow: 0 4px 20px rgba(138, 43, 226, 0.4);
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 4px 20px rgba(138, 43, 226, 0.4); }
    50% { box-shadow: 0 6px 30px rgba(138, 43, 226, 0.6); }
}

.daily-bonus-hero__btn:hover {
    background: linear-gradient(135deg, #9b3ff5, #b866ff);
    transform: translateY(-2px);
}

.daily-bonus-hero__cooldown {
    text-align: center;
    padding: 1.5rem;
    background: var(--bg-tertiary);
    border-radius: 12px;
    border: 1px solid var(--border);
}

.daily-bonus-hero__timer {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--accent), #a855f7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-top: 0.5rem;
}

.daily-bonus-history {
    background: var(--bg-card);
    border-radius: 16px;
    border: 1px solid var(--border);
    padding: 2rem;
}

@media (max-width: 768px) {
    .daily-bonus-hero__content {
        grid-template-columns: 1fr;
    }
    .bonus-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function claimDailyBonus() {
    const btn = document.getElementById('claimBonusBtn');
    if (!btn || btn.disabled) return;
    
    btn.disabled = true;
    btn.innerHTML = '⏳ ОТКРЫВАЕМ...';
    
    fetch(SITE_URL + '/admin/api.php?action=daily_bonus_claim', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const reward = data.reward;
            let message = '🎁 Получено: ' + reward.text;
            if (reward.amount > 0) {
                message += ' ($' + reward.amount.toFixed(2) + ' на баланс)';
            }
            Modal.alert('🎁 Ежедневный бонус', message, '🎁');
            setTimeout(() => location.reload(), 1500);
        } else {
            Modal.alert('Ошибка', data.error || 'Не удалось получить бонус', '❌');
            btn.disabled = false;
            btn.innerHTML = '🎁 ОТКРЫТЬ БОНУС';
        }
    })
    .catch(err => {
        Modal.alert('Ошибка', err.message, '❌');
        btn.disabled = false;
        btn.innerHTML = '🎁 ОТКРЫТЬ БОНУС';
    });
}

function updateDailyBonusTimer() {
    const timerEl = document.getElementById('bonusTimer');
    if (!timerEl) return;
    
    let totalSeconds = parseInt(timerEl.dataset.seconds || 0);
    if (totalSeconds <= 0) {
        location.reload();
        return;
    }
    
    totalSeconds--;
    timerEl.dataset.seconds = totalSeconds;
    
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    
    timerEl.textContent = hours + 'ч ' + minutes + 'м ' + seconds + 'с';
}

document.addEventListener('DOMContentLoaded', () => {
    const timerEl = document.getElementById('bonusTimer');
    if (timerEl && timerEl.dataset.seconds) {
        setInterval(updateDailyBonusTimer, 1000);
    }
});
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
