<?php
require_once '../includes/config.php';
requireRole('admin');
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $sort = (int)$_POST['sort_order'];
    $stmt = $db->prepare("INSERT INTO offices (name, description, sort_order) VALUES (?,?,?)");
    $stmt->bind_param('ssi', $name, $desc, $sort);
    $stmt->execute();
    $msg = 'success:Office added.';
}
if (isset($_GET['delete'])) {
    $db->query("DELETE FROM offices WHERE id=" . (int)$_GET['delete']);
    $msg = 'success:Office deleted.';
}
if (isset($_GET['toggle'])) {
    $db->query("UPDATE offices SET is_active=1-is_active WHERE id=" . (int)$_GET['toggle']);
    $msg = 'success:Office status updated.';
}

$offices = $db->query("SELECT * FROM offices ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);
$pageTitle = 'Offices';
$pageSubtitle = 'Manage clearance offices';
require_once '../includes/header.php';
if ($msg) { [$t, $tx] = explode(':', $msg, 2); echo "<div class='alert alert-$t'>$tx</div>"; }
?>

<div class="card mb-6">
  <div class="card-header"><h3>Add Office</h3></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="grid-3">
        <div class="form-group">
          <label class="form-label">Office Name</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= count($offices)+1 ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Add Office</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>All Offices</h3></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Office Name</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($offices as $i => $o): ?>
        <tr>
          <td class="text-muted"><?= $o['sort_order'] ?></td>
          <td style="font-weight:500"><?= htmlspecialchars($o['name']) ?></td>
          <td class="text-muted"><?= htmlspecialchars($o['description'] ?? '—') ?></td>
          <td><?= $o['is_active'] ? '<span class="badge approved">Active</span>' : '<span class="badge rejected">Inactive</span>' ?></td>
          <td>
            <a href="?toggle=<?= $o['id'] ?>" class="btn btn-outline btn-sm"><?= $o['is_active'] ? 'Disable' : 'Enable' ?></a>
            <a href="?delete=<?= $o['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete office?')">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
