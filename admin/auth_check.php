<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 Session timeout (15 minutes)
$timeout = 900;

if (isset($_SESSION['last_activity']) && 
    (time() - $_SESSION['last_activity']) > $timeout) {

    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

$_SESSION['last_activity'] = time();

// 🧱 Check if logged in as admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>