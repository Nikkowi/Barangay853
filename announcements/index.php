<?php
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$isPublic = isset($_GET['public']);

if (!$isPublic) {
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
} else {
    $conn = getConnection();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($method) {
    case 'GET':
        if ($id) {
            $stmt = $conn->prepare('SELECT * FROM announcements WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $ann = $stmt->get_result()->fetch_assoc();
            if (!$ann) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Announcement not found']);
            } else {
                echo json_encode(['success' => true, 'data' => $ann]);
            }
        } else {
            if ($isPublic) {
                // Public: only published and not expired
                $category = isset($_GET['category']) ? $_GET['category'] : null;
                if ($category) {
                    $stmt = $conn->prepare('SELECT * FROM announcements 
                        WHERE status = "Published" 
                        AND (expires_on IS NULL OR expires_on >= CURDATE())
                        AND category = ?
                        ORDER BY published_on DESC');
                    $stmt->bind_param('s', $category);
                } else {
                    $stmt = $conn->prepare('SELECT * FROM announcements 
                        WHERE status = "Published" 
                        AND (expires_on IS NULL OR expires_on >= CURDATE())
                        ORDER BY published_on DESC');
                }
            } else {
                // Admin: all announcements
                $stmt = $conn->prepare('SELECT * FROM announcements ORDER BY created_at DESC');
            }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['title'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            exit();
        }
        $refNo = 'ANN-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $items = isset($data['items']) ? json_encode($data['items']) : null;
        $highlights = isset($data['highlights']) ? json_encode($data['highlights']) : null;
        $schedule = isset($data['schedule']) ? json_encode($data['schedule']) : null;

        $stmt = $conn->prepare('INSERT INTO announcements 
            (reference_no, title, summary, body, items, info, note, link, highlights, schedule,
            priority, status, category, target_audience, posted_by, published_on, expires_on,
            created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->bind_param('sssssssssssssssss',
            $refNo, $data['title'], $data['summary'], $data['body'],
            $items, $data['info'], $data['note'], $data['link'],
            $highlights, $schedule,
            $data['priority'] ?? 'Normal', $data['status'] ?? 'Draft',
            $data['category'], $data['target_audience'], $data['posted_by'],
            $data['published_on'], $data['expires_on']
        );
        $stmt->execute();
        $newId = $conn->insert_id;
        echo json_encode(['success' => true, 'message' => 'Announcement created successfully',
            'id' => $newId, 'reference_no' => $refNo]);
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $items = isset($data['items']) ? json_encode($data['items']) : null;
        $highlights = isset($data['highlights']) ? json_encode($data['highlights']) : null;
        $schedule = isset($data['schedule']) ? json_encode($data['schedule']) : null;

        $stmt = $conn->prepare('UPDATE announcements SET 
            title=?, summary=?, body=?, items=?, info=?, note=?, link=?,
            highlights=?, schedule=?, priority=?, status=?, category=?,
            target_audience=?, posted_by=?, published_on=?, expires_on=?,
            updated_at=NOW() WHERE id=?');
        $stmt->bind_param('ssssssssssssssssi',
            $data['title'], $data['summary'], $data['body'],
            $items, $data['info'], $data['note'], $data['link'],
            $highlights, $schedule,
            $data['priority'], $data['status'], $data['category'],
            $data['target_audience'], $data['posted_by'],
            $data['published_on'], $data['expires_on'], $id
        );
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Announcement updated successfully']);
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $stmt = $conn->prepare('DELETE FROM announcements WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>