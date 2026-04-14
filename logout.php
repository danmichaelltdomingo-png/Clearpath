<?php
require_once 'includes/config.php';
if (isLoggedIn()) {
    logActivity('LOGOUT', 'User logged out');
}
session_destroy();
header('Location: ' . APP_URL . '/index.php?msg=logged_out');
exit;
