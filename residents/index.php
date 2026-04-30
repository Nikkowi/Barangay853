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

// Verify token and get actor name for logging
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
$actorName = $authUser['name'];

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
            $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
            
            // --- THE FIX: Added r.resident_type to the SELECT query below ---
            $stmt = $conn->prepare('SELECT r.id, r.full_name, r.gender, r.date_of_birth, 
                r.contact_mobile, r.is_senior_citizen, r.is_pwd, r.civil_status, r.resident_type, r.photo_url,
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

        // Required field validation
        $requiredFields = [
            'full_name'      => 'Full name',
            'gender'         => 'Gender',
            'date_of_birth'  => 'Date of birth',
            'civil_status'   => 'Civil status',
            'resident_type'  => 'Resident type',
            'contact_mobile' => 'Contact mobile',
            'contact_email'  => 'Contact email',
            'place_of_birth' => 'Place of birth',
            'nationality'    => 'Nationality',
            'religion'       => 'Religion',
        ];
        foreach ($requiredFields as $field => $label) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "$label is required"]);
                exit();
            }
        }

        // Validate mobile number format
        if (!preg_match('/^09\\d{9}$/', $data['contact_mobile'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Contact number must be exactly 11 digits and start with 09.']);
            exit();
        }

        // --- THE FIX: Clean up the data variables BEFORE passing them to bind_param ---
        $data['place_of_birth'] = $data['place_of_birth'] ?? '';
        $data['nationality'] = $data['nationality'] ?? 'Filipino';
        $data['religion'] = $data['religion'] ?? '';
        $data['is_senior_citizen'] = $data['is_senior_citizen'] ?? 0;
        $data['is_pwd'] = $data['is_pwd'] ?? 0;
        $data['pwd_type'] = $data['pwd_type'] ?? '';
        $data['registered_voter'] = $data['registered_voter'] ?? 0;

        $stmt = $conn->prepare('INSERT INTO residents 
            (full_name, gender, resident_type, date_of_birth, place_of_birth, civil_status, 
            nationality, religion, contact_mobile, contact_email, is_senior_citizen, is_pwd, 
            pwd_type, registered_voter, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        
        $stmt->bind_param('ssssssssssiisi',
            $data['full_name'], $data['gender'], $data['resident_type'],
            $data['date_of_birth'], $data['place_of_birth'], $data['civil_status'],
            $data['nationality'], $data['religion'],
            $data['contact_mobile'], $data['contact_email'],
            $data['is_senior_citizen'], $data['is_pwd'],
            $data['pwd_type'], $data['registered_voter']
        );
        $stmt->execute();
        $newId = $conn->insert_id;
        
// Assign to household if provided
if (!empty($data['household_id']) && !empty($data['household_role'])) {
    $hhId   = intval($data['household_id']);
    $hhRole = $data['household_role'];

    // Validate role value
    $allowedRoles = ['head','spouse','child','sibling','extended','boarder','other'];
    if (!in_array($hhRole, $allowedRoles)) {
        $hhRole = 'other';
    }

    // If this person is being set as head, demote any existing head to 'other'
    if ($hhRole === 'head') {
        $demote = $conn->prepare(
            'UPDATE residents SET household_role = \'other\'
             WHERE household_id = ? AND household_role = \'head\''
        );
        $demote->bind_param('i', $hhId);
        $demote->execute();
    }

    $assignStmt = $conn->prepare(
        'UPDATE residents SET household_id = ?, household_role = ? WHERE id = ?'
    );
    $assignStmt->bind_param('isi', $hhId, $hhRole, $newId);
    $assignStmt->execute();
}

        logActivity($conn, $actorName, 'Created', 'Resident', 'RES-' . $newId);
        echo json_encode(['success' => true, 'message' => 'Resident added successfully', 'id' => $newId]);
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $data = json_decode(file_get_contents('php://input'), true);

        // --- NEW VALIDATION: Check mobile number format ---
        if (!empty($data['contact_mobile'])) {
            if (!preg_match('/^09\d{9}$/', $data['contact_mobile'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Contact number must be exactly 11 digits and start with 09.']);
                exit();
            }
        }

        // --- THE FIX: Clean up PUT variables too ---
        $data['is_senior_citizen'] = $data['is_senior_citizen'] ?? 0;
        $data['is_pwd'] = $data['is_pwd'] ?? 0;
        $data['registered_voter'] = $data['registered_voter'] ?? 0;

        $data['place_of_birth'] = $data['place_of_birth'] ?? '';
        $data['nationality']    = $data['nationality'] ?? 'Filipino';
        $data['religion']       = $data['religion'] ?? '';

        $stmt = $conn->prepare('UPDATE residents SET 
            full_name=?, gender=?, resident_type=?, date_of_birth=?, civil_status=?,
            place_of_birth=?, nationality=?, religion=?,
            contact_mobile=?, contact_email=?, is_senior_citizen=?, is_pwd=?,
            registered_voter=?, updated_at=NOW()
            WHERE id=?');
            
        $stmt->bind_param('ssssssssssiiii',
            $data['full_name'], $data['gender'], $data['resident_type'],
            $data['date_of_birth'], $data['civil_status'],
            $data['place_of_birth'], $data['nationality'], $data['religion'],
            $data['contact_mobile'], $data['contact_email'],
            $data['is_senior_citizen'], $data['is_pwd'],
            $data['registered_voter'], $id
        );
        $stmt->execute();
        logActivity($conn, $actorName, 'Updated', 'Resident', 'RES-' . $id);
        echo json_encode(['success' => true, 'message' => 'Resident updated successfully']);
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        // Grab name before deleting for the log
        $nameStmt = $conn->prepare('SELECT full_name FROM residents WHERE id = ?');
        $nameStmt->bind_param('i', $id);
        $nameStmt->execute();
        $residentName = $nameStmt->get_result()->fetch_assoc()['full_name'] ?? 'Unknown';

        $stmt = $conn->prepare('DELETE FROM residents WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        logActivity($conn, $actorName, 'Deleted', 'Resident', 'RES-' . $id . ' (' . $residentName . ')');
        echo json_encode(['success' => true, 'message' => 'Resident deleted successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>