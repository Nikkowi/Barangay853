<?php
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

$email    = trim($data['email']);
$password = $data['password'];

$conn = getConnection();

// Try login by email first, then fall back to name
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

// Generate session token
$token  = bin2hex(random_bytes(32));
$userId = $user['id'];

$stmt2 = $conn->prepare('INSERT INTO personal_access_tokens 
    (tokenable_type, tokenable_id, name, token, abilities, created_at, updated_at) 
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
$tokenableType = 'App\\Models\\User';
$tokenName     = 'auth_token';
$abilities     = '["*"]';
$stmt2->bind_param('sisss', $tokenableType, $userId, $tokenName, $token, $abilities);
$stmt2->execute();

// Log the login to activity_logs
$actorName = $user['name'];
$logStmt = $conn->prepare('INSERT INTO activity_logs 
    (actor_name, action, module, reference_id, logged_at, created_at, updated_at) 
    VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())');
$action   = 'Logged in';
$module   = 'System';
$refId    = 'USER-' . $userId . ' (' . $user['role'] . ')';
$logStmt->bind_param('ssss', $actorName, $action, $module, $refId);
$logStmt->execute();

$conn->close();

echo json_encode([
    'success' => true,
    'token'   => $token,
    'user'    => [
        'id'     => $user['id'],
        'name'   => $user['name'],
        'email'  => $user['email'],
        'role'   => $user['role'],
        'avatar' => $user['avatar']
    ]
]);
?>