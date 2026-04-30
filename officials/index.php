<?php
/**
 * officials/index.php
 * GET    /officials/          → list all officials (public, no auth needed)
 * POST   /officials/          → create official  (auth required)
 * PUT    /officials/?id=N     → update official  (auth required)
 * DELETE /officials/?id=N     → delete official  (auth required)
 */
require_once '../config/database.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? intval($_GET['id']) : null;
$conn   = getConnection();

// ── Auth helper (not required for GET) ──────────────────────────────────────
function requireAuth($conn) {
    $headers    = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    $token = substr($authHeader, 7);
    $stmt  = $conn->prepare(
        'SELECT u.id, u.name FROM users u
         INNER JOIN personal_access_tokens t ON t.tokenable_id = u.id
         WHERE t.token = ?'
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit();
    }
    return $user;
}

// ── GET — public list ────────────────────────────────────────────────────────
if ($method === 'GET') {
    $result = $conn->query(
        'SELECT id, name, position, gender, sort_order, photo_url
         FROM barangay_officials
         ORDER BY sort_order ASC, id ASC'
    );
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
    $conn->close();
    exit();
}

// ── All other methods require auth ───────────────────────────────────────────
requireAuth($conn);

$data = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST — create ────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $name      = trim($data['name']      ?? '');
    $position  = trim($data['position']  ?? '');
    $gender    = trim($data['gender']    ?? 'Male');
    $sortOrder = intval($data['sort_order'] ?? 0);
    $photoUrl  = trim($data['photo_url'] ?? '') ?: null;

    if (!$name || !$position) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name and position are required']);
        $conn->close(); exit();
    }

    $stmt = $conn->prepare(
        'INSERT INTO barangay_officials
         (name, position, gender, sort_order, photo_url, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->bind_param('sssiss', $name, $position, $gender, $sortOrder, $photoUrl);
    // fix: sort_order is int
    $stmt = $conn->prepare(
        'INSERT INTO barangay_officials
         (name, position, gender, sort_order, photo_url, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->bind_param('sssis', $name, $position, $gender, $sortOrder, $photoUrl);
    $stmt->execute();
    $newId = $conn->insert_id;

    echo json_encode(['success' => true, 'message' => 'Official added', 'id' => $newId]);
    $conn->close();
    exit();
}

// ── PUT — update ─────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        $conn->close(); exit();
    }

    $name      = trim($data['name']      ?? '');
    $position  = trim($data['position']  ?? '');
    $gender    = trim($data['gender']    ?? 'Male');
    $sortOrder = intval($data['sort_order'] ?? 0);
    $photoUrl  = trim($data['photo_url'] ?? '') ?: null;

    if (!$name || !$position) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name and position are required']);
        $conn->close(); exit();
    }

    $stmt = $conn->prepare(
        'UPDATE barangay_officials
         SET name=?, position=?, gender=?, sort_order=?, photo_url=?, updated_at=NOW()
         WHERE id=?'
    );
    $stmt->bind_param('sssisi', $name, $position, $gender, $sortOrder, $photoUrl, $id);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Official updated']);
    $conn->close();
    exit();
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        $conn->close(); exit();
    }
    $stmt = $conn->prepare('DELETE FROM barangay_officials WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Official deleted']);
    $conn->close();
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
$conn->close();
