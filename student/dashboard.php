<?php
require_once '../includes/config.php';
requireRole('student');
$db   = getDB();
$user = currentUser();
$uid  = (int)$user['id'];

// Get latest clearance request
$req = $db->query("SELECT * FROM clearance_requests WHERE student_id=$uid ORDER BY submitted_at DESC LIMIT 1")->fetch_assoc();

$stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
$items = [];

if ($req) {
    $rid   = $req['id'];
    $items = $db->query("
        SELECT ci.*, o.name AS office_name
        FROM clearance_items ci
        JOIN offices o ON o.id=ci.office_id
        WHERE ci.clearance_request_id=$rid
        ORDER BY o.sort_order
    ")->fetch_all(MYSQLI_ASSOC);
    $stats['total']    = count($items);
    $stats['approved'] = count(array_filter($items, fn($i) => $i['status'] === 'approved'));
    $stats['pending']  = count(array_filter($items, fn($i) => $i['status'] === 'pending'));
    $stats['rejected'] = count(array_filter($items, fn($i) => $i['status'] === 'rejected'));
}

$pct = $stats['total'] > 0 ? round($stats['approved'] / $stats['total'] * 100) : 0;

$pageTitle    = 'My Dashboard';
$pageSubtitle = 'Welcome back, ' . $user['name'];
require_once '../includes/header.php';
?>

<?php if (!$req): ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:48px;margin-bottom:16px">📋</div>
  <h3 style="font-family:'Sora',sans-serif;font-size:20px;margin-bottom:8px;color:var(--gray-800)">No Active Clearance Request</h3>
  <p style="color:var(--gray-400);margin-bottom:24px">You haven't submitted a clearance request yet. Click below to get started.</p>
  <a href="clearance.php" class="btn btn-primary">Start Clearance Request</a>
</div>

<?php else: ?>

<!-- Progress Header -->
<div class="card mb-6" style="background:linear-gradient(135deg,var(--blue-900),var(--blue-700));color:#fff;border:none">
  <div class="card-body" style="padding:28px 32px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div>
        <div style="font-size:12px;color:rgba(255,255,255,0.6);margin-bottom:6px;text-transform:uppercase;letter-spacing:1px">Current Clearance</div>
        <div style="font-family:'Sora',sans-serif;font-size:22px;font-weight:700"><?= $req['school_year'] ?> — <?= $req['semester'] ?> Semester</div>
        <div style="margin-top:8px"><span style="display:inline-block;padding:4px 12px;border-radius:20px;background:rgba(255,255,255,0.15);font-size:12px"><?= ucfirst(str_replace('_',' ',$req['status'])) ?></span></div>
      </div>
      <div style="text-align:center">
        <div style="font-family:'Sora',sans-serif;font-size:52px;font-weight:800;line-height:1"><?= $pct ?>%</div>
        <div style="font-size:12px;color:rgba(255,255,255,0.6)">Completed</div>
      </div>
    </div>
    <div style="margin-top:20px">
      <div style="height:8px;background:rgba(255,255,255,0.15);border-radius:10px">
        <div style="width:<?= $pct ?>%;height:100%;background:#fff;border-radius:10px;transition:width 1.5s cubic-bezier(0.16,1,0.3,1)"></div>
      </div>
    </div>
  </div>
</div>

<div class="stats-grid mb-6">
  <div class="stat-card">
    <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none"><rect x="2" y="3" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></div>
    <div class="stat-value"><?= $stats['total'] ?></div>
    <div class="stat-label">Total Offices</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/></svg></div>
    <div class="stat-value"><?= $stats['approved'] ?></div>
    <div class="stat-label">Approved</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M12 8v4l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></div>
    <div class="stat-value"><?= $stats['pending'] ?></div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M15 9l-6 6M9 9l6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></div>
    <div class="stat-value"><?= $stats['rejected'] ?></div>
    <div class="stat-label">Rejected</div>
  </div>
</div>

<!-- Office Status Cards -->
<div class="card">
  <div class="card-header">
    <div><h3>Office Clearance Status</h3><p>Your clearance progress per office</p></div>
    <a href="clearance.php" class="btn btn-outline btn-sm">Full Details</a>
  </div>
  <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
    <?php foreach ($items as $item):
      $color = match($item['status']) {
        'approved' => 'var(--success)',
        'rejected' => 'var(--error)',
        default => 'var(--warning)',
      };
      $bg = match($item['status']) {
        'approved' => 'var(--success-bg)',
        'rejected' => 'var(--error-bg)',
        default => 'var(--warning-bg)',
      };
      $icon = match($item['status']) {
        'approved' => '✓',
        'rejected' => '✗',
        default => '◷',
      };
    ?>
    <div style="border:1.5px solid <?= $bg ?>;border-radius:12px;padding:16px 18px;background:<?= $bg ?>20">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <span style="font-size:13px;font-weight:600;color:var(--gray-700)"><?= htmlspecialchars($item['office_name']) ?></span>
        <span style="width:24px;height:24px;border-radius:50%;background:<?= $color ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700"><?= $icon ?></span>
      </div>
      <span class="badge <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
      <?php if ($item['remarks']): ?>
      <div style="margin-top:8px;font-size:11.5px;color:var(--gray-500)"><?= htmlspecialchars($item['remarks']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
