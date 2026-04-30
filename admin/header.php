<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/library_helpers.php';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$currentPage = $currentPage ?? '';

/* ================= DATABASE ================= */
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=sti_library;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    if (function_exists('setLibraryDbTimezone')) {
        setLibraryDbTimezone($pdo);
    }
} catch (PDOException $e) {
    die("Database error.");
}

$adminName = $_SESSION['fullname'] ?? 'Admin';
$adminEmail = $_SESSION['email'] ?? 'admin';
$adminId = $_SESSION['user_id'] ?? null;
$adminProfileImage = null;

/* ================= CURRENT ADMIN PROFILE DATA ================= */
if ($adminId) {
    try {
        $adminStmt = $pdo->prepare("
            SELECT fullname, email, profile_image
            FROM users
            WHERE id = ? AND role = 'admin'
            LIMIT 1
        ");
        $adminStmt->execute([$adminId]);
        $adminData = $adminStmt->fetch(PDO::FETCH_ASSOC);

        if ($adminData) {
            $adminName = $adminData['fullname'] ?: $adminName;
            $adminEmail = $adminData['email'] ?: $adminEmail;

            if (!empty($adminData['profile_image'])) {
                $adminProfileImage = "../uploads/profile_images/" . $adminData['profile_image'];
            }

            $_SESSION['fullname'] = $adminName;
            $_SESSION['email'] = $adminEmail;
            $_SESSION['profile_image'] = $adminData['profile_image'] ?? null;
        }
    } catch (PDOException $e) {
        // Keep fallback session values if query fails.
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - STI Library</title>
    <link href="/library-management-system/assets/css/output.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

<header class="fixed top-0 left-0 right-0 bg-white border-b border-gray-200 z-50 shadow-sm">
    <div class="max-w-[1489px] mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-20 gap-3">
            <!-- LEFT: LOGO -->
            <div class="flex items-center gap-3 min-w-0">
                <img 
                    src="/library-management-system/assets/images/logo1.png"
                    alt="STI Logo"
                    class="h-10 sm:h-12 w-auto object-contain shrink-0"
                >

                <div class="min-w-0">
                    <h1 class="text-lg sm:text-2xl font-bold text-slate-900 leading-tight truncate">
                        STI College Ormoc
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm truncate">
                        Library Admin Portal
                    </p>
                </div>
            </div>

            <!-- RIGHT: ACTIONS -->
            <div class="flex items-center gap-2 sm:gap-4 shrink-0">
                <!-- PROFILE DESKTOP -->
                <div class="relative hidden sm:block">
                    <button 
                        id="profileDropdownBtn"
                        type="button"
                        class="rounded-2xl px-3 sm:px-4 py-3 flex items-center gap-3 hover:bg-purple-100 transition min-w-[220px] lg:min-w-[270px] max-w-[300px]"
                    >
                        <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full overflow-hidden bg-slate-200 border border-slate-300 shrink-0 flex items-center justify-center">
                            <?php if (!empty($adminProfileImage)): ?>
                                <img src="<?= e($adminProfileImage) ?>" alt="Profile" class="w-full h-full object-cover">
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-slate-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                                </svg>
                            <?php endif; ?>
                        </div>

                        <div class="leading-tight text-left min-w-0 flex-1">
                            <p class="font-semibold text-slate-900 truncate">
                                <?= e($adminName) ?>
                            </p>
                            <p class="text-sm text-slate-500 truncate">
                                <?= e($adminEmail) ?>
                            </p>
                        </div>

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="w-5 h-5 text-slate-500 shrink-0"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <!-- DROPDOWN -->
                    <div 
                        id="profileDropdownMenu"
                        class="hidden absolute right-0 top-full mt-2 w-56 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50"
                    >
                        <a href="profile.php"
                            class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-gray-50 transition">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="w-5 h-5 text-slate-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.118a7.5 7.5 0 0 1 15 0A17.933 17.933 0 0 1 12 21.75a17.933 17.933 0 0 1-7.5-1.632Z" />
                            </svg>
                            <span>My Profile</span>
                        </a>

                        <div class="border-t border-gray-100"></div>

                        <a href="../auth/logout.php"
                            class="flex items-center gap-3 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="w-5 h-5 text-red-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6A2.25 2.25 0 0 0 15.75 18.75V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>

                <!-- MOBILE MENU BUTTON -->
                <button
                    id="mobileMenuBtn"
                    type="button"
                    class="menu-toggle inline-flex sm:hidden"
                    aria-label="Open menu"
                    aria-expanded="false"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </div>

    <!-- DESKTOP NAV -->
    <div class="hidden sm:block border-t bg-gray-50">
        <div class="max-w-[1489px] mx-auto px-6">
            <nav class="flex gap-8 text-sm font-medium overflow-x-auto">
                <a href="dashboard.php"
                    class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'dashboard' ? 'text-purple-600 border-purple-600' : 'text-gray-700 border-transparent hover:text-purple-600' ?>">
                    Dashboard
                </a>

                <a href="manage_books.php"
                    class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'manage_books' ? 'text-purple-600 border-purple-600' : 'text-gray-700 border-transparent hover:text-purple-600' ?>">
                    Manage Books
                </a>

                <a href="manage_borrowings.php"
                    class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'manage_borrowings' ? 'text-purple-600 border-purple-600' : 'text-gray-700 border-transparent hover:text-purple-600' ?>">
                    Borrowings
                </a>

                <a href="manage_returns.php"
                    class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'manage_returns' ? 'text-purple-600 border-purple-600' : 'text-gray-700 border-transparent hover:text-purple-600' ?>">
                    Returns
                </a>

                <a href="manage_reservations.php"
                    class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'manage_reservations' ? 'text-purple-600 border-purple-600' : 'text-gray-700 border-transparent hover:text-purple-600' ?>">
                    Reservations
                </a>

                <a href="reports.php"
                    class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'reports' ? 'text-purple-600 border-purple-600' : 'text-gray-700 border-transparent hover:text-purple-600' ?>">
                    Reports
                </a>
            </nav>
        </div>
    </div>

    <!-- MOBILE MENU -->
    <div id="mobileMenu" class="hidden sm:hidden border-t bg-white shadow-sm">
        <div class="px-4 py-4 space-y-2">
            <a href="profile.php" class="block">
                <div class="flex items-center gap-3 rounded-2xl bg-gray-100 px-3 py-3 hover:bg-gray-200 transition cursor-pointer">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-200 border border-slate-300 shrink-0 flex items-center justify-center">
                        <?php if (!empty($adminProfileImage)): ?>
                            <img src="<?= e($adminProfileImage) ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-500" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                            </svg>
                        <?php endif; ?>
                    </div>

                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900 truncate">
                            <?= e($adminName) ?>
                        </p>
                        <p class="text-sm text-slate-500 truncate">
                            <?= e($adminEmail) ?>
                        </p>
                    </div>
                </div>
            </a>

            <a href="dashboard.php"
                class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'dashboard' ? 'bg-purple-50 text-purple-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                Dashboard
            </a>

            <a href="manage_books.php"
                class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'manage_books' ? 'bg-purple-50 text-purple-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                Manage Books
            </a>

            <a href="manage_borrowings.php"
                class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'manage_borrowings' ? 'bg-purple-50 text-purple-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                Borrowings
            </a>

            <a href="manage_returns.php"
                class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'manage_returns' ? 'bg-purple-50 text-purple-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                Returns
            </a>

            <a href="manage_reservations.php"
                class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'manage_reservations' ? 'bg-purple-50 text-purple-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                Reservations
            </a>

            <a href="reports.php"
                class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'reports' ? 'bg-purple-50 text-purple-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                Reports
            </a>

            <a href="../auth/logout.php"
                class="block rounded-xl px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50">
                Logout
            </a>
        </div>
    </div>
</header>

<style>
.menu-toggle {
    width: 52px;
    height: 52px;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    background: #ffffff;
    cursor: pointer;
    padding: 0;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 6px;
    transition: all 0.3s ease;
}

.menu-toggle:hover {
    background: #f9fafb;
}

.menu-toggle span {
    display: block;
    width: 22px;
    height: 2.5px;
    background: #374151;
    border-radius: 999px;
    transition: all 0.3s ease;
}

.menu-toggle.active span:nth-child(1) {
    transform: translateY(8px) rotate(45deg);
}

.menu-toggle.active span:nth-child(2) {
    opacity: 0;
}

.menu-toggle.active span:nth-child(3) {
    transform: translateY(-8px) rotate(-45deg);
}
</style>

<script>
const profileDropdownBtn = document.getElementById('profileDropdownBtn');
const profileDropdownMenu = document.getElementById('profileDropdownMenu');
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mobileMenu = document.getElementById('mobileMenu');

profileDropdownBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    profileDropdownMenu?.classList.toggle('hidden');
    mobileMenu?.classList.add('hidden');
});

mobileMenuBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    mobileMenu?.classList.toggle('hidden');
    profileDropdownMenu?.classList.add('hidden');

    mobileMenuBtn.classList.toggle('active');

    const expanded = mobileMenuBtn.classList.contains('active');
    mobileMenuBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
});

document.addEventListener('click', function (e) {
    if (!profileDropdownBtn?.contains(e.target) && !profileDropdownMenu?.contains(e.target)) {
        profileDropdownMenu?.classList.add('hidden');
    }

    if (!mobileMenuBtn?.contains(e.target) && !mobileMenu?.contains(e.target)) {
        mobileMenu?.classList.add('hidden');
        mobileMenuBtn?.classList.remove('active');
        mobileMenuBtn?.setAttribute('aria-expanded', 'false');
    }
});
</script>
</header>