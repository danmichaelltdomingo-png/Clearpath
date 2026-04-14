<?php
require_once '../includes/config.php';
requireRole('student');
$db   = getDB();
$user = currentUser();
$uid  = (int)$user['id'];
$msg  = '';

// Submit new clearance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sy  = trim($_POST['school_year']);
    $sem = $_POST['semester'];
    // Check existing
    $existing = $db->query("SELECT id FROM clearance_requests WHERE student_id=$uid AND school_year='$sy' AND semester='$sem' LIMIT 1")->fetch_row();
    if ($existing) {
        $msg = 'error:You already have a clearance request for ' . $sy . ' — ' . $sem . ' semester.';
    } else {
        $stmt = $db->prepare("INSERT INTO clearance_requests (student_id, school_year, semester) VALUES (?,?,?)");
        $stmt->bind_param('iss', $uid, $sy, $sem);
        $stmt->execute();
        $rid = $db->insert_id;

        // Auto-create clearance items for all active offices
        $offices = $db->query("SELECT id FROM offices WHERE is_active=1")->fetch_all(MYSQLI_ASSOC);
        foreach ($offices as $o) {
            $oid = $o['id'];
            $db->query("INSERT INTO clearance_items (clearance_request_id, office_id) VALUES ($rid, $oid)");
        }
        logActivity('SUBMIT_CLEARANCE', "Submitted clearance for $sy $sem");
        $msg = 'success:Clearance request submitted successfully! All offices have been notified.';
    }
}

// Latest request + items
$req = $db->query("SELECT * FROM clearance_requests WHERE student_id=$uid ORDER BY submitted_at DESC LIMIT 1")->fetch_assoc();
$items = [];
if ($req) {
    $rid   = $req['id'];
    $items = $db->query("
        SELECT ci.*, o.name AS office_name, o.description AS office_desc
        FROM clearance_items ci
        JOIN offices o ON o.id=ci.office_id
        WHERE ci.clearance_request_id=$rid
        ORDER BY o.sort_order
    ")->fetch_all(MYSQLI_ASSOC);
}

$pageTitle    = 'My Clearance';
$pageSubtitle = 'View and manage your clearance status';
require_once '../includes/header.php';
if ($msg) { [$t, $tx] = explode(':', $msg, 2); echo "<div class='alert alert-$t'>$tx</div>"; }
?>

<?php if (!$req): ?>
<div class="card mb-6">
  <div class="card-header"><h3>Submit Clearance Request</h3></div>
  <div class="card-body">
    <form method="POST">
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">School Year</label>
          <input type="text" name="school_year" class="form-control" placeholder="e.g. 2025-2026" value="<?= date('Y') . '-' . (date('Y')+1) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Semester</label>
          <select name="semester" class="form-control" required>
            <option value="1st">1st Semester</option>
            <option value="2nd">2nd Semester</option>
            <option value="Summer">Summer</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Submit Clearance Request</button>
    </form>
  </div>
</div>
<?php else: ?>

<div class="card mb-6">
  <div class="card-header">
    <div>
      <h3>Clearance Request — <?= $req['school_year'] ?> (<?= $req['semester'] ?> Sem)</h3>
      <p>Submitted: <?= date('F j, Y g:i a', strtotime($req['submitted_at'])) ?></p>
    </div>
    <span class="badge <?= $req['status'] ?>" style="font-size:13px;padding:6px 14px"><?= ucfirst(str_replace('_',' ',$req['status'])) ?></span>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Office Clearance Status</h3></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Office</th><th>Requirements</th><th>Status</th><th>Remarks</th><th>Reviewed</th></tr></thead>
      <tbody>
        <?php foreach ($items as $i => $item): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($item['office_name']) ?></div>
            <?php if ($item['office_desc']): ?><div class="text-sm text-muted"><?= htmlspecialchars($item['office_desc']) ?></div><?php endif; ?>
          </td>
          <td>
            <?php if ($item['requirements_submitted']): ?>
            <span class="badge approved">✓ Submitted</span>
            <?php else: ?>
            <a href="submit.php?item=<?= $item['id'] ?>" class="btn btn-outline btn-sm">Submit Docs</a>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span></td>
          <td class="text-sm text-muted"><?= $item['remarks'] ? htmlspecialchars($item['remarks']) : '—' ?></td>
          <td class="text-sm text-muted"><?= $item['reviewed_at'] ? date('M j, g:i a', strtotime($item['reviewed_at'])) : 'Not yet' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($req['status'] === 'cleared'): ?>
<div class="card" style="margin-top:20px;background:var(--success-bg);border-color:var(--success-border)">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
    <div>
      <h3 style="color:var(--success);font-family:'Sora',sans-serif">🎉 You are fully cleared!</h3>
      <p style="color:var(--success);opacity:0.8;margin-top:4px">Your clearance has been verified by the Registrar.</p>
    </div>
    <a href="download.php?id=<?= $req['id'] ?>" class="btn btn-success">Download Clearance PDF</a>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top:20px">
  <div class="card-header"><h3>Submit Another Clearance?</h3></div>
  <div class="card-body">
    <form method="POST">
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">School Year</label>
          <input type="text" name="school_year" class="form-control" value="<?= date('Y') . '-' . (date('Y')+1) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Semester</label>
          <select name="semester" class="form-control" required>
            <option value="1st">1st Semester</option>
            <option value="2nd">2nd Semester</option>
            <option value="Summer">Summer</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Submit New Request</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
