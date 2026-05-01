<?php
require_once '../config/database.php';

// ── CORS (allows the public homepage to call this endpoint) ──────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit(); }

$method = $_SERVER['REQUEST_METHOD'];

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$isPublic   = isset($_GET['public']);

if (!$isPublic) {
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    $token = substr($authHeader, 7);
    $conn  = getConnection();
    $stmt  = $conn->prepare('SELECT u.id, u.name, u.role FROM users u
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
} else {
    $conn      = getConnection();
    $actorName = 'Public';
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function logActivity($conn, $actorName, $action, $module, $referenceId) {
    $stmt = $conn->prepare('INSERT INTO activity_logs
        (actor_name, action, module, reference_id, logged_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())');
    $stmt->bind_param('ssss', $actorName, $action, $module, $referenceId);
    $stmt->execute();
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : null;

// ── Route ─────────────────────────────────────────────────────────────────────
switch ($method) {

    // ── GET ──────────────────────────────────────────────────────────────────
    case 'GET':
        // Budget config endpoint
        if (isset($_GET['budget'])) {
            $year  = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
            $stmt  = $conn->prepare('SELECT * FROM budget_config WHERE fiscal_year = ?');
            $stmt->bind_param('i', $year);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            jsonResponse(['success' => true, 'data' => $row]);
        }

        // Single record
        if ($id) {
            $stmt = $conn->prepare('SELECT * FROM expenses WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) jsonResponse(['success' => false, 'message' => 'Record not found'], 404);
            jsonResponse(['success' => true, 'data' => $row]);
        }

        // List — public only shows Posted records
        $year     = isset($_GET['year'])     ? intval($_GET['year'])         : null;
        $month    = isset($_GET['month'])    ? $_GET['month']                : null; // YYYY-MM
        $category = isset($_GET['category']) ? $_GET['category']             : null;
        $status   = isset($_GET['status'])   ? $_GET['status']               : null;

        // Public: always filter to Posted only. Authenticated: honour ?status param if supplied.
        if ($isPublic) {
            $where  = "WHERE e.status = 'Posted'";
        } elseif ($status) {
            $where  = "WHERE e.status = ?";
        } else {
            $where  = "WHERE 1=1";
        }
        $params = [];
        $types  = '';

        // Prepend status param for authenticated filtered queries
        if (!$isPublic && $status) {
            $params[] = $status; $types .= 's';
        }

        if ($year) {
            $where .= ' AND e.fiscal_year = ?';
            $params[] = $year; $types .= 'i';
        }
        if ($month) {
            $where .= ' AND DATE_FORMAT(e.date, "%Y-%m") = ?';
            $params[] = $month; $types .= 's';
        }
        if ($category) {
            $where .= ' AND e.category = ?';
            $params[] = $category; $types .= 's';
        }

        $sql  = "SELECT e.* FROM expenses e $where ORDER BY e.date DESC, e.id DESC";
        $stmt = $conn->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Summary stats
        $totalSpent = array_sum(array_column($rows, 'amount'));
        $latestBal  = !empty($rows) ? $rows[0]['balance_after'] : 0;

        // Annual budget
        $budgetYear = $year ?: date('Y');
        $bStmt = $conn->prepare('SELECT annual_budget FROM budget_config WHERE fiscal_year = ?');
        $bStmt->bind_param('i', $budgetYear);
        $bStmt->execute();
        $bRow = $bStmt->get_result()->fetch_assoc();
        $annualBudget = $bRow ? $bRow['annual_budget'] : 0;

        jsonResponse([
            'success'       => true,
            'data'          => $rows,
            'total'         => count($rows),
            'total_spent'   => $totalSpent,
            'latest_balance'=> $latestBal,
            'annual_budget' => $annualBudget,
        ]);
        break;

    // ── POST ─────────────────────────────────────────────────────────────────
    case 'POST':
        if ($isPublic) jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);

        // Budget update shortcut: POST ?budget=1
        if (isset($_GET['budget'])) {
            $data   = json_decode(file_get_contents('php://input'), true);
            $year   = isset($data['fiscal_year'])   ? intval($data['fiscal_year'])   : date('Y');
            $amount = isset($data['annual_budget'])  ? floatval($data['annual_budget']) : 0;
            $notes  = $data['notes'] ?? null;

            $stmt = $conn->prepare('INSERT INTO budget_config (fiscal_year, annual_budget, notes, updated_by)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE annual_budget = VALUES(annual_budget),
                                        notes         = VALUES(notes),
                                        updated_by    = VALUES(updated_by),
                                        updated_at    = NOW()');
            $stmt->bind_param('idss', $year, $amount, $notes, $actorName);
            $stmt->execute();
            logActivity($conn, $actorName, 'Updated Budget', 'Expenses', "FY$year — ₱" . number_format($amount, 2));
            jsonResponse(['success' => true, 'message' => 'Budget updated successfully']);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['title']) || !isset($data['date']) || !isset($data['amount'])) {
            jsonResponse(['success' => false, 'message' => 'title, date, and amount are required'], 400);
        }

        $refNo       = 'EXP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $date        = $data['date'];
        $category    = $data['category']    ?? 'other';
        $title       = $data['title'];
        $description = $data['description'] ?? null;
        $amount      = floatval($data['amount']);
        $balanceAfter= floatval($data['balance_after'] ?? 0);
        $approvedBy  = $data['approved_by'] ?? null;
        $fiscalYear  = $data['fiscal_year'] ?? date('Y');
        $postedBy    = $data['posted_by']   ?? $actorName;
        $status      = $data['status']      ?? 'Posted';

        $stmt = $conn->prepare('INSERT INTO expenses
            (reference_no, date, category, title, description, amount, balance_after,
             approved_by, fiscal_year, posted_by, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->bind_param('sssssddssss',
            $refNo, $date, $category, $title, $description,
            $amount, $balanceAfter, $approvedBy, $fiscalYear, $postedBy, $status);
        $stmt->execute();
        $newId = $conn->insert_id;

        logActivity($conn, $actorName, 'Added Expense', 'Expenses', $refNo . ' — ' . $title);
        jsonResponse(['success' => true, 'message' => 'Expense recorded successfully',
            'id' => $newId, 'reference_no' => $refNo]);
        break;

    // ── PUT ──────────────────────────────────────────────────────────────────
    case 'PUT':
        if ($isPublic) jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID is required'], 400);

        $data = json_decode(file_get_contents('php://input'), true);

        // Fetch existing
        $fStmt = $conn->prepare('SELECT * FROM expenses WHERE id = ?');
        $fStmt->bind_param('i', $id);
        $fStmt->execute();
        $cur = $fStmt->get_result()->fetch_assoc();
        if (!$cur) jsonResponse(['success' => false, 'message' => 'Record not found'], 404);

        $date         = $data['date']          ?? $cur['date'];
        $category     = $data['category']      ?? $cur['category'];
        $title        = $data['title']         ?? $cur['title'];
        $description  = $data['description']   ?? $cur['description'];
        $amount       = floatval($data['amount']        ?? $cur['amount']);
        $balanceAfter = floatval($data['balance_after'] ?? $cur['balance_after']);
        $approvedBy   = $data['approved_by']   ?? $cur['approved_by'];
        $fiscalYear   = $data['fiscal_year']   ?? $cur['fiscal_year'];
        $postedBy     = $data['posted_by']     ?? $cur['posted_by'];
        $status       = $data['status']        ?? $cur['status'];

        $stmt = $conn->prepare('UPDATE expenses SET
            date=?, category=?, title=?, description=?, amount=?, balance_after=?,
            approved_by=?, fiscal_year=?, posted_by=?, status=?, updated_at=NOW()
            WHERE id=?');
        $stmt->bind_param('ssssddssssi',
            $date, $category, $title, $description, $amount, $balanceAfter,
            $approvedBy, $fiscalYear, $postedBy, $status, $id);
        $stmt->execute();

        logActivity($conn, $actorName, 'Updated Expense', 'Expenses', 'EXP-' . $id . ' — ' . $title);
        jsonResponse(['success' => true, 'message' => 'Expense updated successfully']);
        break;

    // ── DELETE ───────────────────────────────────────────────────────────────
    case 'DELETE':
        if ($isPublic) jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID is required'], 400);

        $tStmt = $conn->prepare('SELECT title FROM expenses WHERE id = ?');
        $tStmt->bind_param('i', $id);
        $tStmt->execute();
        $expTitle = $tStmt->get_result()->fetch_assoc()['title'] ?? 'Unknown';

        $stmt = $conn->prepare('DELETE FROM expenses WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        logActivity($conn, $actorName, 'Deleted Expense', 'Expenses', 'EXP-' . $id . ' — ' . $expTitle);
        jsonResponse(['success' => true, 'message' => 'Expense deleted successfully']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$conn->close();
?>
