<?php
/**
 * Signatory — Clearance Requests
 * ─────────────────────────────────────────────────────────
 * Features:
 *   - Shows only clearance items for this signatory's office
 *   - Filter by Course, Year Level, Section, Status
 *   - Bulk Approve / Reject / Revert for their office items
 *   - Confirmation modal before bulk actions
 *   - Can view submitted documents/photos
 * ─────────────────────────────────────────────────────────
 */
require_once '../includes/config.php';
requireRole('signatory');

$db        = getDB();
$officeId  = (int)$_SESSION['office_id'];  // set at login
$sigId     = (int)$_SESSION['user_id'];

// ── Read filters ─────────────────────────────────────────
$filterCourse  = trim($_GET['course']  ?? '');
$filterYear    = trim($_GET['year']    ?? '');
$filterSection = trim($_GET['section'] ?? '');
$filterStatus  = trim($_GET['status']  ?? '');

// ── Build WHERE clause ────────────────────────────────────
$where   = ["ci.office_id = $officeId"];
if ($filterCourse)  $where[] = "u.course = '"   . $db->real_escape_string($filterCourse)  . "'";
if ($filterYear)    $where[] = "u.year_level = '". $db->real_escape_string($filterYear)   . "'";
if ($filterSection) $where[] = "u.section = '"  . $db->real_escape_string($filterSection) . "'";
if ($filterStatus)  $where[] = "ci.status = '"  . $db->real_escape_string($filterStatus)  . "'";
$whereSQL = implode(' AND ', $where);

// ── Fetch items ───────────────────────────────────────────
$items = $db->query("
    SELECT ci.id, ci.status, ci.remarks, ci.requirements_submitted, ci.reviewed_at,
           ci.deadline, ci.file_path,
           cr.id AS req_id, cr.school_year, cr.semester,
           u.id AS student_user_id, u.full_name, u.student_id AS sid,
           u.email, u.year_level, u.section, u.course,
           o.name AS office_name
    FROM clearance_items ci
    JOIN clearance_requests cr ON cr.id = ci.clearance_request_id
    JOIN users u               ON u.id  = cr.student_id
    JOIN offices o             ON o.id  = ci.office_id
    WHERE $whereSQL
    ORDER BY u.course, u.year_level, u.section, u.full_name
")->fetch_all(MYSQLI_ASSOC);

// ── Dropdown options ──────────────────────────────────────
$courses    = $db->query("SELECT code,name FROM courses WHERE is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$yearLevels = ['1st Year','2nd Year','3rd Year','4th Year'];
$sections   = $db->query("SELECT DISTINCT u.section FROM users u WHERE u.role='student' AND u.section IS NOT NULL ORDER BY u.section")->fetch_all(MYSQLI_ASSOC);
$sections   = array_column($sections, 'section');

$officeName = $items[0]['office_name'] ?? ($_SESSION['office'] ?? 'Office');
$db->close();

// Group by section
$grouped = [];
foreach ($items as $item) {
    $key = ($item['course'] ?? 'No Course') . ' — ' . ($item['year_level'] ?? 'Unknown Year') . ' — Sec. ' . ($item['section'] ?? 'N/A');
    $grouped[$key][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Requests — <?= htmlspecialchars($officeName) ?> | ClearPath</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --blue-900:#0f1f5c; --blue-700:#1e3a9e; --blue-600:#2046c4;
  --blue-50:#eef2ff; --blue-100:#dce6ff;
  --white:#fff; --gray-50:#f8f9fc; --gray-100:#eef0f6; --gray-200:#d8dbe8;
  --gray-400:#8b91ae; --gray-600:#4a5070; --gray-800:#1e2240;
  --green:#059669; --green-bg:#d1fae5;
  --red:#dc2626;   --red-bg:#fee2e2;
  --yellow:#d97706;--yellow-bg:#fef3c7;
  --shadow:0 2px 12px rgba(15,31,92,.10);
}
body { font-family:'DM Sans',sans-serif; background:var(--gray-50); color:var(--gray-800); }
.topbar { background:var(--blue-900); padding:14px 28px; display:flex; align-items:center; justify-content:space-between; }
.topbar .brand { font-family:'Sora',sans-serif; font-weight:800; font-size:16px; color:#fff; }
.topbar .nav-links a { color:rgba(255,255,255,.7); text-decoration:none; margin-left:20px; font-size:13px; }
.topbar .nav-links a:hover, .topbar .nav-links a.active { color:#fff; font-weight:600; }
.page { max-width:1200px; margin:0 auto; padding:28px 24px; }
.page-title { font-family:'Sora',sans-serif; font-size:22px; font-weight:800; color:var(--blue-900); }
.page-sub   { font-size:13px; color:var(--gray-400); margin-top:4px; }
.filter-card { background:var(--white); border-radius:14px; box-shadow:var(--shadow); padding:20px 24px; margin:20px 0; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
.fg label { display:block; font-size:12px; font-weight:700; color:var(--gray-600); text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
.fg select { padding:9px 12px; border:1.5px solid var(--gray-200); border-radius:8px; font-size:13.5px; min-width:160px; outline:none; color:var(--gray-800); background:var(--gray-50); font-family:'DM Sans',sans-serif; }
.fg select:focus { border-color:var(--blue-500); background:#fff; }
.btn-f { padding:10px 20px; background:var(--blue-700); color:#fff; border:none; border-radius:8px; font-size:13.5px; font-weight:600; cursor:pointer; font-family:'Sora',sans-serif; }
.btn-c { padding:10px 16px; background:var(--gray-100); color:var(--gray-600); border:none; border-radius:8px; font-size:13px; cursor:pointer; }
.bulk-bar { display:none; position:sticky; top:0; z-index:50; background:var(--blue-900); color:#fff; padding:12px 24px; border-radius:12px; margin-bottom:16px; align-items:center; justify-content:space-between; gap:12px; }
.bulk-bar.visible { display:flex; }
.bulk-bar .cnt { font-size:14px; font-weight:600; }
.ba { display:flex; gap:10px; }
.ba button { padding:9px 18px; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; }
.baa { background:var(--green);  color:#fff; }
.bav { background:var(--yellow); color:#fff; }
.bar { background:var(--red);    color:#fff; }
.group-header { display:flex; align-items:center; gap:12px; cursor:pointer; background:var(--blue-50); border:1px solid var(--blue-100); border-radius:10px; padding:12px 16px; margin-bottom:8px; }
.group-header:hover { background:var(--blue-100); }
.group-label { font-family:'Sora',sans-serif; font-weight:700; font-size:14px; color:var(--blue-900); flex:1; }
.group-count { font-size:12px; color:var(--blue-500); }
.group-chevron { transition:transform .2s; }
.group-header.collapsed .group-chevron { transform:rotate(-90deg); }
table.rt { width:100%; border-collapse:collapse; margin-bottom:20px; }
table.rt th { background:var(--gray-100); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--gray-600); padding:10px 14px; text-align:left; }
table.rt td { padding:12px 14px; border-bottom:1px solid var(--gray-100); font-size:13.5px; vertical-align:middle; }
table.rt tr:hover td { background:var(--blue-50); }
.badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11.5px; font-weight:600; }
.bc  { background:var(--green-bg);  color:var(--green);  }
.bp  { background:var(--yellow-bg); color:var(--yellow); }
.brj { background:var(--red-bg);    color:var(--red);    }
.bi  { background:var(--blue-50);   color:var(--blue-700); }
.btn-approve { background:var(--green);  color:#fff; border:none; padding:5px 14px; border-radius:7px; font-size:12px; font-weight:700; cursor:pointer; }
.btn-reject  { background:var(--red);    color:#fff; border:none; padding:5px 14px; border-radius:7px; font-size:12px; font-weight:700; cursor:pointer; }
.btn-view-doc { background:var(--blue-50); color:var(--blue-700); border:1px solid var(--blue-100); padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none; }
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,31,92,.5); z-index:1000; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:16px; padding:32px; max-width:440px; width:90%; box-shadow:0 20px 60px rgba(15,31,92,.25); animation:mIn .25s ease; }
@keyframes mIn { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:scale(1)} }
.mi { width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px; }
.mi.approve{background:var(--green-bg)} .mi.revert{background:var(--yellow-bg)} .mi.reject{background:var(--red-bg)}
.mt { font-family:'Sora',sans-serif; font-size:18px; font-weight:800; color:var(--blue-900); text-align:center; margin-bottom:8px; }
.md { font-size:13.5px; color:var(--gray-600); text-align:center; margin-bottom:20px; line-height:1.6; }
.mm { background:var(--gray-50);border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--gray-600); }
.ma { display:flex; gap:10px; }
.ma button { flex:1; padding:12px; border:none; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer; font-family:'Sora',sans-serif; }
.mca { background:var(--green); color:#fff; } .mcv { background:var(--yellow); color:#fff; } .mcr { background:var(--red); color:#fff; }
.mcc { background:var(--gray-100); color:var(--gray-600); }
/* Remarks modal */
.rm-box { max-width:480px; }
.rm-box textarea { width:100%; border:1.5px solid var(--gray-200); border-radius:8px; padding:10px 12px; font-size:14px; font-family:'DM Sans',sans-serif; resize:vertical; min-height:90px; outline:none; }
.rm-box textarea:focus { border-color:var(--blue-500); }
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">CP — <?= htmlspecialchars($officeName) ?></div>
  <div class="nav-links">
    <a href="dashboard.php">Dashboard</a>
    <a href="requests.php" class="active">Requests</a>
    <a href="requirements.php">Requirements</a>
    <a href="../logout.php">Logout</a>
  </div>
</div>

<div class="page">
  <div class="page-title">Clearance Requests</div>
  <div class="page-sub">Review, approve, or reject student clearance requirements for <strong><?= htmlspecialchars($officeName) ?></strong>.</div>

  <!-- Filters -->
  <form method="GET" class="filter-card">
    <div class="fg">
      <label>Course</label>
      <select name="course">
        <option value="">All Courses</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= htmlspecialchars($c['code']) ?>" <?= $filterCourse===$c['code']?'selected':'' ?>>
            <?= htmlspecialchars($c['code'].' — '.$c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fg">
      <label>Year Level</label>
      <select name="year">
        <option value="">All Years</option>
        <?php foreach ($yearLevels as $y): ?>
          <option value="<?= $y ?>" <?= $filterYear===$y?'selected':'' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fg">
      <label>Section</label>
      <select name="section">
        <option value="">All Sections</option>
        <?php foreach ($sections as $sec): ?>
          <option value="<?= htmlspecialchars($sec) ?>" <?= $filterSection===$sec?'selected':'' ?>><?= htmlspecialchars($sec) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fg">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="pending"   <?= $filterStatus==='pending'  ?'selected':'' ?>>Pending</option>
        <option value="approved"  <?= $filterStatus==='approved' ?'selected':'' ?>>Approved</option>
        <option value="rejected"  <?= $filterStatus==='rejected' ?'selected':'' ?>>Rejected</option>
      </select>
    </div>
    <button type="submit" class="btn-f">Apply</button>
    <a href="requests.php"><button type="button" class="btn-c">Clear</button></a>
  </form>

  <!-- Bulk Bar -->
  <div class="bulk-bar" id="bulkBar">
    <div class="cnt"><span id="selCount">0</span> item(s) selected</div>
    <div class="ba">
      <button class="baa" onclick="openModal('approve')">✅ Approve Selected</button>
      <button class="bav" onclick="openModal('revert')">↩ Revert to Pending</button>
      <button class="bar" onclick="openModal('reject')">❌ Reject Selected</button>
    </div>
  </div>

  <!-- Select All -->
  <?php if (!empty($grouped)): ?>
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:13px;color:var(--gray-600);">
    <input type="checkbox" id="masterCb" onchange="toggleAll(this)" style="width:16px;height:16px;accent-color:var(--blue-600);cursor:pointer;">
    <label for="masterCb" style="cursor:pointer;">Select / Deselect All</label>
    <span style="color:var(--gray-400);">(<?= count($items) ?> items)</span>
  </div>
  <?php endif; ?>

  <?php if (empty($grouped)): ?>
  <div style="text-align:center;padding:48px;color:var(--gray-400);">
    <p style="font-size:15px;font-weight:600;">No requests found</p>
    <p style="font-size:13px;margin-top:4px;">Try adjusting your filters.</p>
  </div>
  <?php else: ?>
  <?php foreach ($grouped as $groupName => $groupItems): ?>
  <div style="margin-bottom:16px;">
    <div class="group-header" onclick="toggleGroup(this)">
      <input type="checkbox" onclick="event.stopPropagation();toggleGroupCheck(this)"
             data-group="<?= htmlspecialchars($groupName) ?>"
             style="accent-color:var(--blue-600);width:16px;height:16px;">
      <div class="group-label"><?= htmlspecialchars($groupName) ?></div>
      <div class="group-count"><?= count($groupItems) ?> item<?= count($groupItems)>1?'s':'' ?></div>
      <svg class="group-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M6 9l6 6 6-6" stroke="var(--blue-700)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>

    <div class="group-body">
      <table class="rt">
        <thead>
          <tr>
            <th style="width:40px;text-align:center;"></th>
            <th>Student</th>
            <th>Student ID</th>
            <th>Documents</th>
            <th>Deadline</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groupItems as $item): ?>
          <?php
            $bc = match($item['status']) {
              'approved' => 'bc',
              'rejected' => 'brj',
              'pending'  => 'bp',
              default    => 'bi',
            };
            $bl = match($item['status']) {
              'approved' => '✅ Approved',
              'rejected' => '❌ Rejected',
              'pending'  => '⏳ Pending',
              default    => $item['status'],
            };
            $hasDoc = $item['requirements_submitted'] && $item['file_path'];
          ?>
          <tr>
            <td style="text-align:center;">
              <input type="checkbox" class="item-cb"
                value="<?= $item['id'] ?>"
                data-group="<?= htmlspecialchars($groupName) ?>"
                onchange="updateBar()">
            </td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($item['full_name']) ?></div>
              <div style="font-size:12px;color:var(--gray-400);"><?= htmlspecialchars($item['email']) ?></div>
            </td>
            <td style="font-family:monospace;font-size:13px;"><?= htmlspecialchars($item['sid']) ?></td>
            <td>
              <?php if ($hasDoc): ?>
                <a href="../uploads/<?= htmlspecialchars($item['file_path']) ?>"
                   target="_blank" class="btn-view-doc">📎 View Doc</a>
              <?php elseif ($item['requirements_submitted']): ?>
                <span style="font-size:12px;color:var(--gray-400);">Submitted (no file)</span>
              <?php else: ?>
                <span style="font-size:12px;color:var(--gray-400);">Not submitted</span>
              <?php endif; ?>
            </td>
            <td style="font-size:13px;">
              <?= $item['deadline'] ? date('M j, Y', strtotime($item['deadline'])) : '<span style="color:var(--gray-400);">No deadline</span>' ?>
            </td>
            <td><span class="badge <?= $bc ?>"><?= $bl ?></span></td>
            <td>
              <?php if ($item['status'] === 'pending' && $item['requirements_submitted']): ?>
              <button class="btn-approve" onclick="quickAction(<?= $item['id'] ?>,'approve')">Approve</button>
              <button class="btn-reject"  onclick="quickReject(<?= $item['id'] ?>)">Reject</button>
              <?php elseif ($item['status'] === 'approved'): ?>
              <button onclick="quickAction(<?= $item['id'] ?>,'revert')"
                style="background:var(--yellow-bg);color:var(--yellow);border:none;padding:5px 14px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;">Revert</button>
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

<!-- Bulk Confirmation Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box">
    <div class="mi" id="mIcon"><svg id="mIconSvg" width="28" height="28" viewBox="0 0 24 24" fill="none"></svg></div>
    <div class="mt" id="mTitle">Confirm</div>
    <div class="md" id="mDesc"></div>
    <div class="mm" id="mMeta"></div>
    <div class="ma">
      <button class="mcc" onclick="closeModal()">Cancel</button>
      <button id="mConfirm" onclick="execBulk()">Confirm</button>
    </div>
  </div>
</div>

<!-- Reject Remarks Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-box rm-box">
    <div class="mt">Add Remarks (Optional)</div>
    <div class="md">Provide a reason for rejection. Students will see this.</div>
    <textarea id="remarksInput" placeholder="e.g. Incomplete documents, please resubmit with clear copy of ID."></textarea>
    <div class="ma" style="margin-top:16px;">
      <button class="mcc" onclick="closeRejectModal()">Cancel</button>
      <button class="mcr" onclick="confirmReject()">Reject</button>
    </div>
  </div>
</div>

<script>
let pendingAction = '', rejectItemId = 0;

function toggleGroup(header) {
  const body = header.nextElementSibling;
  header.classList.toggle('collapsed');
  body.style.display = header.classList.contains('collapsed') ? 'none' : '';
}

function toggleAll(master) {
  document.querySelectorAll('.item-cb').forEach(cb => cb.checked = master.checked);
  updateBar();
}

function toggleGroupCheck(cb) {
  const g = cb.dataset.group;
  document.querySelectorAll(`.item-cb[data-group="${CSS.escape(g)}"]`).forEach(c => c.checked = cb.checked);
  updateBar();
}

function updateBar() {
  const checked = document.querySelectorAll('.item-cb:checked').length;
  document.getElementById('selCount').textContent = checked;
  document.getElementById('bulkBar').classList.toggle('visible', checked > 0);
}

function getSelectedIds() {
  return [...document.querySelectorAll('.item-cb:checked')].map(c => c.value);
}

function openModal(action) {
  const ids = getSelectedIds();
  if (!ids.length) return;
  pendingAction = action;

  const icon  = document.getElementById('mIcon');
  const svg   = document.getElementById('mIconSvg');
  const title = document.getElementById('mTitle');
  const desc  = document.getElementById('mDesc');
  const btn   = document.getElementById('mConfirm');
  const meta  = document.getElementById('mMeta');

  icon.className = 'mi ' + action;
  btn.className  = action === 'approve' ? 'mca' : action === 'revert' ? 'mcv' : 'mcr';

  if (action === 'approve') {
    svg.innerHTML = '<path d="M20 6L9 17l-5-5" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
    title.textContent = 'Bulk Approve';
    desc.textContent  = `Approve ${ids.length} selected clearance item(s) for your office.`;
    btn.textContent   = 'Yes, Approve';
  } else if (action === 'revert') {
    svg.innerHTML = '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="#d97706" stroke-width="1.8" stroke-linecap="round"/>';
    title.textContent = 'Revert to Pending';
    desc.textContent  = `Reset ${ids.length} item(s) back to pending status.`;
    btn.textContent   = 'Yes, Revert';
  } else {
    svg.innerHTML = '<circle cx="12" cy="12" r="10" stroke="#dc2626" stroke-width="1.8"/><path d="M15 9l-6 6M9 9l6 6" stroke="#dc2626" stroke-width="1.8" stroke-linecap="round"/>';
    title.textContent = 'Bulk Reject';
    desc.textContent  = `Reject ${ids.length} selected clearance item(s).`;
    btn.textContent   = 'Yes, Reject';
  }
  meta.innerHTML = `<strong>${ids.length} items</strong> selected`;
  document.getElementById('confirmModal').classList.add('open');
}

function closeModal() { document.getElementById('confirmModal').classList.remove('open'); }

function execBulk() {
  const ids = getSelectedIds();
  const btn = document.getElementById('mConfirm');
  btn.textContent = 'Processing...'; btn.disabled = true;

  fetch('ajax_bulk.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ action: pendingAction, item_ids: ids })
  })
  .then(r => r.json())
  .then(d => {
    closeModal();
    if (d.success) { showToast(d.message, 'success'); setTimeout(() => location.reload(), 1400); }
    else { showToast(d.message || 'Error', 'error'); btn.disabled = false; }
  });
}

// ── Single item quick actions ──────────────────────
function quickAction(id, action) {
  fetch('ajax_bulk.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ action, item_ids: [String(id)] })
  })
  .then(r=>r.json())
  .then(d => { if(d.success){showToast(d.message,'success');setTimeout(()=>location.reload(),1200);} else showToast(d.message,'error'); });
}

function quickReject(id) {
  rejectItemId = id;
  document.getElementById('remarksInput').value = '';
  document.getElementById('rejectModal').classList.add('open');
}
function closeRejectModal() { document.getElementById('rejectModal').classList.remove('open'); }
function confirmReject() {
  const remarks = document.getElementById('remarksInput').value.trim();
  fetch('ajax_bulk.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ action:'reject', item_ids:[String(rejectItemId)], remarks })
  })
  .then(r=>r.json())
  .then(d => { closeRejectModal(); if(d.success){showToast(d.message,'success');setTimeout(()=>location.reload(),1200);} else showToast(d.message,'error'); });
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;background:${type==='success'?'#059669':'#dc2626'};color:#fff;padding:14px 20px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.2);animation:sIn .3s ease`;
  t.textContent = msg; document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}
const st = document.createElement('style');
st.textContent = `@keyframes sIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}`;
document.head.appendChild(st);

document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
</body>
</html>
