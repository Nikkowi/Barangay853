<?php
date_default_timezone_set('Asia/Manila');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

$email         = trim($data['email']);
$password      = $data['password'];
$trustedToken  = trim($data['trusted_token'] ?? '');

$conn = getConnection();

$stmt = $conn->prepare('SELECT id, name, email, password, role, avatar FROM users WHERE LOWER(email) = LOWER(?)');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $stmt2 = $conn->prepare('SELECT id, name, email, password, role, avatar FROM users WHERE LOWER(name) = LOWER(?)');
    $stmt2->bind_param('s', $email);
    $stmt2->execute();
    $user = $stmt2->get_result()->fetch_assoc();
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No account found with that email or name']);
    exit();
}

if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Incorrect password. Please check your password and try again.']);
    exit();
}

$requires2FA = in_array($user['role'], ['admin', 'staff'], true);

if ($requires2FA) {

    // ── TRUSTED SESSION CHECK ─────────────────────────────────────────────────
    if (!empty($trustedToken)) {
        $tsStmt = $conn->prepare(
            'SELECT id, duration_days, expires_at FROM trusted_sessions
             WHERE token = ? AND user_id = ? AND revoked = 0 AND expires_at > NOW()
             LIMIT 1'
        );
        $tsStmt->bind_param('si', $trustedToken, $user['id']);
        $tsStmt->execute();
        $trustedRow = $tsStmt->get_result()->fetch_assoc();

        if ($trustedRow) {
            $updTs = $conn->prepare('UPDATE trusted_sessions SET last_used_at = NOW() WHERE id = ?');
            $updTs->bind_param('i', $trustedRow['id']);
            $updTs->execute();

            $realToken = bin2hex(random_bytes(32));
            $userId    = $user['id'];
            $tokStmt   = $conn->prepare(
                "INSERT INTO personal_access_tokens
                    (tokenable_type, tokenable_id, name, token, abilities, created_at, updated_at)
                 VALUES ('App\\\\Models\\\\User', ?, 'auth_token', ?, '[\"*\"]', NOW(), NOW())"
            );
            $tokStmt->bind_param('is', $userId, $realToken);
            $tokStmt->execute();

            $logStmt   = $conn->prepare('INSERT INTO activity_logs (actor_name, action, module, reference_id, logged_at, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())');
            $logAction = 'Logged in (trusted device)';
            $logModule = 'System';
            $logRefId  = 'USER-' . $userId . ' (' . $user['role'] . ')';
            $logStmt->bind_param('ssss', $user['name'], $logAction, $logModule, $logRefId);
            $logStmt->execute();
            $conn->close();

            echo json_encode([
                'success'        => true,
                'requires_2fa'   => false,
                'trusted_bypass' => true,
                'token'          => $realToken,
                'user'           => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role'], 'avatar' => $user['avatar']],
            ]);
            exit();
        }
    }
    // ── END TRUSTED SESSION CHECK ─────────────────────────────────────────────

    require_once '../utils/email.php';

    $preToken   = bin2hex(random_bytes(32));
    $preExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $storeStmt  = $conn->prepare("INSERT INTO personal_access_tokens (tokenable_type, tokenable_id, name, token, abilities, created_at, updated_at) VALUES ('2fa_pending', ?, 'pre_auth', ?, '[\"*\"]', NOW(), ?)");
    $storeStmt->bind_param('iss', $user['id'], $preToken, $preExpires);
    $storeStmt->execute();

    $expiryMins = 3;
    $existing   = $conn->prepare('SELECT code, expires_at FROM two_factor_codes WHERE user_id = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
    $existing->bind_param('i', $user['id']);
    $existing->execute();
    $existingCode = $existing->get_result()->fetch_assoc();

    if ($existingCode) {
        $expiresAt     = $existingCode['expires_at'];
        $twoFaCode     = $existingCode['code'];
        $remainingSecs = strtotime($expiresAt) - time();
        $expiryMins    = max(1, ceil($remainingSecs / 60));
    } else {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryMins} minutes"));
        $inv = $conn->prepare('UPDATE two_factor_codes SET used = 1 WHERE user_id = ? AND used = 0');
        $inv->bind_param('i', $user['id']);
        $inv->execute();
        $max = 999999; $min = 100000; $attempts = 0;
        do {
            if (++$attempts > 100) break;
            $twoFaCode = str_pad((string) random_int($min, $max), 6, '0', STR_PAD_LEFT);
            $dupChk    = $conn->prepare('SELECT id FROM two_factor_codes WHERE user_id = ? AND code = ?');
            $dupChk->bind_param('is', $user['id'], $twoFaCode);
            $dupChk->execute();
        } while ($dupChk->get_result()->fetch_assoc());
        $insCode = $conn->prepare('INSERT INTO two_factor_codes (user_id, code, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())');
        $insCode->bind_param('iss', $user['id'], $twoFaCode, $expiresAt);
        $insCode->execute();
        sendTwoFactorEmail($user['email'], $user['name'], $twoFaCode, "{$expiryMins} minutes");
    }

    $logStmt   = $conn->prepare('INSERT INTO activity_logs (actor_name, action, module, reference_id, logged_at, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())');
    $logAction = '2FA Initiated'; $logModule = 'System'; $logRefId = 'USER-' . $user['id'] . ' (' . $user['role'] . ')';
    $logStmt->bind_param('ssss', $user['name'], $logAction, $logModule, $logRefId);
    $logStmt->execute();
    $conn->close();

    echo json_encode(['success' => true, 'requires_2fa' => true, 'user_id' => $user['id'], 'email' => $user['email'], 'expires_at' => $expiresAt, 'expiry_minutes' => $expiryMins, 'pre_token' => $preToken, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']]]);
    exit();
}

// ── No 2FA (residents) ────────────────────────────────────────────────────────
$token = bin2hex(random_bytes(32)); $userId = $user['id'];
$stmt2 = $conn->prepare('INSERT INTO personal_access_tokens (tokenable_type, tokenable_id, name, token, abilities, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
$tokenableType = 'App\\Models\\User'; $tokenName = 'auth_token'; $abilities = '["*"]';
$stmt2->bind_param('sisss', $tokenableType, $userId, $tokenName, $token, $abilities);
$stmt2->execute();
$logStmt = $conn->prepare('INSERT INTO activity_logs (actor_name, action, module, reference_id, logged_at, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())');
$logAction = 'Logged in'; $logModule = 'System'; $logRefId = 'USER-' . $userId . ' (' . $user['role'] . ')';
$logStmt->bind_param('ssss', $user['name'], $logAction, $logModule, $logRefId);
$logStmt->execute();
$conn->close();
echo json_encode(['success' => true, 'requires_2fa' => false, 'token' => $token, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role'], 'avatar' => $user['avatar']]]);
?>
