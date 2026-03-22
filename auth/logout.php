<?php
session_start();

/* ========================= */
/* GET ROLE BEFORE DESTROY */
/* ========================= */
$role = $_SESSION['role'] ?? null;

/* ========================= */
/* UNSET SESSION */
/* ========================= */
$_SESSION = [];

/* ========================= */
/* DELETE SESSION COOKIE */
/* ========================= */
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

/* ========================= */
/* DESTROY SESSION */
/* ========================= */
session_destroy();

/* ========================= */
/* REDIRECT BASED ON ROLE */
/* ========================= */
if ($role === 'admin') {
    header("Location: ../admin/index.php");
} elseif ($role === 'student') {
    header("Location: ../auth/student_login.php");
} else {
    header("Location: ../index.php");
}

exit();