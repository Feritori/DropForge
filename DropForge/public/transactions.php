<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';
requireAuth();

$user = getCurrentUser();

$stmt = db()->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll();
?>

<h1>📋 История транзакций</h1>

<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Тип</th>
                <th>Сумма</th>
                <th>Описание</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></td>
                    <td style="text-transform:capitalize;"><?= e($t['type']) ?></td>
                    <td style="color:<?= $t['amount'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>; font-weight:600; white-space:nowrap;">
                        <?= $t['amount'] >= 0 ? '+' : '' ?><?= formatMoney($t['amount']) ?>
                    </td>
                    <td style="color:var(--text-secondary);"><?= e($t['description']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">Нет транзакций</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>