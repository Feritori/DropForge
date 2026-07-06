<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$user = getCurrentUser();

// Get stats
$statsStmt = db()->prepare("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM cases) as total_cases,
        (SELECT SUM(balance) FROM users) as total_balance,
        (SELECT COUNT(*) FROM case_open_history) as total_opens,
        (SELECT SUM(amount) FROM transactions WHERE type = 'deposit') as total_deposits,
        (SELECT SUM(amount) FROM transactions WHERE type = 'sell') as total_sales
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

$section = $_GET['section'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ — <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <a href="/index.php" class="header__logo">DropForge <span>ADMIN</span></a>
        <div class="header__user">
            <span style="color:var(--text-secondary);"><?= e($user['username']) ?></span>
            <a href="/api/auth?action=logout" class="btn btn--danger btn--sm">Выйти</a>
        </div>
    </header>

    <div class="admin-layout">
        <!-- SIDEBAR -->
        <nav class="admin-sidebar">
            <a href="?section=dashboard"      class="admin-sidebar__item <?= $section === 'dashboard' ? 'active' : '' ?>">📊 Дашборд</a>
            <a href="?section=cases"          class="admin-sidebar__item <?= $section === 'cases' ? 'active' : '' ?>">📦 Кейсы</a>
            <a href="?section=case_items"     class="admin-sidebar__item <?= $section === 'case_items' ? 'active' : '' ?>">🎯 Предметы кейсов</a>
            <a href="?section=users"          class="admin-sidebar__item <?= $section === 'users' ? 'active' : '' ?>">👥 Пользователи</a>
            <a href="?section=transactions"   class="admin-sidebar__item <?= $section === 'transactions' ? 'active' : '' ?>">💰 Транзакции</a>
            <a href="?section=history"        class="admin-sidebar__item <?= $section === 'history' ? 'active' : '' ?>">📜 История открытий</a>
            <a href="?section=settings"       class="admin-sidebar__item <?= $section === 'settings' ? 'active' : '' ?>">⚙️ Настройки</a>
            <a href="?section=referrals"      class="admin-sidebar__item <?= $section === 'referrals' ? 'active' : '' ?>">🔗 Рефералы</a>
            <a href="?section=payment"        class="admin-sidebar__item <?= $section === 'payment' ? 'active' : '' ?>">💳 Платёжка</a>
        </nav>

        <!-- CONTENT -->
        <div class="admin-content">
