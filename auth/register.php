<?php
require_once '../config/database.php';
require_once '../utils/nameValidator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// ── Validate required fields ──────────────────────────────────────────────────
if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

// ── Determine if this is an admin-created account or a public registration ────
$headers     = getallheaders();
$authHeader  = $headers['Authorization'] ?? '';
$isAdminCall = !empty($authHeader) && str_starts_with($authHeader, 'Bearer ');

$conn = getConnection();

// ── Admin route: create-user (requires valid token + admin role) ──────────────
if ($isAdminCall) {
    $token = substr($authHeader, 7);
    $stmt  = $conn->prepare('SELECT u.id, u.name, u.role FROM users u
        INNER JOIN personal_access_tokens t ON t.tokenable_id = u.id
        WHERE t.token = ?');

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $adminUser = $stmt->get_result()->fetch_assoc();

    if (!$adminUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit();
    }

    // ── Field presence ──────────────────────────────────────────────────────
    $name     = trim($data['name'] ?? '');
    $email    = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role     = $data['role'] ?? 'resident';

    if (!$name || !$email || !$password || !$role) {
        http_response_code(400);
        echo json_encode(['error' => 'Name, email, password, and role are all required.']);
        exit();
    }

    // ── Name validation ─────────────────────────────────────────────────────
    $nameCheck = validatePersonName($name);
    if (!$nameCheck['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $nameCheck['error']]);
        exit();
    }

    // ── Email validation ────────────────────────────────────────────────────
    $emailCheck = validateEmailAddress($email);
    if (!$emailCheck['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $emailCheck['error']]);
        exit();
    }

    // ── Role whitelist ──────────────────────────────────────────────────────
    $allowedRoles = ['admin', 'staff', 'resident'];
    if (!in_array($role, $allowedRoles, true)) {
        $role = 'resident';
    }

    // ── Password length ─────────────────────────────────────────────────────
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters.']);
        exit();
    }

    // ── Duplicate email ─────────────────────────────────────────────────────
    // ── Duplicate email ─────────────────────────────────────────────────────
    $chk = $conn->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?)');
    $chk->bind_param('s', $email);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        http_response_code(409);
        echo json_encode(['error' => 'An account with this email already exists.']);
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $trimmedName    = trim($name);

    $result = $conn->prepare(
        "INSERT INTO users (name, email, password, role, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())"
    );

    if ($result === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $conn->error]);
        exit();
    }

    $result->bind_param('ssss', $trimmedName, $email, $hashedPassword, $role);
    $result->execute();

    if ($result->error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create account: ' . $result->error]);
        exit();
    }

    $newId = $conn->insert_id;

    $conn->close();
    echo json_encode([
        'success' => true,
        'message' => "$role account created successfully.",
        'user'    => ['id' => $newId, 'name' => $trimmedName, 'email' => $email, 'role' => $role],
    ]);
    exit();
}

// ── Public route: resident self-registration ──────────────────────────────────

// ── Handle Name (Admin Modal vs Public Form) ──────────────────────────────────
if (!empty(trim($data['name'] ?? ''))) {
    $fullName = trim($data['name']);
} else {
    $firstName = trim($data['first_name'] ?? '');
    $lastName  = trim($data['last_name']  ?? '');
    $fullName  = trim("$firstName $lastName") ?: 'Resident';
}

$email         = trim($data['email']);
$password      = $data['password'];
$contactNumber = $data['contact_number'] ?? null;
$address       = $data['address']        ?? null;
$dateOfBirth   = $data['date_of_birth']  ?? null;

// ── Field presence ────────────────────────────────────────────────────────────
if (!$fullName || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name, email, and password are required.']);
    exit();
}

// ── Name validation ───────────────────────────────────────────────────────────
if (!empty(trim($data['first_name'] ?? '')) || !empty(trim($data['last_name'] ?? ''))) {
    $firstCheck = validatePersonName(trim($data['first_name'] ?? ''));
    if (!$firstCheck['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $firstCheck['error']]);
        exit();
    }
    $lastCheck = validatePersonName(trim($data['last_name'] ?? ''));
    if (!$lastCheck['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $lastCheck['error']]);
        exit();
    }
} else {
    $nameCheck = validatePersonName($fullName);
    if (!$nameCheck['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $nameCheck['error']]);
        exit();
    }
}

// ── Email validation ──────────────────────────────────────────────────────────
$emailCheck = validateEmailAddress($email);
if (!$emailCheck['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $emailCheck['error']]);
    exit();
}

// ── Password length ───────────────────────────────────────────────────────────
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit();
}

// ── Role handling ─────────────────────────────────────────────────────────────
$role = $data['role'] ?? 'resident';
$allowedRoles = ['admin', 'staff', 'resident'];
if (!in_array($role, $allowedRoles, true)) {
    $role = 'resident';
}

// ── Duplicate email ───────────────────────────────────────────────────────────
$chk = $conn->prepare('SELECT id FROM users WHERE email = ?');
$chk->bind_param('s', $email);
$chk->execute();
if ($chk->get_result()->fetch_assoc()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'An account with this email already exists']);
    exit();
}

// ── Insert user ───────────────────────────────────────────────────────────────
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare(
    'INSERT INTO users (name, email, password, role, created_at, updated_at)
     VALUES (?, ?, ?, ?, NOW(), NOW())'
);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $conn->error]);
    exit();
}

$stmt->bind_param('ssss', $fullName, $email, $hashedPassword, $role);
$stmt->execute();

if ($stmt->error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $stmt->error]);
    exit();
}

$newUserId = $conn->insert_id;

// ── Insert resident record (only for residents) ───────────────────────────────
$stmt2 = $conn->prepare(
    'INSERT INTO residents (full_name, contact_mobile, contact_email, date_of_birth, created_at, updated_at)
     VALUES (?, ?, ?, ?, NOW(), NOW())'
);

if ($stmt2 === false) {
    error_log('Residents insert prepare failed: ' . $conn->error);
} else {
    $contactValue = !empty($contactNumber) ? $contactNumber : null;
    $dobValue     = !empty($dateOfBirth)   ? $dateOfBirth   : null;
    $stmt2->bind_param('ssss', $fullName, $contactValue, $email, $dobValue);
    $stmt2->execute();

    if ($stmt2->error) {
        error_log('Residents insert error: ' . $stmt2->error);
    }
}


$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Account created successfully',
    'user'    => [
        'id'    => $newUserId,
        'name'  => $fullName,
        'email' => $email,
        'role'  => $role,
    ],
]);
?>