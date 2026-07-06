<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';
requireAuth();

$user = getCurrentUser();

$stmt = db()->prepare("SELECT h.*, c.name as case_name FROM case_open_history h LEFT JOIN cases c ON h.case_id = c.id WHERE h.user_id = ? ORDER BY h.created_at DESC LIMIT 100");
$stmt->execute([$user['id']]);
$history = $stmt->fetchAll();
?>

<h1>🎰 История открытий</h1>

<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Кейс</th>
                <th>Предмет</th>
                <th>Редкость</th>
                <th>Цена</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td>
                    <td><?= e($h['case_name'] ?? '—') ?></td>
                    <td><?= e($h['item_name']) ?></td>
                    <td><span class="badge badge--<?= e($h['rarity']) ?>"><?= rarityLabel($h['rarity']) ?></span></td>
                    <td style="color:var(--success); white-space:nowrap;"><?= formatMoney($h['price']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
                <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">Нет открытий</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>