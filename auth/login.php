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

$email = trim($data['email']);
$password = $data['password'];

$conn = getConnection();

$stmt = $conn->prepare('SELECT id, name, email, password, role, avatar FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No account found with that email']);
    exit();
}

if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Incorrect password. Please check your password and try again.']);
    exit();
}

// Generate simple session token
$token = bin2hex(random_bytes(32));

// Store token in database
$userId = $user['id'];
$stmt2 = $conn->prepare('INSERT INTO personal_access_tokens (tokenable_type, tokenable_id, name, token, abilities, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
$tokenableType = 'App\\Models\\User';
$tokenName = 'auth_token';
$abilities = '["*"]';
$stmt2->bind_param('sisss', $tokenableType, $userId, $tokenName, $token, $abilities);
$stmt2->execute();

$conn->close();

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'avatar' => $user['avatar']
    ]
]);
?>