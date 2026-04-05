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
        if ($id) {
            $stmt = $conn->prepare('SELECT bc.*,
                GROUP_CONCAT(DISTINCT CONCAT(bp.role, ":", bp.name, ":", COALESCE(bp.contact, "")) SEPARATOR "|") as parties,
                GROUP_CONCAT(DISTINCT CONCAT(ba.action_label, ":", COALESCE(ba.action_details, "")) SEPARATOR "|") as actions
                FROM blotter_cases bc
                LEFT JOIN blotter_parties bp ON bp.blotter_case_id = bc.id
                LEFT JOIN blotter_actions ba ON ba.blotter_case_id = bc.id
                WHERE bc.id = ?
                GROUP BY bc.id');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $case = $stmt->get_result()->fetch_assoc();
            if (!$case) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Case not found']);
            } else {
                echo json_encode(['success' => true, 'data' => $case]);
            }
        } else {
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            if ($status) {
                $stmt = $conn->prepare('SELECT * FROM blotter_cases WHERE status = ? ORDER BY created_at DESC');
                $stmt->bind_param('s', $status);
            } else {
                $stmt = $conn->prepare('SELECT * FROM blotter_cases ORDER BY created_at DESC');
            }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['case_title'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Case title is required']);
            exit();
        }
        // Generate case number
        $caseNo = 'BLT-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare('INSERT INTO blotter_cases 
            (case_no, case_title, category, status, priority, location, summary, description,
            reporter_name, reporter_contact, reporter_email, reporter_address,
            persons_involved, witnesses, incident_date, incident_time, date_reported,
            created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())');
        $stmt->bind_param('ssssssssssssssss',
            $caseNo, $data['case_title'], $data['category'],
            $data['status'] ?? 'Pending', $data['priority'] ?? 'Medium',
            $data['location'], $data['summary'], $data['description'],
            $data['reporter_name'], $data['reporter_contact'],
            $data['reporter_email'], $data['reporter_address'],
            $data['persons_involved'], $data['witnesses'],
            $data['incident_date'], $data['incident_time']
        );
        $stmt->execute();
        $newId = $conn->insert_id;
        echo json_encode(['success' => true, 'message' => 'Blotter case filed successfully',
            'id' => $newId, 'case_no' => $caseNo]);
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $conn->prepare('UPDATE blotter_cases SET 
            case_title=?, category=?, status=?, priority=?, location=?,
            summary=?, investigator_name=?, schedule_datetime=?,
            schedule_location=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('ssssssssi',
            $data['case_title'], $data['category'], $data['status'],
            $data['priority'], $data['location'], $data['summary'],
            $data['investigator_name'], $data['schedule_datetime'],
            $data['schedule_location'], $id
        );
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Case updated successfully']);
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $stmt = $conn->prepare('DELETE FROM blotter_cases WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Case deleted successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>