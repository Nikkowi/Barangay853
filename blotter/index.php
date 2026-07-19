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

    // ── Case number: BLT-YYYYMMDD-XXXX ──────────────────────────────────────
    $datePart = date('Ymd');
    $countRow = $conn->query("SELECT COUNT(*) as c FROM blotter_cases WHERE DATE(created_at) = CURDATE()")->fetch_assoc();
    $seq      = str_pad(intval($countRow['c']) + 1, 4, '0', STR_PAD_LEFT);
    $caseNo   = "BLT-{$datePart}-{$seq}";

    // ── Evidence fields ──────────────────────────────────────────────────────
    $hasEvidence     = (!empty($body['has_evidence']) && $body['has_evidence']) ? 1 : 0;
    $evidenceCCTV    = (!empty($body['evidence_cctv']) && $body['evidence_cctv'])  ? 1 : 0;
    $evidenceEW      = $body['evidence_eyewitnesses'] ?? 'None';
    $evidenceOther   = $body['evidence_other']        ?? 'None';
    $evidenceSummary = $body['evidence_summary']      ?? 'None';

    // ── Auto-escalate: evidence present → For Summons, else → Pending ───────
    $derivedStatus = ($hasEvidence && $evidenceSummary !== 'None') ? 'For Summons' : 'Pending';
    $priority      = $body['priority'] ?? 'Normal';

    $stmt = $conn->prepare("
        INSERT INTO blotter_cases (
            case_no, case_title, category, status, priority,
            incident_date, incident_time, location, summary, description,
            reporter_name, reporter_contact, reporter_email, reporter_address,
            persons_involved, witnesses,
            has_evidence, evidence_cctv, evidence_eyewitnesses,
            evidence_other, evidence_summary,
            summon_count,
            date_reported, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?,
            0,
            NOW(), NOW(), NOW()
        )
    ");

    $stmt->bind_param(
        'ssssssssssssssssissss',
        $caseNo,
        $body['case_title'],
        $body['category'],
        $derivedStatus,
        $priority,
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
        $body['witnesses'],
        $hasEvidence,
        $evidenceCCTV,
        $evidenceEW,
        $evidenceOther,
        $evidenceSummary
    );

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'id'      => $conn->insert_id,
            'case_no' => $caseNo,
            'status'  => $derivedStatus,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit();
}

// ============================================================
// PUT — update blotter case (staff/admin only)
// ?action=summon    — issue/increment a summon
// ?action=testimony — append a staff testimony note
// (default)         — general case field update
// ============================================================
if ($method === 'PUT') {
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        exit();
    }

    $body   = json_decode(file_get_contents('php://input'), true);
    $action = isset($_GET['action']) ? $_GET['action'] : 'update';

    // ── ACTION: Issue a summon ───────────────────────────────────────────────
    if ($action === 'summon') {
        $cur = $conn->prepare("SELECT summon_count, status FROM blotter_cases WHERE id = ?");
        $cur->bind_param('i', $id);
        $cur->execute();
        $row = $cur->get_result()->fetch_assoc();
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Case not found']); exit(); }

        $newCount    = intval($row['summon_count']) + 1;
        $hearingDate = !empty($body['next_hearing_date']) ? $body['next_hearing_date'] : null;
        $schedLoc    = isset($body['schedule_location']) ? $body['schedule_location'] : null;
        $notes       = isset($body['notes'])             ? $body['notes']             : null;
        $investigator= isset($body['investigator_name']) ? $body['investigator_name'] : null;

        // Status progression
        $statusMap = [1 => '1st Summon Issued', 2 => '2nd Summon Issued', 3 => '3rd Summon Issued'];
        $newStatus = isset($statusMap[$newCount]) ? $statusMap[$newCount] : 'Referred to Police';
        if (!empty($body['status'])) $newStatus = $body['status'];

        $stmt = $conn->prepare("
            UPDATE blotter_cases SET
                summon_count      = ?,
                status            = ?,
                next_hearing_date = ?,
                schedule_location = ?,
                notes             = COALESCE(?, notes),
                investigator_name = COALESCE(?, investigator_name),
                updated_at        = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('isssssi', $newCount, $newStatus, $hearingDate, $schedLoc, $notes, $investigator, $id);

        if ($stmt->execute()) {
            echo json_encode([
                'success'      => true,
                'message'      => 'Summon #' . $newCount . ' issued.',
                'summon_count' => $newCount,
                'status'       => $newStatus,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit();
    }

    // ── ACTION: Staff appends a testimony / follow-up note ───────────────────
    if ($action === 'testimony') {
        $noteText = trim(isset($body['note'])     ? $body['note']     : '');
        $noteBy   = trim(isset($body['noted_by']) ? $body['noted_by'] : $authUser['name']);
        $noteDate = date('Y-m-d H:i');

        if (!$noteText) {
            echo json_encode(['success' => false, 'message' => 'Note text is required']);
            exit();
        }

        $fetch = $conn->prepare("SELECT testimony_log FROM blotter_cases WHERE id = ?");
        $fetch->bind_param('i', $id);
        $fetch->execute();
        $existing = $fetch->get_result()->fetch_assoc();
        $log      = json_decode(isset($existing['testimony_log']) ? $existing['testimony_log'] : '[]', true);
        if (!is_array($log)) $log = [];
        $log[] = ['date' => $noteDate, 'by' => $noteBy, 'note' => $noteText];
        $logJson = json_encode($log, JSON_UNESCAPED_UNICODE);

        $stmt = $conn->prepare("UPDATE blotter_cases SET testimony_log = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $logJson, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Testimony note added.', 'log' => $log]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit();
    }

    // ── DEFAULT: General case update ─────────────────────────────────────────
    $caseTitle    = isset($body['case_title'])        ? $body['case_title']        : null;
    $category     = isset($body['category'])          ? $body['category']          : null;
    $status       = isset($body['status'])            ? $body['status']            : null;
    $priority     = isset($body['priority'])          ? $body['priority']          : null;
    $location     = isset($body['location'])          ? $body['location']          : null;
    $investigator = isset($body['investigator_name']) ? $body['investigator_name'] : null;
    $hearingDate  = !empty($body['next_hearing_date'])? $body['next_hearing_date'] : null;
    $notes        = isset($body['notes'])             ? $body['notes']             : null;
    $schedLoc     = isset($body['schedule_location']) ? $body['schedule_location'] : null;

    $stmt = $conn->prepare("
        UPDATE blotter_cases SET
            case_title        = COALESCE(?, case_title),
            category          = COALESCE(?, category),
            status            = COALESCE(?, status),
            priority          = COALESCE(?, priority),
            location          = COALESCE(?, location),
            investigator_name = COALESCE(?, investigator_name),
            next_hearing_date = ?,
            notes             = COALESCE(?, notes),
            schedule_location = COALESCE(?, schedule_location),
            updated_at        = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('sssssssssi', $caseTitle, $category, $status, $priority, $location,
                      $investigator, $hearingDate, $notes, $schedLoc, $id);

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