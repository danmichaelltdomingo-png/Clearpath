<?php
require_once '../includes/config.php';
requireRole('admin');
$db = getDB();
$logs = $db->query("
    SELECT al.*, u.full_name, u.role
    FROM activity_logs al
    LEFT JOIN users u ON u.id=al.user_id
    ORDER BY al.created_at DESC LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Activity Logs';
$pageSubtitle = 'System event history';
require_once '../includes/header.php';
?>
<div class="card">
  <div class="card-header"><h3>Activity Logs</h3><p><?= count($logs) ?> records (latest 200)</p></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP Address</th><th>Time</th></tr></thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--gray-400);padding:32px">No logs yet.</td></tr>
        <?php else: ?>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($l['full_name'] ?? 'System') ?></td>
          <td><span class="badge in_progress"><?= $l['role'] ?? '—' ?></span></td>
          <td style="font-family:'Sora',sans-serif;font-size:12px;font-weight:600;color:var(--blue-600)"><?= htmlspecialchars($l['action']) ?></td>
          <td class="text-muted text-sm"><?= htmlspecialchars($l['description'] ?? '—') ?></td>
          <td class="text-muted text-sm"><?= htmlspecialchars($l['ip_address'] ?? '—') ?></td>
          <td class="text-muted text-sm"><?= date('M j, Y g:i a', strtotime($l['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
