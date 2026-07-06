<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';

$user = getCurrentUser();

// Get free cases (только если включены)
$freeCases = [];
if (getSetting('free_case_enabled', '1') === '1') {
    $stmt = db()->query("SELECT * FROM free_cases WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC");
    $freeCases = $stmt->fetchAll();

    // Add access info for each free case
    $userDeposit = $user ? getUserDepositLast24h($user['id']) : 0;
    foreach ($freeCases as &$case) {
        $case['can_open'] = $user ? $userDeposit >= (float)$case['min_deposit'] : false;
        $case['user_deposit'] = $userDeposit;
        $case['progress_percent'] = $case['min_deposit'] > 0 ? min(100, ($userDeposit / $case['min_deposit']) * 100) : 0;
    }
    unset($case);
}

// Get all paid cases with categories
$stmt = db()->prepare("
    SELECT DISTINCT c.*, cat.id as category_id, cat.name as category_name
    FROM cases c
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE c.is_active = 1
    ORDER BY cat.name, c.price ASC
");
$stmt->execute();
$allCases = $stmt->fetchAll();

// Group paid cases by category
$categories = [];
foreach ($allCases as $case) {
    $catId = $case['category_id'] ?? 0;
    $catName = $case['category_name'] ?? 'Другие';
    
    if (!isset($categories[$catId])) {
        $categories[$catId] = [
            'id' => $catId,
            'name' => $catName,
            'cases' => []
        ];
    }
    $categories[$catId]['cases'][] = $case;
}

// Sort categories by name
usort($categories, function($a, $b) {
    if ($a['id'] === 0) return 1;
    if ($b['id'] === 0) return -1;
    return strcmp($a['name'], $b['name']);
});

// Получаем последние 5 выигрышей для главной
$recentWins = [];
try {
    $tableCheck = db()->query("SHOW TABLES LIKE 'live_wins'")->fetch();
    if ($tableCheck) {
        $stmt = db()->query("
            SELECT lw.*, c.name as case_name
            FROM live_wins lw
            LEFT JOIN cases c ON lw.case_id = c.id
            ORDER BY lw.created_at DESC
            LIMIT 5
        ");
        $recentWins = $stmt->fetchAll();
        
        foreach ($recentWins as &$w) {
            $w['rarity_color'] = RAIRITY_COLORS[$w['rarity']] ?? '#888';
            $diff = time() - strtotime($w['created_at']);
            if ($diff < 60) $w['time_ago'] = 'только что';
            elseif ($diff < 3600) $w['time_ago'] = floor($diff / 60) . ' мин. назад';
            elseif ($diff < 86400) $w['time_ago'] = floor($diff / 3600) . ' ч. назад';
            else $w['time_ago'] = floor($diff / 86400) . ' д. назад';
        }
        unset($w);
    }
} catch (Exception $e) {}
?>

<!-- RECENT WINS -->
<?php if (!empty($recentWins)): ?>
    <section class="recent-wins">
        <div class="recent-wins__header">
            <h2 class="recent-wins__title">🏆 Последние выигрыши</h2>
            <a href="/history.php" class="recent-wins__view-all">Все выигрыши →</a>
        </div>
        <div class="recent-wins__grid">
            <?php foreach ($recentWins as $w): ?>
                <div class="recent-win-card">
                    <img src="<?= e($w['user_avatar'] ?: 'https://cdn.jsdelivr.net/gh/loganmcdaniel/loganmcdaniel/avatar.svg') ?>" class="recent-win-card__avatar" alt="" onerror="this.src='https://cdn.jsdelivr.net/gh/loganmcdaniel/loganmcdaniel/avatar.svg'">
                    <div class="recent-win-card__info">
                        <div class="recent-win-card__user"><?= e($w['username']) ?></div>
                        <div class="recent-win-card__item" style="color: <?= $w['rarity_color'] ?>"><?= e($w['item_name']) ?></div>
                    </div>
                    <div style="text-align: right;">
                        <div class="recent-win-card__price"><?= formatMoney($w['price']) ?></div>
                        <div class="recent-win-card__time"><?= $w['time_ago'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- FREE CASES SECTION -->
<?php if (!empty($freeCases)): ?>
    <h2 class="section-title">🎁 Бесплатные кейсы</h2>
    <div class="cases-grid">
        <?php foreach ($freeCases as $freeCase): ?>
            <div class="case-card" style="border: 2px solid <?= $freeCase['can_open'] ? '#4CAF50' : '#ff4444' ?>;" onclick="<?= $freeCase['can_open'] ? "location.href='/free_case.php?id=" . $freeCase['id'] . "'" : 'return false' ?>">
                <span class="case-card__badge" style="background:linear-gradient(135deg, #4CAF50, #45a049);">FREE</span>
                <img src="<?= e($freeCase['image_path'] ?: "/assets/images/default-case.png") ?>" class="case-card__image" alt="<?= e($freeCase['name']) ?>">
                <div class="case-card__body">
                    <div class="case-card__name"><?= e($freeCase['name']) ?></div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin:0.5rem 0;">
                        Депозит от: <strong style="color:var(--success);">$<?= number_format($freeCase['min_deposit'], 2) ?></strong>
                    </div>
                    <?php if ($user): ?>
                        <div style="font-size:0.7rem; color:var(--text-muted); margin-bottom:0.5rem;">
                            Ваш депозит: $<?= number_format($freeCase['user_deposit'], 2) ?>
                        </div>
                        <div style="background:var(--bg-tertiary); border-radius:4px; height:6px; overflow:hidden; margin-bottom:0.5rem;">
                            <div style="width:<?= $freeCase['progress_percent'] ?>%; background:<?= $freeCase['can_open'] ? '#4CAF50' : '#ff4444' ?>; height:100%; transition:width 0.3s;"></div>
                        </div>
                        <div style="text-align:center; font-size:0.75rem; color:<?= $freeCase['can_open'] ? '#4CAF50' : '#ff4444' ?>; font-weight:600;">
                            <?= $freeCase['can_open'] ? '✓ Доступно' : '✗ Недостаточно депозита' ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; font-size:0.75rem; color:var(--text-muted);">
                            <a href="/login.php" style="color:var(--accent);">Войдите</a> чтобы проверить доступ
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php foreach ($categories as $category): ?>
    <h2 class="section-title"><?= e($category['name']) ?></h2>
    <div class="cases-grid">
        <?php foreach ($category['cases'] as $case): ?>
            <div class="case-card" onclick="location.href='/case.php?id=<?= $case['id'] ?>'">
                <?php if ($case['price'] >= 50): ?>
                    <span class="case-card__badge" style="background:linear-gradient(135deg, #e4ae39, #de9b35);">LEGENDARY</span>
                <?php elseif ($case['price'] >= 10): ?>
                    <span class="case-card__badge" style="background:linear-gradient(135deg, #d32ce6, #8847ff);">EPIC</span>
                <?php elseif ($case['price'] <= 1): ?>
                    <span class="case-card__badge" style="background:linear-gradient(135deg, #4b69ff, #5e98d9);">BUDGET</span>
                <?php endif; ?>
                <img src="<?= e($case['image_path'] ?: "/assets/images/default-case.png") ?>" class="case-card__image" alt="<?= e($case['name']) ?>">
                <div class="case-card__body">
                    <div class="case-card__name"><?= e($case['name']) ?></div>
                    <div class="case-card__price"><?= formatMoney($case['price']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php if (empty($categories)): ?>
    <h2 class="section-title">🔥 Кейсы</h2>
    <div class="empty-state" style="text-align:center; padding:3rem; color:var(--text-muted);">
        <div class="empty-state__icon">📦</div>
        <div class="empty-state__text">Кейсы скоро появятся!</div>
        <?php if (isAdmin()): ?>
            <a href="/admin/index.php" class="btn btn--primary" style="margin-top:1rem;">Создать кейс</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- HOW IT WORKS -->
<h2 class="section-title">⚡ Как это работает</h2>
<div class="how-it-works">
    <div class="step-card">
        <div class="step-icon">🎫</div>
        <h3 class="step-title">1. Войди</h3>
        <p class="step-desc">Авторизуйся через Steam аккаунт</p>
    </div>
    <div class="step-card">
        <div class="step-icon">🎰</div>
        <h3 class="step-title">2. Открой кейс</h3>
        <p class="step-desc">Выбери кейс и крути рулетку</p>
    </div>
    <div class="step-card">
        <div class="step-icon">💎</div>
        <h3 class="step-title">3. Получи скин</h3>
        <p class="step-desc">Обменяй или выведи свои скины</p>
    </div>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

