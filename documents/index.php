<?php
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

// Verify token
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
        if ($isPublic) {
            // Public: return document types only
            $types = ['Barangay Clearance', 'Certificate of Indigency', 'Certificate of Residency', 
                      'Barangay ID', 'Business Clearance', 'Certificate of Good Moral Character'];
            echo json_encode(['success' => true, 'data' => $types]);
            break;
        }
        if ($id) {
            $stmt = $conn->prepare('SELECT dr.*, 
                GROUP_CONCAT(dra.label, ": ", dra.value SEPARATOR " | ") as attachments
                FROM document_requests dr
                LEFT JOIN document_request_attachments dra ON dra.document_request_id = dr.id
                WHERE dr.id = ?
                GROUP BY dr.id');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $doc = $stmt->get_result()->fetch_assoc();
            if (!$doc) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Request not found']);
            } else {
                echo json_encode(['success' => true, 'data' => $doc]);
            }
        } else {
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            if ($status) {
                $stmt = $conn->prepare('SELECT * FROM document_requests WHERE status = ? ORDER BY created_at DESC');
                $stmt->bind_param('s', $status);
            } else {
                $stmt = $conn->prepare('SELECT * FROM document_requests ORDER BY created_at DESC');
            }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['requester_name']) || !isset($data['document_type'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Requester name and document type are required']);
            exit();
        }
        // Generate reference number
        $refNo = 'DOC-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare('INSERT INTO document_requests 
            (reference_no, requester_name, contact_number, email, address, document_type, 
            purpose, status, date_filed, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, "Pending", NOW(), NOW(), NOW())');
        $stmt->bind_param('sssssss',
            $refNo, $data['requester_name'], $data['contact_number'],
            $data['email'], $data['address'], $data['document_type'], $data['purpose']
        );
        $stmt->execute();
        $newId = $conn->insert_id;
        echo json_encode(['success' => true, 'message' => 'Request submitted successfully', 
            'id' => $newId, 'reference_no' => $refNo]);
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $conn->prepare('UPDATE document_requests SET 
            status=?, remarks=?, receiving_staff=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('sssi', $data['status'], $data['remarks'], $data['receiving_staff'], $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Request updated successfully']);
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $stmt = $conn->prepare('DELETE FROM document_requests WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Request deleted successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>