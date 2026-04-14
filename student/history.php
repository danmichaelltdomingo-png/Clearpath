<?php
require_once '../includes/config.php';
requireRole('student');
$db   = getDB();
$user = currentUser();
$uid  = (int)$user['id'];

$history = $db->query("
    SELECT cr.*,
        (SELECT COUNT(*) FROM clearance_items ci WHERE ci.clearance_request_id = cr.id) AS total,
        (SELECT COUNT(*) FROM clearance_items ci WHERE ci.clearance_request_id = cr.id AND ci.status = 'approved') AS approved
    FROM clearance_requests cr
    WHERE cr.student_id = $uid
    ORDER BY cr.submitted_at DESC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle    = 'Clearance History';
$pageSubtitle = 'All your past clearance requests';
require_once '../includes/header.php';
?>

<div class="card">
  <div class="card-header"><h3>All Clearance Requests</h3><p><?= count($history) ?> total</p></div>
  <?php if (empty($history)): ?>
  <div style="text-align:center;padding:48px;color:var(--gray-400)">
    <div style="font-size:40px;margin-bottom:12px">📋</div>
    <h3 style="font-family:'Sora',sans-serif;color:var(--gray-600);margin-bottom:8px">No history yet</h3>
    <p>You haven't submitted any clearance requests.</p>
    <a href="clearance.php" class="btn btn-primary" style="margin-top:16px">Start a Clearance Request</a>
  </div>
  <?php else: ?>
  <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
    <?php foreach ($history as $h):
      $pct = $h['total'] > 0 ? round($h['approved'] / $h['total'] * 100) : 0;
    ?>
    <div style="border:1.5px solid var(--gray-100);border-radius:14px;padding:20px 24px;transition:box-shadow 0.2s" onmouseover="this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.boxShadow='none'">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:var(--gray-800)"><?= $h['school_year'] ?></div>
          <div style="font-size:13px;color:var(--gray-400);margin-top:2px"><?= $h['semester'] ?> Semester &bull; Submitted <?= date('F j, Y', strtotime($h['submitted_at'])) ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
          <span class="badge <?= $h['status'] ?>" style="font-size:12.5px;padding:5px 12px"><?= ucfirst(str_replace('_',' ',$h['status'])) ?></span>
          <a href="clearance.php" class="btn btn-outline btn-sm">View Details</a>
          <?php if ($h['status'] === 'cleared'): ?>
          <a href="download.php?id=<?= $h['id'] ?>" class="btn btn-success btn-sm">Download PDF</a>
          <?php endif; ?>
        </div>
      </div>
      <div style="margin-top:16px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <span style="font-size:12px;color:var(--gray-400)">Progress</span>
          <span style="font-size:12px;font-weight:600;color:<?= $pct == 100 ? 'var(--success)' : 'var(--blue-600)' ?>"><?= $pct ?>% — <?= $h['approved'] ?>/<?= $h['total'] ?> offices</span>
        </div>
        <div style="height:6px;background:var(--gray-100);border-radius:10px">
          <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct == 100 ? 'var(--success)' : 'var(--blue-500)' ?>;border-radius:10px"></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
