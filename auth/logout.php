<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit();
}

$token = substr($authHeader, 7);

$conn = getConnection();

$stmt = $conn->prepare('DELETE FROM personal_access_tokens WHERE token = ?');
$stmt->bind_param('s', $token);
$stmt->execute();

$conn->close();

echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
?>