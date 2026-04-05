<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

$firstName = trim($data['first_name'] ?? '');
$lastName = trim($data['last_name'] ?? '');
$email = trim($data['email']);
$password = $data['password'];
$contactNumber = $data['contact_number'] ?? null;
$address = $data['address'] ?? null;
$dateOfBirth = $data['date_of_birth'] ?? null;

// Build full name
$fullName = trim("$firstName $lastName") ?: 'Resident';

// Validate password length
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit();
}

$conn = getConnection();

// Check if email already exists
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'An account with this email already exists']);
    exit();
}

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Insert new user as resident
$stmt = $conn->prepare('INSERT INTO users (name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, "resident", NOW(), NOW())');
$stmt->bind_param('sss', $fullName, $email, $hashedPassword);
$stmt->execute();
$newUserId = $conn->insert_id;

// Also add to residents table
if ($newUserId) {
    $stmt2 = $conn->prepare('INSERT INTO residents (full_name, contact_mobile, date_of_birth, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
    $stmt2->bind_param('sss', $fullName, $contactNumber, $dateOfBirth);
    $stmt2->execute();
}

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Account created successfully',
    'user' => [
        'id' => $newUserId,
        'name' => $fullName,
        'email' => $email,
        'role' => 'resident'
    ]
]);
?>
