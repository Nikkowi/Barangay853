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

// ─── Safe query helpers ───────────────────────────────────────────────────────
// Returns [] on failure instead of crashing
function safeRows($conn, $sql) {
    $r = $conn->query($sql);
    if ($r === false) {
        error_log('safeRows failed: ' . $conn->error . ' | SQL: ' . $sql);
        return [];
    }
    return $r->fetch_all(MYSQLI_ASSOC);
}

// Returns [] on failure for single-row fetches
function safeRow($conn, $sql) {
    $r = $conn->query($sql);
    if ($r === false) {
        error_log('safeRow failed: ' . $conn->error . ' | SQL: ' . $sql);
        return [];
    }
    $row = $r->fetch_assoc();
    return $row ?: [];
}

// Returns 0 on failure for COUNT queries
function safeCount($conn, $sql) {
    $r = $conn->query($sql);
    if ($r === false) {
        error_log('safeCount failed: ' . $conn->error . ' | SQL: ' . $sql);
        return 0;
    }
    $row = $r->fetch_assoc();
    return $row ? intval(reset($row)) : 0;
}

// ============================================================
// OVERVIEW COUNTS
// ============================================================
$totalResidents  = safeCount($conn, 'SELECT COUNT(*) as c FROM residents');
$pendingRequests = safeCount($conn, 'SELECT COUNT(*) as c FROM document_requests WHERE status = "Pending"');
$activeBlotters  = safeCount($conn, 'SELECT COUNT(*) as c FROM blotter_cases WHERE status NOT IN ("Resolved","Closed")');

// households table may not exist — fallback to 0 safely
$totalHouseholds = safeCount($conn, 'SELECT COUNT(*) as c FROM households');

// Recent 7-day counts
$recentResidents = safeCount($conn, 'SELECT COUNT(*) as c FROM residents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$recentDocs      = safeCount($conn, 'SELECT COUNT(*) as c FROM document_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$recentBlotters  = safeCount($conn, 'SELECT COUNT(*) as c FROM blotter_cases WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');

// ============================================================
// DOCUMENT QUERIES
// ============================================================
$docStatusRows = safeRows($conn, 'SELECT status, COUNT(*) as count FROM document_requests GROUP BY status');
$docByStatus = [];
foreach ($docStatusRows as $r) $docByStatus[$r['status']] = intval($r['count']);

$docTypeRows = safeRows($conn, 'SELECT document_type, COUNT(*) as count FROM document_requests GROUP BY document_type ORDER BY count DESC LIMIT 8');

// ============================================================
// BLOTTER QUERIES — EXISTING
// ============================================================
$blotterStatusRows = safeRows($conn, 'SELECT status, COUNT(*) as count FROM blotter_cases GROUP BY status');
$blotterByStatus = [];
foreach ($blotterStatusRows as $r) $blotterByStatus[$r['status']] = intval($r['count']);

$blotterCatRows = safeRows($conn, 'SELECT category, COUNT(*) as count FROM blotter_cases WHERE category IS NOT NULL AND category != "" GROUP BY category ORDER BY count DESC LIMIT 8');

// ============================================================
// BLOTTER QUERIES — EXPANDED
// ============================================================

$blotterMonthlyByCat = safeRows($conn, "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        category,
        COUNT(*) AS count
    FROM blotter_cases
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
      AND category IS NOT NULL AND category != ''
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), category
    ORDER BY month ASC
");

$blotterByPriority = safeRows($conn, "
    SELECT priority, COUNT(*) AS count
    FROM blotter_cases
    WHERE priority IS NOT NULL AND priority != ''
    GROUP BY priority
    ORDER BY FIELD(priority, 'Urgent', 'High', 'Normal', 'Medium', 'Low')
");

$blotterPriorityStatus = safeRows($conn, "
    SELECT priority, status, COUNT(*) AS count
    FROM blotter_cases
    WHERE priority IS NOT NULL AND priority != ''
    GROUP BY priority, status
    ORDER BY FIELD(priority, 'Urgent', 'High', 'Normal', 'Medium', 'Low'), status
");

$blotterAvgResolutionByCat = safeRows($conn, "
    SELECT 
        category,
        ROUND(AVG(DATEDIFF(updated_at, created_at)), 1) AS avg_days,
        COUNT(*) AS case_count
    FROM blotter_cases
    WHERE status IN ('Resolved', 'Closed')
      AND category IS NOT NULL AND category != ''
      AND updated_at > created_at
    GROUP BY category
    ORDER BY avg_days DESC
");

$blotterInvestigatorLoad = safeRows($conn, "
    SELECT 
        COALESCE(NULLIF(investigator_name, ''), 'Unassigned') AS investigator,
        COUNT(*) AS open_cases,
        SUM(CASE WHEN priority IN ('Urgent','High') THEN 1 ELSE 0 END) AS high_priority_cases
    FROM blotter_cases
    WHERE status NOT IN ('Resolved', 'Closed')
    GROUP BY investigator_name
    ORDER BY open_cases DESC
    LIMIT 10
");

$blotterTimeHeatmap = safeRows($conn, "
    SELECT 
        HOUR(incident_time) AS hour_of_day,
        DAYOFWEEK(incident_date) AS day_of_week,
        COUNT(*) AS count
    FROM blotter_cases
    WHERE incident_time IS NOT NULL
      AND incident_date IS NOT NULL
      AND incident_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY HOUR(incident_time), DAYOFWEEK(incident_date)
    ORDER BY day_of_week, hour_of_day
");

$blotterLocationHotspots = safeRows($conn, "
    SELECT 
        TRIM(location) AS location,
        COUNT(*) AS count,
        SUM(CASE WHEN status NOT IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS open_count
    FROM blotter_cases
    WHERE location IS NOT NULL AND location != ''
    GROUP BY TRIM(location)
    ORDER BY count DESC
    LIMIT 10
");

$blotterRepeatReporters = safeRows($conn, "
    SELECT 
        reporter_name,
        reporter_contact,
        COUNT(*) AS total_cases,
        SUM(CASE WHEN status NOT IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS open_cases,
        MIN(created_at) AS first_case,
        MAX(created_at) AS latest_case
    FROM blotter_cases
    WHERE reporter_name IS NOT NULL AND reporter_name != ''
    GROUP BY reporter_name, reporter_contact
    HAVING total_cases > 1
    ORDER BY total_cases DESC
    LIMIT 10
");

$blotterUpcomingHearings = safeRows($conn, "
    SELECT 
        id,
        case_no,
        case_title,
        category,
        status,
        priority,
        next_hearing_date,
        COALESCE(NULLIF(schedule_location, ''), location) AS hearing_location,
        COALESCE(NULLIF(investigator_name, ''), 'Unassigned') AS investigator
    FROM blotter_cases
    WHERE next_hearing_date IS NOT NULL
      AND next_hearing_date >= CURDATE()
      AND next_hearing_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      AND status NOT IN ('Resolved', 'Closed')
    ORDER BY next_hearing_date ASC
    LIMIT 20
");

$blotterCaseAge = safeRows($conn, "
    SELECT 
        id,
        case_no,
        case_title,
        category,
        priority,
        status,
        COALESCE(NULLIF(investigator_name, ''), 'Unassigned') AS investigator,
        created_at,
        DATEDIFF(NOW(), created_at) AS days_open
    FROM blotter_cases
    WHERE status NOT IN ('Resolved', 'Closed')
    ORDER BY days_open DESC
    LIMIT 15
");

$blotterMonthlyTrend = safeRows($conn, "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS filed,
        SUM(CASE WHEN status IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS resolved
    FROM blotter_cases
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");

// KPIs — uses safeRow since it returns one row
$blotterKPIs = safeRow($conn, "
    SELECT
        COUNT(*) AS total_cases,
        SUM(CASE WHEN status = 'Pending'             THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'Active'              THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status = 'Under Investigation' THEN 1 ELSE 0 END) AS under_investigation,
        SUM(CASE WHEN status IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS resolved_closed,
        SUM(CASE WHEN priority = 'Urgent'            THEN 1 ELSE 0 END) AS urgent,
        SUM(CASE WHEN priority = 'High'              THEN 1 ELSE 0 END) AS high_priority,
        SUM(CASE WHEN investigator_name IS NULL OR investigator_name = '' THEN 1 ELSE 0 END) AS unassigned,
        ROUND(AVG(CASE WHEN status IN ('Resolved','Closed') AND updated_at > created_at 
                       THEN DATEDIFF(updated_at, created_at) END), 1) AS avg_resolution_days
    FROM blotter_cases
");

// ============================================================
// SHARED TRENDS
// ============================================================
$monthlyTrends = safeRows($conn, "
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
");

// ============================================================
// RESIDENT QUERIES
// ============================================================
$genderRows  = safeRows($conn, 'SELECT gender, COUNT(*) as count FROM residents WHERE gender IS NOT NULL AND gender != "" GROUP BY gender');
$seniorCount = safeCount($conn, 'SELECT COUNT(*) as c FROM residents WHERE is_senior_citizen = 1');
$pwdCount    = safeCount($conn, 'SELECT COUNT(*) as c FROM residents WHERE is_pwd = 1');
$voterCount  = safeCount($conn, 'SELECT COUNT(*) as c FROM residents WHERE registered_voter = 1');

// ============================================================
// RECENT RECORDS
// ============================================================
$recentRequests = safeRows($conn, 'SELECT id, reference_no, requester_name, document_type, date_filed, status FROM document_requests ORDER BY created_at DESC LIMIT 5');
$recentLogs     = safeRows($conn, 'SELECT id, actor_name, action, module, reference_id, logged_at FROM activity_logs ORDER BY logged_at DESC LIMIT 10');

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
            'by_status'             => $blotterByStatus,
            'by_category'           => $blotterCatRows,
            'kpis'                  => $blotterKPIs,
            'monthly_by_category'   => $blotterMonthlyByCat,
            'monthly_trend'         => $blotterMonthlyTrend,
            'by_priority'           => $blotterByPriority,
            'priority_x_status'     => $blotterPriorityStatus,
            'avg_resolution_by_cat' => $blotterAvgResolutionByCat,
            'investigator_load'     => $blotterInvestigatorLoad,
            'time_heatmap'          => $blotterTimeHeatmap,
            'location_hotspots'     => $blotterLocationHotspots,
            'repeat_reporters'      => $blotterRepeatReporters,
            'upcoming_hearings'     => $blotterUpcomingHearings,
            'case_age_report'       => $blotterCaseAge,
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