<?php
require_once '../config/database.php';

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$token = substr($authHeader, 7);
$conn = getConnection();

$stmt = $conn->prepare('SELECT u.id FROM users u 
    INNER JOIN personal_access_tokens t ON t.tokenable_id = u.id 
    WHERE t.token = ?');
$stmt->bind_param('s', $token);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();

if (!$userRow) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}
$userId = $userRow['id'];

// Ensure profile columns exist
$cols = $conn->query("SHOW COLUMNS FROM users")->fetch_all(MYSQLI_ASSOC);
$colNames = array_column($cols, 'Field');
if (!in_array('phone', $colNames))     $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL");
if (!in_array('address', $colNames))   $conn->query("ALTER TABLE users ADD COLUMN address TEXT NULL");
if (!in_array('bio', $colNames))       $conn->query("ALTER TABLE users ADD COLUMN bio TEXT NULL");
if (!in_array('avatar_url', $colNames)) $conn->query("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $conn->prepare('SELECT id, name, email, role, phone, address, bio, avatar_url, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $user]);

} elseif ($method === 'POST') {
    // Handle avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg','image/jpg','image/png','image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image type']);
            exit();
        }
        $uploadDir = '../uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar-' . $userId . '-' . time() . '.' . strtolower($ext);
        move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
        $avatarUrl = 'uploads/avatars/' . $filename;
        $stmt = $conn->prepare('UPDATE users SET avatar_url = ? WHERE id = ?');
        $stmt->bind_param('si', $avatarUrl, $userId);
        $stmt->execute();
        echo json_encode(['success' => true, 'avatar_url' => $avatarUrl]);
        exit();
    }

    // Handle profile update (JSON body)
    $data = json_decode(file_get_contents('php://input'), true);
    $name    = trim($data['name'] ?? '');
    $phone   = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $bio     = trim($data['bio'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        exit();
    }

    // Handle password change
    if (!empty($data['new_password'])) {
        if (strlen($data['new_password']) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit();
        }
        // Verify current password
        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $pw = $stmt->get_result()->fetch_assoc()['password'];
        if (!password_verify($data['current_password'] ?? '', $pw)) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit();
        }
        $hashed = password_hash($data['new_password'], PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE users SET name=?, phone=?, address=?, bio=?, password=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('sssssi', $name, $phone, $address, $bio, $hashed, $userId);
    } else {
        $stmt = $conn->prepare('UPDATE users SET name=?, phone=?, address=?, bio=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('ssssi', $name, $phone, $address, $bio, $userId);
    }

    $stmt->execute();
    // Update localStorage name
    $stmt2 = $conn->prepare('SELECT id, name, email, role, phone, address, bio, avatar_url FROM users WHERE id = ?');
    $stmt2->bind_param('i', $userId);
    $stmt2->execute();
    $updated = $stmt2->get_result()->fetch_assoc();
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully', 'data' => $updated]);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>
