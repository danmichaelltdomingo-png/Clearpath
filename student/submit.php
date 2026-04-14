<?php
/**
 * Student — Submit Clearance Requirements
 * ─────────────────────────────────────────────────────────
 * Features:
 *   - View all office requirements at a glance
 *   - Upload file (PDF/image) OR capture live photo via camera
 *   - Preview file/photo BEFORE submitting
 *   - Submit button disabled until file/photo is selected
 *   - View previously submitted documents
 *   - All submissions scoped to this student's clearance only
 * ─────────────────────────────────────────────────────────
 */
require_once '../includes/config.php';
requireRole('student');

$db         = getDB();
$studentId  = (int)$_SESSION['user_id'];
$itemId     = (int)($_GET['item'] ?? 0);
$msg        = '';
$msgType    = '';

// ── Get current active clearance request ────────────────
$request = $db->query("
    SELECT * FROM clearance_requests
    WHERE student_id = $studentId
    ORDER BY submitted_at DESC LIMIT 1
")->fetch_assoc();

if (!$request) {
    // No clearance started yet — redirect
    header('Location: clearance.php?msg=no_request');
    exit;
}

$reqId = (int)$request['id'];

// ── Handle file submission ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $itemId > 0) {
    // Verify this item belongs to this student
    $item = $db->query("
        SELECT ci.* FROM clearance_items ci
        WHERE ci.id = $itemId AND ci.clearance_request_id = $reqId
        LIMIT 1
    ")->fetch_assoc();

    if (!$item) {
        $msg = 'Invalid request.';
        $msgType = 'error';
    } elseif ($item['status'] === 'approved') {
        $msg = 'This item is already approved.';
        $msgType = 'error';
    } else {
        $uploadDir  = dirname(__DIR__) . '/uploads/';
        $uploadedOk = false;
        $filePath   = '';
        $fileType   = 'upload';

        // ── Handle camera capture (base64 image) ──────────
        if (!empty($_POST['camera_data'])) {
            $base64 = $_POST['camera_data'];
            // Strip data URI prefix if present
            $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
            $imgData = base64_decode($base64);

            if ($imgData) {
                $filename = 'cam_' . $itemId . '_' . $studentId . '_' . time() . '.jpg';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                file_put_contents($uploadDir . $filename, $imgData);
                $filePath   = $filename;
                $fileType   = 'camera';
                $uploadedOk = true;
            } else {
                $msg = 'Camera capture failed. Please try again.';
                $msgType = 'error';
            }
        }
        // ── Handle file upload ─────────────────────────────
        elseif (!empty($_FILES['req_file']) && $_FILES['req_file']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['req_file'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            // Validate type (images + PDF only)
            $allowed  = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!in_array($mimeType, $allowed)) {
                $msg = 'Only JPG, PNG, GIF, WEBP, or PDF files are allowed.';
                $msgType = 'error';
            } elseif ($file['size'] > $maxSize) {
                $msg = 'File too large. Maximum allowed size is 5MB.';
                $msgType = 'error';
            } else {
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'req_' . $reqId . '_' . $itemId . '_' . time() . '.' . $ext;
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $filePath   = $filename;
                    $fileType   = 'upload';
                    $uploadedOk = true;
                } else {
                    $msg = 'Upload failed. Check server permissions.';
                    $msgType = 'error';
                }
            }
        } else {
            $msg = 'Please attach a file or take a photo before submitting.';
            $msgType = 'error';
        }

        // ── Save to DB if upload succeeded ─────────────────
        if ($uploadedOk) {
            $safeFile = $db->real_escape_string($filePath);
            $safeType = $db->real_escape_string($fileType);

            // Add to clearance_files table (multiple files allowed)
            $db->query("
                INSERT INTO clearance_files (clearance_item_id, file_path, file_type, original_name)
                VALUES ($itemId, '$safeFile', '$safeType', '$safeFile')
            ");

            // Update the main clearance_item
            $db->query("
                UPDATE clearance_items
                SET requirements_submitted = 1, file_path = '$safeFile', status = 'pending'
                WHERE id = $itemId AND clearance_request_id = $reqId
            ");

            logActivity('SUBMIT_DOCS', "Submitted documents for item ID $itemId via $fileType");

            $msg     = 'Your document has been submitted successfully!';
            $msgType = 'success';
        }
    }
}

// ── Fetch all clearance items for this request ───────────
$allItems = $db->query("
    SELECT ci.id, ci.status, ci.remarks, ci.requirements_submitted, ci.file_path,
           ci.deadline, ci.reviewed_at,
           o.name AS office_name, o.description AS office_desc,
           GROUP_CONCAT(oreq.requirement_name SEPARATOR '||') AS req_names,
           GROUP_CONCAT(oreq.description SEPARATOR '||')      AS req_descs,
           (SELECT COUNT(*) FROM clearance_files cf WHERE cf.clearance_item_id = ci.id) AS file_count
    FROM clearance_items ci
    JOIN offices o ON o.id = ci.office_id
    LEFT JOIN office_requirements oreq ON oreq.office_id = ci.office_id
    WHERE ci.clearance_request_id = $reqId
    GROUP BY ci.id
    ORDER BY o.sort_order
")->fetch_all(MYSQLI_ASSOC);

// Currently selected item
$selectedItem = null;
if ($itemId > 0) {
    foreach ($allItems as $ai) {
        if ((int)$ai['id'] === $itemId) { $selectedItem = $ai; break; }
    }
}

// Fetch files for selected item
$selectedFiles = [];
if ($selectedItem) {
    $selectedFiles = $db->query("
        SELECT * FROM clearance_files
        WHERE clearance_item_id = $itemId
        ORDER BY uploaded_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submit Requirements — ClearPath BPC</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --blue-900:#0f1f5c; --blue-700:#1e3a9e; --blue-600:#2046c4; --blue-500:#2d55d4;
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
.layout { display:grid; grid-template-columns:300px 1fr; gap:24px; max-width:1100px; margin:28px auto; padding:0 24px; }
@media(max-width:768px) { .layout { grid-template-columns:1fr; } }

/* ── Office list sidebar ── */
.sidebar { background:var(--white); border-radius:14px; box-shadow:var(--shadow); overflow:hidden; height:fit-content; }
.sidebar-title { font-family:'Sora',sans-serif; font-size:13px; font-weight:700; color:var(--gray-600); text-transform:uppercase; letter-spacing:.5px; padding:16px 20px; border-bottom:1px solid var(--gray-100); }
.office-item { display:flex; align-items:center; gap:12px; padding:14px 20px; border-bottom:1px solid var(--gray-100); text-decoration:none; transition:background .15s; cursor:pointer; }
.office-item:hover { background:var(--blue-50); }
.office-item.active { background:var(--blue-50); border-left:3px solid var(--blue-600); }
.office-item.active .office-name { color:var(--blue-700); font-weight:600; }
.office-icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:16px; }
.office-name { font-size:13.5px; color:var(--gray-800); flex:1; }
.badge-sm { font-size:10.5px; font-weight:700; padding:3px 8px; border-radius:12px; }
.bc  { background:var(--green-bg);  color:var(--green);  }
.bp  { background:var(--yellow-bg); color:var(--yellow); }
.brj { background:var(--red-bg);    color:var(--red);    }
.bn  { background:var(--gray-100);  color:var(--gray-400); }

/* ── Main panel ── */
.main-card { background:var(--white); border-radius:14px; box-shadow:var(--shadow); overflow:hidden; }
.card-top { background:linear-gradient(135deg,var(--blue-900),var(--blue-700)); padding:24px 28px; }
.card-top h2 { font-family:'Sora',sans-serif; font-size:18px; font-weight:800; color:#fff; }
.card-top p  { color:rgba(255,255,255,.65); font-size:13px; margin-top:4px; }
.card-body   { padding:28px; }

/* ── Alert ── */
.alert { display:flex; align-items:flex-start; gap:10px; padding:14px 16px; border-radius:10px; font-size:13.5px; line-height:1.5; margin-bottom:20px; }
.alert.success { background:var(--green-bg); border:1px solid #6ee7b7; color:var(--green); }
.alert.error   { background:var(--red-bg);   border:1px solid #fca5a5; color:var(--red); }
.alert.info    { background:var(--blue-50);  border:1px solid var(--blue-100); color:var(--blue-700); }

/* ── Requirements list ── */
.req-list { background:var(--gray-50); border:1px solid var(--gray-100); border-radius:10px; padding:16px 20px; margin-bottom:24px; }
.req-list h4 { font-size:12px; font-weight:700; color:var(--gray-600); text-transform:uppercase; letter-spacing:.4px; margin-bottom:10px; }
.req-item { display:flex; align-items:flex-start; gap:10px; margin-bottom:8px; font-size:13.5px; color:var(--gray-700); }
.req-item:last-child { margin-bottom:0; }
.req-dot { width:7px; height:7px; border-radius:50%; background:var(--blue-600); flex-shrink:0; margin-top:5px; }

/* ── Upload area ── */
.upload-section h3 { font-family:'Sora',sans-serif; font-size:15px; font-weight:700; color:var(--blue-900); margin-bottom:16px; }
.upload-tabs { display:flex; gap:0; border:1.5px solid var(--gray-200); border-radius:10px; overflow:hidden; margin-bottom:20px; }
.upload-tab { flex:1; padding:11px; text-align:center; font-size:13.5px; font-weight:600; cursor:pointer; background:var(--gray-50); color:var(--gray-600); transition:all .15s; border:none; font-family:'DM Sans',sans-serif; }
.upload-tab.active { background:var(--blue-600); color:#fff; }
.upload-tab:first-child { border-right:1px solid var(--gray-200); }

/* File upload drag zone */
.drop-zone {
  border:2px dashed var(--gray-200); border-radius:12px; padding:32px 20px;
  text-align:center; cursor:pointer; transition:all .2s;
  background:var(--gray-50);
}
.drop-zone:hover, .drop-zone.drag-over { border-color:var(--blue-500); background:var(--blue-50); }
.drop-zone svg { margin-bottom:10px; }
.drop-zone p { font-size:14px; color:var(--gray-600); margin-bottom:4px; }
.drop-zone span { font-size:12px; color:var(--gray-400); }
.drop-zone input[type=file] { display:none; }

/* Camera section */
.camera-section { text-align:center; }
.video-wrap { position:relative; border-radius:12px; overflow:hidden; background:#000; aspect-ratio:4/3; max-height:280px; }
#cameraVideo { width:100%; height:100%; object-fit:cover; }
.camera-overlay { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.6); }
.camera-overlay p { color:#fff; font-size:14px; }
.camera-controls { display:flex; justify-content:center; gap:12px; margin-top:16px; }
.btn-cam { padding:10px 20px; border:none; border-radius:8px; font-size:13.5px; font-weight:600; cursor:pointer; font-family:'Sora',sans-serif; }
.btn-cam-start    { background:var(--blue-600);   color:#fff; }
.btn-cam-capture  { background:var(--green);       color:#fff; }
.btn-cam-retake   { background:var(--yellow-bg);   color:var(--yellow); }
.btn-cam-stop     { background:var(--gray-100);    color:var(--gray-600); }

/* Preview box */
.preview-box { border:2px solid var(--green); border-radius:12px; overflow:hidden; margin-top:16px; position:relative; }
.preview-box img  { width:100%; max-height:280px; object-fit:contain; background:#f3f4f6; }
.preview-box.pdf-preview { background:var(--gray-50); padding:20px; text-align:center; }
.preview-label { position:absolute; top:10px; left:10px; background:var(--green); color:#fff; font-size:11px; font-weight:700; padding:4px 10px; border-radius:6px; }
.preview-remove { position:absolute; top:10px; right:10px; background:var(--red); color:#fff; border:none; border-radius:6px; padding:4px 10px; font-size:12px; font-weight:700; cursor:pointer; }

/* Submit button */
.btn-submit {
  width:100%; padding:14px; background:linear-gradient(135deg,var(--blue-600),var(--blue-800));
  color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:700;
  font-family:'Sora',sans-serif; cursor:pointer; margin-top:20px;
  transition:all .2s; box-shadow:0 4px 20px rgba(32,70,196,.35);
  display:flex; align-items:center; justify-content:center; gap:8px;
}
.btn-submit:disabled { opacity:.4; cursor:not-allowed; transform:none; box-shadow:none; }
.btn-submit:not(:disabled):hover { transform:translateY(-1px); box-shadow:0 8px 28px rgba(32,70,196,.5); }

/* Submitted files */
.files-list { display:flex; flex-direction:column; gap:8px; margin-top:12px; }
.file-item { display:flex; align-items:center; gap:10px; padding:10px 14px; background:var(--gray-50); border:1px solid var(--gray-100); border-radius:8px; }
.file-item a { font-size:13px; color:var(--blue-600); font-weight:600; text-decoration:none; flex:1; }
.file-item a:hover { text-decoration:underline; }
.file-badge { font-size:10.5px; padding:2px 8px; border-radius:10px; font-weight:700; }
.file-badge.upload { background:var(--blue-50); color:var(--blue-700); }
.file-badge.camera { background:var(--yellow-bg); color:var(--yellow); }

/* Empty state */
.empty-prompt { text-align:center; padding:48px 24px; color:var(--gray-400); }
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">CP ClearPath</div>
  <div class="nav-links">
    <a href="dashboard.php">Dashboard</a>
    <a href="clearance.php">My Clearance</a>
    <a href="submit.php" class="active">Submit Docs</a>
    <a href="history.php">History</a>
    <a href="../logout.php">Logout</a>
  </div>
</div>

<div class="layout">

  <!-- ── Sidebar: office list ── -->
  <div class="sidebar">
    <div class="sidebar-title">All Offices</div>
    <?php foreach ($allItems as $ai):
      $bc = match($ai['status']) {
        'approved' => 'bc',
        'rejected' => 'brj',
        'pending'  => ($ai['requirements_submitted'] ? 'bi' : 'bp'),
        default    => 'bn',
      };
      $bl = match($ai['status']) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'pending'  => ($ai['requirements_submitted'] ? 'Under Review' : 'Action Needed'),
        default    => 'Pending',
      };
      $icons = ['📋','📚','🔬','👨‍👩‍👧','🎓','🏛️','👩‍🏫','📖','💰'];
      $icon  = $icons[($ai['id'] - 1) % count($icons)];
      $active = ((int)$ai['id'] === $itemId) ? 'active' : '';
    ?>
    <a href="submit.php?item=<?= $ai['id'] ?>" class="office-item <?= $active ?>">
      <div class="office-icon"><?= $icon ?></div>
      <div class="office-name"><?= htmlspecialchars($ai['office_name']) ?></div>
      <span class="badge-sm <?= $bc ?>"><?= $bl ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- ── Main Panel ── -->
  <div class="main-card">
    <?php if (!$selectedItem): ?>
    <!-- No office selected -->
    <div class="empty-prompt">
      <div style="font-size:48px;margin-bottom:12px;">👈</div>
      <p style="font-size:16px;font-weight:600;margin-bottom:6px;">Select an Office</p>
      <p style="font-size:13px;">Click any office on the left to view requirements and submit documents.</p>
    </div>

    <?php else:
      $officeStatusClass = match($selectedItem['status']) {
        'approved' => 'bc', 'rejected' => 'brj', 'pending' => 'bp', default => 'bn'
      };
    ?>

    <!-- Office header -->
    <div class="card-top">
      <h2>📋 <?= htmlspecialchars($selectedItem['office_name']) ?></h2>
      <p><?= htmlspecialchars($selectedItem['office_desc'] ?? '') ?></p>
    </div>

    <div class="card-body">

      <!-- Flash message -->
      <?php if ($msg): ?>
      <div class="alert <?= $msgType ?>">
        <?= $msgType === 'success'
          ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
          : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.5"/></svg>'
        ?>
        <?= htmlspecialchars($msg) ?>
      </div>
      <?php endif; ?>

      <!-- Status info -->
      <?php if ($selectedItem['status'] === 'approved'): ?>
      <div class="alert success">
        ✅ This office has already <strong>approved</strong> your submission. No further action needed.
      </div>
      <?php elseif ($selectedItem['status'] === 'rejected'): ?>
      <div class="alert error">
        ❌ Your submission was <strong>rejected</strong>. Reason: <?= htmlspecialchars($selectedItem['remarks'] ?? 'No reason provided.') ?>
        <br>Please resubmit below.
      </div>
      <?php elseif ($selectedItem['requirements_submitted']): ?>
      <div class="alert info">
        ⏳ Your documents are submitted and <strong>under review</strong>. Check back later.
      </div>
      <?php endif; ?>

      <!-- Deadline notice -->
      <?php if ($selectedItem['deadline']): ?>
      <?php $daysLeft = (int)((strtotime($selectedItem['deadline']) - time()) / 86400); ?>
      <div class="alert <?= $daysLeft <= 3 ? 'error' : 'info' ?>" style="margin-bottom:20px;">
        📅 Deadline: <strong><?= date('F j, Y', strtotime($selectedItem['deadline'])) ?></strong>
        <?= $daysLeft >= 0 ? " · $daysLeft day(s) left" : " · <strong>OVERDUE</strong>" ?>
      </div>
      <?php endif; ?>

      <!-- Requirements List -->
      <?php if ($selectedItem['req_names']): ?>
      <div class="req-list">
        <h4>📌 Required Documents</h4>
        <?php
          $reqNames = explode('||', $selectedItem['req_names']);
          $reqDescs = explode('||', $selectedItem['req_descs'] ?? '');
          foreach ($reqNames as $i => $rn):
            if (!trim($rn)) continue;
        ?>
        <div class="req-item">
          <div class="req-dot"></div>
          <div>
            <div style="font-weight:600;"><?= htmlspecialchars(trim($rn)) ?></div>
            <?php if (!empty($reqDescs[$i])): ?>
            <div style="font-size:12px;color:var(--gray-400);margin-top:2px;"><?= htmlspecialchars(trim($reqDescs[$i])) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Previously submitted files -->
      <?php if (!empty($selectedFiles)): ?>
      <div style="margin-bottom:24px;">
        <h4 style="font-size:13px;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;">📎 Submitted Files</h4>
        <div class="files-list">
          <?php foreach ($selectedFiles as $f): ?>
          <div class="file-item">
            <span style="font-size:20px;"><?= $f['file_type'] === 'camera' ? '📷' : '📄' ?></span>
            <a href="../uploads/<?= htmlspecialchars($f['file_path']) ?>" target="_blank"><?= htmlspecialchars($f['original_name'] ?? $f['file_path']) ?></a>
            <span class="file-badge <?= $f['file_type'] ?>"><?= $f['file_type'] === 'camera' ? 'Camera' : 'File' ?></span>
            <span style="font-size:11px;color:var(--gray-400);"><?= date('M j, g:i A', strtotime($f['uploaded_at'])) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── Upload/Camera Form ── -->
      <?php if ($selectedItem['status'] !== 'approved'): ?>
      <div class="upload-section">
        <h3>Submit <?= htmlspecialchars($selectedItem['requirements_submitted'] ? 'Additional ' : '') ?>Document</h3>

        <!-- Tabs: File Upload vs Camera -->
        <div class="upload-tabs">
          <button type="button" class="upload-tab active" id="tabFile" onclick="switchTab('file')">
            📁 Upload File
          </button>
          <button type="button" class="upload-tab" id="tabCam" onclick="switchTab('camera')">
            📷 Take Photo
          </button>
        </div>

        <form method="POST" action="submit.php?item=<?= $itemId ?>" enctype="multipart/form-data" id="submitForm">
          <input type="hidden" name="camera_data" id="cameraData" value="">

          <!-- FILE UPLOAD TAB -->
          <div id="fileTab">
            <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" stroke="var(--gray-400)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <p>Click to browse, or drag & drop a file here</p>
              <span>JPG, PNG, PDF — max 5MB</span>
              <input type="file" id="fileInput" name="req_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" onchange="previewFile(this)">
            </div>

            <!-- File Preview -->
            <div id="filePreview" style="display:none;"></div>
          </div>

          <!-- CAMERA TAB -->
          <div id="cameraTab" style="display:none;">
            <div class="camera-section">
              <div class="video-wrap">
                <video id="cameraVideo" autoplay playsinline></video>
                <canvas id="cameraCanvas" style="display:none;"></canvas>
                <div class="camera-overlay" id="cameraOverlay">
                  <p>📷 Camera not started</p>
                </div>
              </div>
              <div class="camera-controls">
                <button type="button" class="btn-cam btn-cam-start"   id="btnStart"   onclick="startCamera()">Start Camera</button>
                <button type="button" class="btn-cam btn-cam-capture" id="btnCapture" onclick="capturePhoto()" style="display:none;">📸 Capture</button>
                <button type="button" class="btn-cam btn-cam-retake"  id="btnRetake"  onclick="retakePhoto()"  style="display:none;">🔄 Retake</button>
                <button type="button" class="btn-cam btn-cam-stop"    id="btnStop"    onclick="stopCamera()"   style="display:none;">Stop</button>
              </div>
              <!-- Camera capture preview -->
              <div id="capturePreview" style="display:none;"></div>
            </div>
          </div>

          <!-- Submit button (disabled until file/photo ready) -->
          <button type="submit" class="btn-submit" id="submitBtn" disabled>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Submit Document
          </button>
          <p style="text-align:center;font-size:12px;color:var(--gray-400);margin-top:8px;" id="submitHint">
            Please attach a file or take a photo first.
          </p>
        </form>
      </div>
      <?php endif; ?>

    </div><!-- /card-body -->
    <?php endif; ?>
  </div><!-- /main-card -->

</div><!-- /layout -->

<script>
let cameraStream = null;
let photoCaptured = false;
let filePicked    = false;

// ── Tab switching ─────────────────────────────────────────
function switchTab(tab) {
  document.getElementById('fileTab').style.display   = tab === 'file'   ? '' : 'none';
  document.getElementById('cameraTab').style.display = tab === 'camera' ? '' : 'none';
  document.getElementById('tabFile').classList.toggle('active', tab === 'file');
  document.getElementById('tabCam').classList.toggle('active', tab === 'camera');

  // Reset the other tab's state when switching
  if (tab === 'file') {
    stopCamera();
    photoCaptured = false;
    document.getElementById('cameraData').value = '';
  } else {
    clearFilePreview();
    filePicked = false;
    document.getElementById('fileInput').value = '';
  }
  updateSubmitBtn();
}

// ── Enable/disable submit button ──────────────────────────
function updateSubmitBtn() {
  const ready = filePicked || photoCaptured;
  const btn   = document.getElementById('submitBtn');
  const hint  = document.getElementById('submitHint');
  btn.disabled      = !ready;
  hint.style.display = ready ? 'none' : '';
}

// ── File upload: preview ──────────────────────────────────
function previewFile(input) {
  const file = input.files[0];
  if (!file) return;

  filePicked = true;
  const preview = document.getElementById('filePreview');
  preview.innerHTML = '';

  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = e => {
      preview.style.display = '';
      preview.innerHTML = `
        <div class="preview-box" style="margin-top:16px;">
          <span class="preview-label">✅ Preview</span>
          <button type="button" class="preview-remove" onclick="clearFilePreview()">✕ Remove</button>
          <img src="${e.target.result}" alt="Preview">
        </div>`;
    };
    reader.readAsDataURL(file);
  } else {
    // PDF — show filename
    preview.style.display = '';
    preview.innerHTML = `
      <div class="preview-box pdf-preview" style="margin-top:16px;">
        <span class="preview-label">✅ Ready</span>
        <button type="button" class="preview-remove" onclick="clearFilePreview()">✕ Remove</button>
        <div style="font-size:32px;margin-bottom:8px;">📄</div>
        <div style="font-weight:600;">${file.name}</div>
        <div style="font-size:12px;color:var(--gray-400);">${(file.size/1024).toFixed(1)} KB</div>
      </div>`;
  }
  updateSubmitBtn();
}

// Clear file selection
function clearFilePreview() {
  document.getElementById('fileInput').value = '';
  document.getElementById('filePreview').innerHTML = '';
  document.getElementById('filePreview').style.display = 'none';
  filePicked = false;
  updateSubmitBtn();
}

// Drag & drop support
const dropZone = document.getElementById('dropZone');
if (dropZone) {
  dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
  dropZone.addEventListener('dragleave', ()  => dropZone.classList.remove('drag-over'));
  dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    const dt = e.dataTransfer;
    if (dt.files.length > 0) {
      const fi = document.getElementById('fileInput');
      // Use DataTransfer to set files
      const container = new DataTransfer();
      container.items.add(dt.files[0]);
      fi.files = container.files;
      previewFile(fi);
    }
  });
}

// ── Camera: start ─────────────────────────────────────────
async function startCamera() {
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    const video = document.getElementById('cameraVideo');
    video.srcObject = cameraStream;
    document.getElementById('cameraOverlay').style.display = 'none';
    document.getElementById('btnStart').style.display   = 'none';
    document.getElementById('btnCapture').style.display = '';
    document.getElementById('btnStop').style.display    = '';
  } catch (err) {
    alert('Could not access camera. Please allow camera permission or use file upload instead.');
  }
}

// ── Camera: capture ───────────────────────────────────────
function capturePhoto() {
  const video  = document.getElementById('cameraVideo');
  const canvas = document.getElementById('cameraCanvas');
  canvas.width  = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext('2d').drawImage(video, 0, 0);

  const dataURL = canvas.toDataURL('image/jpeg', 0.9);
  document.getElementById('cameraData').value = dataURL;

  // Show preview
  const preview = document.getElementById('capturePreview');
  preview.style.display = '';
  preview.innerHTML = `
    <div class="preview-box" style="margin-top:16px;">
      <span class="preview-label">📷 Captured</span>
      <button type="button" class="preview-remove" onclick="retakePhoto()">✕ Retake</button>
      <img src="${dataURL}" alt="Captured photo">
    </div>`;

  photoCaptured = true;
  document.getElementById('btnCapture').style.display = 'none';
  document.getElementById('btnRetake').style.display  = '';
  stopCamera();
  updateSubmitBtn();
}

// ── Camera: retake ────────────────────────────────────────
function retakePhoto() {
  photoCaptured = false;
  document.getElementById('cameraData').value = '';
  document.getElementById('capturePreview').innerHTML = '';
  document.getElementById('capturePreview').style.display = 'none';
  document.getElementById('btnRetake').style.display  = 'none';
  document.getElementById('btnCapture').style.display = '';
  updateSubmitBtn();
  startCamera(); // restart camera
}

// ── Camera: stop ──────────────────────────────────────────
function stopCamera() {
  if (cameraStream) {
    cameraStream.getTracks().forEach(t => t.stop());
    cameraStream = null;
  }
  document.getElementById('cameraVideo').srcObject = null;
  document.getElementById('cameraOverlay').style.display = '';
  document.getElementById('btnStart').style.display   = '';
  document.getElementById('btnCapture').style.display = 'none';
  document.getElementById('btnStop').style.display    = 'none';
}

// Stop camera when leaving the page
window.addEventListener('beforeunload', stopCamera);

// ── Disable submit on form submission (prevent double click) ──
document.getElementById('submitForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<div style="width:18px;height:18px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;"></div> Uploading...';
});

const s = document.createElement('style');
s.textContent = `@keyframes spin{to{transform:rotate(360deg)}}`;
document.head.appendChild(s);
</script>
</body>
</html>
