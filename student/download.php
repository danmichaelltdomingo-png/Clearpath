<?php
require_once '../includes/config.php';
requireLogin();
$db   = getDB();
$user = currentUser();
$uid  = (int)$user['id'];
$rid  = (int)($_GET['id'] ?? 0);

// Admin can view any; student only their own
if ($user['role'] === 'student') {
    $req = $db->query("SELECT cr.*, u.full_name, u.student_id AS sid, u.email FROM clearance_requests cr JOIN users u ON u.id=cr.student_id WHERE cr.id=$rid AND cr.student_id=$uid AND cr.status='cleared' LIMIT 1")->fetch_assoc();
} else {
    $req = $db->query("SELECT cr.*, u.full_name, u.student_id AS sid, u.email FROM clearance_requests cr JOIN users u ON u.id=cr.student_id WHERE cr.id=$rid LIMIT 1")->fetch_assoc();
}

if (!$req) {
    die('<div style="text-align:center;padding:60px;font-family:sans-serif"><h2>Clearance not found or not yet cleared.</h2><a href="javascript:history.back()">Go Back</a></div>');
}

$items = $db->query("
    SELECT ci.*, o.name AS office_name, u.full_name AS signatory_name
    FROM clearance_items ci
    JOIN offices o ON o.id=ci.office_id
    LEFT JOIN users u ON u.id=ci.signatory_id
    WHERE ci.clearance_request_id=$rid
    ORDER BY o.sort_order
")->fetch_all(MYSQLI_ASSOC);

$qrData = 'BPC-CLEARPATH-' . $rid . '-' . strtoupper(md5($rid . $req['sid']));
logActivity('DOWNLOAD_PDF', "Downloaded clearance PDF for request ID $rid");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Clearance Certificate — <?= htmlspecialchars($req['full_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DM Sans', sans-serif; background: #f0f4ff; min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 40px 20px; }

  .print-controls {
    display: flex; gap: 12px; margin-bottom: 24px;
  }
  .btn-print { padding: 10px 24px; background: #1e3a9e; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
  .btn-back { padding: 10px 24px; background: #fff; color: #4a5070; border: 1px solid #d8dbe8; border-radius: 8px; font-size: 14px; cursor: pointer; font-family: 'DM Sans', sans-serif; text-decoration: none; display: inline-flex; align-items: center; }

  .certificate {
    width: 794px; background: #fff;
    box-shadow: 0 8px 40px rgba(15,31,92,0.15);
    border-radius: 4px; overflow: hidden;
  }

  .cert-header {
    background: linear-gradient(135deg, #0f1f5c, #1e3a9e);
    color: #fff; padding: 32px 40px;
    display: flex; align-items: center; justify-content: space-between;
  }
  .cert-header-left { display: flex; align-items: center; gap: 16px; }
  .school-logo {
    width: 64px; height: 64px; background: rgba(255,255,255,0.15);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-family: 'Sora', sans-serif; font-weight: 800; font-size: 16px; color: #fff;
    border: 2px solid rgba(255,255,255,0.3);
  }
  .school-info h1 { font-family: 'Sora', sans-serif; font-size: 18px; font-weight: 800; letter-spacing: -0.3px; }
  .school-info p { font-size: 12px; color: rgba(255,255,255,0.7); margin-top: 2px; }
  .cert-title { text-align: right; }
  .cert-title h2 { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
  .cert-title p { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 2px; }

  .cert-body { padding: 32px 40px; }

  .info-section {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 0; border: 1.5px solid #e0e4f0; border-radius: 10px;
    overflow: hidden; margin-bottom: 24px;
  }
  .info-row { display: contents; }
  .info-label {
    background: #f5f7ff; padding: 10px 16px;
    font-size: 11px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.5px; color: #8b91ae;
    border-bottom: 1px solid #e0e4f0;
  }
  .info-value {
    background: #fff; padding: 10px 16px;
    font-size: 13.5px; font-weight: 500; color: #1e2240;
    border-bottom: 1px solid #e0e4f0;
  }

  .offices-title {
    font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 700;
    color: #1e2240; text-transform: uppercase; letter-spacing: 0.8px;
    margin-bottom: 12px; padding-bottom: 8px;
    border-bottom: 2px solid #1e3a9e;
  }

  .offices-grid {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 10px; margin-bottom: 28px;
  }
  .office-item {
    border: 1.5px solid #e0e4f0; border-radius: 8px;
    padding: 12px 14px;
  }
  .office-item.approved { border-color: #6ee7b7; background: #f0fdf9; }
  .office-item.rejected { border-color: #fca5a5; background: #fff5f5; }
  .office-name { font-size: 12px; font-weight: 600; color: #1e2240; margin-bottom: 4px; }
  .office-sig { font-size: 10.5px; color: #8b91ae; margin-bottom: 6px; }
  .office-status {
    display: inline-block; padding: 2px 8px; border-radius: 20px;
    font-size: 10px; font-weight: 600;
  }
  .office-status.approved { background: #d1fae5; color: #059669; }
  .office-status.rejected { background: #fee2e2; color: #dc2626; }
  .office-status.pending  { background: #fef3c7; color: #d97706; }

  .cert-footer {
    margin-top: 24px; padding-top: 20px;
    border-top: 1.5px dashed #e0e4f0;
    display: flex; justify-content: space-between; align-items: flex-end;
  }
  .qr-section { text-align: center; }
  .qr-box {
    width: 80px; height: 80px; border: 1.5px solid #e0e4f0;
    border-radius: 8px; display: flex; align-items: center;
    justify-content: center; background: #f5f7ff;
    font-size: 9px; color: #8b91ae; text-align: center; padding: 6px;
    font-family: monospace;
  }
  .qr-label { font-size: 9px; color: #8b91ae; margin-top: 4px; }

  .registrar-sig { text-align: center; }
  .sig-line { width: 180px; border-top: 1.5px solid #1e2240; padding-top: 6px; margin-top: 40px; }
  .sig-name { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 700; color: #1e2240; }
  .sig-title { font-size: 11px; color: #8b91ae; }

  .cleared-stamp {
    position: absolute; right: 40px; top: 50%;
    transform: translateY(-50%) rotate(-15deg);
    border: 4px solid rgba(5,150,105,0.5); border-radius: 8px;
    padding: 8px 16px; color: rgba(5,150,105,0.6);
    font-family: 'Sora', sans-serif; font-weight: 800;
    font-size: 24px; letter-spacing: 3px; text-transform: uppercase;
    pointer-events: none;
  }

  .cert-notice {
    background: #f5f7ff; border-radius: 8px; padding: 12px 16px;
    font-size: 11px; color: #8b91ae; margin-bottom: 20px;
    border: 1px solid #e0e4f0;
  }
  .cert-notice strong { color: #4a5070; }

  @media print {
    body { background: #fff; padding: 0; }
    .print-controls { display: none; }
    .certificate { box-shadow: none; width: 100%; }
  }
</style>
</head>
<body>

<div class="print-controls">
  <a href="javascript:history.back()" class="btn-back">← Back</a>
  <button onclick="window.print()" class="btn-print">🖨 Print / Save PDF</button>
</div>

<div class="certificate">
  <!-- Header -->
  <div class="cert-header">
    <div class="cert-header-left">
      <div class="school-logo">BPC</div>
      <div class="school-info">
        <h1>Bestlink College of the Philippines</h1>
        <p>Rodolfo N. Pelaez Blvd., Sampaloc, Manila</p>
        <p style="margin-top:1px;font-size:11px;color:rgba(255,255,255,0.5)">ClearPath Digital Clearance System</p>
      </div>
    </div>
    <div class="cert-title">
      <h2>CLEARANCE CERTIFICATE</h2>
      <p>School Year <?= $req['school_year'] ?> | <?= $req['semester'] ?> Semester</p>
      <p style="margin-top:4px;font-size:10px">Ref. No.: BPC-<?= str_pad($rid, 6, '0', STR_PAD_LEFT) ?></p>
    </div>
  </div>

  <!-- Body -->
  <div class="cert-body">

    <div class="cert-notice">
      <strong>OFFICIAL DOCUMENT</strong> — This clearance certificate is issued by the BPC Registrar's Office through the ClearPath digital system.
      Verify authenticity using the QR code or reference number below.
    </div>

    <!-- Student Info -->
    <div class="info-section">
      <div class="info-label">Full Name</div>
      <div class="info-value"><?= htmlspecialchars($req['full_name']) ?></div>
      <div class="info-label">Student ID</div>
      <div class="info-value"><?= htmlspecialchars($req['sid']) ?></div>
      <div class="info-label">Email Address</div>
      <div class="info-value"><?= htmlspecialchars($req['email']) ?></div>
      <div class="info-label">School Year</div>
      <div class="info-value"><?= $req['school_year'] ?></div>
      <div class="info-label">Semester</div>
      <div class="info-value"><?= $req['semester'] ?></div>
      <div class="info-label">Date Cleared</div>
      <div class="info-value"><?= date('F j, Y', strtotime($req['updated_at'])) ?></div>
    </div>

    <!-- Offices -->
    <div class="offices-title">Office Clearance Summary</div>
    <div class="offices-grid">
      <?php foreach ($items as $item): ?>
      <div class="office-item <?= $item['status'] ?>">
        <div class="office-name"><?= htmlspecialchars($item['office_name']) ?></div>
        <div class="office-sig"><?= $item['signatory_name'] ? htmlspecialchars($item['signatory_name']) : 'Office Signatory' ?></div>
        <span class="office-status <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="cert-footer">
      <div class="qr-section">
        <div class="qr-box"><?= wordwrap($qrData, 12, "\n", true) ?></div>
        <div class="qr-label">Scan to verify</div>
      </div>

      <div style="font-size:11px;color:#8b91ae;text-align:center;max-width:200px">
        This document is computer-generated and is valid without a physical signature when verified via QR code.
      </div>

      <div class="registrar-sig">
        <div class="sig-line">
          <div class="sig-name">The Registrar</div>
          <div class="sig-title">BPC Registrar's Office</div>
          <div style="font-size:10px;color:#8b91ae;margin-top:2px">Verified via ClearPath System</div>
        </div>
      </div>
    </div>

  </div>
</div>

</body>
</html>
