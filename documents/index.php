<?php
/**
 * documents/index.php
 *
 * Converted & upgraded from:
 *   - documents-fixed.js  (Node.js/Express)
 *   - documentRequests-fixed.js (Node.js/Express)
 *
 * Key changes vs old PHP version:
 *   1. Secure reference number — uses random_bytes() instead of rand()
 *      (mirrors crypto.randomBytes() from documents-fixed.js)
 *   2. Collision check loop — same do-while logic as generateReferenceNumber()
 *   3. Email notifications — via PHPMailer (mirrors nodemailer transporter)
 *   4. Public document tracking by reference OR numeric ID
 *      (mirrors /public/track-document/:id from documentRequests-fixed.js)
 */

require_once '../config/database.php';
require_once '../utils/email.php';

$method     = $_SERVER['REQUEST_METHOD'];
$headers    = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$isPublic   = isset($_GET['public']);
$conn       = getConnection();

// ─── Token helpers ────────────────────────────────────────────────────────────

function resolveToken(string $authHeader): string {
    if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
        return substr($authHeader, 7);
    }
    return '';
}

function verifyToken($conn, string $token): ?array {
    if (empty($token)) return null;
    $stmt = $conn->prepare(
        'SELECT u.id, u.name, u.email FROM users u
         INNER JOIN personal_access_tokens t ON t.tokenable_id = u.id
         WHERE t.token = ?'
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

// ─── Secure Reference Number Generator ───────────────────────────────────────
// Converted from generateReferenceNumber() in documents-fixed.js
// Uses random_bytes() — PHP's cryptographically secure equivalent of
// Node's crypto.randomBytes(). Loops on the rare DB collision.

function generateReferenceNumber($conn): string {
    $year = date('Y');
    do {
        // 6 random bytes → 12 hex chars, take first 8 → DOC-YYYY-XXXXXXXX
        $token       = strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
        $referenceNo = "DOC-{$year}-{$token}";

        $stmt = $conn->prepare('SELECT id FROM document_requests WHERE reference_no = ?');
        $stmt->bind_param('s', $referenceNo);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
    } while ($exists); // Loop until unique — practically never loops twice

    return $referenceNo;
}

// ─── Activity Logger ──────────────────────────────────────────────────────────

function logActivity($conn, string $actorName, string $action, string $module, string $referenceId): void {
    $stmt = $conn->prepare(
        'INSERT INTO activity_logs
         (actor_name, action, module, reference_id, logged_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())'
    );
    $stmt->bind_param('ssss', $actorName, $action, $module, $referenceId);
    $stmt->execute();
}

// ─── Resolve Auth ─────────────────────────────────────────────────────────────

$token    = resolveToken($authHeader);
$authUser = verifyToken($conn, $token);

// Public GET (?public or ?track) and public POST do NOT require auth
$isPublicGet  = ($method === 'GET'  && ($isPublic || isset($_GET['track'])));
$isPublicPost = ($method === 'POST' && !isset($_GET['admin']));

if (!$isPublicGet && !$isPublicPost) {
    if (!$authUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
}

$actorName = $authUser['name'] ?? 'Public';
$id        = isset($_GET['id']) ? intval($_GET['id']) : null;


// ─── ROUTING ──────────────────────────────────────────────────────────────────

switch ($method) {

    // ── GET ───────────────────────────────────────────────────────────────────
    case 'GET':

        // ?public → list available document types (no auth)
        if ($isPublic) {
            $types = [
                'Barangay Clearance',
                'Certificate of Indigency',
                'Certificate of Residency',
                'Barangay ID',
                'Business Clearance',
                'Certificate of Good Moral Character',
                'First-Time Jobseeker Certificate',
            ];
            echo json_encode(['success' => true, 'data' => $types]);
            break;
        }

        // ?track=REF-NO or ?track=numeric-id → public document tracking
        // Converted from router.get('/public/track-document/:id') in documentRequests-fixed.js
        if (isset($_GET['track'])) {
            $trackingId = trim($_GET['track']);
            $doc = null;

            // Try reference_no first
            $stmt = $conn->prepare(
                'SELECT id, reference_no, requester_name, document_type,
                        purpose, status, date_filed, created_at, updated_at, remarks
                 FROM document_requests WHERE reference_no = ?'
            );
            $stmt->bind_param('s', $trackingId);
            $stmt->execute();
            $doc = $stmt->get_result()->fetch_assoc();

            // Fall back to numeric ID
            if (!$doc && ctype_digit($trackingId)) {
                $numId = intval($trackingId);
                $stmt2 = $conn->prepare(
                    'SELECT id, reference_no, requester_name, document_type,
                            purpose, status, date_filed, created_at, updated_at, remarks
                     FROM document_requests WHERE id = ?'
                );
                $stmt2->bind_param('i', $numId);
                $stmt2->execute();
                $doc = $stmt2->get_result()->fetch_assoc();
            }

            if (!$doc) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Request not found or invalid ID.']);
                break;
            }

            // Build status timeline
            $statusTimeline = [
                ['step' => 'Submitted',    'done' => true,
                 'date' => $doc['date_filed'] ?? $doc['created_at']],
                ['step' => 'Under Review', 'done' => in_array($doc['status'],
                    ['Review', 'Approved', 'Released', 'Rejected']), 'date' => null],
                ['step' => 'Approved',     'done' => in_array($doc['status'],
                    ['Approved', 'Released']), 'date' => null],
                ['step' => 'Released',     'done' => $doc['status'] === 'Released', 'date' => null],
            ];
            if ($doc['status'] === 'Rejected') {
                $statusTimeline = [
                    ['step' => 'Submitted',    'done' => true, 'date' => $doc['date_filed'] ?? $doc['created_at']],
                    ['step' => 'Under Review', 'done' => true, 'date' => null],
                    ['step' => 'Rejected',     'done' => true, 'date' => $doc['updated_at']],
                ];
            }

            // Map fields — mirrors mappedRequest in documentRequests-fixed.js
            echo json_encode([
                'success'  => true,
                'data'     => [
                    'id'            => $doc['reference_no'] ?: $doc['id'],
                    'full_name'     => $doc['requester_name'] ?? 'Resident',
                    'document_type' => $doc['document_type']  ?? 'Document Request',
                    'purpose'       => $doc['purpose']        ?? 'Not specified',
                    'status'        => $doc['status'],
                    'remarks'       => $doc['remarks']        ?? '',
                    'created_at'    => $doc['date_filed']     ?? $doc['created_at'],
                ],
                'timeline' => $statusTimeline,
            ]);
            break;
        }

        // ?my=1 → resident's own requests only
        if (isset($_GET['my'])) {
            $userEmail = $authUser['email'] ?? '';
            if (empty($userEmail)) {
                echo json_encode(['success' => true, 'data' => [], 'total' => 0]);
                break;
            }
            $stmt = $conn->prepare(
                'SELECT * FROM document_requests WHERE email = ? ORDER BY created_at DESC'
            );
            $stmt->bind_param('s', $userEmail);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
            break;
        }

        // ?id=X → single record
        if ($id) {
            $stmt = $conn->prepare(
                'SELECT dr.*,
                        GROUP_CONCAT(dra.label, ": ", dra.value SEPARATOR " | ") as attachments
                 FROM document_requests dr
                 LEFT JOIN document_request_attachments dra ON dra.document_request_id = dr.id
                 WHERE dr.id = ? GROUP BY dr.id'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $doc = $stmt->get_result()->fetch_assoc();
            if (!$doc) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Request not found']);
            } else {
                echo json_encode(['success' => true, 'data' => $doc]);
            }
            break;
        }

        // Default → all requests, optional ?status= filter
        $status = $_GET['status'] ?? null;
        if ($status) {
            $stmt = $conn->prepare('SELECT * FROM document_requests WHERE status = ? ORDER BY created_at DESC');
            $stmt->bind_param('s', $status);
        } else {
            $stmt = $conn->prepare('SELECT * FROM document_requests ORDER BY created_at DESC');
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
        break;


    // ── POST — Public document request submission ──────────────────────────────
    // Converted from router.post('/public/document-request') in documents-fixed.js
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        // Prefer email from verified token over form-supplied email
        $finalEmail = $authUser['email'] ?? ($data['email'] ?? null);

        // Validate required fields — mirrors documents-fixed.js validation
        $required = ['requester_name', 'document_type', 'purpose', 'contact_number', 'address'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
                exit();
            }
        }

        // Secure, collision-safe reference number (replaces old rand())
        $referenceNo = generateReferenceNumber($conn);

        $stmt = $conn->prepare(
            'INSERT INTO document_requests
             (reference_no, requester_name, document_type, purpose,
              contact_number, email, address, status, date_filed,
              additional_info, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, "Pending", CURDATE(), ?, NOW(), NOW())'
        );
        $additionalInfo = $data['additional_info'] ?? null;
        $stmt->bind_param('ssssssss',
            $referenceNo,
            $data['requester_name'],
            $data['document_type'],
            $data['purpose'],
            $data['contact_number'],
            $finalEmail,
            $data['address'],
            $additionalInfo
        );
        $stmt->execute();
        $newId = $conn->insert_id;

        // Confirmation email to resident
        if (!empty($finalEmail)) {
            sendDocumentStatusEmail(
                $finalEmail,
                $data['requester_name'],
                $data['document_type'],
                $referenceNo,
                'Pending'
            );
        }

        logActivity($conn, $actorName, 'Requested', 'Document',
            $referenceNo . ' — ' . $data['document_type']);

        echo json_encode([
            'success'      => true,
            'message'      => 'Document request submitted successfully',
            'reference_no' => $referenceNo,
            'data'         => [
                'id'             => $newId,
                'reference_no'   => $referenceNo,
                'requester_name' => $data['requester_name'],
                'document_type'  => $data['document_type'],
                'status'         => 'Pending',
            ],
        ]);
        break;


    // ── PUT — Admin updates status + sends notification email ──────────────────
    // Converted from router.put('/:id') in documentRequests-fixed.js
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Fetch current record for email data
        $fetchStmt = $conn->prepare(
            'SELECT requester_name, email, document_type, reference_no FROM document_requests WHERE id = ?'
        );
        $fetchStmt->bind_param('i', $id);
        $fetchStmt->execute();
        $existing = $fetchStmt->get_result()->fetch_assoc();

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Document request not found']);
            exit();
        }

        $remarks        = $data['remarks']         ?? null;
        $receivingStaff = $data['receiving_staff'] ?? null;

        $stmt = $conn->prepare(
            'UPDATE document_requests SET status=?, remarks=?, receiving_staff=?, updated_at=NOW() WHERE id=?'
        );
        $stmt->bind_param('sssi', $data['status'], $remarks, $receivingStaff, $id);
        $stmt->execute();

        // Send email on meaningful status changes — mirrors documentRequests-fixed.js
        $notifyStatuses = ['Approved', 'Released', 'Rejected', 'Review'];
        if (!empty($existing['email']) && in_array($data['status'], $notifyStatuses)) {
            sendDocumentStatusEmail(
                $existing['email'],
                $existing['requester_name'],
                $existing['document_type'],
                $existing['reference_no'],
                $data['status']
            );
        }

        logActivity($conn, $actorName, $data['status'], 'Document',
            ($existing['reference_no'] ?? 'DOC-' . $id) . ' — ' . ($existing['document_type'] ?? ''));

        echo json_encode(['success' => true, 'message' => 'Status updated and email sent if applicable']);
        break;


    // ── DELETE ────────────────────────────────────────────────────────────────
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        $stmt = $conn->prepare('DELETE FROM document_requests WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        logActivity($conn, $actorName, 'Deleted', 'Document', 'DOC-' . $id);
        echo json_encode(['success' => true, 'message' => 'Request deleted successfully']);
        break;


    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>