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

// Overview counts
$totalResidents   = $conn->query('SELECT COUNT(*) as c FROM residents')->fetch_assoc()['c'];
$pendingRequests  = $conn->query('SELECT COUNT(*) as c FROM document_requests WHERE status = "Pending"')->fetch_assoc()['c'];
$activeBlotters   = $conn->query('SELECT COUNT(*) as c FROM blotter_cases WHERE status NOT IN ("Resolved","Closed")')->fetch_assoc()['c'];
$totalHouseholds  = $conn->query('SELECT COUNT(*) as c FROM households')->fetch_assoc()['c'];

// Recent 7-day counts
$recentResidents  = $conn->query('SELECT COUNT(*) as c FROM residents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetch_assoc()['c'];
$recentDocs       = $conn->query('SELECT COUNT(*) as c FROM document_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetch_assoc()['c'];
$recentBlotters   = $conn->query('SELECT COUNT(*) as c FROM blotter_cases WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetch_assoc()['c'];

// Document requests by status
$docStatusRows = $conn->query('SELECT status, COUNT(*) as count FROM document_requests GROUP BY status')->fetch_all(MYSQLI_ASSOC);
$docByStatus = [];
foreach ($docStatusRows as $r) $docByStatus[$r['status']] = intval($r['count']);

// Document requests by type
$docTypeRows = $conn->query('SELECT document_type, COUNT(*) as count FROM document_requests GROUP BY document_type ORDER BY count DESC LIMIT 8')->fetch_all(MYSQLI_ASSOC);

// Blotter cases by status
$blotterStatusRows = $conn->query('SELECT status, COUNT(*) as count FROM blotter_cases GROUP BY status')->fetch_all(MYSQLI_ASSOC);
$blotterByStatus = [];
foreach ($blotterStatusRows as $r) $blotterByStatus[$r['status']] = intval($r['count']);

// Blotter cases by category
$blotterCatRows = $conn->query('SELECT category, COUNT(*) as count FROM blotter_cases WHERE category IS NOT NULL AND category != "" GROUP BY category ORDER BY count DESC LIMIT 8')->fetch_all(MYSQLI_ASSOC);

// Monthly trends — last 6 months (documents + blotters + residents)
$monthlyTrends = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as count, 'documents' as type
    FROM document_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    UNION ALL
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as count, 'blotters' as type
    FROM blotter_cases WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    UNION ALL
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as count, 'residents' as type
    FROM residents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ORDER BY month DESC
")->fetch_all(MYSQLI_ASSOC);

// Resident gender breakdown
$genderRows = $conn->query('SELECT gender, COUNT(*) as count FROM residents WHERE gender IS NOT NULL AND gender != "" GROUP BY gender')->fetch_all(MYSQLI_ASSOC);

// Senior citizens & PWD counts
$seniorCount = $conn->query('SELECT COUNT(*) as c FROM residents WHERE is_senior_citizen = 1')->fetch_assoc()['c'];
$pwdCount    = $conn->query('SELECT COUNT(*) as c FROM residents WHERE is_pwd = 1')->fetch_assoc()['c'];
$voterCount  = $conn->query('SELECT COUNT(*) as c FROM residents WHERE registered_voter = 1')->fetch_assoc()['c'];

// Recent document requests
$recentRequests = $conn->query('SELECT id, reference_no, requester_name, document_type, date_filed, status FROM document_requests ORDER BY created_at DESC LIMIT 5')->fetch_all(MYSQLI_ASSOC);

// Recent activity logs
$recentLogs = $conn->query('SELECT id, actor_name, action, module, reference_id, logged_at FROM activity_logs ORDER BY logged_at DESC LIMIT 10')->fetch_all(MYSQLI_ASSOC);

$conn->close();

echo json_encode([
    'success' => true,
    'data' => [
        'overview' => [
            'total_residents'  => intval($totalResidents),
            'pending_requests' => intval($pendingRequests),
            'active_blotters'  => intval($activeBlotters),
            'total_households' => intval($totalHouseholds),
        ],
        'recent_7days' => [
            'residents' => intval($recentResidents),
            'documents' => intval($recentDocs),
            'blotters'  => intval($recentBlotters),
        ],
        'documents' => [
            'by_status' => $docByStatus,
            'by_type'   => $docTypeRows,
        ],
        'blotters' => [
            'by_status'   => $blotterByStatus,
            'by_category' => $blotterCatRows,
        ],
        'residents' => [
            'by_gender' => $genderRows,
            'seniors'   => intval($seniorCount),
            'pwd'       => intval($pwdCount),
            'voters'    => intval($voterCount),
        ],
        'trends'          => $monthlyTrends,
        'recent_requests' => $recentRequests,
        'recent_logs'     => $recentLogs,
    ]
]);
?>
