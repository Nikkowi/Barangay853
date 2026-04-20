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
    $stmt = $conn->prepare('SELECT u.id, u.name FROM users u 
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
    $conn = getConnection();
    $actorName = 'Public';
}

// Helper: write to activity_logs
function logActivity($conn, $actorName, $action, $module, $referenceId) {
    $stmt = $conn->prepare('INSERT INTO activity_logs 
        (actor_name, action, module, reference_id, logged_at, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())');
    $stmt->bind_param('ssss', $actorName, $action, $module, $referenceId);
    $stmt->execute();
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
            
            $title          = $data['title'] ?? '';
            $summary        = $data['summary'] ?? null;
            $body           = $data['body'] ?? null;
            $items          = isset($data['items']) ? json_encode($data['items']) : null;
            $info           = $data['info'] ?? null;
            $note           = $data['note'] ?? null;
            $link           = $data['link'] ?? null;
            $highlights     = isset($data['highlights']) ? json_encode($data['highlights']) : null;
            $schedule       = isset($data['schedule']) ? json_encode($data['schedule']) : null;
            $priority       = $data['priority'] ?? 'Normal';
            $status         = $data['status'] ?? 'Draft';
            $category       = $data['category'] ?? null;
            $targetAudience = $data['target_audience'] ?? null;
            $postedBy       = $data['posted_by'] ?? 'Admin';
            $expiresOn      = $data['expires_on'] ?? null;
            
            // FIX: Auto-fill published_on if status is Published and no date is provided
            $publishedOn    = $data['published_on'] ?? null;
            if ($status === 'Published' && empty($publishedOn)) {
                $publishedOn = date('Y-m-d H:i:s');
            }
    
            $stmt = $conn->prepare('INSERT INTO announcements 
                (reference_no, title, summary, body, items, info, note, link, highlights, schedule,
                priority, status, category, target_audience, posted_by, published_on, expires_on,
                created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
                
            $stmt->bind_param('sssssssssssssssss',
                $refNo, $title, $summary, $body, $items, $info, $note, $link,
                $highlights, $schedule, $priority, $status, $category,
                $targetAudience, $postedBy, $publishedOn, $expiresOn
            );
            $stmt->execute();
            $newId = $conn->insert_id;
            logActivity($conn, $actorName, 'Created', 'Announcement', $refNo . ' — ' . $title);
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
            
            // FIX: Fetch the existing record first so we don't overwrite untouched fields with NULL
            $fetchStmt = $conn->prepare('SELECT * FROM announcements WHERE id = ?');
            $fetchStmt->bind_param('i', $id);
            $fetchStmt->execute();
            $current = $fetchStmt->get_result()->fetch_assoc();
            
            if (!$current) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Announcement not found']);
                exit();
            }
            
            // Use incoming data if it exists, otherwise keep existing data
            $title          = isset($data['title']) ? $data['title'] : $current['title'];
            $summary        = isset($data['summary']) ? $data['summary'] : $current['summary'];
            $body           = isset($data['body']) ? $data['body'] : $current['body'];
            $items          = isset($data['items']) ? json_encode($data['items']) : $current['items'];
            $info           = isset($data['info']) ? $data['info'] : $current['info'];
            $note           = isset($data['note']) ? $data['note'] : $current['note'];
            $link           = isset($data['link']) ? $data['link'] : $current['link'];
            $highlights     = isset($data['highlights']) ? json_encode($data['highlights']) : $current['highlights'];
            $schedule       = isset($data['schedule']) ? json_encode($data['schedule']) : $current['schedule'];
            $priority       = isset($data['priority']) ? $data['priority'] : $current['priority'];
            $status         = isset($data['status']) ? $data['status'] : $current['status'];
            $category       = isset($data['category']) ? $data['category'] : $current['category'];
            $targetAudience = isset($data['target_audience']) ? $data['target_audience'] : $current['target_audience'];
            $postedBy       = isset($data['posted_by']) ? $data['posted_by'] : $current['posted_by'];
            $expiresOn      = isset($data['expires_on']) ? $data['expires_on'] : $current['expires_on'];
            
            // FIX: Auto-fill published_on if changed to Published and no existing date is set
            $publishedOn    = isset($data['published_on']) ? $data['published_on'] : $current['published_on'];
            if ($status === 'Published' && empty($publishedOn)) {
                $publishedOn = date('Y-m-d H:i:s');
            }
    
            $stmt = $conn->prepare('UPDATE announcements SET 
                title=?, summary=?, body=?, items=?, info=?, note=?, link=?,
                highlights=?, schedule=?, priority=?, status=?, category=?,
                target_audience=?, posted_by=?, published_on=?, expires_on=?,
                updated_at=NOW() WHERE id=?');
                
            $stmt->bind_param('ssssssssssssssssi',
                $title, $summary, $body, $items, $info, $note, $link,
                $highlights, $schedule, $priority, $status, $category,
                $targetAudience, $postedBy, $publishedOn, $expiresOn, $id
            );
            $stmt->execute();
            logActivity($conn, $actorName, 'Updated', 'Announcement', 'ANN-' . $id . ' — ' . $title);
            echo json_encode(['success' => true, 'message' => 'Announcement updated successfully']);
            break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        // Grab title before deleting for the log
        $titleStmt = $conn->prepare('SELECT title FROM announcements WHERE id = ?');
        $titleStmt->bind_param('i', $id);
        $titleStmt->execute();
        $annTitle = $titleStmt->get_result()->fetch_assoc()['title'] ?? 'Unknown';

        $stmt = $conn->prepare('DELETE FROM announcements WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        logActivity($conn, $actorName, 'Deleted', 'Announcement', 'ANN-' . $id . ' — ' . $annTitle);
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>