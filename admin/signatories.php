<?php
require_once '../includes/config.php';
requireRole('admin');
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $email  = trim($_POST['email']);
    $name   = trim($_POST['full_name']);
    $office = trim($_POST['office']);
    $pass   = sha1('@bpc123');
    $sid    = 'SIG-' . strtoupper(substr(md5($email), 0, 6));
    $stmt = $db->prepare("INSERT INTO users (student_id, email, password, full_name, role, office) VALUES (?,?,?,?,'signatory',?)");
    $stmt->bind_param('sssss', $sid, $email, $pass, $name, $office);
    if ($stmt->execute()) {
        logActivity('ADD_SIGNATORY', "Added signatory: $name");
        $msg = 'success:Signatory added. Default password: @bpc123';
    } else {
        $msg = 'error:' . $db->error;
    }
}

if (isset($_GET['delete'])) {
    $db->query("DELETE FROM users WHERE id=" . (int)$_GET['delete'] . " AND role='signatory'");
    $msg = 'success:Signatory deleted.';
}

$offices    = $db->query("SELECT * FROM offices WHERE is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$signatories= $db->query("SELECT * FROM users WHERE role='signatory' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

$pageTitle    = 'Signatories';
$pageSubtitle = 'Manage office signatories';
require_once '../includes/header.php';

if ($msg) { [$t, $tx] = explode(':', $msg, 2); echo "<div class='alert alert-$t'>$tx</div>"; }
?>

<div class="card mb-6">
  <div class="card-header"><h3>Add Signatory</h3></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="grid-3">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" placeholder="Office In-charge Name" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Office / Department</label>
          <select name="office" class="form-control" required>
            <option value="">— Select Office —</option>
            <?php foreach ($offices as $o): ?>
            <option value="<?= htmlspecialchars($o['name']) ?>"><?= htmlspecialchars($o['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Add Signatory</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>All Signatories</h3><p><?= count($signatories) ?> total</p></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Office</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($signatories)): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--gray-400);padding:32px">No signatories found.</td></tr>
        <?php else: ?>
        <?php foreach ($signatories as $s): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($s['full_name']) ?></td>
          <td class="text-muted"><?= htmlspecialchars($s['email']) ?></td>
          <td><span class="badge in_progress"><?= htmlspecialchars($s['office'] ?? '—') ?></span></td>
          <td><?= $s['is_active'] ? '<span class="badge approved">Active</span>' : '<span class="badge rejected">Inactive</span>' ?></td>
          <td>
            <a href="?delete=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete signatory?')">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
