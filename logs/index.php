<?php
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

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

switch ($method) {
    case 'GET':
        $module = isset($_GET['module']) ? $_GET['module'] : null;
        if ($module) {
            $stmt = $conn->prepare('SELECT * FROM activity_logs 
                WHERE module = ? ORDER BY logged_at DESC LIMIT 100');
            $stmt->bind_param('s', $module);
        } else {
            $stmt = $conn->prepare('SELECT * FROM activity_logs 
                ORDER BY logged_at DESC LIMIT 100');
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['action'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action is required']);
            exit();
        }
        $stmt = $conn->prepare('INSERT INTO activity_logs 
            (actor_name, action, module, reference_id, logged_at, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())');
        $stmt->bind_param('ssss',
            $data['actor_name'], $data['action'],
            $data['module'], $data['reference_id']
        );
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Log recorded successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>