<?php
/**
 * ClearPath Notifications Engine
 * ─────────────────────────────────────────────────────────
 * Run this file on EVERY admin/signatory page load (include it in header.php)
 * OR set up a cron job to run it every hour:
 *   0 * * * * php /path/to/clearpath/includes/notifications.php
 *
 * What it does:
 *   1. Sends "clearance started" email if not yet sent
 *   2. Sends "deadline warning" email 3 days before due
 *   3. Sends "failed compliance" email when deadline passes without submission
 *   4. Sends "cleared" email with PDF when all offices approve
 * ─────────────────────────────────────────────────────────
 */

// Prevent direct browser access when run as cron
if (php_sapi_name() !== 'cli') {
    // Include config only if not already loaded (safe to call multiple times)
    if (!function_exists('getDB')) {
        require_once __DIR__ . '/config.php';
    }
}

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/pdf_clearance.php';

/**
 * Main notification dispatcher
 * Call this once per page load from header.php to keep it lightweight
 */
function runNotifications(): void {
    // Throttle: only run once every 15 minutes per session to avoid spam
    if (isset($_SESSION['notif_last_run'])) {
        if (time() - $_SESSION['notif_last_run'] < 900) return;
    }
    $_SESSION['notif_last_run'] = time();

    $db = getDB();

    _notifyNewClearances($db);
    _notifyDeadlineWarnings($db);
    _notifyFailedCompliance($db);
    _notifyCleared($db);

    $db->close();
}

// ──────────────────────────────────────────────────────
// 1. Clearance Started Notification
// ──────────────────────────────────────────────────────
function _notifyNewClearances($db): void {
    // Get clearance requests where start email not yet sent
    $result = $db->query("
        SELECT cr.id, cr.school_year, cr.semester, cr.clearance_deadline,
               u.email, u.full_name, u.student_id
        FROM clearance_requests cr
        JOIN users u ON u.id = cr.student_id
        WHERE cr.notified_start = 0
          AND u.is_active = 1
        LIMIT 20
    ");

    while ($row = $result->fetch_assoc()) {
        $sent = sendClearanceStartEmail(
            $row['email'],
            $row['full_name'],
            $row['student_id'],
            $row['school_year'],
            $row['semester'],
            $row['clearance_deadline']
        );

        if ($sent === true) {
            $id = (int)$row['id'];
            $db->query("UPDATE clearance_requests SET notified_start=1 WHERE id=$id");
            _logEmailSent($db, (int)$row['id'], 'clearance_start', $id);
        }
    }
}

// ──────────────────────────────────────────────────────
// 2. Deadline Warning (3 days or less before due)
// ──────────────────────────────────────────────────────
function _notifyDeadlineWarnings($db): void {
    $result = $db->query("
        SELECT ci.id, ci.deadline, o.name AS office_name,
               u.email, u.full_name, u.student_id,
               DATEDIFF(ci.deadline, CURDATE()) AS days_left
        FROM clearance_items ci
        JOIN clearance_requests cr ON cr.id = ci.clearance_request_id
        JOIN offices o             ON o.id  = ci.office_id
        JOIN users u               ON u.id  = cr.student_id
        WHERE ci.status = 'pending'
          AND ci.requirements_submitted = 0
          AND ci.deadline IS NOT NULL
          AND ci.notified_deadline = 0
          AND DATEDIFF(ci.deadline, CURDATE()) BETWEEN 0 AND 3
          AND u.is_active = 1
        LIMIT 50
    ");

    while ($row = $result->fetch_assoc()) {
        $daysLeft = (int)$row['days_left'];
        if ($daysLeft < 0) continue; // already past, handled by failed compliance

        $sent = sendDeadlineWarningEmail(
            $row['email'],
            $row['full_name'],
            $row['office_name'],
            $row['deadline'],
            $daysLeft
        );

        if ($sent === true) {
            $id = (int)$row['id'];
            $db->query("UPDATE clearance_items SET notified_deadline=1 WHERE id=$id");
            _logEmailSent($db, _getUserIdFromItem($db, $id), 'deadline_warning', $id);
        }
    }
}

// ──────────────────────────────────────────────────────
// 3. Failed Compliance (deadline passed, not submitted)
// ──────────────────────────────────────────────────────
function _notifyFailedCompliance($db): void {
    $result = $db->query("
        SELECT ci.id, ci.deadline, o.name AS office_name,
               u.email, u.full_name, u.student_id
        FROM clearance_items ci
        JOIN clearance_requests cr ON cr.id = ci.clearance_request_id
        JOIN offices o             ON o.id  = ci.office_id
        JOIN users u               ON u.id  = cr.student_id
        WHERE ci.status = 'pending'
          AND ci.requirements_submitted = 0
          AND ci.deadline IS NOT NULL
          AND ci.notified_failed = 0
          AND ci.deadline < CURDATE()
          AND u.is_active = 1
        LIMIT 50
    ");

    while ($row = $result->fetch_assoc()) {
        $sent = sendFailedComplianceEmail(
            $row['email'],
            $row['full_name'],
            $row['office_name'],
            $row['deadline']
        );

        if ($sent === true) {
            $id = (int)$row['id'];
            $db->query("UPDATE clearance_items SET notified_failed=1, status='rejected' WHERE id=$id");
            _logEmailSent($db, _getUserIdFromItem($db, $id), 'failed_compliance', $id);
        }
    }
}

// ──────────────────────────────────────────────────────
// 4. Clearance Complete (all offices approved)
// ──────────────────────────────────────────────────────
function _notifyCleared($db): void {
    // Find clearances where ALL items are approved but cleared email not yet sent
    $result = $db->query("
        SELECT cr.id, cr.school_year, cr.semester, cr.student_id,
               u.email, u.full_name, u.student_id AS sid, u.section, u.year_level, u.course
        FROM clearance_requests cr
        JOIN users u ON u.id = cr.student_id
        WHERE cr.status = 'cleared'
          AND u.is_active = 1
          AND NOT EXISTS (
            SELECT 1 FROM email_logs el
            WHERE el.user_id = cr.student_id
              AND el.type = 'cleared'
              AND el.reference_id = cr.id
          )
        LIMIT 20
    ");

    while ($row = $result->fetch_assoc()) {
        // Generate the PDF
        $pdfPath = generateClearancePDF($db, (int)$row['id']);

        $sent = sendClearanceCompleteEmail(
            $row['email'],
            $row['full_name'],
            $row['sid'],
            $row['school_year'],
            $row['semester'],
            $pdfPath ?: null
        );

        if ($sent === true) {
            _logEmailSent($db, (int)$row['student_id'], 'cleared', (int)$row['id']);

            // Clean up temp PDF after sending
            if ($pdfPath && file_exists($pdfPath)) {
                unlink($pdfPath);
            }
        }
    }
}

// ──────────────────────────────────────────────────────
// HELPER: Log a sent email to prevent duplicates
// ──────────────────────────────────────────────────────
function _logEmailSent($db, int $userId, string $type, int $referenceId): void {
    $type = $db->real_escape_string($type);
    $db->query("INSERT INTO email_logs (user_id, type, reference_id) VALUES ($userId, '$type', $referenceId)");
}

/** Get user_id from a clearance_item id */
function _getUserIdFromItem($db, int $itemId): int {
    $r = $db->query("
        SELECT cr.student_id FROM clearance_items ci
        JOIN clearance_requests cr ON cr.id = ci.clearance_request_id
        WHERE ci.id = $itemId LIMIT 1
    ")->fetch_assoc();
    return (int)($r['student_id'] ?? 0);
}
