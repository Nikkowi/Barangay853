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

// --- BUG FIX 1: Handle Name (Admin Modal vs Public Form) ---
if (isset($data['name']) && !empty(trim($data['name']))) {
    // Came from Admin Dashboard Modal
    $fullName = trim($data['name']);
} else {
    // Came from Public Resident Registration Form
    $firstName = trim($data['first_name'] ?? '');
    $lastName = trim($data['last_name'] ?? '');
    $fullName = trim("$firstName $lastName") ?: 'Resident';
}

$email = trim($data['email']);
$password = $data['password'];
$contactNumber = $data['contact_number'] ?? null;
$address = $data['address'] ?? null;
$dateOfBirth = $data['date_of_birth'] ?? null;

// --- BUG FIX 2: Handle Role (Dynamic instead of Hardcoded) ---
// If the form sends a role (like the Admin Modal), use it. Otherwise, default to 'resident'.
$role = $data['role'] ?? 'resident'; 

// Security Check: Ensure nobody tries to hack the system by sending a fake role like "SuperGodAdmin"
$allowedRoles = ['admin', 'staff', 'resident'];
if (!in_array($role, $allowedRoles)) {
    $role = 'resident';
}

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

// Insert new user with the DYNAMIC role, not the hardcoded one!
$stmt = $conn->prepare('INSERT INTO users (name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
$stmt->bind_param('ssss', $fullName, $email, $hashedPassword, $role);
$stmt->execute();
$newUserId = $conn->insert_id;

// Only add to the 'residents' table if they are actually a resident
if ($newUserId && $role === 'resident') {
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
        'role' => $role
    ]
]);
?>