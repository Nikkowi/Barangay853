<?php
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

// Get token and verify
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$token = substr($authHeader, 7);
$conn = getConnection();

// Verify token
$stmt = $conn->prepare('SELECT u.id FROM users u 
    INNER JOIN personal_access_tokens t ON t.tokenable_id = u.id 
    WHERE t.token = ?');
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();
if (!$result->fetch_assoc()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($method) {
    case 'GET':
        if ($id) {
            // Get single resident
            $stmt = $conn->prepare('SELECT r.*, h.household_code, h.address_line, h.purok_sitio 
                FROM residents r 
                LEFT JOIN households h ON h.id = r.household_id 
                WHERE r.id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $resident = $stmt->get_result()->fetch_assoc();
            if (!$resident) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Resident not found']);
            } else {
                echo json_encode(['success' => true, 'data' => $resident]);
            }
        } else {
            // Get all residents with search/filter
            $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
            $stmt = $conn->prepare('SELECT r.id, r.full_name, r.gender, r.date_of_birth, 
                r.contact_mobile, r.is_senior_citizen, r.is_pwd, r.civil_status,
                h.household_code, h.address_line, h.purok_sitio
                FROM residents r 
                LEFT JOIN households h ON h.id = r.household_id
                WHERE r.full_name LIKE ?
                ORDER BY r.full_name ASC');
            $stmt->bind_param('s', $search);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['full_name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full name is required']);
            exit();
        }
        $stmt = $conn->prepare('INSERT INTO residents 
            (full_name, gender, resident_type, date_of_birth, place_of_birth, civil_status, 
            nationality, religion, contact_mobile, contact_email, is_senior_citizen, is_pwd, 
            pwd_type, registered_voter, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->bind_param('ssssssssssiisi',
            $data['full_name'], $data['gender'], $data['resident_type'],
            $data['date_of_birth'], $data['place_of_birth'], $data['civil_status'],
            $data['nationality'] ?? 'Filipino', $data['religion'],
            $data['contact_mobile'], $data['contact_email'],
            $data['is_senior_citizen'] ?? 0, $data['is_pwd'] ?? 0,
            $data['pwd_type'], $data['registered_voter'] ?? 0
        );
        $stmt->execute();
        $newId = $conn->insert_id;
        echo json_encode(['success' => true, 'message' => 'Resident added successfully', 'id' => $newId]);
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $conn->prepare('UPDATE residents SET 
            full_name=?, gender=?, resident_type=?, date_of_birth=?, civil_status=?,
            contact_mobile=?, contact_email=?, is_senior_citizen=?, is_pwd=?,
            registered_voter=?, updated_at=NOW()
            WHERE id=?');
        $stmt->bind_param('sssssssiiui',
            $data['full_name'], $data['gender'], $data['resident_type'],
            $data['date_of_birth'], $data['civil_status'],
            $data['contact_mobile'], $data['contact_email'],
            $data['is_senior_citizen'] ?? 0, $data['is_pwd'] ?? 0,
            $data['registered_voter'] ?? 0, $id
        );
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Resident updated successfully']);
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $stmt = $conn->prepare('DELETE FROM residents WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Resident deleted successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>