<?php
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$isPublic = isset($_GET['public']);

// Always require auth for all requests
$conn = getConnection();
$token = '';
if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
    $token = substr($authHeader, 7);
}

// Only validate token for non-public GET requests
$isPublicGet = ($method === 'GET' && $isPublic);
if (!$isPublicGet) {
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
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
}

// Get actor name from token for logging (if token is present)
$actorName = 'Public';
if (!empty($token)) {
    $actorStmt = $conn->prepare('SELECT u.name FROM users u 
        INNER JOIN personal_access_tokens t ON t.tokenable_id = u.id WHERE t.token = ?');
    $actorStmt->bind_param('s', $token);
    $actorStmt->execute();
    $actorRow = $actorStmt->get_result()->fetch_assoc();
    if ($actorRow) $actorName = $actorRow['name'];
}

// Helper: write to activity_logs
function logActivity($conn, $actorName, $action, $module, $referenceId) {
    $stmt = $conn->prepare('INSERT INTO activity_logs 
        (actor_name, action, module, reference_id, logged_at, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())');
    $stmt->bind_param('ssss', $actorName, $action, $module, $referenceId);
    $stmt->execute();
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
        // ── NEW: ?my=1 → return only the logged-in resident's own cases ──
        } elseif (isset($_GET['my'])) {
            // Look up the email that belongs to this token
            $stmt = $conn->prepare('SELECT u.email FROM users u 
                INNER JOIN personal_access_tokens t ON t.tokenable_id = u.id 
                WHERE t.token = ?');
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $userEmail = $stmt->get_result()->fetch_assoc()['email'] ?? '';

            if (empty($userEmail)) {
                echo json_encode(['success' => true, 'data' => [], 'total' => 0]);
                break;
            }

            $stmt = $conn->prepare('SELECT * FROM blotter_cases WHERE reporter_email = ? ORDER BY created_at DESC');
            $stmt->bind_param('s', $userEmail);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
        // ──────────────────────────────────────────────────────────────────
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
        $requiredFields = [
            'case_title'       => 'Case title',
            'category'         => 'Category',
            'priority'         => 'Priority',
            'incident_date'    => 'Incident date',
            'incident_time'    => 'Incident time',
            'location'         => 'Location',
            'summary'          => 'Summary',
            'description'      => 'Full description',
            'reporter_name'    => 'Reporter name',
            'reporter_contact' => 'Reporter contact',
            'reporter_email'   => 'Reporter email',
            'reporter_address' => 'Reporter address',
            'persons_involved' => 'Persons involved',
            'witnesses'        => 'Witnesses',
        ];
        foreach ($requiredFields as $field => $label) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "$label is required"]);
                exit();
            }
        }
        if (!preg_match('/^09\\d{9}$/', $data['reporter_contact'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Reporter contact must be 11 digits and start with 09.']);
            exit();
        }
        $caseNo = 'BLT-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare('INSERT INTO blotter_cases 
            (case_no, case_title, category, status, priority, location, summary, description,
            reporter_name, reporter_contact, reporter_email, reporter_address,
            persons_involved, witnesses, incident_date, incident_time, date_reported,
            created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())');
        $status   = $data['status']   ?? 'Pending';
        $priority = $data['priority'] ?? 'Normal';
        $stmt->bind_param('ssssssssssssssss',
            $caseNo, $data['case_title'], $data['category'],
            $status, $priority,
            $data['location'], $data['summary'], $data['description'],
            $data['reporter_name'], $data['reporter_contact'],
            $data['reporter_email'], $data['reporter_address'],
            $data['persons_involved'], $data['witnesses'],
            $data['incident_date'], $data['incident_time']
        );
        $stmt->execute();
        $newId = $conn->insert_id;
        logActivity($conn, $actorName, 'Filed', 'Blotter', $caseNo . ' — ' . $data['case_title']);
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
        
        // FIX 1: Convert empty date strings to proper NULL so MySQL doesn't crash
        $nextHearingDate = !empty($data['next_hearing_date']) ? $data['next_hearing_date'] : null;
        $notes = isset($data['notes']) ? $data['notes'] : null;

        $stmt = $conn->prepare('UPDATE blotter_cases SET 
            case_title=?, category=?, status=?, priority=?, location=?,
            notes=?, investigator_name=?, next_hearing_date=?,
            schedule_location=?, updated_at=NOW() WHERE id=?');
            
        // FIX 2: If the SQL fails (e.g., missing column), catch it cleanly
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]);
            exit();
        }

        $stmt->bind_param('sssssssssi',
            $data['case_title'], 
            $data['category'], 
            $data['status'],
            $data['priority'], 
            $data['location'], 
            $notes, 
            $data['investigator_name'], 
            $nextHearingDate,
            $data['schedule_location'], 
            $id
        );
        
        if ($stmt->execute()) {
            logActivity($conn, $actorName, 'Updated to ' . $data['status'], 'Blotter', 'BLT-' . $id);
            echo json_encode(['success' => true, 'message' => 'Case updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
        }
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
        logActivity($conn, $actorName, 'Deleted', 'Blotter', 'BLT-' . $id);
        echo json_encode(['success' => true, 'message' => 'Case deleted successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>