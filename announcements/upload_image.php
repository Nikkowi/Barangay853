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

// Verify user
$stmt = $conn->prepare('SELECT u.id, u.name FROM users u 
    INNER JOIN personal_access_tokens t ON t.tokenable_id = u.id 
    WHERE t.token = ?');
$stmt->bind_param('s', $token);
$stmt->execute();
$authUser = $stmt->get_result()->fetch_assoc();

if (!$authUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$announcementId = isset($_POST['announcement_id']) ? intval($_POST['announcement_id']) : 0;
if (!$announcementId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Announcement ID required']);
    exit();
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image uploaded or upload error']);
    exit();
}

$file = $_FILES['image'];
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

$uploadDir = '../uploads/announcements/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'announcement-' . $announcementId . '-' . time() . '.' . strtolower($ext);
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit();
}

// Check if image_url column exists, add it dynamically if not
$colCheck = $conn->query("SHOW COLUMNS FROM announcements LIKE 'image_url'");
if ($colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE announcements ADD COLUMN image_url VARCHAR(255) DEFAULT NULL");
}

$imageUrl = 'uploads/announcements/' . $filename;
$stmt = $conn->prepare('UPDATE announcements SET image_url = ?, updated_at = NOW() WHERE id = ?');
$stmt->bind_param('si', $imageUrl, $announcementId);
$stmt->execute();

// Log Activity
$logStmt = $conn->prepare("INSERT INTO activity_logs (actor_name, action, module, reference_id, logged_at) VALUES (?, 'Uploaded Image', 'Announcement', ?, NOW())");
$refId = 'ANN-' . $announcementId;
$logStmt->bind_param('ss', $authUser['name'], $refId);
$logStmt->execute();

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Image uploaded successfully',
    'image_url' => $imageUrl
]);
?>