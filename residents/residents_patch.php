<?php
/**
 * PATCH for residents/index.php — POST case only
 *
 * In your existing residents/index.php, find the POST case.
 * After the line:  $newId = $conn->insert_id;
 * Add the block below BEFORE the logActivity() call.
 *
 * This assigns the new resident to a household if the caller
 * sent household_id and household_role in the request body.
 */

// ── PASTE THIS BLOCK after "$newId = $conn->insert_id;" ──────────────────────

// Assign to household if provided
if (!empty($data['household_id']) && !empty($data['household_role'])) {
    $hhId   = intval($data['household_id']);
    $hhRole = $data['household_role'];

    // Validate role value
    $allowedRoles = ['head','spouse','child','sibling','extended','boarder','other'];
    if (!in_array($hhRole, $allowedRoles)) {
        $hhRole = 'other';
    }

    // If this person is being set as head, demote any existing head to 'other'
    if ($hhRole === 'head') {
        $demote = $conn->prepare(
            'UPDATE residents SET household_role = \'other\'
             WHERE household_id = ? AND household_role = \'head\''
        );
        $demote->bind_param('i', $hhId);
        $demote->execute();
    }

    $assignStmt = $conn->prepare(
        'UPDATE residents SET household_id = ?, household_role = ? WHERE id = ?'
    );
    $assignStmt->bind_param('isi', $hhId, $hhRole, $newId);
    $assignStmt->execute();
}

// ── END OF PATCH ─────────────────────────────────────────────────────────────
// Your existing logActivity() and echo json_encode() lines follow unchanged.


/*
 * For reference, here is what the full POST case should look like
 * after applying the patch (showing only the tail end from insert onwards):
 *
 *   $stmt->execute();
 *   $newId = $conn->insert_id;
 *
 *   // ── PATCH START ──
 *   if (!empty($data['household_id']) && !empty($data['household_role'])) {
 *       $hhId   = intval($data['household_id']);
 *       $hhRole = $data['household_role'];
 *       $allowedRoles = ['head','spouse','child','sibling','extended','boarder','other'];
 *       if (!in_array($hhRole, $allowedRoles)) $hhRole = 'other';
 *       if ($hhRole === 'head') {
 *           $demote = $conn->prepare(
 *               'UPDATE residents SET household_role = \'other\'
 *                WHERE household_id = ? AND household_role = \'head\''
 *           );
 *           $demote->bind_param('i', $hhId);
 *           $demote->execute();
 *       }
 *       $assignStmt = $conn->prepare(
 *           'UPDATE residents SET household_id = ?, household_role = ? WHERE id = ?'
 *       );
 *       $assignStmt->bind_param('isi', $hhId, $hhRole, $newId);
 *       $assignStmt->execute();
 *   }
 *   // ── PATCH END ──
 *
 *   logActivity($conn, $actorName, 'Created', 'Resident', 'RES-' . $newId);
 *   echo json_encode(['success' => true, 'message' => 'Resident added successfully', 'id' => $newId]);
 *   break;
 */
