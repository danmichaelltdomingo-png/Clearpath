<?php
// includes/header.php — shared top nav for all dashboard pages
$user = currentUser();
$roleLabels = ['admin' => 'Administrator', 'signatory' => 'Signatory', 'student' => 'Student'];
$roleLabel  = $roleLabels[$user['role']] ?? ucfirst($user['role']);
$basePath   = APP_URL;

// Nav links per role
$navLinks = [];
if ($user['role'] === 'admin') {
    $navLinks = [
        ['href' => "$basePath/admin/dashboard.php",   'label' => 'Dashboard',   'icon' => 'grid'],
        ['href' => "$basePath/admin/students.php",    'label' => 'Students',    'icon' => 'users'],
        ['href' => "$basePath/admin/clearances.php",  'label' => 'Clearances',  'icon' => 'file-check'],
        ['href' => "$basePath/admin/signatories.php", 'label' => 'Signatories', 'icon' => 'pen-tool'],
        ['href' => "$basePath/admin/offices.php",     'label' => 'Offices',     'icon' => 'briefcase'],
        ['href' => "$basePath/admin/logs.php",        'label' => 'Logs',        'icon' => 'activity'],
    ];
} elseif ($user['role'] === 'signatory') {
    $navLinks = [
        ['href' => "$basePath/signatory/dashboard.php", 'label' => 'Dashboard', 'icon' => 'grid'],
        ['href' => "$basePath/signatory/requests.php",  'label' => 'Requests',  'icon' => 'inbox'],
        ['href' => "$basePath/signatory/requirements.php", 'label' => 'Requirements', 'icon' => 'list'],
    ];
} else {
    $navLinks = [
        ['href' => "$basePath/student/dashboard.php",  'label' => 'Dashboard',  'icon' => 'grid'],
        ['href' => "$basePath/student/clearance.php",  'label' => 'My Clearance','icon' => 'file-check'],
        ['href' => "$basePath/student/submit.php",     'label' => 'Submit Docs', 'icon' => 'upload'],
        ['href' => "$basePath/student/history.php",    'label' => 'History',    'icon' => 'clock'],
    ];
}

// Add Settings link for all roles
$settingsLink = "$basePath/includes/settings.php";
$navLinks[] = ['href' => $settingsLink, 'label' => 'Settings', 'icon' => 'settings'];

$icons = [
    'grid'       => '<path d="M3 3h4v4H3zM9 3h4v4H9zM3 9h4v4H3zM9 9h4v4H9z" stroke="currentColor" stroke-width="1.4" fill="none" stroke-linejoin="round"/>',
    'users'      => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>',
    'file-check' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linejoin="round"/><path d="M14 2v6h6M9 15l2 2 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    'pen-tool'   => '<path d="M12 19l7-7 3 3-7 7-3-3z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linejoin="round"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linejoin="round"/><path d="M2 2l7.586 7.586" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="11" cy="11" r="2" stroke="currentColor" stroke-width="1.5" fill="none"/>',
    'briefcase'  => '<rect x="2" y="7" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2M12 12v.01" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>',
    'activity'   => '<path d="M22 12h-4l-3 9L9 3l-3 9H2" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    'inbox'      => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linejoin="round"/>',
    'list'       => '<line x1="8" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="8" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="8" y1="18" x2="21" y2="18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="3" y1="6" x2="3.01" y2="6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="3" y1="12" x2="3.01" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="3" y1="18" x2="3.01" y2="18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
    'upload'     => '<polyline points="16 16 12 12 8 16" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="12" x2="12" y2="21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>',
    'clock'      => '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>',
    'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    'bell'       => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    'settings'   => '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" stroke="currentColor" stroke-width="1.5" fill="none"/>',
];

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'Dashboard' ?> — ClearPath BPC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --blue-900: #0f1f5c; --blue-800: #1a2f7a; --blue-700: #1e3a9e;
    --blue-600: #2046c4; --blue-500: #2d55d4; --blue-400: #4f73e0;
    --blue-100: #dce6ff; --blue-50: #eef2ff;
    --white: #ffffff; --gray-50: #f8f9fc; --gray-100: #eef0f6;
    --gray-200: #d8dbe8; --gray-300: #b8bdd4; --gray-400: #8b91ae;
    --gray-500: #636880; --gray-600: #4a5070; --gray-700: #363b58;
    --gray-800: #1e2240; --gray-900: #12162e;
    --success: #059669; --success-bg: #d1fae5; --success-border: #6ee7b7;
    --warning: #d97706; --warning-bg: #fef3c7; --warning-border: #fcd34d;
    --error: #dc2626;   --error-bg: #fee2e2;   --error-border: #fca5a5;
    --sidebar-w: 240px;
    --header-h: 64px;
    --shadow-sm: 0 1px 4px rgba(15,31,92,0.08);
    --shadow-md: 0 4px 16px rgba(15,31,92,0.12);
    --shadow-lg: 0 8px 32px rgba(15,31,92,0.16);
  }

  html, body { height: 100%; font-family: 'DM Sans', sans-serif; color: var(--gray-800); background: var(--gray-50); }

  /* LAYOUT */
  .app-layout { display: flex; min-height: 100vh; }

  /* SIDEBAR */
  .sidebar {
    width: var(--sidebar-w); flex-shrink: 0;
    background: var(--blue-900);
    display: flex; flex-direction: column;
    position: fixed; top: 0; left: 0; bottom: 0;
    z-index: 100;
  }
  .sidebar-brand {
    padding: 24px 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    display: flex; align-items: center; gap: 12px;
  }
  .sidebar-logo {
    width: 36px; height: 36px;
    background: rgba(255,255,255,0.12);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Sora', sans-serif; font-weight: 800; font-size: 12px;
    color: #fff; border: 1px solid rgba(255,255,255,0.18);
  }
  .sidebar-brand-text h3 {
    font-family: 'Sora', sans-serif; font-weight: 700; font-size: 15px;
    color: #fff; letter-spacing: -0.3px;
  }
  .sidebar-brand-text p { font-size: 11px; color: rgba(255,255,255,0.45); font-weight: 300; }

  .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
  .nav-section-label {
    font-size: 10px; font-weight: 500; text-transform: uppercase;
    letter-spacing: 1px; color: rgba(255,255,255,0.3);
    padding: 0 8px; margin: 16px 0 8px;
  }
  .nav-link {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 8px;
    color: rgba(255,255,255,0.65); font-size: 13.5px; font-weight: 400;
    text-decoration: none; transition: all 0.15s;
    margin-bottom: 2px;
  }
  .nav-link svg { width: 17px; height: 17px; flex-shrink: 0; }
  .nav-link:hover { background: rgba(255,255,255,0.08); color: #fff; }
  .nav-link.active { background: rgba(255,255,255,0.14); color: #fff; font-weight: 500; }
  .nav-link.active::before {
    content: ''; position: absolute; left: 0; width: 3px; height: 28px;
    background: var(--blue-400); border-radius: 0 3px 3px 0;
    margin-top: -0px; /* offset */
  }
  .nav-link { position: relative; }

  .sidebar-user {
    padding: 16px 12px;
    border-top: 1px solid rgba(255,255,255,0.08);
  }
  .sidebar-user-card {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 10px;
    background: rgba(255,255,255,0.06);
  }
  .user-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--blue-500);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px; color: #fff; flex-shrink: 0;
  }
  .user-info { flex: 1; min-width: 0; }
  .user-name { font-size: 12.5px; font-weight: 500; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .user-role { font-size: 10.5px; color: rgba(255,255,255,0.45); }
  .logout-btn {
    width: 28px; height: 28px; border-radius: 7px;
    background: rgba(255,255,255,0.08); border: none;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: rgba(255,255,255,0.5);
    text-decoration: none; transition: all 0.15s; flex-shrink: 0;
  }
  .logout-btn:hover { background: rgba(220,38,38,0.3); color: #fca5a5; }
  .logout-btn svg { width: 14px; height: 14px; }

  /* MAIN CONTENT */
  .main-content { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }

  .top-bar {
    height: var(--header-h); background: var(--white);
    border-bottom: 1px solid var(--gray-100);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 32px;
    position: sticky; top: 0; z-index: 50;
    box-shadow: var(--shadow-sm);
  }
  .top-bar-left h1 {
    font-family: 'Sora', sans-serif; font-size: 18px; font-weight: 700;
    color: var(--gray-800); letter-spacing: -0.4px;
  }
  .top-bar-left p { font-size: 12px; color: var(--gray-400); margin-top: 1px; }
  .top-bar-right { display: flex; align-items: center; gap: 12px; }

  .notification-btn {
    width: 36px; height: 36px; border-radius: 9px;
    background: var(--gray-50); border: 1px solid var(--gray-100);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--gray-500); transition: all 0.15s;
    position: relative;
  }
  .notification-btn svg { width: 18px; height: 18px; }
  .notification-btn:hover { background: var(--blue-50); color: var(--blue-600); }
  .notif-badge {
    position: absolute; top: 5px; right: 5px;
    width: 8px; height: 8px; background: var(--error);
    border-radius: 50%; border: 2px solid var(--white);
  }

  .page-content { flex: 1; padding: 32px; }

  /* CARDS */
  .card {
    background: var(--white); border-radius: 14px;
    border: 1px solid var(--gray-100);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
  }
  .card-header {
    padding: 20px 24px; border-bottom: 1px solid var(--gray-100);
    display: flex; align-items: center; justify-content: space-between;
  }
  .card-header h3 {
    font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 600;
    color: var(--gray-800); letter-spacing: -0.2px;
  }
  .card-header p { font-size: 12px; color: var(--gray-400); margin-top: 2px; }
  .card-body { padding: 24px; }

  /* STAT CARDS */
  .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
  .stat-card {
    background: var(--white); border-radius: 14px;
    padding: 20px 22px; border: 1px solid var(--gray-100);
    box-shadow: var(--shadow-sm); transition: transform 0.2s;
  }
  .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
  .stat-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 14px;
  }
  .stat-icon svg { width: 20px; height: 20px; }
  .stat-icon.blue  { background: var(--blue-50);    color: var(--blue-600); }
  .stat-icon.green { background: var(--success-bg); color: var(--success); }
  .stat-icon.amber { background: var(--warning-bg); color: var(--warning); }
  .stat-icon.red   { background: var(--error-bg);   color: var(--error); }
  .stat-value { font-family: 'Sora', sans-serif; font-size: 30px; font-weight: 700; color: var(--gray-800); letter-spacing: -1px; }
  .stat-label { font-size: 12.5px; color: var(--gray-400); margin-top: 4px; }

  /* BADGE / STATUS */
  .badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 500;
  }
  .badge-dot { width: 6px; height: 6px; border-radius: 50%; }
  .badge.pending   { background: var(--warning-bg); color: var(--warning); }
  .badge.approved  { background: var(--success-bg); color: var(--success); }
  .badge.rejected  { background: var(--error-bg);   color: var(--error); }
  .badge.cleared   { background: var(--blue-50);    color: var(--blue-600); }
  .badge.in_progress { background: #ede9fe; color: #7c3aed; }

  /* TABLE */
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  thead th {
    text-align: left; padding: 10px 16px;
    font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px;
    color: var(--gray-400); background: var(--gray-50);
    border-bottom: 1px solid var(--gray-100);
  }
  tbody td {
    padding: 13px 16px; font-size: 13.5px; color: var(--gray-700);
    border-bottom: 1px solid var(--gray-100);
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background: var(--gray-50); }

  /* BTN */
  .btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500;
    border: none; cursor: pointer; text-decoration: none; transition: all 0.15s;
    font-family: 'DM Sans', sans-serif;
  }
  .btn svg { width: 15px; height: 15px; }
  .btn-primary { background: var(--blue-600); color: #fff; }
  .btn-primary:hover { background: var(--blue-700); }
  .btn-success { background: var(--success); color: #fff; }
  .btn-success:hover { background: #047857; }
  .btn-danger { background: var(--error); color: #fff; }
  .btn-danger:hover { background: #b91c1c; }
  .btn-outline {
    background: transparent; color: var(--gray-600);
    border: 1px solid var(--gray-200);
  }
  .btn-outline:hover { background: var(--gray-50); }
  .btn-sm { padding: 5px 10px; font-size: 12px; }

  /* FORM */
  .form-group { margin-bottom: 18px; }
  .form-label { display: block; font-size: 13px; font-weight: 500; color: var(--gray-600); margin-bottom: 7px; }
  .form-control {
    width: 100%; padding: 10px 14px;
    border: 1.5px solid var(--gray-200); border-radius: 9px;
    font-size: 14px; font-family: 'DM Sans', sans-serif;
    color: var(--gray-800); background: var(--gray-50);
    transition: all 0.15s; outline: none;
  }
  .form-control:focus { border-color: var(--blue-500); background: #fff; box-shadow: 0 0 0 3px rgba(45,85,212,0.1); }
  select.form-control { cursor: pointer; }

  /* ALERT */
  .alert { padding: 12px 16px; border-radius: 9px; font-size: 13.5px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
  .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }
  .alert-error   { background: var(--error-bg);   color: var(--error);   border: 1px solid var(--error-border); }
  .alert-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning-border); }

  /* Grid helpers */
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
  .mb-6 { margin-bottom: 24px; }
  .mb-4 { margin-bottom: 16px; }
  .flex { display: flex; }
  .items-center { align-items: center; }
  .justify-between { justify-content: space-between; }
  .gap-3 { gap: 12px; }
  .text-sm { font-size: 12.5px; }
  .text-muted { color: var(--gray-400); }

  @media (max-width: 900px) {
    .sidebar { transform: translateX(-100%); }
    .main-content { margin-left: 0; }
    .grid-2, .grid-3 { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="app-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-logo">CP</div>
      <div class="sidebar-brand-text">
        <h3>ClearPath</h3>
        <p>BPC Clearance</p>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Menu</div>
      <?php foreach ($navLinks as $link):
        $isSettings = ($link['icon'] === 'settings');
        $active     = (basename($_SERVER['PHP_SELF']) === basename($link['href'])) ? 'active' : '';
        $iconSvg    = $icons[$link['icon']] ?? '';
        if ($isSettings): ?>
          <div style="border-top:1px solid rgba(255,255,255,0.08);margin:10px 0"></div>
          <div class="nav-section-label">Account</div>
        <?php endif; ?>
        <a href="<?= $link['href'] ?>" class="nav-link <?= $active ?>">
          <svg viewBox="0 0 24 24" fill="none"><?= $iconSvg ?></svg>
          <?= $link['label'] ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
      <div class="sidebar-user-card">
        <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="user-role"><?= $roleLabel ?></div>
        </div>
        <a href="<?= $basePath ?>/logout.php" class="logout-btn" title="Logout">
          <svg viewBox="0 0 24 24" fill="none"><?= $icons['logout'] ?></svg>
        </a>
      </div>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <div class="top-bar">
      <div class="top-bar-left">
        <h1><?= $pageTitle ?? 'Dashboard' ?></h1>
        <p><?= $pageSubtitle ?? date('l, F j, Y') ?></p>
      </div>
      <div class="top-bar-right">
        <div class="notification-btn" title="Notifications">
          <svg viewBox="0 0 24 24" fill="none"><?= $icons['bell'] ?></svg>
          <div class="notif-badge"></div>
        </div>
        <div style="font-size:12px; color:var(--gray-400);">
          <?= htmlspecialchars($user['role'] === 'signatory' ? $user['office'] : ($user['student_id'] ?: '')) ?>
        </div>
      </div>
    </div>
    <div class="page-content">
