<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/layout.php';
requireAuth();

$user = getCurrentUser();
$action = $_GET['action'] ?? '';
$ticketId = (int)($_GET['id'] ?? 0);

// Создание нового тикета
if ($action === 'new') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        
        if (!empty($subject) && !empty($message)) {
            $stmt = db()->prepare("
                INSERT INTO support_tickets (user_id, subject, message, priority)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user['id'], $subject, $message, $priority]);
            header('Location: /support.php');
            exit;
        }
    }
}

// Получение тикетов
$stmt = db()->prepare("
    SELECT st.*, 
           (SELECT COUNT(*) FROM support_messages WHERE ticket_id = st.id) as message_count
    FROM support_tickets st
    WHERE st.user_id = ?
    ORDER BY st.updated_at DESC
");
$stmt->execute([$user['id']]);
$tickets = $stmt->fetchAll();

// Просмотр тикета
$ticket = null;
$messages = [];
if ($ticketId > 0) {
    $stmt = db()->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticketId, $user['id']]);
    $ticket = $stmt->fetch();
    
    if ($ticket) {
        $stmt = db()->prepare("
            SELECT sm.*, u.username
            FROM support_messages sm
            LEFT JOIN users u ON sm.user_id = u.id
            WHERE sm.ticket_id = ? AND sm.user_id = ?
            ORDER BY sm.created_at ASC
        ");
        $stmt->execute([$ticketId, $user['id']]);
        $messages = $stmt->fetchAll();
    }
}
?>

<style>
.support-page { max-width: 900px; margin: 0 auto; }
.support-header { margin-bottom: 2rem; }
.support-header h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; }
.support-header p { color: var(--text-secondary); }

.ticket-list { display: flex; flex-direction: column; gap: 1rem; }
.ticket-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.25rem;
    cursor: pointer;
    transition: all 0.2s;
}
.ticket-card:hover { border-color: var(--accent); transform: translateY(-2px); }
.ticket-card__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.ticket-card__subject { font-weight: 600; font-size: 1.05rem; }
.ticket-card__meta { display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-secondary); }
.ticket-status {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;
}
.ticket-status.open { background: rgba(0, 230, 118, 0.15); color: #00e676; }
.ticket-status.pending { background: rgba(255, 193, 7, 0.15); color: #ffc107; }
.ticket-status.closed { background: rgba(255, 82, 82, 0.15); color: #ff5252; }
.ticket-priority {
    display: inline-flex; align-items: center; gap: 0.25rem;
    padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600;
}
.ticket-priority.high { background: rgba(255, 82, 82, 0.2); color: #ff5252; }
.ticket-priority.medium { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
.ticket-priority.low { background: rgba(136, 146, 176, 0.2); color: #8892b0; }

.ticket-form { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 2rem; }
.ticket-form h2 { margin-bottom: 1.5rem; font-size: 1.3rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 0.75rem 1rem; background: var(--bg-tertiary);
    border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);
    font-size: 0.95rem;
}
.form-group textarea { min-height: 120px; resize: vertical; }

.ticket-view { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.ticket-view__header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
.ticket-view__body { padding: 1.5rem; }
.message-list { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem; }
.message { padding: 1rem; border-radius: 8px; }
.message.user { background: var(--bg-tertiary); border-left: 3px solid var(--accent); }
.message.admin { background: rgba(0, 230, 118, 0.05); border-left: 3px solid #00e676; }
.message__header { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.85rem; }
.message__author { font-weight: 600; }
.message__time { color: var(--text-muted); }
.message__text { line-height: 1.5; white-space: pre-wrap; }
.message-input { display: flex; gap: 0.75rem; }
.message-input input { flex: 1; }

.empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
.empty-state__icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.4; }

.btn-new-ticket {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.75rem 1.5rem; background: var(--accent); color: #fff;
    border-radius: 8px; font-weight: 600; text-decoration: none;
    transition: all 0.2s;
}
.btn-new-ticket:hover { background: var(--accent-hover); transform: translateY(-2px); }

@media (max-width: 768px) {
    .form-row { grid-template-columns: 1fr; }
    .ticket-card__meta { flex-direction: column; gap: 0.5rem; }
}
</style>

<div class="support-page">
    <div class="support-header">
        <h1>🎫 Поддержка</h1>
        <p>Создайте тикет или просмотрите существующие обращения</p>
    </div>

    <?php if ($ticket): ?>
        <!-- Просмотр тикета -->
        <div class="ticket-view">
            <div class="ticket-view__header">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <a href="/support.php" style="color: var(--accent); text-decoration: none;">← Назад к тикетам</a>
                    <span class="ticket-status <?= $ticket['status'] ?>">
                        <?= $ticket['status'] === 'open' ? '● Открыт' : ($ticket['status'] === 'pending' ? '◐ Ожидает' : '○ Закрыт') ?>
                    </span>
                </div>
                <h2 style="font-size: 1.3rem; margin-bottom: 0.5rem;"><?= e($ticket['subject']) ?></h2>
                <div style="display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                    <span>Создан: <?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></span>
                    <span class="ticket-priority <?= $ticket['priority'] ?>">
                        <?= $ticket['priority'] === 'high' ? '🔴 Высокий' : ($ticket['priority'] === 'medium' ? '🟡 Средний' : '🟢 Низкий') ?>
                    </span>
                </div>
            </div>
            <div class="ticket-view__body">
                <div class="message-list">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message <?= $msg['is_admin'] ? 'admin' : 'user' ?>">
                            <div class="message__header">
                                <span class="message__author"><?= e($msg['username']) ?> <?= $msg['is_admin'] ? '(Админ)' : '(Вы)' ?></span>
                                <span class="message__time"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></span>
                            </div>
                            <div class="message__text"><?= e($msg['message']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($ticket['status'] !== 'closed'): ?>
                    <form method="POST" action="/api/support.php?action=ticket_message">
                        <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                        <div class="message-input">
                            <input type="text" name="message" placeholder="Введите сообщение..." required>
                            <button type="submit" class="btn btn--primary">Отправить</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 1rem;">Тикет закрыт</p>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($action === 'new'): ?>
        <!-- Форма создания тикета -->
        <div class="ticket-form">
            <h2>📝 Новый тикет</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Тема *</label>
                        <input type="text" name="subject" placeholder="Кратко опишите проблему" required>
                    </div>
                    <div class="form-group">
                        <label>Приоритет</label>
                        <select name="priority">
                            <option value="low">🟢 Низкий</option>
                            <option value="medium" selected>🟡 Средний</option>
                            <option value="high">🔴 Высокий</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Сообщение *</label>
                    <textarea name="message" placeholder="Подробно опишите вашу проблему..." required></textarea>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn btn--primary">Создать тикет</button>
                    <a href="/support.php" class="btn btn--outline">Отмена</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Список тикетов -->
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <div class="empty-state__icon">📭</div>
                <p style="margin-bottom: 1.5rem;">У вас пока нет тикетов</p>
                <a href="?action=new" class="btn-new-ticket">➕ Создать тикет</a>
            </div>
        <?php else: ?>
            <div class="ticket-list">
                <?php foreach ($tickets as $t): ?>
                    <a href="/support.php?id=<?= $t['id'] ?>" class="ticket-card">
                        <div class="ticket-card__header">
                            <span class="ticket-card__subject"><?= e($t['subject']) ?></span>
                            <span class="ticket-status <?= $t['status'] ?>">
                                <?= $t['status'] === 'open' ? '● Открыт' : ($t['status'] === 'pending' ? '◐ Ожидает' : '○ Закрыт') ?>
                            </span>
                        </div>
                        <div class="ticket-card__meta">
                            <span>📅 <?= date('d.m.Y H:i', strtotime($t['updated_at'])) ?></span>
                            <span>💬 <?= $t['message_count'] ?> сообщ.</span>
                            <span class="ticket-priority <?= $t['priority'] ?>">
                                <?= $t['priority'] === 'high' ? '🔴' : ($t['priority'] === 'medium' ? '🟡' : '🟢') ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 2rem; text-align: center;">
            <a href="?action=new" class="btn-new-ticket">➕ Создать новый тикет</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
