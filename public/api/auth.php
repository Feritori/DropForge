<?php
/**
 * DropForge — Steam Authentication API
 */
// Сессия уже запущена в functions.php, проверяем статус
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

// Проверяем Steam Web API Key
$steamApiKey = defined('STEAM_API_KEY') ? STEAM_API_KEY : '';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        // Steam OpenID Login
        if (empty($steamApiKey)) {
            jsonResponse(['success' => false, 'error' => 'Steam API key not configured'], 500);
        }

        $loginUrl = 'https://steamcommunity.com/openid/login';
        $returnUrl = SITE_URL . '/api/auth?action=steam_callback';
        
        // Build OpenID parameters
        $params = http_build_query([
            'openid.ns'         => 'http://specs.openid.net/auth/2.0',
            'openid.mode'       => 'checkid_setup',
            'openid.return_to'  => $returnUrl,
            'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ]);
        
        header("Location: {$loginUrl}?{$params}");
        exit;

    case 'steam_callback':
        // Steam OpenID Callback
        if (empty($steamApiKey)) {
            die('Steam API key not configured');
        }

        if (!isset($_GET['openid_mode']) || $_GET['openid_mode'] !== 'id_res') {
            die('Invalid Steam response: ' . ($_GET['openid_mode'] ?? 'no mode'));
        }

        // Debug: log the callback
        error_log('Steam callback received');

        // Verify OpenID response with Steam (updated URL)
        $verifyUrl = 'https://steamcommunity.com/openid/opend2/op_verify';
        $verificationParams = [
            'openid.assoc_handle' => $_GET['openid_assoc_handle'] ?? '',
            'openid.signed'       => $_GET['openid_signed'] ?? '',
            'openid.sig'          => $_GET['openid_sig'] ?? '',
            'openid.ns'           => 'http://specs.openid.net/auth/2.0',
        ];

        // Parse signed parameters
        $signedParams = explode(',', $_GET['openid_signed']);
        foreach ($signedParams as $param) {
            $key = str_replace('openid.', '', urldecode($param));
            $value = urldecode($_GET['openid_' . $key]);
            $verificationParams['openid.' . $key] = $value;
        }
        $verificationParams['openid.mode'] = 'check_authentication';

        // Send verification request to Steam
        $ch = curl_init($verifyUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($verificationParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log('Steam verify response: ' . $response);
        error_log('Steam verify http code: ' . $httpCode);
        error_log('Steam verify curl error: ' . $curlError);

        // Check response — Steam returns "is_valid:true" or "is_valid:false"
        $isValid = (strpos($response, 'is_valid:true') !== false);

        if ($isValid) {
            // Extract SteamID from identity URL
            $identityUrl = $_GET['openid_claimed_id'] ?? '';
            error_log('Steam identity URL: ' . $identityUrl);
            
            preg_match('#^https?://steamcommunity\.com/id/(?P<id>[^/\?]+)$#', $identityUrl, $matches);
            
            if (isset($matches['id'])) {
                $steamId = $matches['id'];
            } else {
                // Extract from URL: https://steamcommunity.com/profiles/76561198000000000
                preg_match('#/profiles/(?P<id>\d+)$#', $identityUrl, $matches);
                if (isset($matches['id'])) {
                    $steamId = $matches['id'];
                } else {
                    die('Could not extract SteamID from: ' . $identityUrl);
                }
            }

            error_log('Extracted SteamID: ' . $steamId);

            // Get Steam profile info
            $profileUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key={$steamApiKey}&steamids={$steamId}";
            $ch = curl_init($profileUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $profileResponse = curl_exec($ch);
            curl_close($ch);

            error_log('Steam profile response: ' . $profileResponse);

            $profileData = json_decode($profileResponse, true);
            
            if (isset($profileData['response']['players'][0])) {
                $player = $profileData['response']['players'][0];
                $username = $player['personaname'] ?? 'Player';
                $avatar = $player['avatarfull'] ?? '';
            } else {
                $username = 'Player';
                $avatar = '';
            }

            error_log('Username: ' . $username);

            // === ПРОВЕРЯЕМ ПРИВЯЗКУ ПЕРВОЙ ===
            if (isset($_SESSION['link_steam_user_id'])) {
                $targetUserId = $_SESSION['link_steam_user_id'];
                error_log('Linking Steam to user ID: ' . $targetUserId);
                
                // Проверяем не привязан ли уже к другому
                $stmt = db()->prepare("SELECT id FROM users WHERE steam_id = ? AND id != ?");
                $stmt->execute([$steamId, $targetUserId]);
                if ($stmt->fetch()) {
                    unset($_SESSION['link_steam_user_id']);
                    header("Location: /profile.php?error=steam_already_linked");
                    exit;
                }
                
                // Привязываем
                $stmt = db()->prepare("UPDATE users SET steam_id = ?, avatar = ? WHERE id = ?");
                $stmt->execute([$steamId, $avatar, $targetUserId]);
                unset($_SESSION['link_steam_user_id']);
                error_log('Steam linked to user ID: ' . $targetUserId);
                header("Location: /profile.php?success=steam_linked");
                exit;
            }

            // === ЛОГИН / РЕГИСТРАЦИЯ ===
            // Check if Steam account already exists
            $stmt = db()->prepare("SELECT id FROM users WHERE steam_id = ?");
            $stmt->execute([$steamId]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                // Account already linked - just login
                $_SESSION['user_id'] = $existingUser['id'];
                error_log('Existing user logged in: ' . $existingUser['id']);
                
                // Redirect to original URL if specified
                $redirect = $_GET['redirect'] ?? '/index.php';
                header("Location: {$redirect}");
                exit;
            }

            // New user - create account
            $referredBy = null;
            if (isset($_SESSION['ref_code'])) {
                $ref = strtoupper($_SESSION['ref_code']);
                $stmt = db()->prepare("SELECT id FROM users WHERE ref_code = ?");
                $stmt->execute([$ref]);
                $referrer = $stmt->fetch();
                if ($referrer) {
                    $referredBy = $referrer['id'];
                }
                unset($_SESSION['ref_code']);
            }

            $refCode = generateRefCode();
            $stmt = db()->prepare("INSERT INTO users (steam_id, username, avatar, ref_code, referred_by, first_deposit) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$steamId, $username, $avatar, $refCode, $referredBy]);
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            error_log('New user created: ' . $_SESSION['user_id']);

            // Redirect to original URL if specified
            $redirect = $_GET['redirect'] ?? '/index.php';
            header("Location: {$redirect}");
            exit;
        } else {
            error_log('Steam verification failed: ' . $response);
            die('Steam verification failed: ' . $response);
        }
        break;

    case 'link_steam':
        // Start Steam linking process for logged-in user
        if (empty($steamApiKey)) {
            jsonResponse(['success' => false, 'error' => 'Steam API key not configured'], 500);
        }
        
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
        }
        
        // Check if already linked
        $user = getCurrentUser();
        if (!empty($user['steam_id'])) {
            jsonResponse(['success' => false, 'error' => 'Steam already linked'], 400);
        }
        
        // Store user ID for linking after Steam callback
        $_SESSION['link_steam_user_id'] = $user['id'];
        error_log('Starting Steam link for user ID: ' . $user['id']);
        
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

    case 'logout':
        session_destroy();
        header("Location: /index.php");
        exit;

    case 'user':
        requireAuth();
        $user = getCurrentUser();
        $uAction = $_GET['sub'] ?? $_POST['action'] ?? '';

        switch ($uAction) {
            case 'deposit':
                $data = json_decode(file_get_contents('php://input'), true);
                $amount = (float)($data['amount'] ?? 0);
                if ($amount < 1) jsonResponse(['success' => false, 'error' => 'Минимум 1$'], 400);
                if (addBalance($user['id'], $amount, 'deposit', 'Пополнение баланса')) {
                    jsonResponse(['success' => true, 'amount' => $amount]);
                }
                jsonResponse(['success' => false, 'error' => 'Ошибка'], 500);
                break;

            case 'create_ref_code':
                $data = json_decode(file_get_contents('php://input'), true);
                $code = strtoupper(trim($data['code'] ?? ''));
                if (!preg_match('/^[A-Z0-9]{3,10}$/', $code)) {
                    jsonResponse(['success' => false, 'error' => 'Код должен содержать 3-10 символов (A-Z, 0-9)'], 400);
                }
                $stmt = db()->prepare("SELECT id FROM users WHERE ref_code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetch()) {
                    jsonResponse(['success' => false, 'error' => 'Этот код уже занят'], 400);
                }
                $stmt = db()->prepare("UPDATE users SET ref_code = ? WHERE id = ?");
                $stmt->execute([$code, $user['id']]);
                jsonResponse(['success' => true, 'code' => $code]);
                break;

            case 'info':
                jsonResponse(['success' => true, 'user' => $user]);
                break;

            default:
                jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
        }
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown endpoint'], 404);
}
