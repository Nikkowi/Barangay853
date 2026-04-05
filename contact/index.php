<?php
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$isPublic = isset($_GET['public']);

if (!$isPublic) {
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
} else {
    $conn = getConnection();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($method) {
    case 'GET':
        if ($id) {
            $stmt = $conn->prepare('SELECT * FROM contacts WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $contact = $stmt->get_result()->fetch_assoc();
            if (!$contact) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Contact not found']);
            } else {
                // Mark as read
                $conn->query("UPDATE contacts SET status='read', read_at=NOW() WHERE id=$id");
                echo json_encode(['success' => true, 'data' => $contact]);
            }
        } else {
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            if ($status) {
                $stmt = $conn->prepare('SELECT * FROM contacts WHERE status = ? ORDER BY created_at DESC');
                $stmt->bind_param('s', $status);
            } else {
                $stmt = $conn->prepare('SELECT * FROM contacts ORDER BY created_at DESC');
            }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['name']) || !isset($data['email']) || !isset($data['message'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name, email and message are required']);
            exit();
        }
        $stmt = $conn->prepare('INSERT INTO contacts 
            (name, email, phone, subject, message, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, "new", NOW(), NOW())');
        $stmt->bind_param('sssss',
            $data['name'], $data['email'], $data['phone'],
            $data['subject'], $data['message']
        );
        $stmt->execute();
        $newId = $conn->insert_id;
        echo json_encode(['success' => true, 'message' => 'Message sent successfully', 'id' => $newId]);
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $conn->prepare('UPDATE contacts SET 
            status=?, admin_notes=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('ssi', $data['status'], $data['admin_notes'], $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Contact updated successfully']);
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $stmt = $conn->prepare('DELETE FROM contacts WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Contact deleted successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>