<?php
/**
 * auth/2fa.php
 *
 * Handles all 2FA operations:
 *   POST  { action: "send"   }  → generate + email a code
 *   POST  { action: "verify" }  → verify a submitted code
 *   POST  { action: "resend" }  → resend (only allowed after cooldown expires)
 */
date_default_timezone_set('Asia/Manila');
require_once '../config/database.php';
require_once '../utils/email.php';

define('TWO_FA_EXPIRY_MINUTES',   3);
define('TWO_FA_COOLDOWN_MINUTES', 3);
define('TWO_FA_CODE_LENGTH',      6);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$data   = json_decode(file_get_contents('php://input'), true);
$action = trim($data['action'] ?? '');

if (!in_array($action, ['send', 'verify', 'resend', 'trust', 'revoke_trusted'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit();
}

$conn = getConnection();

// Debug — remove after fixing
$tzCheck = $conn->query("SELECT NOW() as mysql_time")->fetch_assoc();
error_log("PHP time:   " . date('Y-m-d H:i:s'));
error_log("MySQL time: " . $tzCheck['mysql_time']);

// ── Helper: resolve the user from either user_id or email ─────────────────────
function resolveUser($conn, array $data): ?array {
    if (!empty($data['user_id'])) {
        $stmt = $conn->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
        $stmt->bind_param('i', $data['user_id']);
    } elseif (!empty($data['email'])) {
        $stmt = $conn->prepare('SELECT id, name, email, role FROM users WHERE LOWER(email) = LOWER(?)');
        $stmt->bind_param('s', $data['email']);
    } else {
        return null;
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

// ── Helper: generate a cryptographically random OTP ───────────────────────────
function generateUniqueCode($conn, int $userId): string {
    $length = TWO_FA_CODE_LENGTH;
    $max    = (int) str_repeat('9', $length);
    $min    = (int) ('1' . str_repeat('0', $length - 1));

    $attempts = 0;
    do {
        if (++$attempts > 100) {
            throw new RuntimeException('Could not generate a unique 2FA code. Please contact support.');
        }
        $code = str_pad((string) random_int($min, $max), $length, '0', STR_PAD_LEFT);
        $chk  = $conn->prepare('SELECT id FROM two_factor_codes WHERE user_id = ? AND code = ?');
        $chk->bind_param('is', $userId, $code);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
    } while ($exists);

    return $code;
}

// ── Helper: insert code into DB and send the email ────────────────────────────
function issueCode($conn, array $user): array {
    $userId    = (int) $user['id'];
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . TWO_FA_EXPIRY_MINUTES . ' minutes'));

    $invalidate = $conn->prepare('UPDATE two_factor_codes SET used = 1 WHERE user_id = ? AND used = 0');
    $invalidate->bind_param('i', $userId);
    $invalidate->execute();

    $code = generateUniqueCode($conn, $userId);

    $ins = $conn->prepare(
        'INSERT INTO two_factor_codes (user_id, code, expires_at, used, created_at)
         VALUES (?, ?, ?, 0, NOW())'
    );
    $ins->bind_param('iss', $userId, $code, $expiresAt);
    $ins->execute();

    $expiryLabel = TWO_FA_EXPIRY_MINUTES . ' minute' . (TWO_FA_EXPIRY_MINUTES !== 1 ? 's' : '');
    $sent = sendTwoFactorEmail($user['email'], $user['name'], $code, $expiryLabel);

    return [
        'code_id'    => $conn->insert_id,
        'expires_at' => $expiresAt,
        'email_sent' => $sent,
    ];
}

// ── Utility: mask email for display ──────────────────────────────────────────
function maskEmail(string $email): string {
    [$local, $domain] = explode('@', $email, 2);
    $visible = substr($local, 0, 1);
    return $visible . str_repeat('*', max(1, strlen($local) - 1)) . '@' . $domain;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: send
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'send') {
    $user = resolveUser($conn, $data);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit();
    }

    $result = issueCode($conn, $user);
    $conn->close();

    echo json_encode([
        'success'        => true,
        'message'        => '2FA code sent to ' . maskEmail($user['email']),
        'expires_at'     => $result['expires_at'],
        'expiry_minutes' => TWO_FA_EXPIRY_MINUTES,
    ]);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: resend
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'resend') {
    $user = resolveUser($conn, $data);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit();
    }

    $userId = (int) $user['id'];

    $recent = $conn->prepare(
        'SELECT created_at FROM two_factor_codes
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $recent->bind_param('i', $userId);
    $recent->execute();
    $lastRow = $recent->get_result()->fetch_assoc();

    if ($lastRow) {
        $cooldownSecs   = TWO_FA_COOLDOWN_MINUTES * 60;
        $lastSentAt     = strtotime($lastRow['created_at']);
        $secondsElapsed = time() - $lastSentAt;
        $secondsLeft    = $cooldownSecs - $secondsElapsed;

        if ($secondsLeft > 0) {
            $conn->close();
            echo json_encode([
                'success'      => false,
                'cooldown'     => true,
                'seconds_left' => (int) $secondsLeft,
                'message'      => "Please wait {$secondsLeft} seconds before requesting a new code.",
            ]);
            exit();
        }
    }

    $result = issueCode($conn, $user);
    $conn->close();

    echo json_encode([
        'success'        => true,
        'message'        => 'New 2FA code sent to ' . maskEmail($user['email']),
        'expires_at'     => $result['expires_at'],
        'expiry_minutes' => TWO_FA_EXPIRY_MINUTES,
    ]);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: verify
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'verify') {
    $user = resolveUser($conn, $data);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit();
    }

    $submittedCode = trim($data['code'] ?? '');
    if (empty($submittedCode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Code is required.']);
        exit();
    }

    $userId = (int) $user['id'];

    $stmt = $conn->prepare(
        'SELECT id, code, expires_at FROM two_factor_codes
         WHERE user_id = ?
           AND used      = 0
           AND expires_at > NOW()
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        $conn->close();
        echo json_encode([
            'success' => false,
            'expired' => true,
            'message' => 'Your code has expired or was already used. Please request a new one.',
        ]);
        exit();
    }

    if (!hash_equals($row['code'], $submittedCode)) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Incorrect code. Please try again.']);
        exit();
    }

    // Mark code as used
    $markUsed = $conn->prepare('UPDATE two_factor_codes SET used = 1 WHERE id = ?');
    $markUsed->bind_param('i', $row['id']);
    $markUsed->execute();

    // ── Upgrade pre_token → real auth token ──────────────────────────────────
    $findPre = $conn->prepare(
        "SELECT id FROM personal_access_tokens
         WHERE tokenable_id = ? AND tokenable_type = '2fa_pending' AND name = 'pre_auth'
         ORDER BY created_at DESC LIMIT 1"
    );
    $findPre->bind_param('i', $userId);
    $findPre->execute();
    $preRow = $findPre->get_result()->fetch_assoc();

    $realToken = bin2hex(random_bytes(32));

    if ($preRow) {
        $upgrade = $conn->prepare(
            "UPDATE personal_access_tokens
             SET tokenable_type = 'App\\\\Models\\\\User',
                 name           = 'auth_token',
                 token          = ?,
                 abilities      = '[\"*\"]',
                 updated_at     = NOW()
             WHERE id = ?"
        );
        $upgrade->bind_param('si', $realToken, $preRow['id']);
        $upgrade->execute();
    } else {
        $ins = $conn->prepare(
            "INSERT INTO personal_access_tokens
                (tokenable_type, tokenable_id, name, token, abilities, created_at, updated_at)
             VALUES ('App\\\\Models\\\\User', ?, 'auth_token', ?, '[\"*\"]', NOW(), NOW())"
        );
        $ins->bind_param('is', $userId, $realToken);
        $ins->execute();
    }

    // ── Log successful login ──────────────────────────────────────────────────
    $logStmt   = $conn->prepare(
        'INSERT INTO activity_logs (actor_name, action, module, reference_id, logged_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())'
    );
    $logAction = 'Logged in';
    $logModule = 'System';
    $logRefId  = 'USER-' . $userId . ' (' . $user['role'] . ')';
    $logStmt->bind_param('ssss', $user['name'], $logAction, $logModule, $logRefId);
    $logStmt->execute();

    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => '2FA verification successful.',
        'token'   => $realToken,
        'user'    => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
    ]);
    exit();
}
// ─────────────────────────────────────────────────────────────────────────────
// ACTION: trust
// Called after successful 2FA verify when user checks "Trust this device".
// Creates a trusted_sessions row and returns the token to store in localStorage.
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'trust') {
    $user = resolveUser($conn, $data);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit();
    }

    // Must send a valid auth token to prove they just passed 2FA
    $authHeader = isset(getallheaders()['Authorization']) ? getallheaders()['Authorization'] : '';
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authorization required.']);
        exit();
    }
    $authToken = substr($authHeader, 7);
    $authCheck = $conn->prepare("SELECT id FROM personal_access_tokens WHERE token = ? AND tokenable_id = ? AND tokenable_type = 'App\\\\Models\\\\User'");
    $authCheck->bind_param('si', $authToken, $user['id']);
    $authCheck->execute();
    if (!$authCheck->get_result()->fetch_assoc()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid auth token.']);
        exit();
    }

    // Duration: 30, 60, or 90 days — default 30
    $allowedDays  = [30, 60, 90];
    $durationDays = intval($data['duration_days'] ?? 30);
    if (!in_array($durationDays, $allowedDays, true)) $durationDays = 30;

    $trustedToken = bin2hex(random_bytes(32));
    $expiresAt    = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
    $ipAddress    = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent    = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Build a human-readable device label from User-Agent
    $deviceLabel = 'Unknown Device';
    if ($userAgent) {
        if (str_contains($userAgent, 'Chrome'))       $deviceLabel = 'Chrome';
        elseif (str_contains($userAgent, 'Firefox'))  $deviceLabel = 'Firefox';
        elseif (str_contains($userAgent, 'Safari'))   $deviceLabel = 'Safari';
        elseif (str_contains($userAgent, 'Edge'))     $deviceLabel = 'Edge';
        if (str_contains($userAgent, 'Windows'))      $deviceLabel .= ' on Windows';
        elseif (str_contains($userAgent, 'Macintosh')) $deviceLabel .= ' on Mac';
        elseif (str_contains($userAgent, 'Linux'))    $deviceLabel .= ' on Linux';
        elseif (str_contains($userAgent, 'Android'))  $deviceLabel .= ' on Android';
        elseif (str_contains($userAgent, 'iPhone'))   $deviceLabel .= ' on iPhone';
    }

    $ins = $conn->prepare(
        'INSERT INTO trusted_sessions
            (user_id, token, device_label, ip_address, user_agent, duration_days, expires_at, last_used_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    // 7 placeholders: user_id(i), token(s), device_label(s), ip_address(s), user_agent(s), duration_days(i), expires_at(s)
    $ins->bind_param('issssis',
        $user['id'],
        $trustedToken,
        $deviceLabel,
        $ipAddress,
        $userAgent,
        $durationDays,
        $expiresAt
    );
    if (!$ins->execute()) {
        error_log('[Trust] Insert failed: ' . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Failed to save trusted session: ' . $conn->error]);
        exit();
    }

    $conn->close();

    echo json_encode([
        'success'       => true,
        'trusted_token' => $trustedToken,
        'expires_at'    => $expiresAt,
        'duration_days' => $durationDays,
        'device_label'  => $deviceLabel,
        'message'       => "This device is now trusted for {$durationDays} days.",
    ]);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: revoke_trusted
// Lets an authenticated user revoke all their trusted sessions (or one by token).
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'revoke_trusted') {
    $user = resolveUser($conn, $data);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit();
    }

    $authHeader = isset(getallheaders()['Authorization']) ? getallheaders()['Authorization'] : '';
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authorization required.']);
        exit();
    }
    $authToken = substr($authHeader, 7);
    $authCheck = $conn->prepare("SELECT id FROM personal_access_tokens WHERE token = ? AND tokenable_id = ?");
    $authCheck->bind_param('si', $authToken, $user['id']);
    $authCheck->execute();
    if (!$authCheck->get_result()->fetch_assoc()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid auth token.']);
        exit();
    }

    $revokeToken = trim($data['trusted_token'] ?? '');
    if (!empty($revokeToken)) {
        // Revoke a specific device
        $rev = $conn->prepare('UPDATE trusted_sessions SET revoked = 1 WHERE user_id = ? AND token = ?');
        $rev->bind_param('is', $user['id'], $revokeToken);
    } else {
        // Revoke ALL trusted devices for this user
        $rev = $conn->prepare('UPDATE trusted_sessions SET revoked = 1 WHERE user_id = ?');
        $rev->bind_param('i', $user['id']);
    }
    $rev->execute();
    $affected = $rev->affected_rows;
    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => "Revoked {$affected} trusted session(s). You will need to verify via 2FA on next login.",
    ]);
    exit();
}
?>