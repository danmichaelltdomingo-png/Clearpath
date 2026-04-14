<?php
require_once '../includes/config.php';
requireRole('admin');

$db = getDB();

// Stats
$stats = [];
$stats['total_students']   = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetch_row()[0];
$stats['total_clearances'] = $db->query("SELECT COUNT(*) FROM clearance_requests")->fetch_row()[0];
$stats['cleared']          = $db->query("SELECT COUNT(*) FROM clearance_requests WHERE status='cleared'")->fetch_row()[0];
$stats['pending']          = $db->query("SELECT COUNT(*) FROM clearance_requests WHERE status IN ('pending','in_progress')")->fetch_row()[0];
$stats['signatories']      = $db->query("SELECT COUNT(*) FROM users WHERE role='signatory'")->fetch_row()[0];

// Recent clearance requests
$recentQ = $db->query("
    SELECT cr.*, u.full_name, u.student_id AS sid
    FROM clearance_requests cr
    JOIN users u ON u.id = cr.student_id
    ORDER BY cr.submitted_at DESC LIMIT 10
");
$recentRequests = $recentQ->fetch_all(MYSQLI_ASSOC);

// Recent activity logs
$logsQ = $db->query("
    SELECT al.*, u.full_name FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC LIMIT 8
");
$logs = $logsQ->fetch_all(MYSQLI_ASSOC);

$db->close();

$pageTitle = 'Admin Dashboard';
$pageSubtitle = 'Overview of the clearance system';
require_once '../includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue">
      <svg viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
    </div>
    <div class="stat-value"><?= $stats['total_students'] ?></div>
    <div class="stat-label">Total Students</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber">
      <svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
    </div>
    <div class="stat-value"><?= $stats['pending'] ?></div>
    <div class="stat-label">Pending Clearances</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">
      <svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M9 15l2 2 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <div class="stat-value"><?= $stats['cleared'] ?></div>
    <div class="stat-label">Cleared Students</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
    </div>
    <div class="stat-value"><?= $stats['signatories'] ?></div>
    <div class="stat-label">Signatories</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">
      <svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
    </div>
    <div class="stat-value"><?= $stats['total_clearances'] ?></div>
    <div class="stat-label">Total Requests</div>
  </div>
</div>

<div class="grid-2 mb-6">
  <!-- Recent Clearance Requests -->
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Recent Clearance Requests</h3>
        <p>Latest student submissions</p>
      </div>
      <a href="clearances.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>School Year</th>
            <th>Semester</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentRequests)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--gray-400);padding:28px">No clearance requests yet.</td></tr>
          <?php else: ?>
          <?php foreach ($recentRequests as $r): ?>
          <tr>
            <td>
              <div style="font-weight:500"><?= htmlspecialchars($r['full_name']) ?></div>
              <div class="text-sm text-muted"><?= htmlspecialchars($r['sid']) ?></div>
            </td>
            <td><?= htmlspecialchars($r['school_year']) ?></td>
            <td><?= htmlspecialchars($r['semester']) ?></td>
            <td><span class="badge <?= $r['status'] ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Activity Log -->
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Recent Activity</h3>
        <p>System event log</p>
      </div>
      <a href="logs.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($logs)): ?>
      <div style="text-align:center;color:var(--gray-400);padding:28px">No activity recorded.</div>
      <?php else: ?>
      <?php foreach ($logs as $log): ?>
      <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 20px;border-bottom:1px solid var(--gray-100)">
        <div style="width:32px;height:32px;background:var(--blue-50);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--blue-600);font-size:13px;font-weight:700;">
          <?= strtoupper(substr($log['full_name'] ?? 'S', 0, 1)) ?>
        </div>
        <div>
          <div style="font-size:13px;font-weight:500;color:var(--gray-700)"><?= htmlspecialchars($log['action']) ?></div>
          <div style="font-size:12px;color:var(--gray-400)"><?= htmlspecialchars($log['full_name'] ?? 'System') ?> &bull; <?= date('M j, g:i a', strtotime($log['created_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="card">
  <div class="card-header"><h3>Quick Actions</h3></div>
  <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap">
    <a href="students.php?action=add" class="btn btn-primary">+ Add Student</a>
    <a href="signatories.php?action=add" class="btn btn-outline">+ Add Signatory</a>
    <a href="offices.php" class="btn btn-outline">Manage Offices</a>
    <a href="clearances.php" class="btn btn-outline">View All Clearances</a>
    <a href="logs.php" class="btn btn-outline">System Logs</a>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
