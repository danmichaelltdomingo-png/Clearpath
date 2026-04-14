<?php
require_once '../includes/config.php';
requireRole('admin');
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: clearances.php'); exit; }

$req = $db->query("SELECT cr.*, u.full_name, u.student_id AS sid, u.email FROM clearance_requests cr JOIN users u ON u.id=cr.student_id WHERE cr.id=$id")->fetch_assoc();
if (!$req) { header('Location: clearances.php'); exit; }

$items = $db->query("
    SELECT ci.*, o.name AS office_name, u.full_name AS signatory_name
    FROM clearance_items ci
    JOIN offices o ON o.id=ci.office_id
    LEFT JOIN users u ON u.id=ci.signatory_id
    WHERE ci.clearance_request_id=$id
    ORDER BY o.sort_order
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Clearance Detail';
$pageSubtitle = 'Request #' . $id . ' — ' . $req['full_name'];
require_once '../includes/header.php';
?>

<div style="margin-bottom:20px">
  <a href="clearances.php" class="btn btn-outline btn-sm">← Back to Clearances</a>
</div>

<div class="grid-2 mb-6">
  <div class="card">
    <div class="card-header"><h3>Student Information</h3></div>
    <div class="card-body">
      <table style="width:100%">
        <tr><td class="text-muted text-sm" style="padding:6px 0;width:140px">Full Name</td><td style="font-weight:500"><?= htmlspecialchars($req['full_name']) ?></td></tr>
        <tr><td class="text-muted text-sm" style="padding:6px 0">Student ID</td><td><?= htmlspecialchars($req['sid']) ?></td></tr>
        <tr><td class="text-muted text-sm" style="padding:6px 0">Email</td><td><?= htmlspecialchars($req['email']) ?></td></tr>
        <tr><td class="text-muted text-sm" style="padding:6px 0">School Year</td><td><?= $req['school_year'] ?></td></tr>
        <tr><td class="text-muted text-sm" style="padding:6px 0">Semester</td><td><?= $req['semester'] ?></td></tr>
        <tr><td class="text-muted text-sm" style="padding:6px 0">Submitted</td><td><?= date('F j, Y g:i a', strtotime($req['submitted_at'])) ?></td></tr>
        <tr><td class="text-muted text-sm" style="padding:6px 0">Status</td><td><span class="badge <?= $req['status'] ?>"><?= ucfirst(str_replace('_',' ',$req['status'])) ?></span></td></tr>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Clearance Progress</h3></div>
    <div class="card-body">
      <?php
        $total    = count($items);
        $approved = count(array_filter($items, fn($i) => $i['status'] === 'approved'));
        $rejected = count(array_filter($items, fn($i) => $i['status'] === 'rejected'));
        $pending  = $total - $approved - $rejected;
        $pct = $total > 0 ? round($approved / $total * 100) : 0;
      ?>
      <div style="text-align:center;margin-bottom:20px">
        <div style="font-family:'Sora',sans-serif;font-size:40px;font-weight:800;color:var(--blue-600)"><?= $pct ?>%</div>
        <div class="text-muted text-sm">Overall Clearance Progress</div>
      </div>
      <div style="height:8px;background:var(--gray-100);border-radius:10px;margin-bottom:16px">
        <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct==100 ? 'var(--success)' : 'var(--blue-500)' ?>;border-radius:10px;transition:width 1s"></div>
      </div>
      <div style="display:flex;gap:16px;justify-content:center">
        <div style="text-align:center"><div style="font-size:20px;font-weight:700;color:var(--success)"><?= $approved ?></div><div class="text-sm text-muted">Approved</div></div>
        <div style="text-align:center"><div style="font-size:20px;font-weight:700;color:var(--warning)"><?= $pending ?></div><div class="text-sm text-muted">Pending</div></div>
        <div style="text-align:center"><div style="font-size:20px;font-weight:700;color:var(--error)"><?= $rejected ?></div><div class="text-sm text-muted">Rejected</div></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Office Clearance Items</h3></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Office</th>
          <th>Signatory</th>
          <th>Requirements</th>
          <th>Status</th>
          <th>Remarks</th>
          <th>Reviewed</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i => $item): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td style="font-weight:500"><?= htmlspecialchars($item['office_name']) ?></td>
          <td class="text-muted"><?= $item['signatory_name'] ? htmlspecialchars($item['signatory_name']) : '—' ?></td>
          <td><?= $item['requirements_submitted'] ? '<span class="badge approved">Submitted</span>' : '<span class="badge pending">Pending</span>' ?></td>
          <td><span class="badge <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span></td>
          <td class="text-muted text-sm"><?= $item['remarks'] ? htmlspecialchars($item['remarks']) : '—' ?></td>
          <td class="text-sm text-muted"><?= $item['reviewed_at'] ? date('M j, g:i a', strtotime($item['reviewed_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
