<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$token = substr($authHeader, 7);
$conn = getConnection();

$stmt = $conn->prepare('SELECT u.id, u.name, u.role FROM users u 
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

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id'])     ? intval($_GET['id'])     : null;
$status = isset($_GET['status']) ? $_GET['status']         : null;

// ============================================================
// GET — fetch list or single record
// ============================================================
if ($method === 'GET') {

    if ($id) {
        // Single record
        $stmt = $conn->prepare("
            SELECT * FROM blotter_cases WHERE id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Case not found']);
        } else {
            echo json_encode(['success' => true, 'data' => $row]);
        }
        exit();
    }

    // List — optional status filter
    if ($status) {
        $stmt = $conn->prepare("
            SELECT id, case_no, case_title, category, status, priority,
                   date_reported, reporter_name, reporter_contact,
                   location, investigator_name, next_hearing_date, notes
            FROM blotter_cases
            WHERE status = ?
            ORDER BY created_at DESC
        ");
        $stmt->bind_param('s', $status);
    } else {
        $stmt = $conn->prepare("
            SELECT id, case_no, case_title, category, status, priority,
                   date_reported, reporter_name, reporter_contact,
                   location, investigator_name, next_hearing_date, notes,
                   description, summary
            FROM blotter_cases
            ORDER BY created_at DESC
        ");
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

// ============================================================
// POST — create new blotter case
// ============================================================
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    // Generate case number: BLT-YYYYMMDD-XXXX
    $datePart = date('Ymd');
    $countRow = $conn->query("SELECT COUNT(*) as c FROM blotter_cases WHERE DATE(created_at) = CURDATE()")->fetch_assoc();
    $seq      = str_pad(intval($countRow['c']) + 1, 4, '0', STR_PAD_LEFT);
    $caseNo   = "BLT-{$datePart}-{$seq}";

    $stmt = $conn->prepare("
        INSERT INTO blotter_cases (
            case_no, case_title, category, status, priority,
            incident_date, incident_time, location, summary, description,
            reporter_name, reporter_contact, reporter_email, reporter_address,
            persons_involved, witnesses,
            date_reported, created_at, updated_at
        ) VALUES (
            ?, ?, ?, 'Pending', ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?,
            NOW(), NOW(), NOW()
        )
    ");

    $stmt->bind_param(
        'sssssssssssssss',
        $caseNo,
        $body['case_title'],
        $body['category'],
        $body['priority'],
        $body['incident_date'],
        $body['incident_time'],
        $body['location'],
        $body['summary'],
        $body['description'],
        $body['reporter_name'],
        $body['reporter_contact'],
        $body['reporter_email'],
        $body['reporter_address'],
        $body['persons_involved'],
        $body['witnesses']
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'case_no' => $caseNo]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit();
}

// ============================================================
// PUT — update blotter case
// ============================================================
if ($method === 'PUT') {
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        exit();
    }

    $body = json_decode(file_get_contents('php://input'), true);

    $stmt = $conn->prepare("
        UPDATE blotter_cases SET
            case_title        = COALESCE(?, case_title),
            category          = COALESCE(?, category),
            status            = COALESCE(?, status),
            priority          = COALESCE(?, priority),
            location          = COALESCE(?, location),
            investigator_name = ?,
            next_hearing_date = ?,
            notes             = ?,
            schedule_location = ?,
            updated_at        = NOW()
        WHERE id = ?
    ");

    $caseTitle       = $body['case_title']       ?? null;
    $category        = $body['category']          ?? null;
    $status          = $body['status']            ?? null;
    $priority        = $body['priority']          ?? null;
    $location        = $body['location']          ?? null;
    $investigator    = $body['investigator_name'] ?? null;
    $hearingDate     = !empty($body['next_hearing_date']) ? $body['next_hearing_date'] : null;
    $notes           = $body['notes']             ?? null;
    $schedLocation   = $body['schedule_location'] ?? null;

    $stmt->bind_param(
        'sssssssssi',
        $caseTitle,
        $category,
        $status,
        $priority,
        $location,
        $investigator,
        $hearingDate,
        $notes,
        $schedLocation,
        $id
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Case updated']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit();
}

// ============================================================
// DELETE — remove blotter case
// ============================================================
if ($method === 'DELETE') {
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM blotter_cases WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Case deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);