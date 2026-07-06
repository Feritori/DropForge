<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';

// Get all active cases
$stmt = db()->prepare("SELECT c.*, cat.name as category_name FROM cases c LEFT JOIN categories cat ON c.category_id = cat.id WHERE c.is_active = 1 ORDER BY c.created_at DESC");
$stmt->execute();
$cases = $stmt->fetchAll();
?>

<h1 style="margin-bottom:2rem;">Кейсы</h1>

<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:1.5rem;">
    <?php foreach ($cases as $case): ?>
        <div class="case-card" style="background:var(--bg-card); border-radius:12px; overflow:hidden; border:1px solid var(--border); transition:transform 0.2s, box-shadow 0.2s;">
            <a href="/case.php?id=<?= $case['id'] ?>" style="text-decoration:none; color:inherit;">
                <div style="padding:1.5rem; text-align:center; background:linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-card) 100%);">
                    <?php if (!empty($case['image_path'])): ?>
                        <img src="<?= e($case['image_path']) ?>" style="width:180px; height:140px; object-fit:contain;" alt="<?= e($case['name']) ?>">
                    <?php else: ?>
                        <div style="width:180px; height:140px; background:var(--bg-secondary); border-radius:8px; display:flex; align-items:center; justify-content:center; margin:0 auto;">📦</div>
                    <?php endif; ?>
                </div>
                <div style="padding:1.25rem;">
                    <h3 style="margin-bottom:0.5rem; font-size:1.1rem;"><?= e($case['name']) ?></h3>
                    <?php if ($case['category_name']): ?>
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.75rem;"><?= e($case['category_name']) ?></div>
                    <?php endif; ?>
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <span style="color:var(--success); font-weight:700; font-size:1.1rem;"><?= formatMoney($case['price']) ?></span>
                        <span class="btn btn--primary btn--sm">Открыть</span>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php if (empty($cases)): ?>
    <div style="text-align:center; padding:3rem; color:var(--text-muted);">
        <p style="font-size:1.2rem;">Кейсов пока нет</p>
        <?php if (isAdmin()): ?>
            <a href="/admin/index.php" class="btn btn--primary" style="margin-top:1rem;">Создать кейс</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

