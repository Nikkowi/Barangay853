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
if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$residentId = isset($_POST['resident_id']) ? intval($_POST['resident_id']) : 0;
if (!$residentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Resident ID required']);
    exit();
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No photo uploaded or upload error']);
    exit();
}

$file = $_FILES['photo'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.']);
    exit();
}

if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB.']);
    exit();
}

$uploadDir = '../uploads/residents/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'resident-' . $residentId . '-' . time() . '.' . strtolower($ext);
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit();
}

// Check if photo_url column exists, add it if not
$colCheck = $conn->query("SHOW COLUMNS FROM residents LIKE 'photo_url'");
if ($colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE residents ADD COLUMN photo_url VARCHAR(255) DEFAULT NULL");
}

$photoUrl = 'uploads/residents/' . $filename;
$stmt = $conn->prepare('UPDATE residents SET photo_url = ?, updated_at = NOW() WHERE id = ?');
$stmt->bind_param('si', $photoUrl, $residentId);
$stmt->execute();

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Photo uploaded successfully',
    'photo_url' => $photoUrl
]);
?>
