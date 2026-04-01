<?php
session_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/student_login.php");
    exit();
}

$currentPage = $currentPage ?? '';
$studentName = $_SESSION['fullname'] ?? 'Student';
$studentId   = $_SESSION['student_id'] ?? '—';

function navClass($page, $currentPage) {
    return $page === $currentPage
        ? 'text-blue-600 border-b-2 border-blue-600'
        : 'text-gray-700 hover:text-blue-600';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal</title>
    <link href="/library-management-system/assets/css/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<!-- TOP HEADER -->
<header class="fixed top-0 left-0 right-0 bg-white border-b border-gray-200 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex items-center justify-between h-20">
            
            <!-- LOGO / TITLE -->
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center text-white text-2xl font-bold">
                    📖
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 leading-tight">STI College Ormoc</h1>
                    <p class="text-slate-500 text-sm">Library Management System</p>
                </div>
            </div>

            <!-- USER / LOGOUT -->
            <div class="flex items-center gap-4">
                <div class="bg-gray-100 rounded-2xl px-4 py-3 flex items-center gap-3">
                    <div class="text-gray-600 text-lg">👤</div>
                    <div class="leading-tight">
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-sm text-slate-500"><?= htmlspecialchars($studentId, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>

                <a href="../auth/logout.php"
                   class="border border-gray-300 rounded-2xl px-6 py-3 font-semibold text-slate-900 hover:bg-gray-50">
                    <span>↩</span>Logout
                </a>
            </div>
        </div>
    </div>

    <!-- NAVBAR -->
    <div class="border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-6">
            <nav class="flex items-center gap-8 h-12">
                <a href="student_dashboard.php"
                class="pb-3 pt-3 font-medium border-b-2 <?= $currentPage === 'student_dashboard' ? 'text-blue-600 border-blue-600' : 'text-gray-700 border-transparent hover:text-blue-600' ?>">
                     Dashboard
                </a>

                <a href="book_catalog.php"
                class="pb-3 pt-3 font-medium border-b-2 <?= $currentPage === 'book_catalog' ? 'text-blue-600 border-blue-600' : 'text-gray-700 border-transparent hover:text-blue-600' ?>">
                     Book Catalog
                </a>

                <a href="my_borrowings.php"
                class="pb-3 pt-3 font-medium border-b-2 <?= $currentPage === 'my_borrowings' ? 'text-blue-600 border-blue-600' : 'text-gray-700 border-transparent hover:text-blue-600' ?>">
                     My Borrowings
                </a>

                <a href="reservations.php"
                class="pb-3 pt-3 font-medium border-b-2 <?= $currentPage === 'reservations' ? 'text-blue-600 border-blue-600' : 'text-gray-700 border-transparent hover:text-blue-600' ?>">
                     Reservations
                </a>
            </nav>
        </div>
    </div>
</header>