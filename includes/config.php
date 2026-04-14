<?php
// BPC ClearPath - Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bpc_clearpath');
define('APP_NAME', 'ClearPath');
define('APP_SUBTITLE', 'BPC Digital School Clearance');
define('APP_URL', 'http://localhost/clearpath');

// Create DB connection
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth helpers
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role && $_SESSION['role'] !== 'admin') {
        header('Location: ' . APP_URL . '/index.php?error=unauthorized');
        exit;
    }
}

function requireRoleIn($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: ' . APP_URL . '/index.php?error=unauthorized');
        exit;
    }
}

function currentUser() {
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'name'      => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? '',
        'email'     => $_SESSION['email'] ?? '',
        'office'    => $_SESSION['office'] ?? '',
        'student_id'=> $_SESSION['student_id'] ?? '',
    ];
}

function logActivity($action, $description = '') {
    if (!isLoggedIn()) return;
    $db = getDB();
    $uid = (int)$_SESSION['user_id'];
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?,?,?,?)");
    $stmt->bind_param('isss', $uid, $action, $description, $ip);
    $stmt->execute();
    $db->close();
}

date_default_timezone_set('Asia/Manila');

?>
