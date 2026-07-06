<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/layouts/header.php';
requireAuth();

$user = getCurrentUser();

// Реферальная система — проверка
$referralsEnabled = (getSetting('referrals_enabled', '1') === '1');
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Get Steam API key from settings
$stmt = db()->prepare("SELECT value FROM settings WHERE `key` = 'steam_api_key'");
$stmt->execute();
$steamApiKey = $stmt->fetchColumn() ?? '';

// === ОБРАБОТКА ПРИВЯЗКИ STEAM ===
if (isset($_GET['link_steam'])) {
    if (empty($steamApiKey)) {
        $error = 'Steam API key not configured';
    } elseif (!empty($user['steam_id'])) {
        $error = 'Steam уже привязан';
    } else {
        $linkToken = bin2hex(random_bytes(32));
        $_SESSION['steam_link_' . $linkToken] = $user['id'];
        
        $returnUrl = SITE_URL . '/profile.php?action=steam_callback&token=' . $linkToken;
        
        $params = http_build_query([
            'openid.ns'         => 'http://specs.openid.net/auth/2.0',
            'openid.mode'       => 'checkid_setup',
            'openid.return_to'  => $returnUrl,
            'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ]);
        
        header("Location: https://steamcommunity.com/openid/login?{$params}");
        exit;
    }
}

// === CALLBACK ОТ STEAM ===
if (isset($_GET['action']) && $_GET['action'] === 'steam_callback') {
    if (empty($steamApiKey)) {
        die('Steam API key not configured');
    }
    
    if (!isset($_GET['openid_mode']) || $_GET['openid_mode'] !== 'id_res') {
        die('Invalid Steam response');
    }
    
    $token = $_GET['token'] ?? '';
    if (empty($token) || !isset($_SESSION['steam_link_' . $token])) {
        die('Invalid link token');
    }
    
    $targetUserId = $_SESSION['steam_link_' . $token];
    unset($_SESSION['steam_link_' . $token]);
    
    $verifyUrl = 'https://steamcommunity.com/openid/opend2/op_verify';
    $verificationParams = [
        'openid.assoc_handle' => $_GET['openid_assoc_handle'] ?? '',
        'openid.signed'       => $_GET['openid_signed'] ?? '',
        'openid.sig'          => $_GET['openid_sig'] ?? '',
        'openid.ns'           => 'http://specs.openid.net/auth/2.0',
    ];
    
    $signedParams = explode(',', $_GET['openid_signed']);
    foreach ($signedParams as $param) {
        $key = str_replace('openid.', '', urldecode($param));
        $value = urldecode($_GET['openid_' . $key]);
        $verificationParams['openid.' . $key] = $value;
    }
    $verificationParams['openid.mode'] = 'check_authentication';
    
    $ch = curl_init($verifyUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($verificationParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === 'is_valid:true') {
        $identityUrl = $_GET['openid_claimed_id'] ?? '';
        
        preg_match('#^https?://steamcommunity\.com/id/(?P<id>[^/\?]+)$#', $identityUrl, $matches);
        if (isset($matches['id'])) {
            $steamId = $matches['id'];
        } else {
            preg_match('#/profiles/(?P<id>\d+)$#', $identityUrl, $matches);
            $steamId = $matches['id'] ?? die('Invalid SteamID');
        }
        
        $profileUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key={$steamApiKey}&steamids={$steamId}";
        $ch = curl_init($profileUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $profileResponse = curl_exec($ch);
        curl_close($ch);
        
        $profileData = json_decode($profileResponse, true);
        $username = 'Player';
        $avatar = '';
        
        if (isset($profileData['response']['players'][0])) {
            $username = $profileData['response']['players'][0]['personaname'] ?? 'Player';
            $avatar = $profileData['response']['players'][0]['avatarfull'] ?? '';
        }
        
        $stmt = db()->prepare("SELECT id FROM users WHERE steam_id = ? AND id != ?");
        $stmt->execute([$steamId, $targetUserId]);
        if ($stmt->fetch()) {
            header("Location: /profile.php?error=steam_already_linked");
            exit;
        }
        
        $stmt = db()->prepare("UPDATE users SET steam_id = ?, avatar = ? WHERE id = ?");
        $stmt->execute([$steamId, $avatar, $targetUserId]);
        
        header("Location: /profile.php?success=steam_linked");
        exit;
    } else {
        die('Steam verification failed: ' . $response);
    }
}

// Обработка смены пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $new_password_confirm = $_POST['new_password_confirm'] ?? '';
    
    if (!password_verify($old_password, $user['password_hash'])) {
        $error = 'Неверный текущий пароль';
    } elseif (strlen($new_password) < 6) {
        $error = 'Пароль: минимум 6 символов';
    } elseif ($new_password !== $new_password_confirm) {
        $error = 'Пароли не совпадают';
    } else {
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $user['id']]);
        $success = 'password_changed';
    }
}

// Referral stats
$stmt = db()->prepare("SELECT COUNT(*) as count FROM users WHERE referred_by = ?");
$stmt->execute([$user['id']]);
$referralData = $stmt->fetch();
$referralCount = (int)($referralData['count'] ?? 0);

$stmt = db()->prepare("SELECT COALESCE(SUM(t.amount) * 0.05, 0) as total_bonus FROM transactions t JOIN users u ON t.user_id = u.id WHERE u.referred_by = ? AND t.type = 'deposit'");
$stmt->execute([$user['id']]);
$referralBonus = (float)($stmt->fetch()['total_bonus'] ?? 0);

// Get referred users
$stmt = db()->prepare("SELECT id, username, balance, created_at FROM users WHERE referred_by = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$referrals = $stmt->fetchAll();

// Count inventory
$stmt = db()->prepare("SELECT COUNT(*) FROM user_inventory WHERE user_id = ? AND is_sold = 0");
$stmt->execute([$user['id']]);
$inventoryCount = (int)$stmt->fetchColumn();

// Count case opens
$stmt = db()->prepare("SELECT COUNT(*) FROM case_open_history WHERE user_id = ?");
$stmt->execute([$user['id']]);
$opensCount = (int)$stmt->fetchColumn();
?>

<h1>👤 Профиль</h1>

<?php if ($success === 'steam_linked'): ?>
    <div style="background:rgba(0,230,118,0.1); color:#00e676; padding:1rem; border-radius:8px; margin-bottom:1.5rem;">
        ✅ Steam аккаунт успешно привязан!
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background:rgba(255,82,82,0.1); color:#ff5252; padding:1rem; border-radius:8px; margin-bottom:1.5rem;">
        ❌ <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Steam Link Section -->
<div style="background:var(--bg-card); border-radius:12px; padding:1.5rem; border:1px solid var(--border); margin-bottom:2rem;">
    <h3 style="margin-bottom:0.5rem;">🎮 Steam</h3>
    
    <?php if (!empty($user['steam_id'])): ?>
        <div style="display:flex; align-items:center; gap:1rem;">
            <div style="width:48px; height:48px; border-radius:50%; overflow:hidden;">
                <img src="https://steamcdn-a.akamaihd.net/steamcommunity/public/images/avatars/<?= substr($user['steam_id'], -10) ?>.jpg" style="width:100%; height:100%; object-fit:cover;" alt="Steam">
            </div>
            <div>
                <div style="font-weight:600;">Steam аккаунт привязан</div>
                <div style="color:var(--text-muted); font-size:0.85rem;">ID: <?= e(substr($user['steam_id'], 0, 16)) ?>...</div>
            </div>
        </div>
    <?php else: ?>
        <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
            <div>
                <div style="font-weight:600;">Steam не привязан</div>
                <div style="color:var(--text-muted); font-size:0.85rem;">Привяжите для быстрого входа</div>
            </div>
            <a href="?link_steam=1" class="btn btn--primary">🔗 Привязать Steam</a>
        </div>
    <?php endif; ?>
</div>

<!-- Password Change Section -->
<div style="background:var(--bg-card); border-radius:12px; padding:1.5rem; border:1px solid var(--border); margin-bottom:2rem;">
    <h3 style="margin-bottom:0.5rem;">🔑 Сменить пароль</h3>
    
    <?php if (!empty($error)): ?>
        <div style="background:rgba(255,82,82,0.1); color:#ff5252; padding:1rem; border-radius:8px; margin-bottom:1rem;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success === 'password_changed'): ?>
        <div style="background:rgba(0,230,118,0.1); color:#00e676; padding:1rem; border-radius:8px; margin-bottom:1rem;">
            Пароль успешно изменён!
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" style="max-width:400px;">
        <div class="form-group">
            <label>Текущий пароль</label>
            <input type="password" name="old_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Новый пароль</label>
            <input type="password" name="new_password" class="form-control" minlength="6" required>
        </div>
        <div class="form-group">
            <label>Повторите новый пароль</label>
            <input type="password" name="new_password_confirm" class="form-control" minlength="6" required>
        </div>
        <button type="submit" name="change_password" class="btn btn--primary">Сменить пароль</button>
    </form>
</div>

<!-- Quick Links -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:2rem;">
    <a href="/deposits.php" style="background:var(--bg-card); border-radius:12px; padding:1.5rem; border:1px solid var(--border); text-align:center; text-decoration:none; color:var(--text-primary); transition:all .2s;">
        <div style="font-size:2rem; margin-bottom:0.5rem;">💰</div>
        <div style="font-weight:600;">Пополнить баланс</div>
    </a>
    <a href="/transactions.php" style="background:var(--bg-card); border-radius:12px; padding:1.5rem; border:1px solid var(--border); text-align:center; text-decoration:none; color:var(--text-primary); transition:all .2s;">
        <div style="font-size:2rem; margin-bottom:0.5rem;">📋</div>
        <div style="font-weight:600;">Транзакции</div>
    </a>
    <a href="/history.php" style="background:var(--bg-card); border-radius:12px; padding:1.5rem; border:1px solid var(--border); text-align:center; text-decoration:none; color:var(--text-primary); transition:all .2s;">
        <div style="font-size:2rem; margin-bottom:0.5rem;">🎰</div>
        <div style="font-weight:600;">История открытий</div>
    </a>
</div>

<!-- Stats -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:2rem;">
    <div style="background:var(--bg-card); border-radius:12px; padding:1.5rem; border:1px solid var(--border); text-align:center;">
        <div style="color:var(--text-muted); font-size:0.85rem;">Баланс</div>
        <div style="font-size:1.8rem; font-weight:800; color:var(--success);"><?= formatMoney($user['balance']) ?></div>
    </div>
    <div style="background:var(--bg-card); border-radius:12px; padding:1.5rem; border:1px solid var(--border); text-align:center;">
        <div style="color:var(--text-muted); font-size:0.85rem;">Открыто кейсов</div>
        <div style="font-size:1.8rem; font-weight:800;"><?= $opensCount ?></div>
    </div>
    <div style="background:var(--bg-card); border-radius:12px; padding:1.5rem; border:1px solid var(--border); text-align:center;">
        <div style="color:var(--text-muted); font-size:0.85rem;">Предметов в инвентаре</div>
        <div style="font-size:1.8rem; font-weight:800;"><?= $inventoryCount ?></div>
    </div>
</div>

<!-- Referral Section (if enabled) -->
<?php if ($referralsEnabled): ?>
<!-- CREATE REF CODE MODAL -->
<div class="modal-overlay" id="createRefModal" style="display:none;">
    <div class="modal" style="max-width:400px;">
        <button class="modal__close" onclick="document.getElementById('createRefModal').style.display='none'">✕</button>
        <h3 style="margin-bottom:1rem;">Создать реферальный код</h3>
        <div class="form-group">
            <label>Введите код (3-10 символов, буквы и цифры)</label>
            <input type="text" class="form-control" id="newRefCodeInput" placeholder="Например: MYCODE123" maxlength="10" style="text-transform:uppercase; font-weight:700; letter-spacing:2px;">
        </div>
        <button class="btn btn--primary" onclick="submitRefCode()" style="width:100%;">Создать код</button>
    </div>
</div>

<script>
function submitRefCode() {
    const input = document.getElementById('newRefCodeInput');
    const code = input.value.trim().toUpperCase();
    
    if (!/^[A-Z0-9]{3,10}$/.test(code)) {
        notify('Код должен содержать 3-10 символов (A-Z, 0-9)', 'error');
        return;
    }

    fetch(SITE_URL + '/api/user?action=create_ref_code', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ code: code })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            notify('Реферальный код создан: ' + d.code, 'success');
            document.getElementById('createRefModal').style.display = 'none';
            setTimeout(() => location.reload(), 1000);
        } else {
            notify(d.error || 'Ошибка', 'error');
        }
    });
}

function copyRefCode() {
    const code = document.getElementById('refCodeDisplay').value;
    if (!code || code === 'Не создан') return;
    navigator.clipboard.writeText(code).then(() => {
        notify('Код скопирован!', 'success');
    }).catch(() => {
        notify('Не удалось скопировать', 'error');
    });
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

