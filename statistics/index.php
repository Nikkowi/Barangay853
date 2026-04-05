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

// Total residents
$totalResidents = $conn->query('SELECT COUNT(*) as count FROM residents')->fetch_assoc()['count'];

// Pending document requests
$pendingRequests = $conn->query('SELECT COUNT(*) as count FROM document_requests WHERE status = "Pending"')->fetch_assoc()['count'];

// Active blotter cases
$activeBlotters = $conn->query('SELECT COUNT(*) as count FROM blotter_cases WHERE status NOT IN ("Resolved", "Closed")')->fetch_assoc()['count'];

// Total households
$totalHouseholds = $conn->query('SELECT COUNT(*) as count FROM households')->fetch_assoc()['count'];

// Recent document requests
$recentRequests = $conn->query('SELECT id, reference_no, requester_name, document_type, date_filed, status 
    FROM document_requests 
    ORDER BY created_at DESC 
    LIMIT 5')->fetch_all(MYSQLI_ASSOC);

// Recent activity logs
$recentLogs = $conn->query('SELECT id, actor_name, action, module, reference_id, logged_at 
    FROM activity_logs 
    ORDER BY logged_at DESC 
    LIMIT 10')->fetch_all(MYSQLI_ASSOC);

$conn->close();

echo json_encode([
    'success' => true,
    'data' => [
        'total_residents' => intval($totalResidents),
        'pending_requests' => intval($pendingRequests),
        'active_blotters' => intval($activeBlotters),
        'total_households' => intval($totalHouseholds),
        'recent_requests' => $recentRequests,
        'recent_logs' => $recentLogs
    ]
]);
?>