<?php
require_once __DIR__ . '/../../includes/functions.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = getCurrentUser();
$allSettings = getAllSettings();

// Получаем информацию о курсе валют
$currencyRate = '';
$currencyDate = '';
try {
    $stmt = db()->query("SELECT `value`, updated_at FROM settings WHERE `key` = 'usd_rub_rate'");
    $row = $stmt->fetch();
    if ($row) {
        $currencyRate = number_format((float)$row['value'], 2, '.', '.');
        $currencyDate = date('d.m.Y H:i', strtotime($row['updated_at']));
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(SITE_NAME) ?> — Skin Case Opening</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎰</text></svg>">
    <script>
        // Настройки валюты для JavaScript
        window.SITE_CURRENCY = '<?= e($allSettings['site_currency'] ?? 'USD') ?>';
        window.CURRENCY_SYMBOL = '<?= e($allSettings['currency_symbol'] ?? '$') ?>';
        window.USD_RATE = <?= getUsdRubRate() ?>;
    </script>
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()" style="display: none; background: none; border: none; color: var(--text-primary); font-size: 1.5rem; cursor: pointer;">☰</button>
            <a href="/index.php" class="header__logo">DropForge <span>SKIN MARKET</span></a>
        </div>
        <nav class="header__nav" id="mainNav">
            <a href="/index.php"       class="<?= $currentPage === 'index' ? 'active' : '' ?>">Кейсы</a>
            <?php if (($allSettings['upgrade_enabled'] ?? '1') === '1'): ?>
            <a href="/upgrade.php"     class="<?= $currentPage === 'upgrade' ? 'active' : '' ?>">Апгрейд</a>
            <?php endif; ?>
            <?php if (($allSettings['contract_enabled'] ?? '1') === '1'): ?>
            <a href="/contract.php"    class="<?= $currentPage === 'contract' ? 'active' : '' ?>">Контракт</a>
            <?php endif; ?>
            <?php if (($allSettings['battle_pass_enabled'] ?? '1') === '1'): ?>
            <a href="/battle_pass.php" class="<?= $currentPage === 'battle_pass' ? 'active' : '' ?>">Battle Pass</a>
            <?php endif; ?>
            <?php if (($allSettings['daily_bonus_enabled'] ?? '1') === '1'): ?>
            <a href="/daily_bonus.php" class="<?= $currentPage === 'daily_bonus' ? 'active' : '' ?>">Бонус</a>
            <?php endif; ?>
            <?php if (($allSettings['inventory_enabled'] ?? '1') === '1'): ?>
            <a href="/inventory.php"   class="<?= $currentPage === 'inventory' ? 'active' : '' ?>">Инвентарь</a>
            <?php endif; ?>
            <a href="/support.php"     class="<?= $currentPage === 'support' ? 'active' : '' ?>">Поддержка</a>
            <?php if (isAdmin()): ?>
                <a href="/admin/index.php">Админка</a>
            <?php endif; ?>
        </nav>
        <div class="header__user">
            <!-- Currency converter -->
            <div class="currency-converter" id="currencyConverter" style="position: relative;">
                <button class="currency-btn" onclick="toggleCurrencyMenu()" style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 20px; padding: 0.5rem 1rem; color: var(--text-primary); font-size: 0.85rem; cursor: pointer; font-weight: 500;">
                    <span>💱</span>
                    <span id="currencyDisplay">1$ = <?= $currencyRate ?>₽</span>
                </button>
                <div class="currency-menu" id="currencyMenu" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 0.5rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; min-width: 250px; z-index: 100;">
                    <div style="margin-bottom: 0.75rem; font-size: 0.85rem; color: var(--text-secondary);">
                        Курс обновлён: <strong><?= $currencyDate ?></strong>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: var(--bg-tertiary); border-radius: 6px;">
                            <span>$1 USD</span>
                            <strong><?= $currencyRate ?> RUB</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: var(--bg-tertiary); border-radius: 6px;">
                            <span>$10 USD</span>
                            <strong><?= number_format($currencyRate * 10, 0, '.', ' ') ?> RUB</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: var(--bg-tertiary); border-radius: 6px;">
                            <span>$50 USD</span>
                            <strong><?= number_format($currencyRate * 50, 0, '.', ' ') ?> RUB</strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($user): ?>
                <a href="/deposits.php" style="display:flex; align-items:center; gap:0.5rem; text-decoration:none; color:var(--success); font-weight:600; font-size:0.9rem; padding:0.5rem 1rem; background:var(--bg-tertiary); border-radius:20px; border:1px solid var(--border);">
                    💰 <?= formatMoney($user['balance']) ?>
                    <?php if (isset($user['promo_bonus_percent']) && $user['promo_bonus_percent'] > 0): ?>
                        <span style="background:var(--accent); color:#fff; font-size:0.7rem; padding:0.15rem 0.5rem; border-radius:10px; font-weight:700;">+<?= number_format($user['promo_bonus_percent'], 0) ?>%</span>
                    <?php endif; ?>
                </a>
                <div style="position:relative;">
                    <img src="<?= e($user['avatar'] ?: 'https://cdn.jsdelivr.net/gh/loganmcdaniel/loganmcdaniel/avatar.svg') ?>" class="header__avatar" alt="avatar" onclick="document.getElementById('userMenu').style.display = document.getElementById('userMenu').style.display === 'block' ? 'none' : 'block';" style="cursor:pointer;">
                    <div class="dropdown-menu" id="userMenu" style="display:none; position:absolute; top:100%; right:0; margin-top:0.5rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 0.5rem; min-width: 200px; z-index: 100;">
                        <a href="/profile.php" class="dropdown-menu__item">👤 Профиль</a>
                        <a href="/deposits.php" class="dropdown-menu__item">💰 Пополнить</a>
                        <a href="/transactions.php" class="dropdown-menu__item">📋 Транзакции</a>
                        <a href="/history.php" class="dropdown-menu__item">🎰 История</a>
                        <div style="border-top:1px solid var(--border); margin:0.5rem 0;"></div>
                        <a href="/api/auth?action=logout" class="dropdown-menu__item" style="color:var(--danger);">🚪 Выйти</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login.php" class="btn btn--primary btn--sm" style="display:flex; align-items:center; gap:0.5rem;">
                    <img src="/assets/images/icon_steam.png" style="width:18px; height:18px;" alt="Steam">
                    Войти
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="main">

