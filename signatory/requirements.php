<?php
require_once '../includes/config.php';
requireRoleIn(['signatory', 'admin']);
$db   = getDB();
$user = currentUser();

$officeName = $user['office'];
$officeRow  = $db->query("SELECT * FROM offices WHERE name='" . $db->real_escape_string($officeName) . "' LIMIT 1")->fetch_assoc();
$officeId   = $officeRow['id'] ?? 0;
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reqName = trim($_POST['requirement_name']);
    $desc    = trim($_POST['description']);
    $isReq   = isset($_POST['is_required']) ? 1 : 0;
    $stmt    = $db->prepare("INSERT INTO office_requirements (office_id, requirement_name, description, is_required) VALUES (?,?,?,?)");
    $stmt->bind_param('issi', $officeId, $reqName, $desc, $isReq);
    $stmt->execute();
    logActivity('ADD_REQUIREMENT', "Added requirement: $reqName to $officeName");
    $msg = 'success:Requirement added.';
}

if (isset($_GET['delete'])) {
    $db->query("DELETE FROM office_requirements WHERE id=" . (int)$_GET['delete'] . " AND office_id=$officeId");
    $msg = 'success:Requirement deleted.';
}

$requirements = $db->query("SELECT * FROM office_requirements WHERE office_id=$officeId ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$pageTitle    = 'Requirements';
$pageSubtitle = 'Manage clearance requirements for ' . htmlspecialchars($officeName);
require_once '../includes/header.php';
if ($msg) { [$t, $tx] = explode(':', $msg, 2); echo "<div class='alert alert-$t'>$tx</div>"; }
?>

<div class="card mb-6">
  <div class="card-header"><h3>Add Requirement</h3></div>
  <div class="card-body">
    <form method="POST">
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Requirement Name</label>
          <input type="text" name="requirement_name" class="form-control" placeholder="e.g., Clearance Form, Receipt, etc." required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-control" placeholder="Optional description">
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
        <input type="checkbox" name="is_required" id="is_required" checked>
        <label for="is_required" style="font-size:13px;color:var(--gray-600)">Mark as required</label>
      </div>
      <button type="submit" class="btn btn-primary">Add Requirement</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Office Requirements</h3><p><?= count($requirements) ?> items</p></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Requirement</th><th>Description</th><th>Required</th><th>Added</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($requirements)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--gray-400);padding:32px">No requirements added yet.</td></tr>
        <?php else: ?>
        <?php foreach ($requirements as $i => $req): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td style="font-weight:500"><?= htmlspecialchars($req['requirement_name']) ?></td>
          <td class="text-muted"><?= htmlspecialchars($req['description'] ?? '—') ?></td>
          <td><?= $req['is_required'] ? '<span class="badge rejected">Required</span>' : '<span class="badge pending">Optional</span>' ?></td>
          <td class="text-sm text-muted"><?= date('M j, Y', strtotime($req['created_at'])) ?></td>
          <td><a href="?delete=<?= $req['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete requirement?')">Delete</a></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
