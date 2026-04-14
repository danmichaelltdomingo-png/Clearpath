<?php
require_once '../includes/config.php';
requireRole('admin');
$db = getDB();
$msg = '';

// Handle verify/mark cleared
if (isset($_GET['verify'])) {
    $rid = (int)$_GET['verify'];
    // Check if all items approved
    $total    = $db->query("SELECT COUNT(*) FROM clearance_items WHERE clearance_request_id=$rid")->fetch_row()[0];
    $approved = $db->query("SELECT COUNT(*) FROM clearance_items WHERE clearance_request_id=$rid AND status='approved'")->fetch_row()[0];
    if ($total > 0 && $total == $approved) {
        $db->query("UPDATE clearance_requests SET status='cleared' WHERE id=$rid");
        logActivity('VERIFY_CLEARANCE', "Marked clearance ID $rid as CLEARED");
        $msg = 'success:Clearance verified and marked as CLEARED.';
    } else {
        $msg = 'error:Cannot clear — not all office items are approved yet. (' . $approved . '/' . $total . ' approved)';
    }
}

// Handle reject all
if (isset($_GET['reject'])) {
    $rid = (int)$_GET['reject'];
    $db->query("UPDATE clearance_requests SET status='rejected' WHERE id=$rid");
    logActivity('REJECT_CLEARANCE', "Rejected clearance ID $rid");
    $msg = 'success:Clearance rejected.';
}

$filter = $_GET['filter'] ?? 'all';
$where  = '';
if ($filter !== 'all') $where = "WHERE cr.status='$filter'";

$data = $db->query("
    SELECT cr.*, u.full_name, u.student_id AS sid,
        (SELECT COUNT(*) FROM clearance_items ci WHERE ci.clearance_request_id=cr.id) AS total_items,
        (SELECT COUNT(*) FROM clearance_items ci WHERE ci.clearance_request_id=cr.id AND ci.status='approved') AS approved_items
    FROM clearance_requests cr
    JOIN users u ON u.id=cr.student_id
    $where
    ORDER BY cr.submitted_at DESC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Clearances';
$pageSubtitle = 'Manage and verify student clearance requests';
require_once '../includes/header.php';

if ($msg) { [$t, $tx] = explode(':', $msg, 2); echo "<div class='alert alert-$t'>$tx</div>"; }
?>

<div class="card mb-6" style="padding:16px 20px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach (['all','pending','in_progress','cleared','rejected'] as $f): ?>
    <a href="?filter=<?= $f ?>" class="btn <?= $filter==$f ? 'btn-primary' : 'btn-outline' ?> btn-sm">
      <?= ucfirst(str_replace('_',' ',$f)) ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div><h3>Clearance Requests</h3><p><?= count($data) ?> records</p></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>School Year</th>
          <th>Sem</th>
          <th>Progress</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($data)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--gray-400);padding:32px">No requests found.</td></tr>
        <?php else: ?>
        <?php foreach ($data as $r):
          $pct = $r['total_items'] > 0 ? round(($r['approved_items'] / $r['total_items']) * 100) : 0;
        ?>
        <tr>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($r['full_name']) ?></div>
            <div class="text-sm text-muted"><?= htmlspecialchars($r['sid']) ?></div>
          </td>
          <td><?= $r['school_year'] ?></td>
          <td><?= $r['semester'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="flex:1;height:6px;background:var(--gray-100);border-radius:10px;min-width:80px">
                <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct==100 ? 'var(--success)' : 'var(--blue-500)' ?>;border-radius:10px"></div>
              </div>
              <span class="text-sm text-muted"><?= $r['approved_items'] ?>/<?= $r['total_items'] ?></span>
            </div>
          </td>
          <td><span class="badge <?= $r['status'] ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
          <td class="text-sm text-muted"><?= date('M j, Y', strtotime($r['submitted_at'])) ?></td>
          <td>
            <a href="clearance_view.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">View</a>
            <?php if ($r['status'] !== 'cleared'): ?>
            <a href="?verify=<?= $r['id'] ?>&filter=<?= $filter ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark as cleared?')">Clear</a>
            <?php endif; ?>
            <?php if ($r['status'] !== 'rejected'): ?>
            <a href="?reject=<?= $r['id'] ?>&filter=<?= $filter ?>" class="btn btn-danger btn-sm" onclick="return confirm('Reject this clearance?')">Reject</a>
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
