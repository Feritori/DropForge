<?php
/**
 * DropForge — Авторизация
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// Если уже вошёл — на главную
if (isLoggedIn()) {
    header("Location: /index.php");
    exit;
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$mode = $_GET['mode'] ?? 'login'; // login | register

// Обработка регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'register') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Валидация
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Неверный email';
    } elseif (strlen($username) < 3 || strlen($username) > 32) {
        $error = 'Ник: 3-32 символа';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль: минимум 6 символов';
    } elseif ($password !== $password_confirm) {
        $error = 'Пароли не совпадают';
    } else {
        // Проверяем уникальность
        $stmt = db()->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = 'Email или ник уже занят';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $token = bin2hex(random_bytes(32));
            
            $stmt = db()->prepare("INSERT INTO users (email, username, password_hash, verification_token, first_deposit) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$email, $username, $hash, $token]);
            
            // Отправка письма (заглушка)
            // mail($email, "Подтверждение email", "Перейдите по ссылке: " . SITE_URL . "/verify.php?token=$token");
            
            $success = 'Регистрация успешна! Проверьте email.';
            $mode = 'login';
        }
    }
}

// Обработка входа по email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: /index.php");
        exit;
    } else {
        $error = 'Неверный email или пароль';
    }
}

// Обработка входа через Steam (OpenID)
if ($mode === 'steam_login') {
    $steamApiKey = defined('STEAM_API_KEY') ? STEAM_API_KEY : '';
    
    if (empty($steamApiKey)) {
        die('Steam API key not configured');
    }
    
    $loginUrl = 'https://steamcommunity.com/openid/login';
    $returnUrl = SITE_URL . '/api/auth?action=steam_callback';
    
    $params = http_build_query([
        'openid.ns'         => 'http://specs.openid.net/auth/2.0',
        'openid.mode'       => 'checkid_setup',
        'openid.return_to'  => $returnUrl,
        'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
        'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
    ]);
    
    header("Location: {$loginUrl}?{$params}");
    exit;
}

require_once __DIR__ . '/layouts/header.php';
?>

<div style="max-width:450px; margin:3rem auto;">
    <!-- Steam Login -->
    <div style="background:var(--bg-card); border-radius:16px; padding:2rem; border:1px solid var(--border); margin-bottom:1.5rem;">
        <h2 style="margin-bottom:1rem; text-align:center; display:flex; align-items:center; justify-content:center; gap:0.75rem;">
            Быстрый вход
        </h2>
        <a href="?mode=steam_login" class="btn btn--primary btn--lg" style="width:100%; display:flex; align-items:center; justify-content:center; gap:0.75rem;">
            <img src="/assets/images/icon_steam.png" style="width:24px; height:24px;" alt="Steam">
            Войти через Steam
        </a>
    </div>
    
    <!-- Email Login/Register -->
    <div style="background:var(--bg-card); border-radius:16px; padding:2rem; border:1px solid var(--border);">
        <h2 style="margin-bottom:1rem; text-align:center;">
            <?= $mode === 'register' ? 'Регистрация' : 'Вход' ?>
        </h2>
        
        <?php if ($error): ?>
            <div style="background:rgba(255,82,82,0.1); color:#ff5252; padding:1rem; border-radius:8px; margin-bottom:1rem;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div style="background:rgba(0,230,118,0.1); color:#00e676; padding:1rem; border-radius:8px; margin-bottom:1rem;">
                <?= e($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($mode === 'login'): ?>
            <form method="POST" action="?mode=login">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn--primary btn--lg" style="width:100%;">Войти</button>
            </form>
            <p style="text-align:center; margin-top:1rem; color:var(--text-secondary);">
                Нет аккаунта? 
                <a href="?mode=register">Зарегистрироваться</a>
            </p>
        <?php else: ?>
            <form method="POST" action="?mode=register">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Никнейм</label>
                    <input type="text" name="username" class="form-control" minlength="3" maxlength="32" required>
                </div>
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="password" class="form-control" minlength="6" required>
                </div>
                <div class="form-group">
                    <label>Повторите пароль</label>
                    <input type="password" name="password_confirm" class="form-control" minlength="6" required>
                </div>
                <button type="submit" class="btn btn--primary btn--lg" style="width:100%;">Зарегистрироваться</button>
            </form>
            <p style="text-align:center; margin-top:1rem; color:var(--text-secondary);">
                Уже есть аккаунт? 
                <a href="?mode=login">Войти</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

