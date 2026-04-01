<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$timeout = 900;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /library-management-system/admin/index.php");
    exit();
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

if ((time() - $_SESSION['last_activity']) > $timeout) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
    header("Location: /library-management-system/admin/index.php?expired=1");
    exit();
}

$_SESSION['last_activity'] = time();
?>