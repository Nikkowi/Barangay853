<?php
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$ref = isset($_GET['ref']) ? trim($_GET['ref']) : '';
if (empty($ref)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Reference number is required']);
    exit();
}

$conn = getConnection();

$stmt = $conn->prepare('SELECT id, reference_no, requester_name, document_type, purpose, status, date_filed, created_at, updated_at FROM document_requests WHERE reference_no = ?');
$stmt->bind_param('s', $ref);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

$conn->close();

if (!$doc) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No request found with that reference number. Please check and try again.']);
    exit();
}

// Build a timeline based on status
$statusTimeline = [
    ['step' => 'Submitted', 'done' => true,  'date' => $doc['date_filed'] ?? $doc['created_at']],
    ['step' => 'Under Review', 'done' => in_array($doc['status'], ['Review','Approved','Released','Rejected']), 'date' => null],
    ['step' => 'Approved',    'done' => in_array($doc['status'], ['Approved','Released']), 'date' => null],
    ['step' => 'Released',    'done' => $doc['status'] === 'Released', 'date' => null],
];
if ($doc['status'] === 'Rejected') {
    $statusTimeline = [
        ['step' => 'Submitted',  'done' => true,  'date' => $doc['date_filed'] ?? $doc['created_at']],
        ['step' => 'Under Review','done' => true, 'date' => null],
        ['step' => 'Rejected',   'done' => true,  'date' => $doc['updated_at']],
    ];
}

echo json_encode([
    'success'  => true,
    'data'     => $doc,
    'timeline' => $statusTimeline,
]);
?>
