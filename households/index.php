<?php
/**
 * households/index.php
 * GET  /households/          → list all households (with member count + head name)
 * GET  /households/?id=N     → single household detail + members
 * POST /households/          → create a new household (returns new id)
 */
require_once '../config/database.php';
header('Content-Type: application/json');

// ── Auth ────────────────────────────────────────────────────────────────────
$headers    = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
$token = substr($authHeader, 7);
$conn  = getConnection();

$stmt = $conn->prepare('SELECT u.id, u.name FROM users u
    INNER JOIN personal_access_tokens t ON t.tokenable_id = u.id WHERE t.token = ?');
$stmt->bind_param('s', $token);
$stmt->execute();
$authUser = $stmt->get_result()->fetch_assoc();
if (!$authUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? intval($_GET['id']) : null;

// ── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    if ($id) {
        // Single household + all members with emergency contacts
        $stmt = $conn->prepare('SELECT * FROM households WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $household = $stmt->get_result()->fetch_assoc();
        if (!$household) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Household not found']);
            $conn->close(); exit();
        }

        $stmt = $conn->prepare('
            SELECT
                r.id, r.full_name, r.gender, r.household_role,
                r.date_of_birth, r.civil_status, r.resident_type,
                r.nationality, r.religion,
                r.contact_mobile, r.contact_email,
                r.is_senior_citizen, r.is_pwd, r.pwd_type,
                r.chronic_illnesses, r.registered_voter, r.photo_url,
                e.name           AS ec_name,
                e.contact_number AS ec_contact,
                e.relationship   AS ec_relationship,
                emp.employment_status,
                emp.employer_name,
                emp.monthly_income_range
            FROM residents r
            LEFT JOIN resident_emergency_contacts e   ON e.resident_id  = r.id
            LEFT JOIN resident_employment         emp ON emp.resident_id = r.id
            WHERE r.household_id = ?
            ORDER BY FIELD(r.household_role,
                \'head\',\'spouse\',\'child\',\'sibling\',\'extended\',\'boarder\',\'other\')
        ');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($members as &$m) {
            $m['is_senior_citizen'] = (bool)$m['is_senior_citizen'];
            $m['is_pwd']            = (bool)$m['is_pwd'];
            $m['registered_voter']  = (bool)$m['registered_voter'];
        }
        unset($m);

        echo json_encode([
            'success'   => true,
            'household' => $household,
            'members'   => $members,
            'total'     => count($members),
        ]);

    } else {
        // List all households with member count + head name
        $result = $conn->query('
            SELECT h.id, h.household_code, h.address_line, h.purok_sitio, h.zone,
                   COUNT(r.id) AS member_count,
                   GROUP_CONCAT(
                       CASE WHEN r.household_role = \'head\' THEN r.full_name ELSE NULL END
                   ) AS head_name
            FROM households h
            LEFT JOIN residents r ON r.household_id = h.id
            GROUP BY h.id
            ORDER BY h.household_code ASC
        ');
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
    }

    $conn->close();
    exit();
}

// ── POST — create new household ──────────────────────────────────────────────
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $address  = trim($data['address_line'] ?? '');
    $purok    = trim($data['purok_sitio']  ?? '');
    $zone     = trim($data['zone']         ?? 'Zone 93');
    $barangay = trim($data['barangay']     ?? 'Barangay 853');
    $city     = trim($data['city']         ?? 'Pandacan, Manila');
    $province = trim($data['province']     ?? 'Metro Manila');
    $postal   = trim($data['postal_code']  ?? '1011');

    if (!$address) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'address_line is required']);
        $conn->close(); exit();
    }

    // Auto-generate household code: HH-93-XXX (next available number)
    $maxRes = $conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(household_code,'-',-1) AS UNSIGNED)) AS mx FROM households WHERE household_code LIKE 'HH-93-%'");
    $maxRow = $maxRes->fetch_assoc();
    $nextNum = (intval($maxRow['mx'] ?? 0)) + 1;
    $hhCode  = 'HH-93-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare('INSERT INTO households
        (household_code, address_line, purok_sitio, zone, barangay, city, province, postal_code, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('ssssssss',
        $hhCode, $address, $purok, $zone, $barangay, $city, $province, $postal);
    $stmt->execute();
    $newId = $conn->insert_id;

    echo json_encode([
        'success'        => true,
        'message'        => 'Household created successfully',
        'id'             => $newId,
        'household_code' => $hhCode,
    ]);
    $conn->close();
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
$conn->close();
