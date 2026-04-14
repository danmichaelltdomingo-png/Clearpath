<?php
require_once '../includes/config.php';
requireRoleIn(['signatory', 'admin']);
$db   = getDB();
$user = currentUser();

// Find this signatory's office
$officeName = $user['office'];
$officeRow  = $db->query("SELECT * FROM offices WHERE name='" . $db->real_escape_string($officeName) . "' LIMIT 1")->fetch_assoc();
$officeId   = $officeRow['id'] ?? 0;

// Stats
$total    = $db->query("SELECT COUNT(*) FROM clearance_items WHERE office_id=$officeId")->fetch_row()[0] ?? 0;
$pending  = $db->query("SELECT COUNT(*) FROM clearance_items WHERE office_id=$officeId AND status='pending'")->fetch_row()[0] ?? 0;
$approved = $db->query("SELECT COUNT(*) FROM clearance_items WHERE office_id=$officeId AND status='approved'")->fetch_row()[0] ?? 0;
$rejected = $db->query("SELECT COUNT(*) FROM clearance_items WHERE office_id=$officeId AND status='rejected'")->fetch_row()[0] ?? 0;

// Recent requests for this office
$requests = $db->query("
    SELECT ci.*, cr.school_year, cr.semester, u.full_name, u.student_id AS sid
    FROM clearance_items ci
    JOIN clearance_requests cr ON cr.id=ci.clearance_request_id
    JOIN users u ON u.id=cr.student_id
    WHERE ci.office_id=$officeId
    ORDER BY ci.created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$pageTitle    = 'Signatory Dashboard';
$pageSubtitle = htmlspecialchars($officeName) . ' Office';
require_once '../includes/header.php';
?>

<div class="stats-grid mb-6">
  <div class="stat-card">
    <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="1.5" fill="none"/></svg></div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-label">Total Requests</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M12 8v4l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></div>
    <div class="stat-value"><?= $pending ?></div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/></svg></div>
    <div class="stat-value"><?= $approved ?></div>
    <div class="stat-label">Approved</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M15 9l-6 6M9 9l6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></div>
    <div class="stat-value"><?= $rejected ?></div>
    <div class="stat-label">Rejected</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div><h3>Recent Clearance Requests</h3><p>Requests for your office</p></div>
    <a href="requests.php" class="btn btn-primary btn-sm">View All Requests</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Student</th><th>School Year</th><th>Sem</th><th>Requirements</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($requests)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--gray-400);padding:32px">No requests yet for your office.</td></tr>
        <?php else: ?>
        <?php foreach ($requests as $r): ?>
        <tr>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($r['full_name']) ?></div>
            <div class="text-sm text-muted"><?= htmlspecialchars($r['sid']) ?></div>
          </td>
          <td><?= $r['school_year'] ?></td>
          <td><?= $r['semester'] ?></td>
          <td><?= $r['requirements_submitted'] ? '<span class="badge approved">Submitted</span>' : '<span class="badge pending">Not Yet</span>' ?></td>
          <td><span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
          <td>
            <?php if ($r['status'] === 'pending'): ?>
            <a href="requests.php?approve=<?= $r['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this clearance?')">Approve</a>
            <a href="requests.php?reject=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Reject this clearance?')">Reject</a>
            <?php else: ?>
            <span class="text-muted text-sm">Reviewed</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
