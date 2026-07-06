<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';
requireAuth();

// Проверка: страница отключена?
if (getSetting('battle_pass_enabled', '1') !== '1') {
    redirect('/index.php');
}

$user = getCurrentUser();

// Get active season
$stmt = db()->prepare("SELECT * FROM battle_pass_seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$stmt->execute();
$season = $stmt->fetch();

if (!$season) {
    redirect('/index.php');
}

// Get user's battle pass progress
$stmt = db()->prepare("SELECT * FROM user_battle_pass WHERE user_id = ? AND season_id = ?");
$stmt->execute([$user['id'], $season['id']]);
$userBP = $stmt->fetch();

// Calculate XP needed for next level
$xpPerLevel = 1000;
$nextLevelXp = $userBP ? ($userBP['current_level'] + 1) * $xpPerLevel : $xpPerLevel;
$currentXp = $userBP ? $userBP['experience'] : 0;
$currentLevel = $userBP ? $userBP['current_level'] : 1;
$progress = $nextLevelXp > 0 ? (($currentXp - ($currentLevel - 1) * $xpPerLevel) / $xpPerLevel) * 100 : 0;

// Get rewards for this season
$stmt = db()->prepare("SELECT * FROM battle_pass_rewards WHERE season_id = ? ORDER BY level ASC");
$stmt->execute([$season['id']]);
$rewards = $stmt->fetchAll();

// Get claimed rewards
$claimedRewards = [];
if ($userBP) {
    $stmt = db()->prepare("SELECT reward_id FROM user_battle_pass_claims WHERE user_id = ? AND season_id = ?");
    $stmt->execute([$userBP['user_id'], $season['id']]);
    $claimed = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $claimedRewards = array_flip($claimed);
}

// Get available tasks
$stmt = db()->prepare("SELECT t.*, ut.progress, ut.completed FROM battle_pass_tasks t 
    LEFT JOIN user_battle_pass_tasks ut ON t.id = ut.task_id AND ut.user_id = ? AND ut.season_id = ?
    WHERE t.season_id = ?");
$stmt->execute([$user['id'], $season['id'], $season['id']]);
$tasks = $stmt->fetchAll();
?>

<style>
.bp-hero {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    border-radius: 20px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(108, 92, 231, 0.3);
    position: relative;
    overflow: hidden;
}
.bp-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(108, 92, 231, 0.15) 0%, transparent 70%);
    pointer-events: none;
}
.bp-level-badge {
    background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
    border-radius: 16px;
    padding: 1.5rem 2rem;
    text-align: center;
    box-shadow: 0 8px 32px rgba(108, 92, 231, 0.3);
}
.bp-level-badge__number {
    font-size: 3.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #fff 0%, #a29bfe 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
}
.bp-level-badge__label {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    margin-top: 0.5rem;
}
.bp-xp-card {
    background: rgba(255,255,255,0.05);
    border-radius: 16px;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
}
.bp-progress-track {
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    height: 20px;
    overflow: hidden;
    position: relative;
}
.bp-progress-fill {
    background: linear-gradient(90deg, #6c5ce7 0%, #00b894 50%, #00cec9 100%);
    height: 100%;
    border-radius: 12px;
    transition: width 0.5s ease;
    position: relative;
}
.bp-progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%);
    animation: shimmer 2s infinite;
}
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.bp-premium-btn {
    background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
    border: none;
    color: #000;
    font-weight: 700;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(243, 156, 18, 0.4);
}
.bp-premium-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(243, 156, 18, 0.5);
}
.bp-section {
    background: var(--bg-card);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid var(--border);
    margin-bottom: 2rem;
}
.bp-section__title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.bp-tasks-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
}
@media (max-width: 1400px) {
    .bp-tasks-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
@media (max-width: 1100px) {
    .bp-tasks-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 768px) {
    .bp-tasks-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 480px) {
    .bp-tasks-grid {
        grid-template-columns: 1fr;
    }
}
.bp-task-card {
    background: linear-gradient(135deg, rgba(108, 92, 231, 0.15) 0%, rgba(108, 92, 231, 0.05) 100%);
    border: 1px solid rgba(108, 92, 231, 0.3);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    min-height: 180px;
}
.bp-task-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
    border-radius: 4px 0 0 4px;
}
.bp-task-card:hover {
    border-color: var(--accent);
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(108, 92, 231, 0.3);
    background: linear-gradient(135deg, rgba(108, 92, 231, 0.2) 0%, rgba(108, 92, 231, 0.08) 100%);
}
.bp-task-card.completed {
    opacity: 0.7;
    background: linear-gradient(135deg, rgba(0, 184, 148, 0.15) 0%, rgba(0, 184, 148, 0.05) 100%);
    border-color: rgba(0, 184, 148, 0.3);
}
.bp-task-card.completed::before {
    background: linear-gradient(135deg, #00b894 0%, #55efc4 100%);
}
.bp-task-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    flex-shrink: 0;
    position: relative;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.bp-task-icon.pending {
    background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
    box-shadow: 0 4px 20px rgba(108, 92, 231, 0.4);
}
.bp-task-icon.completed {
    background: linear-gradient(135deg, #00b894 0%, #55efc4 100%);
    box-shadow: 0 4px 20px rgba(0, 184, 148, 0.4);
}
.bp-task-icon::after {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: inherit;
    border-radius: inherit;
    filter: blur(10px);
    opacity: 0.5;
    z-index: -1;
}
.bp-task-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.bp-task-title {
    font-weight: 600;
    font-size: 1rem;
    color: var(--text-primary);
    line-height: 1.4;
}
.bp-task-meta {
    font-size: 0.85rem;
    color: var(--text-muted);
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
    margin-top: auto;
}
.bp-task-xp {
    background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
    color: #fff;
    padding: 0.35rem 0.85rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    box-shadow: 0 2px 10px rgba(108, 92, 231, 0.3);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.bp-task-xp::before {
    content: '⭐';
    font-size: 0.9rem;
}
.bp-task-progress {
    flex: 1;
    min-width: 120px;
}
.bp-task-progress-bar {
    width: 100%;
    height: 8px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 0.5rem;
}
.bp-task-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6c5ce7 0%, #00b894 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
}
.bp-task-card.completed .bp-task-progress-fill {
    background: linear-gradient(90deg, #00b894 0%, #55efc4 100%);
}
.bp-task-progress-text {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: var(--text-muted);
}
.bp-rewards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1.25rem;
}
.bp-reward-card {
    background: var(--bg-tertiary);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    border: 2px solid var(--border);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}
.bp-reward-card:hover {
    transform: translateY(-5px);
    border-color: var(--accent);
}
.bp-reward-card.unlocked {
    cursor: pointer;
}
.bp-reward-card.unlocked:hover {
    box-shadow: 0 10px 40px rgba(108, 92, 231, 0.3);
}
.bp-reward-card.claimed {
    border-color: var(--success);
    background: rgba(0, 184, 148, 0.1);
}
.bp-reward-card.premium {
    border-color: #f39c12;
}
.bp-reward-card.premium::before {
    content: '👑 PREMIUM';
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
    color: #000;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}
.bp-reward-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}
.bp-reward-level {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}
.bp-reward-title {
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.bp-reward-value {
    color: var(--success);
    font-weight: 600;
    font-size: 0.9rem;
}
.bp-reward-claimed {
    color: var(--success);
    font-weight: 600;
    font-size: 0.85rem;
}
.bp-reward-locked {
    color: var(--text-muted);
    font-size: 0.85rem;
}
.bp-claim-btn {
    background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
    color: #fff;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    margin-top: 0.75rem;
    cursor: pointer;
    transition: all 0.3s;
}
.bp-claim-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(108, 92, 231, 0.4);
}
.bp-page-btn {
    background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-secondary) 100%);
    border: 1px solid var(--accent);
    color: var(--text-primary);
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}
.bp-page-btn:hover:not(:disabled) {
    background: var(--accent);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(108, 92, 231, 0.4);
}
.bp-page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    border-color: var(--border);
}
</style>

<div style="max-width:1200px; margin:0 auto;">
    <!-- Hero Section -->
    <div class="bp-hero">
        <div style="display:grid; grid-template-columns: auto 1fr auto; gap:2rem; align-items:center; position:relative; z-index:1;">
            <!-- Level Badge -->
            <div class="bp-level-badge">
                <div class="bp-level-badge__number"><?= $currentLevel ?></div>
                <div class="bp-level-badge__label">Уровень</div>
            </div>
            
            <!-- XP Progress -->
            <div class="bp-xp-card">
                <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
                    <span style="color:var(--text-muted); font-size:0.9rem;">Прогресс до <?= $currentLevel + 1 ?> уровня</span>
                    <span style="font-weight:700; color:#fff;"><?= number_format($currentXp) ?> / <?= number_format($nextLevelXp) ?> XP</span>
                </div>
                <div class="bp-progress-track">
                    <div class="bp-progress-fill" style="width:<?= min($progress, 100) ?>%;"></div>
                </div>
                <div style="text-align:center; margin-top:0.75rem; font-size:0.85rem; color:var(--text-muted);">
                    ⭐ <?= number_format($xpPerLevel) ?> XP за уровень
                </div>
            </div>
            
            <!-- Premium Status -->
            <div>
                <?php if ($userBP && $userBP['is_premium']): ?>
                    <div style="text-align:center;">
                        <div style="font-size:3rem;">👑</div>
                        <div style="font-weight:700; color:#f39c12;">Premium активен</div>
                        <div style="font-size:0.85rem; color:var(--text-muted);">Все привилегии разблокированы</div>
                    </div>
                <?php else: ?>
                    <button class="bp-premium-btn" onclick="buyBattlePass()">
                        👑 Купить Premium<br>
                        <span style="font-size:0.9rem; font-weight:600;"><?= number_format($season['price'], 2) ?>$</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tasks Section -->
    <div class="bp-section">
        <div class="bp-section__title">
            <span>📋</span>
            <span>Задания сезона</span>
            <span style="font-size:0.9rem; color:var(--text-muted); font-weight:400; margin-left:auto;">
                Страница <span id="tasksCurrentPageNum">1</span> из <span id="tasksTotalPagesNum">1</span>
            </span>
        </div>
        
        <!-- Pagination Controls -->
        <div style="display:flex; justify-content:center; gap:1rem; margin-bottom:1.5rem;">
            <button id="tasksPrevPageBtn" class="bp-page-btn" onclick="changeTasksPage(-1)" style="background:var(--bg-tertiary); border:1px solid var(--border); color:var(--text-primary); padding:0.5rem 1rem; border-radius:8px; cursor:pointer; transition:all 0.3s;" disabled>
                ← Назад
            </button>
            <div id="tasksPageIndicators" style="display:flex; gap:0.5rem; align-items:center;"></div>
            <button id="tasksNextPageBtn" class="bp-page-btn" onclick="changeTasksPage(1)" style="background:var(--bg-tertiary); border:1px solid var(--border); color:var(--text-primary); padding:0.5rem 1rem; border-radius:8px; cursor:pointer; transition:all 0.3s;">
                Вперёд →
            </button>
        </div>
        
        <div id="tasksContainer">
            <!-- Задания будут загружены через JS -->
        </div>
    </div>

    <!-- Rewards Section -->
    <div class="bp-section">
        <div class="bp-section__title">
            <span>🎁</span>
            <span>Награды</span>
            <span style="font-size:0.9rem; color:var(--text-muted); font-weight:400; margin-left:auto;">
                Страница <span id="currentPageNum">1</span> из <span id="totalPagesNum">1</span>
            </span>
        </div>
        
        <!-- Pagination Controls -->
        <div style="display:flex; justify-content:center; gap:1rem; margin-bottom:1.5rem;">
            <button id="prevPageBtn" class="bp-page-btn" onclick="changePage(-1)" style="background:var(--bg-tertiary); border:1px solid var(--border); color:var(--text-primary); padding:0.5rem 1rem; border-radius:8px; cursor:pointer; transition:all 0.3s;" disabled>
                ← Назад
            </button>
            <div id="pageIndicators" style="display:flex; gap:0.5rem; align-items:center;"></div>
            <button id="nextPageBtn" class="bp-page-btn" onclick="changePage(1)" style="background:var(--bg-tertiary); border:1px solid var(--border); color:var(--text-primary); padding:0.5rem 1rem; border-radius:8px; cursor:pointer; transition:all 0.3s;">
                Вперёд →
            </button>
        </div>
        
        <div class="bp-rewards-grid" id="rewardsGrid">
            <!-- Награды будут загружены через JS -->
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

<script>
// Данные для заданий
const tasksData = [];
<?php foreach ($tasks as $t): ?>
tasksData.push({
    id: <?= $t['id'] ?>,
    task_description: '<?= addslashes($t['task_description']) ?>',
    target_value: <?= $t['target_value'] ?>,
    experience_reward: <?= $t['experience_reward'] ?>,
    is_repeatable: <?= $t['is_repeatable'] ? '1' : '0' ?>,
    progress: <?= $t['progress'] ?? 0 ?>,
    completed: <?= $t['completed'] ? '1' : '0' ?>
});
<?php endforeach; ?>

const tasksPerPage = 5;
let tasksCurrentPage = 1;

function renderTasks() {
    const totalPages = Math.ceil(tasksData.length / tasksPerPage) || 1;
    const startIdx = (tasksCurrentPage - 1) * tasksPerPage;
    const endIdx = Math.min(startIdx + tasksPerPage, tasksData.length);
    const pageTasks = tasksData.slice(startIdx, endIdx);
    
    // Обновляем индикаторы страниц
    document.getElementById('tasksCurrentPageNum').textContent = tasksCurrentPage;
    document.getElementById('tasksTotalPagesNum').textContent = totalPages;
    document.getElementById('tasksPrevPageBtn').disabled = tasksCurrentPage === 1;
    document.getElementById('tasksNextPageBtn').disabled = tasksCurrentPage === totalPages;
    
    // Рендерим индикаторы страниц
    let indicatorsHtml = '';
    for (let i = 1; i <= totalPages; i++) {
        indicatorsHtml += `<span onclick="goToTasksPage(${i})" style="width:10px; height:10px; border-radius:50%; background:${i === tasksCurrentPage ? 'var(--accent)' : 'var(--border)'}; cursor:pointer; transition:all 0.3s;" title="Страница ${i}"></span>`;
    }
    document.getElementById('tasksPageIndicators').innerHTML = indicatorsHtml;
    
    // Рендерим задания
    if (pageTasks.length === 0) {
        document.getElementById('tasksContainer').innerHTML = `
            <div style="text-align:center; padding:3rem; color:var(--text-muted);">
                <div style="font-size:3rem; margin-bottom:1rem;">🎯</div>
                <div>Задания появятся здесь вскоре после начала сезона</div>
            </div>
        `;
        return;
    }
    
    let html = '<div class="bp-tasks-grid">';
    pageTasks.forEach(task => {
        const isCompleted = task.completed == 1;
        const progressPercent = Math.min((task.progress / task.target_value) * 100, 100);
        
        html += `
            <div class="bp-task-card ${isCompleted ? 'completed' : ''}">
                <div class="bp-task-content">
                    <div class="bp-task-title">${escapeHtml(task.task_description)}</div>
                    <div class="bp-task-progress">
                        <div class="bp-task-progress-text">
                            <span>📊 Прогресс</span>
                            <span>${task.progress} / ${task.target_value}</span>
                        </div>
                        <div class="bp-task-progress-bar">
                            <div class="bp-task-progress-fill" style="width: ${progressPercent}%"></div>
                        </div>
                    </div>
                    <div class="bp-task-meta">
                        <span class="bp-task-xp">+${task.experience_reward} XP</span>
                        ${task.is_repeatable == 1 ? '<span style="color:var(--text-muted); font-size:0.8rem;">🔄 Повторяемое</span>' : ''}
                        ${isCompleted && task.is_repeatable == 0 ? '<span style="color:var(--success); font-weight:600; font-size:0.8rem;">✓ Выполнено</span>' : ''}
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    document.getElementById('tasksContainer').innerHTML = html;
}

function changeTasksPage(delta) {
    const totalPages = Math.ceil(tasksData.length / tasksPerPage) || 1;
    const newPage = tasksCurrentPage + delta;
    if (newPage >= 1 && newPage <= totalPages) {
        tasksCurrentPage = newPage;
        renderTasks();
    }
}

function goToTasksPage(page) {
    tasksCurrentPage = page;
    renderTasks();
}

// Данные для пагинации наград
const rewardsData = [];
<?php foreach ($rewards as $r): ?>
rewardsData.push({
    id: <?= $r['id'] ?>,
    level: <?= $r['level'] ?>,
    reward_type: '<?= addslashes($r['reward_type']) ?>',
    reward_value: '<?= addslashes($r['reward_value']) ?>',
    reward_description: '<?= addslashes($r['reward_description']) ?>',
    is_premium_only: <?= $r['is_premium_only'] ? '1' : '0' ?>,
    claimed: <?= isset($claimedRewards[$r['id']]) ? 'true' : 'false' ?>
});
<?php endforeach; ?>

const maxLevel = <?= $season['max_level'] ?>;
const currentLevel = <?= $currentLevel ?>;
const userPremium = <?= ($userBP && $userBP['is_premium']) ? 'true' : 'false' ?>;
const rewardsPerPage = 4;
let currentPage = 1;

function getRewardsForLevel(level) {
    return rewardsData.filter(r => r.level == level);
}

function isLevelUnlocked(level) {
    return currentLevel >= level;
}

function isLevelClaimed(level) {
    const rewards = getRewardsForLevel(level);
    if (rewards.length === 0) return false;
    return rewards.every(r => r.claimed);
}

function getRewardIcon(type) {
    const icons = {
        'balance': '💰',
        'case': '📦',
        'promo': '🎁',
        'premium': '👑'
    };
    return icons[type] || '🎁';
}

function getRewardValueHtml(reward) {
    if (reward.reward_type === 'balance') {
        return `<div class="bp-reward-value">${parseFloat(reward.reward_value).toFixed(2)}$</div>`;
    } else if (reward.reward_type === 'promo') {
        return `<div class="bp-reward-value">+${Math.round(reward.reward_value)}% к пополнению</div>`;
    } else if (reward.reward_type === 'case') {
        return `<div class="bp-reward-value">📦 Билет на кейс</div>`;
    }
    return '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderRewards() {
    const startLevel = (currentPage - 1) * rewardsPerPage + 1;
    const endLevel = Math.min(startLevel + rewardsPerPage - 1, maxLevel);
    const totalPages = Math.ceil(maxLevel / rewardsPerPage);
    
    // Обновляем индикаторы страниц
    document.getElementById('currentPageNum').textContent = currentPage;
    document.getElementById('totalPagesNum').textContent = totalPages;
    document.getElementById('prevPageBtn').disabled = currentPage === 1;
    document.getElementById('nextPageBtn').disabled = currentPage === totalPages;
    
    // Рендерим индикаторы страниц
    let indicatorsHtml = '';
    for (let i = 1; i <= totalPages; i++) {
        indicatorsHtml += `<span onclick="goToPage(${i})" style="width:10px; height:10px; border-radius:50%; background:${i === currentPage ? 'var(--accent)' : 'var(--border)'}; cursor:pointer; transition:all 0.3s;" title="Страница ${i}"></span>`;
    }
    document.getElementById('pageIndicators').innerHTML = indicatorsHtml;
    
    // Рендерим награды
    let html = '';
    for (let level = startLevel; level <= endLevel; level++) {
        const levelRewards = getRewardsForLevel(level);
        const isUnlocked = isLevelUnlocked(level);
        const isClaimed = isLevelClaimed(level);
        const firstReward = levelRewards[0] || null;
        const isPremium = firstReward && firstReward.is_premium_only;
        const hasRewards = levelRewards.length > 0;
        
        let rewardIcon = '🔒';
        if (hasRewards) {
            rewardIcon = getRewardIcon(firstReward.reward_type);
        }
        
        let cardClass = 'bp-reward-card';
        if (isUnlocked) cardClass += ' unlocked';
        if (isClaimed) cardClass += ' claimed';
        if (isPremium) cardClass += ' premium';
        
        const onClick = isUnlocked && !isClaimed && hasRewards ? `onclick="claimLevelRewards(${level})"` : '';
        
        html += `
            <div class="${cardClass}" ${onClick}>
                <div class="bp-reward-icon">${hasRewards ? rewardIcon : '🔒'}</div>
                <div class="bp-reward-level">Уровень ${level}</div>
                ${isClaimed ? `
                    <div class="bp-reward-claimed">✓ Получено</div>
                ` : (hasRewards ? `
                    <div class="bp-reward-title">${escapeHtml(firstReward.reward_description)}</div>
                    ${getRewardValueHtml(firstReward)}
                    ${levelRewards.length > 1 ? `<div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.5rem;">+ ещё ${levelRewards.length - 1} наград</div>` : ''}
                    ${isUnlocked ? `<button class="bp-claim-btn">Забрать</button>` : ''}
                ` : `
                    <div class="bp-reward-locked">🔒 Недоступно</div>
                `)}
            </div>
        `;
    }
    
    document.getElementById('rewardsGrid').innerHTML = html;
}

function changePage(delta) {
    const totalPages = Math.ceil(maxLevel / rewardsPerPage);
    const newPage = currentPage + delta;
    if (newPage >= 1 && newPage <= totalPages) {
        currentPage = newPage;
        renderRewards();
    }
}

function goToPage(page) {
    currentPage = page;
    renderRewards();
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    console.log('BP Debug: maxLevel=', maxLevel, 'currentLevel=', currentLevel, 'rewardsData.length=', rewardsData.length);
    renderTasks();
    renderRewards();
});

function buyBattlePass() {
    Modal.confirm(
        'Premium Battle Pass',
        'Купить Premium за <?= number_format($season['price'], 2) ?>$?\nСписано с баланса.',
        function() {
            fetch(SITE_URL + '/api/battle_pass.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'buy' })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Modal.alert('✅ Успешно!', 'Premium Battle Pass активирован!', '👑');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Modal.alert('Ошибка', d.error || 'Не удалось приобрести', '❌');
                }
            });
        },
        '👑'
    );
}

function claimLevelRewards(level) {
    fetch(SITE_URL + '/api/battle_pass.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'claim_level', level: level })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (d.promo_codes && d.promo_codes.length > 0) {
                let codesHtml = d.promo_codes.map(pc => 
                    '<div style="font-family:monospace; font-size:1.2rem; font-weight:700; letter-spacing:2px; color:var(--success); background:var(--bg-tertiary); padding:0.5rem 1rem; border-radius:8px; border:2px dashed var(--success); margin:0.5rem 0;">' + pc.code + '</div>' +
                    '<div style="font-size:0.85rem; color:var(--text-muted);">+' + pc.bonus_percent + '% к пополнению</div>'
                ).join('');
                Modal.alert(
                    '🎁 Промокоды получены!',
                    '<div style="text-align:center;">' + codesHtml + 
                    '<div style="margin-top:1rem; color:var(--text-muted); font-size:0.85rem;">Действуют 48 часов. Активируй на странице <a href="/deposits.php" style="color:var(--accent);">Пополнения</a></div>' +
                    '</div>',
                    '🎁',
                    function() { location.reload(); }
                );
            } else {
                Modal.alert('✅ Награды получены!', 'Уровень ' + level + ' разблокирован!', '🎁', function() { location.reload(); });
            }
        } else {
            Modal.alert('Ошибка', d.error || 'Не удалось получить награды', '❌');
        }
    });
}
</script>

