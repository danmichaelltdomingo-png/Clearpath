<?php
/**
 * Admin — Students Management
 * ─────────────────────────────────────────────────────────
 * Features:
 *   - Filter/sort students by Course, Year Level, Section
 *   - View each student's current clearance status
 *   - Bulk Approve by Section or Year Level
 *   - Bulk Revert to Processing (undo approval)
 *   - Confirmation modal before bulk actions
 * ─────────────────────────────────────────────────────────
 */
require_once '../includes/config.php';

// Only admin can access
requireRole('admin');

$db = getDB();

// ── Read filter values from URL ──────────────────────────
$filterCourse = trim($_GET['course']    ?? '');
$filterYear   = trim($_GET['year']      ?? '');
$filterSection = trim($_GET['section']  ?? '');
$filterStatus  = trim($_GET['status']   ?? '');

// ── Build student query with filters ────────────────────
$where = ["u.role = 'student'", "u.is_active = 1"];
if ($filterCourse)  $where[] = "u.course = '"  . $db->real_escape_string($filterCourse)  . "'";
if ($filterYear)    $where[] = "u.year_level = '".$db->real_escape_string($filterYear)."'";
if ($filterSection) $where[] = "u.section = '"  . $db->real_escape_string($filterSection) . "'";
$whereSQL = implode(' AND ', $where);

$students = $db->query("
    SELECT u.id, u.full_name, u.student_id, u.email,
           u.year_level, u.section, u.course,
           cr.id AS req_id, cr.status AS clearance_status,
           cr.school_year, cr.semester,
           COUNT(ci.id) AS total_items,
           SUM(ci.status = 'approved') AS approved_count,
           SUM(ci.status = 'rejected') AS rejected_count
    FROM users u
    LEFT JOIN clearance_requests cr ON cr.student_id = u.id
    LEFT JOIN clearance_items ci    ON ci.clearance_request_id = cr.id
    WHERE $whereSQL
    GROUP BY u.id, cr.id
    ORDER BY u.course, u.year_level, u.section, u.full_name
")->fetch_all(MYSQLI_ASSOC);

// Apply status filter in PHP (simpler than SQL for computed status)
if ($filterStatus) {
    $students = array_filter($students, fn($s) => ($s['clearance_status'] ?? 'none') === $filterStatus);
}

// ── Dropdown options ─────────────────────────────────────
$courses   = $db->query("SELECT code, name FROM courses WHERE is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
$sections   = $db->query("SELECT DISTINCT section FROM users WHERE role='student' AND section IS NOT NULL ORDER BY section")->fetch_all(MYSQLI_ASSOC);
$sections   = array_column($sections, 'section');

$db->close();

// Group students by section for display
$grouped = [];
foreach ($students as $s) {
    $key = ($s['course'] ?? 'No Course') . ' — ' . ($s['year_level'] ?? 'Unknown Year') . ' — Sec. ' . ($s['section'] ?? 'N/A');
    $grouped[$key][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Students — Admin | ClearPath BPC</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --blue-900:#0f1f5c; --blue-700:#1e3a9e; --blue-600:#2046c4; --blue-500:#2d55d4;
  --blue-50:#eef2ff; --blue-100:#dce6ff;
  --white:#ffffff; --gray-50:#f8f9fc; --gray-100:#eef0f6; --gray-200:#d8dbe8;
  --gray-400:#8b91ae; --gray-600:#4a5070; --gray-800:#1e2240;
  --green:#059669; --green-bg:#d1fae5;
  --red:#dc2626; --red-bg:#fee2e2;
  --yellow:#d97706; --yellow-bg:#fef3c7;
  --shadow:0 2px 12px rgba(15,31,92,0.10);
}
body { font-family:'DM Sans',sans-serif; background:var(--gray-50); color:var(--gray-800); }

/* ── Top Bar ── */
.topbar { background:var(--blue-900); padding:14px 28px; display:flex; align-items:center; justify-content:space-between; }
.topbar .brand { font-family:'Sora',sans-serif; font-weight:800; font-size:16px; color:#fff; }
.topbar .nav-links a { color:rgba(255,255,255,0.7); text-decoration:none; margin-left:20px; font-size:13px; }
.topbar .nav-links a:hover { color:#fff; }

/* ── Page wrapper ── */
.page { max-width:1280px; margin:0 auto; padding:28px 24px; }
.page-title { font-family:'Sora',sans-serif; font-size:22px; font-weight:800; color:var(--blue-900); margin-bottom:6px; }
.page-sub   { font-size:13px; color:var(--gray-400); }

/* ── Filter Card ── */
.filter-card {
  background:var(--white); border-radius:14px; box-shadow:var(--shadow);
  padding:20px 24px; margin:20px 0; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;
}
.filter-group { display:flex; flex-direction:column; gap:5px; }
.filter-group label { font-size:12px; font-weight:600; color:var(--gray-600); text-transform:uppercase; letter-spacing:.4px; }
.filter-group select, .filter-group input {
  padding:9px 12px; border:1.5px solid var(--gray-200); border-radius:8px;
  font-size:13.5px; color:var(--gray-800); background:var(--gray-50);
  min-width:160px; outline:none; font-family:'DM Sans',sans-serif;
}
.filter-group select:focus { border-color:var(--blue-500); background:#fff; }
.btn-filter {
  padding:10px 20px; background:var(--blue-700); color:#fff; border:none;
  border-radius:8px; font-size:13.5px; font-weight:600; cursor:pointer; font-family:'Sora',sans-serif;
}
.btn-filter:hover { background:var(--blue-600); }
.btn-clear-filter { padding:10px 16px; background:var(--gray-100); color:var(--gray-600); border:none; border-radius:8px; font-size:13px; cursor:pointer; }

/* ── Bulk action bar ── */
.bulk-bar {
  display:none; position:sticky; top:0; z-index:50;
  background:var(--blue-900); color:#fff;
  padding:12px 24px; border-radius:12px; margin-bottom:16px;
  align-items:center; justify-content:space-between; gap:12px;
}
.bulk-bar.visible { display:flex; }
.bulk-bar .count { font-size:14px; font-weight:600; }
.bulk-actions { display:flex; gap:10px; }
.btn-bulk-approve { background:#059669; color:#fff; border:none; padding:9px 18px; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; }
.btn-bulk-revert  { background:var(--yellow); color:#fff; border:none; padding:9px 18px; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; }
.btn-bulk-reject  { background:var(--red);    color:#fff; border:none; padding:9px 18px; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; }

/* ── Group section ── */
.group-header {
  display:flex; align-items:center; gap:12px; cursor:pointer;
  background:var(--blue-50); border:1px solid var(--blue-100);
  border-radius:10px; padding:12px 16px; margin-bottom:8px;
}
.group-header:hover { background:var(--blue-100); }
.group-label { font-family:'Sora',sans-serif; font-weight:700; font-size:14px; color:var(--blue-900); flex:1; }
.group-count { font-size:12px; color:var(--blue-500); }
.group-chevron { transition:transform .2s; }
.group-header.collapsed .group-chevron { transform:rotate(-90deg); }

/* ── Student table ── */
.student-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
.student-table th { background:var(--gray-100); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--gray-600); padding:10px 14px; text-align:left; }
.student-table td { padding:12px 14px; border-bottom:1px solid var(--gray-100); font-size:13.5px; vertical-align:middle; }
.student-table tr:hover td { background:var(--blue-50); }
.student-table .check-col { width:40px; text-align:center; }
.student-table input[type=checkbox] { width:16px; height:16px; cursor:pointer; accent-color:var(--blue-600); }

/* ── Status badges ── */
.badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11.5px; font-weight:600; }
.badge-cleared    { background:var(--green-bg);  color:var(--green);  }
.badge-pending    { background:var(--yellow-bg); color:var(--yellow); }
.badge-rejected   { background:var(--red-bg);    color:var(--red);    }
.badge-progress   { background:var(--blue-50);   color:var(--blue-700); }
.badge-none       { background:var(--gray-100);  color:var(--gray-400); }

/* ── Progress bar ── */
.progress-wrap { background:var(--gray-100); border-radius:20px; height:6px; width:100px; overflow:hidden; }
.progress-bar  { background:var(--blue-600); height:100%; border-radius:20px; transition:width .3s; }

/* ── Action buttons ── */
.btn-view { background:var(--blue-50); color:var(--blue-700); border:1px solid var(--blue-100); padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none; }
.btn-view:hover { background:var(--blue-100); }

/* ── Confirmation modal ── */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,31,92,0.5); z-index:1000; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:16px; padding:32px; max-width:440px; width:90%; box-shadow:0 20px 60px rgba(15,31,92,0.25); animation:modalIn .25s ease; }
@keyframes modalIn { from{opacity:0;transform:scale(0.95)} to{opacity:1;transform:scale(1)} }
.modal-icon { width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
.modal-icon.approve { background:var(--green-bg); }
.modal-icon.revert  { background:var(--yellow-bg); }
.modal-icon.reject  { background:var(--red-bg); }
.modal-title { font-family:'Sora',sans-serif; font-size:18px; font-weight:800; color:var(--blue-900); text-align:center; margin-bottom:8px; }
.modal-desc  { font-size:13.5px; color:var(--gray-600); text-align:center; margin-bottom:24px; line-height:1.6; }
.modal-actions { display:flex; gap:10px; }
.modal-actions button { flex:1; padding:12px; border:none; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer; font-family:'Sora',sans-serif; }
.modal-btn-confirm.approve { background:var(--green); color:#fff; }
.modal-btn-confirm.revert  { background:var(--yellow); color:#fff; }
.modal-btn-confirm.reject  { background:var(--red); color:#fff; }
.modal-btn-cancel  { background:var(--gray-100); color:var(--gray-600); }

.empty-state { text-align:center; padding:48px 24px; color:var(--gray-400); }
.empty-state svg { margin-bottom:12px; opacity:.4; }

/* ── Select all row ── */
.select-all-row { display:flex; align-items:center; gap:8px; margin-bottom:12px; font-size:13px; color:var(--gray-600); }
.select-all-row input { accent-color:var(--blue-600); width:16px; height:16px; cursor:pointer; }
</style>
</head>
<body>

<!-- Top Bar -->
<div class="topbar">
  <div class="brand">CP ClearPath — Admin</div>
  <div class="nav-links">
    <a href="dashboard.php">Dashboard</a>
    <a href="students.php" style="color:#fff;font-weight:600;">Students</a>
    <a href="clearances.php">Clearances</a>
    <a href="offices.php">Offices</a>
    <a href="logs.php">Logs</a>
    <a href="../logout.php">Logout</a>
  </div>
</div>

<div class="page">
  <div class="page-title">Student Management</div>
  <div class="page-sub">Filter, view, and bulk-manage student clearances by course, year level, and section.</div>

  <!-- ── Filter Card ── -->
  <form method="GET" class="filter-card">
    <div class="filter-group">
      <label>Course / Program</label>
      <select name="course">
        <option value="">All Courses</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= htmlspecialchars($c['code']) ?>" <?= $filterCourse === $c['code'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['code'] . ' — ' . $c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label>Year Level</label>
      <select name="year">
        <option value="">All Years</option>
        <?php foreach ($yearLevels as $y): ?>
          <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label>Section</label>
      <select name="section">
        <option value="">All Sections</option>
        <?php foreach ($sections as $sec): ?>
          <option value="<?= htmlspecialchars($sec) ?>" <?= $filterSection === $sec ? 'selected' : '' ?>><?= htmlspecialchars($sec) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label>Clearance Status</label>
      <select name="status">
        <option value="">All Statuses</option>
        <option value="pending"      <?= $filterStatus==='pending'     ? 'selected':'' ?>>Pending</option>
        <option value="in_progress"  <?= $filterStatus==='in_progress' ? 'selected':'' ?>>In Progress</option>
        <option value="cleared"      <?= $filterStatus==='cleared'     ? 'selected':'' ?>>Cleared</option>
        <option value="rejected"     <?= $filterStatus==='rejected'    ? 'selected':'' ?>>Rejected</option>
      </select>
    </div>
    <button type="submit" class="btn-filter">Apply Filters</button>
    <a href="students.php"><button type="button" class="btn-clear-filter">Clear</button></a>
  </form>

  <!-- ── Bulk Action Bar (appears when items selected) ── -->
  <div class="bulk-bar" id="bulkBar">
    <div class="count"><span id="selectedCount">0</span> student(s) selected</div>
    <div class="bulk-actions">
      <button class="btn-bulk-approve" onclick="openConfirmModal('approve')">✅ Approve All</button>
      <button class="btn-bulk-revert"  onclick="openConfirmModal('revert')">↩ Revert to Processing</button>
      <button class="btn-bulk-reject"  onclick="openConfirmModal('reject')">❌ Reject All</button>
    </div>
  </div>

  <!-- ── Students List ── -->
  <?php if (empty($grouped)): ?>
  <div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="#8b91ae" stroke-width="1.5" stroke-linecap="round"/><circle cx="9" cy="7" r="4" stroke="#8b91ae" stroke-width="1.5"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="#8b91ae" stroke-width="1.5" stroke-linecap="round"/></svg>
    <p style="font-size:15px;font-weight:600;">No students found</p>
    <p style="font-size:13px;margin-top:4px;">Try adjusting your filters.</p>
  </div>

  <?php else: ?>

  <!-- Select All -->
  <div class="select-all-row">
    <input type="checkbox" id="masterCheck" onchange="toggleAll(this)">
    <label for="masterCheck" style="cursor:pointer;">Select / Deselect All Visible Students</label>
    <span style="margin-left:12px;color:var(--gray-400);">(<?= count($students) ?> students shown)</span>
  </div>

  <?php foreach ($grouped as $groupName => $groupStudents): ?>
  <div style="margin-bottom:16px;" class="student-group">
    <!-- Group header (collapsible) -->
    <div class="group-header" onclick="toggleGroup(this)">
      <input type="checkbox" onclick="event.stopPropagation(); toggleGroupCheck(this)"
             data-group="<?= htmlspecialchars($groupName) ?>" style="accent-color:var(--blue-600);width:16px;height:16px;">
      <div class="group-label"><?= htmlspecialchars($groupName) ?></div>
      <div class="group-count"><?= count($groupStudents) ?> student<?= count($groupStudents) > 1 ? 's' : '' ?></div>
      <svg class="group-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M6 9l6 6 6-6" stroke="var(--blue-700)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>

    <!-- Students Table -->
    <div class="group-body">
      <table class="student-table">
        <thead>
          <tr>
            <th class="check-col"></th>
            <th>Student</th>
            <th>Student ID</th>
            <th>Course / Section</th>
            <th>Clearance</th>
            <th>Progress</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groupStudents as $s): ?>
          <?php
            $cs = $s['clearance_status'] ?? 'none';
            $total   = (int)($s['total_items'] ?? 0);
            $approved = (int)($s['approved_count'] ?? 0);
            $pct = $total > 0 ? round($approved / $total * 100) : 0;
            $badgeClass = match($cs) {
              'cleared'     => 'badge-cleared',
              'in_progress' => 'badge-progress',
              'rejected'    => 'badge-rejected',
              'pending'     => 'badge-pending',
              default       => 'badge-none',
            };
            $badgeLabel = match($cs) {
              'cleared'     => '✅ Cleared',
              'in_progress' => '🔄 In Progress',
              'rejected'    => '❌ Rejected',
              'pending'     => '⏳ Pending',
              default       => '— No Request',
            };
          ?>
          <tr>
            <td class="check-col">
              <input type="checkbox"
                class="student-check"
                value="<?= $s['id'] ?>"
                data-req-id="<?= $s['req_id'] ?? '' ?>"
                data-group="<?= htmlspecialchars($groupName) ?>"
                onchange="updateBulkBar()">
            </td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($s['full_name']) ?></div>
              <div style="font-size:12px;color:var(--gray-400);"><?= htmlspecialchars($s['email']) ?></div>
            </td>
            <td style="font-family:monospace;font-size:13px;"><?= htmlspecialchars($s['student_id']) ?></td>
            <td>
              <div style="font-size:13px;"><?= htmlspecialchars($s['course'] ?? 'N/A') ?></div>
              <div style="font-size:12px;color:var(--gray-400);"><?= htmlspecialchars(($s['year_level'] ?? '') . ' · Sec. ' . ($s['section'] ?? 'N/A')) ?></div>
            </td>
            <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
            <td>
              <?php if ($total > 0): ?>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="progress-wrap"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
                <span style="font-size:11px;color:var(--gray-400);"><?= $approved ?>/<?= $total ?></span>
              </div>
              <?php else: ?>
              <span style="color:var(--gray-400);font-size:12px;">No clearance</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($s['req_id']): ?>
              <a href="clearance_view.php?id=<?= $s['req_id'] ?>" class="btn-view">View</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ── Confirmation Modal ── -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box">
    <div class="modal-icon" id="modalIcon">
      <svg id="modalIconSvg" width="28" height="28" viewBox="0 0 24 24" fill="none"></svg>
    </div>
    <div class="modal-title" id="modalTitle">Confirm Action</div>
    <div class="modal-desc"  id="modalDesc">Are you sure?</div>
    <div style="background:var(--gray-50);border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--gray-600);" id="modalMeta"></div>
    <div class="modal-actions">
      <button class="modal-btn-cancel" onclick="closeModal()">Cancel</button>
      <button class="modal-btn-confirm" id="modalConfirmBtn" onclick="executeBulkAction()">Confirm</button>
    </div>
  </div>
</div>

<script>
let pendingAction = '';

// ── Toggle collapsible group ──────────────────────────
function toggleGroup(header) {
  const body = header.nextElementSibling;
  header.classList.toggle('collapsed');
  body.style.display = header.classList.contains('collapsed') ? 'none' : '';
}

// ── Checkbox: select all ──────────────────────────────
function toggleAll(master) {
  document.querySelectorAll('.student-check').forEach(cb => cb.checked = master.checked);
  updateBulkBar();
}

// ── Checkbox: select group ────────────────────────────
function toggleGroupCheck(groupCb) {
  const groupName = groupCb.dataset.group;
  document.querySelectorAll(`.student-check[data-group="${CSS.escape(groupName)}"]`)
    .forEach(cb => cb.checked = groupCb.checked);
  updateBulkBar();
}

// ── Update bulk bar visibility ────────────────────────
function updateBulkBar() {
  const checked = document.querySelectorAll('.student-check:checked');
  const bar = document.getElementById('bulkBar');
  document.getElementById('selectedCount').textContent = checked.length;
  bar.classList.toggle('visible', checked.length > 0);
}

// ── Get selected student IDs and request IDs ─────────
function getSelected() {
  const checks = document.querySelectorAll('.student-check:checked');
  const users   = [], reqs = [];
  checks.forEach(cb => {
    users.push(cb.value);
    if (cb.dataset.reqId) reqs.push(cb.dataset.reqId);
  });
  return { users, reqs, count: checks.length };
}

// ── Open confirmation modal ───────────────────────────
function openConfirmModal(action) {
  const { count, reqs } = getSelected();
  if (count === 0) return;

  pendingAction = action;

  const icon    = document.getElementById('modalIcon');
  const iconSvg = document.getElementById('modalIconSvg');
  const title   = document.getElementById('modalTitle');
  const desc    = document.getElementById('modalDesc');
  const btn     = document.getElementById('modalConfirmBtn');
  const meta    = document.getElementById('modalMeta');

  icon.className = 'modal-icon ' + action;
  btn.className  = 'modal-btn-confirm ' + action;

  if (action === 'approve') {
    iconSvg.innerHTML = '<path d="M20 6L9 17l-5-5" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
    title.textContent = 'Bulk Approve Clearances';
    desc.textContent  = `You are about to approve all clearance items for ${count} selected student(s). All pending items from all offices will be marked as approved.`;
    btn.textContent   = 'Yes, Approve All';
  } else if (action === 'revert') {
    iconSvg.innerHTML = '<path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" stroke="#d97706" stroke-width="2" stroke-linecap="round"/>';
    title.textContent = 'Revert to Processing';
    desc.textContent  = `This will revert all approved clearance items back to "pending" status for ${count} selected student(s).`;
    btn.textContent   = 'Yes, Revert';
  } else {
    iconSvg.innerHTML = '<circle cx="12" cy="12" r="10" stroke="#dc2626" stroke-width="1.8"/><path d="M15 9l-6 6M9 9l6 6" stroke="#dc2626" stroke-width="1.8" stroke-linecap="round"/>';
    title.textContent = 'Bulk Reject Clearances';
    desc.textContent  = `You are about to reject all clearance items for ${count} selected student(s). This action will mark all pending items as rejected.`;
    btn.textContent   = 'Yes, Reject All';
  }

  meta.innerHTML = `<strong>${count} students selected</strong> · ${reqs.length} clearance request(s) will be affected`;

  document.getElementById('confirmModal').classList.add('open');
}

function closeModal() {
  document.getElementById('confirmModal').classList.remove('open');
  pendingAction = '';
}

// ── Execute bulk action via AJAX ──────────────────────
function executeBulkAction() {
  const { users, reqs } = getSelected();
  if (!pendingAction || reqs.length === 0) return;

  const btn = document.getElementById('modalConfirmBtn');
  btn.textContent = 'Processing...';
  btn.disabled    = true;

  fetch('ajax_bulk.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: pendingAction, request_ids: reqs, user_ids: users })
  })
  .then(r => r.json())
  .then(data => {
    closeModal();
    if (data.success) {
      // Show success toast and reload
      showToast(data.message, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast(data.message || 'Something went wrong.', 'error');
      btn.disabled = false;
      btn.textContent = 'Confirm';
    }
  })
  .catch(() => {
    showToast('Request failed. Please try again.', 'error');
    btn.disabled = false;
  });
}

// ── Toast notification ────────────────────────────────
function showToast(msg, type) {
  const t = document.createElement('div');
  t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;
    background:${type==='success'?'#059669':'#dc2626'};color:#fff;
    padding:14px 20px;border-radius:10px;font-size:14px;font-weight:600;
    box-shadow:0 4px 20px rgba(0,0,0,0.2);animation:slideIn .3s ease`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

const style = document.createElement('style');
style.textContent = `@keyframes slideIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}`;
document.head.appendChild(style);

// Close modal on overlay click
document.getElementById('confirmModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>

</body>
</html>
