<?php
require_once '../config/database.php';

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

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit();
}

$like = '%' . $q . '%';
$results = [];

// Search residents
$stmt = $conn->prepare('SELECT id, full_name as label, contact_mobile as sub, "resident" as type FROM residents WHERE full_name LIKE ? OR contact_mobile LIKE ? LIMIT 5');
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $r) $results[] = $r;

// Search document requests
$stmt = $conn->prepare('SELECT id, CONCAT(requester_name, " — ", document_type) as label, reference_no as sub, "document" as type FROM document_requests WHERE requester_name LIKE ? OR reference_no LIKE ? OR document_type LIKE ? LIMIT 5');
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $r) $results[] = $r;

// Search blotter cases
$stmt = $conn->prepare('SELECT id, case_title as label, case_no as sub, "blotter" as type FROM blotter_cases WHERE case_title LIKE ? OR case_no LIKE ? OR reporter_name LIKE ? LIMIT 5');
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $r) $results[] = $r;

// Search announcements
$stmt = $conn->prepare('SELECT id, title as label, status as sub, "announcement" as type FROM announcements WHERE title LIKE ? OR body LIKE ? LIMIT 3');
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $r) $results[] = $r;

$conn->close();
echo json_encode(['success' => true, 'results' => $results, 'query' => $q]);
?>
