<?php
/**
 * Admin — Bulk Action AJAX Handler
 * ─────────────────────────────────────────────────────────
 * Handles bulk approve / reject / revert actions for admin.
 * Called via fetch() from admin/students.php
 *
 * POST JSON body:
 *   action       : 'approve' | 'reject' | 'revert'
 *   request_ids  : [1, 2, 3, ...]   (clearance_requests.id)
 *   user_ids     : [5, 6, 7, ...]   (users.id)
 * ─────────────────────────────────────────────────────────
 */
require_once '../includes/config.php';
header('Content-Type: application/json');

// Only admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Parse JSON body
$body       = json_decode(file_get_contents('php://input'), true);
$action     = $body['action']      ?? '';
$requestIds = $body['request_ids'] ?? [];
$userIds    = $body['user_ids']    ?? [];

// Validate input
if (!in_array($action, ['approve', 'reject', 'revert']) || empty($requestIds)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Sanitize IDs to integers
$requestIds = array_map('intval', $requestIds);
$userIds    = array_map('intval', $userIds);
$reqList    = implode(',', $requestIds);
$userList   = implode(',', $userIds);

$db = getDB();
$adminId = (int)$_SESSION['user_id'];
$now     = date('Y-m-d H:i:s');

switch ($action) {

    // ── APPROVE ALL: mark all pending items as approved, update request status ──
    case 'approve':
        $db->query("
            UPDATE clearance_items
            SET status = 'approved', reviewed_at = '$now', signatory_id = $adminId
            WHERE clearance_request_id IN ($reqList)
              AND status != 'approved'
        ");
        $db->query("
            UPDATE clearance_requests
            SET status = 'cleared', updated_at = '$now'
            WHERE id IN ($reqList)
        ");
        $count = count($requestIds);
        logActivity('BULK_APPROVE', "Admin bulk-approved $count clearance request(s): IDs [$reqList]");
        echo json_encode(['success' => true, 'message' => "✅ $count clearance(s) approved successfully."]);
        break;

    // ── REJECT ALL: mark all pending items as rejected ──
    case 'reject':
        $db->query("
            UPDATE clearance_items
            SET status = 'rejected', reviewed_at = '$now', signatory_id = $adminId
            WHERE clearance_request_id IN ($reqList)
              AND status = 'pending'
        ");
        $db->query("
            UPDATE clearance_requests
            SET status = 'rejected', updated_at = '$now'
            WHERE id IN ($reqList)
        ");
        $count = count($requestIds);
        logActivity('BULK_REJECT', "Admin bulk-rejected $count clearance request(s): IDs [$reqList]");
        echo json_encode(['success' => true, 'message' => "❌ $count clearance(s) rejected."]);
        break;

    // ── REVERT: reset approved/rejected items back to pending, update request status ──
    case 'revert':
        $db->query("
            UPDATE clearance_items
            SET status = 'pending', reviewed_at = NULL, signatory_id = NULL
            WHERE clearance_request_id IN ($reqList)
        ");
        $db->query("
            UPDATE clearance_requests
            SET status = 'in_progress', updated_at = '$now'
            WHERE id IN ($reqList)
        ");
        $count = count($requestIds);
        logActivity('BULK_REVERT', "Admin reverted $count clearance request(s) to processing: IDs [$reqList]");
        echo json_encode(['success' => true, 'message' => "↩ $count clearance(s) reverted to processing."]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$db->close();
