<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/library_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/student_login.php");
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

    setLibraryDbTimezone($pdo);
} catch (PDOException $e) {
    die("Database error.");
}

$studentName = $_SESSION['fullname'] ?? 'Student';
$studentId   = $_SESSION['student_id'] ?? '—';
$userId      = $_SESSION['user_id'] ?? null;
$studentProfileImage = null;

/* ================= CURRENT STUDENT PROFILE DATA ================= */
if ($userId) {
    try {
        $studentStmt = $pdo->prepare("
            SELECT fullname, student_id, profile_image
            FROM users
            WHERE id = ? AND role = 'student'
            LIMIT 1
        ");
        $studentStmt->execute([$userId]);
        $studentData = $studentStmt->fetch(PDO::FETCH_ASSOC);

        if ($studentData) {
            $studentName = $studentData['fullname'] ?: $studentName;
            $studentId = $studentData['student_id'] ?: $studentId;

            if (!empty($studentData['profile_image'])) {
                $studentProfileImage = "../uploads/profile_images/" . $studentData['profile_image'];
            }

            // keep session updated too
            $_SESSION['fullname'] = $studentName;
            $_SESSION['student_id'] = $studentId;

            $_SESSION['profile_image'] = $studentData['profile_image'] ?? null;
        }
    } catch (PDOException $e) {
        // keep fallback session values if query fails
    }
}

function navClass($page, $currentPage) {
    return $page === $currentPage
        ? 'text-blue-600 border-b-2 border-blue-600'
        : 'text-gray-700 hover:text-blue-600';
}

$totalNotifications = countUnreadStudentNotifications($pdo, $userId, $studentId, $studentName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal</title>
    <link href="/library-management-system/assets/css/output.css" rel="stylesheet">

    <!-- Mobile App Icon -->
<!-- <link rel="apple-touch-icon" href="/library-management-system/assets/images/mobile-logo.png">
<link rel="manifest" href="/library-management-system/assets/app.webmanifest"> -->

<!-- Theme color (top bar color) -->
<!-- <meta name="theme-color" content="#1e3a8a"> -->

</head>
<body class="bg-gray-100">

<header class="fixed top-0 left-0 right-0 bg-white border-b border-gray-200 z-50 shadow-sm">
    <div class="max-w-[1489px] mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-20 gap-3">

            <!-- LEFT: LOGO -->
            <div class="flex items-center gap-3 min-w-0">
                <img src="/library-management-system/assets/images/logo1.png"
                     alt="STI Logo"
                     class="h-10 sm:h-12 w-auto object-contain shrink-0">

                <div class="min-w-0">
                    <h1 class="text-lg sm:text-2xl font-bold text-slate-900 leading-tight truncate">
                        STI College Ormoc
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm truncate">
                        Library Management System
                    </p>
                </div>
            </div>

            <!-- RIGHT: ACTIONS -->
            <div class="flex items-center gap-2 sm:gap-4 shrink-0">

                <!-- NOTIFICATIONS -->
                <div class="relative">
                    <button id="notifBtn"
                            type="button"
                            class="relative w-11 h-11 sm:w-12 sm:h-12 rounded-2xl border border-gray-300 bg-white flex items-center justify-center hover:bg-gray-50 transition">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="w-5 h-5 sm:w-6 sm:h-6 text-gray-700"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>

                        <span id="notifBadge"
                              class="<?= $totalNotifications > 0 ? '' : 'hidden' ?> absolute -top-1.5 -right-1.5 min-w-[22px] h-[22px] px-1.5 rounded-full bg-red-600 text-white text-[11px] font-bold inline-flex items-center justify-center leading-none shadow">
                            <?= $totalNotifications > 99 ? '99+' : $totalNotifications ?>
                        </span>
                    </button>

                    <div id="notifDropdown"
                    class="hidden fixed top-[5.5rem] left-2 right-2 w-auto sm:absolute sm:top-full sm:mt-3 sm:right-auto sm:left-0 sm:w-[335px] bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50">

                    <div class="px-4 py-3 border-b bg-gray-50">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h3 class="font-semibold text-slate-900 text-base">Notifications</h3>

                        <div class="flex items-center gap-3 flex-wrap">
                                <button id="markAllReadBtn"
                                        type="button"
                                        class="text-sm font-medium text-blue-600 hover:text-blue-700 leading-tight">
                                    Mark all as read
                                </button>

                                <button id="deleteAllBtn"
                                        type="button"
                                        class="ml-auto sm:ml-0 text-sm font-medium text-red-600 hover:text-red-700 leading-tight">
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>

        <div id="notifList" class="max-h-[60vh] sm:max-h-[420px] overflow-y-auto">
            <div class="p-4 text-sm text-gray-500">Loading notifications...</div>
        </div>
</div>
                </div>

                <!-- PROFILE -->
                <div class="relative hidden sm:block">
                    <button id="profileDropdownBtn"
                            type="button"
                            class="bg-gray-100 rounded-2xl px-3 sm:px-4 py-3 flex items-center gap-3 hover:bg-gray-200 transition min-w-[220px] lg:min-w-[270px] max-w-[300px]">
                        <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full overflow-hidden bg-slate-200 border border-slate-300 shrink-0 flex items-center justify-center">
                            <?php if (!empty($studentProfileImage)): ?>
                                <img src="<?= e($studentProfileImage) ?>" alt="Profile" class="w-full h-full object-cover">
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-slate-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                                </svg>
                            <?php endif; ?>
                        </div>

                        <div class="leading-tight text-left min-w-0 flex-1">
                            <p class="font-semibold text-slate-900 truncate"><?= e($studentName) ?></p>
                            <p class="text-sm text-slate-500 truncate"><?= e($studentId) ?></p>
                        </div>

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="w-5 h-5 text-slate-500 shrink-0"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <!-- Dropdown -->
                <div id="profileDropdownMenu"
                    class="hidden absolute right-0 top-full mt-2 w-56 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50">

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
                <a href="student_dashboard.php"
                   class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'student_dashboard' ? 'text-blue-600 border-blue-600' : 'text-gray-700 border-transparent hover:text-blue-600' ?>">
                    Dashboard
                </a>

                <a href="book_catalog.php"
                   class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'book_catalog' ? 'text-blue-600 border-blue-600' : 'text-gray-700 border-transparent hover:text-blue-600' ?>">
                    Book Catalog
                </a>

                <a href="my_borrowings.php"
                   class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'my_borrowings' ? 'text-blue-600 border-blue-600' : 'text-gray-700 border-transparent hover:text-blue-600' ?>">
                    My Borrowings
                </a>

                <a href="reservations.php"
                   class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'reservations' ? 'text-blue-600 border-blue-600' : 'text-gray-700 border-transparent hover:text-blue-600' ?>">
                    Reservations
                </a>
<!-- 
                <a href="profile.php"
                   class="pb-3 pt-3 font-medium border-b-2 whitespace-nowrap <?= $currentPage === 'profile' ? 'text-blue-600 border-blue-600' : 'text-gray-700 border-transparent hover:text-blue-600' ?>">
                    Profile
                </a> -->
            </nav>
        </div>
    </div>

    <!-- MOBILE MENU -->
    <div id="mobileMenu"
         class="hidden sm:hidden border-t bg-white shadow-sm">
        <div class="px-4 py-4 space-y-2">
           <a href="profile.php" class="block">
                <div class="flex items-center gap-3 rounded-2xl bg-gray-100 px-3 py-3 hover:bg-gray-200 transition cursor-pointer">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-200 border border-slate-300 shrink-0 flex items-center justify-center">
                        <?php if (!empty($studentProfileImage)): ?>
                            <img src="<?= e($studentProfileImage) ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-500" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                            </svg>
                        <?php endif; ?>
                    </div>

                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900 truncate"><?= e($studentName) ?></p>
                        <p class="text-sm text-slate-500 truncate"><?= e($studentId) ?></p>
                    </div>
                </div>
            </a>

            <a href="student_dashboard.php"
               class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'student_dashboard' ? 'bg-blue-50 text-blue-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                Dashboard
            </a>

            <a href="book_catalog.php"
               class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'book_catalog' ? 'bg-blue-50 text-blue-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                Book Catalog
            </a>

            <a href="my_borrowings.php"
               class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'my_borrowings' ? 'bg-blue-50 text-blue-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                My Borrowings
            </a>

            <a href="reservations.php"
               class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'reservations' ? 'bg-blue-50 text-blue-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                Reservations
            </a>

            <!-- <a href="profile.php"
               class="block rounded-xl px-4 py-3 text-sm font-medium <?= $currentPage === 'profile' ? 'bg-blue-50 text-blue-600' : 'text-slate-700 hover:bg-gray-50' ?>">
                Profile
            </a> -->

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

<audio id="notifSound" preload="auto">
    <source src="../assets/notification.mp3" type="audio/mpeg">
</audio>

<script>
const notifBtn = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');
const notifList = document.getElementById('notifList');
const notifBadge = document.getElementById('notifBadge');
const markAllReadBtn = document.getElementById('markAllReadBtn');
const notifSound = document.getElementById('notifSound');
const deleteAllBtn = document.getElementById('deleteAllBtn');
const profileDropdownBtn = document.getElementById('profileDropdownBtn');
const profileDropdownMenu = document.getElementById('profileDropdownMenu');
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mobileMenu = document.getElementById('mobileMenu');

let lastUnreadCount = <?= (int)$totalNotifications ?>;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function formatTimeAgo(datetime) {
    const created = new Date(datetime.replace(' ', 'T'));
    const now = new Date();
    const diff = Math.floor((now - created) / 1000);

    if (diff < 60) return 'Just now';
    if (diff < 3600) {
        const mins = Math.floor(diff / 60);
        return mins + ' minute' + (mins === 1 ? '' : 's') + ' ago';
    }
    if (diff < 86400) {
        const hours = Math.floor(diff / 3600);
        return hours + ' hour' + (hours === 1 ? '' : 's') + ' ago';
    }
    if (diff < 604800) {
        const days = Math.floor(diff / 86400);
        return days + ' day' + (days === 1 ? '' : 's') + ' ago';
    }

    return datetime;
}

function renderNotifications(data) {
    const unreadCount = data.unreadCount || 0;
    const items = data.notifications || [];

    if (unreadCount > 0) {
        notifBadge.classList.remove('hidden');
        notifBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
    } else {
        notifBadge.classList.add('hidden');
        notifBadge.textContent = '0';
    }

    if (unreadCount > lastUnreadCount) {
        if (notifSound) {
            notifSound.play().catch(() => {});
        }
    }

    lastUnreadCount = unreadCount;

    if (items.length === 0) {
        notifList.innerHTML = `
            <div class="p-5 text-sm text-gray-500 text-center">
                No notifications yet.
            </div>
        `;
        return;
    }

    notifList.innerHTML = items.map(item => {
        const isUnread = Number(item.is_read) === 0;
        const bg = isUnread ? 'bg-blue-50' : 'bg-white';
        const link = item.link ? item.link : 'student_dashboard.php';

        let iconClass = 'text-blue-500';

        if (item.type === 'reservation_ready') {
            iconClass = 'text-green-500';
        } else if (item.type === 'overdue') {
            iconClass = 'text-red-500';
        }

        return `
            <div id="notif-item-${Number(item.id)}"
                class="notif-item group flex items-start justify-between gap-3 px-4 py-4 border-b last:border-b-0 hover:bg-gray-50 transition ${bg}">
                
                <a href="${escapeHtml(link)}"
                   class="flex-1 min-w-0"
                   onclick="markSingleNotificationRead(${Number(item.id)})">

                    <div class="flex items-center gap-2">
                        <span class="${iconClass} text-xs font-bold">•</span>
                        <p class="font-semibold text-slate-900 text-[15px] leading-6">
                            ${escapeHtml(item.title)}
                        </p>
                    </div>

                    <p class="text-sm text-slate-600 mt-1 leading-6">
                        ${escapeHtml(item.message)}
                    </p>

                    <p class="text-xs text-gray-400 mt-2">
                        ${escapeHtml(formatTimeAgo(item.created_at))}
                    </p>
                </a>

                <div class="flex items-center gap-2 shrink-0">
                    ${Number(item.is_read) === 0 ? `
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    ` : ''}

                    <button onclick="deleteNotification(${Number(item.id)})"
                            class="opacity-0 group-hover:opacity-100 transition duration-200 text-gray-400 hover:text-red-500 text-sm leading-none">
                        ✕
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

async function fetchNotifications() {
    try {
        const response = await fetch('fetch_notifications.php', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) return;

        const data = await response.json();
        renderNotifications(data);
    } catch (error) {
        console.error('Notification fetch failed:', error);
    }
}

async function markSingleNotificationRead(id) {
    try {
        await fetch('mark_notifications_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
    } catch (error) {
        console.error('Mark single notification failed:', error);
    }
}

async function markAllNotificationsRead() {
    try {
        await fetch('mark_notifications_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });

        await fetchNotifications();
    } catch (error) {
        console.error('Mark all read failed:', error);
    }
}

notifBtn?.addEventListener('click', async function (e) {
    e.stopPropagation();
    notifDropdown.classList.toggle('hidden');
    profileDropdownMenu?.classList.add('hidden');
    mobileMenu?.classList.add('hidden');

    if (!notifDropdown.classList.contains('hidden')) {
        await fetchNotifications();
    }
});

markAllReadBtn?.addEventListener('click', async function (e) {
    e.preventDefault();
    await markAllNotificationsRead();
});

deleteAllBtn?.addEventListener('click', async function () {
    if (!confirm('Clear all notifications? This cannot be undone.')) return;

    try {
        const items = [...document.querySelectorAll('.notif-item')];
        await Promise.all(items.map((el) => {
            const id = el.id.replace('notif-item-', '');
            return animateRemoveNotification(id);
        }));

        await fetch('delete_notifications.php', { method: 'POST' });
        await fetchNotifications();
    } catch (error) {
        console.error('Clear notifications failed:', error);
        await fetchNotifications();
    }
});

function animateRemoveNotification(id) {
    const item = document.getElementById(`notif-item-${id}`);
    if (!item) return Promise.resolve();

    return new Promise((resolve) => {
        item.style.transition = 'opacity 220ms ease, transform 220ms ease, max-height 220ms ease, margin 220ms ease, padding 220ms ease';
        item.style.opacity = '1';
        item.style.transform = 'translateX(0)';
        item.style.maxHeight = item.offsetHeight + 'px';

        requestAnimationFrame(() => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(8px)';
            item.style.maxHeight = '0px';
            item.style.marginTop = '0';
            item.style.marginBottom = '0';
            item.style.paddingTop = '0';
            item.style.paddingBottom = '0';
            item.style.overflow = 'hidden';
        });

        setTimeout(() => {
            item.remove();
            resolve();
        }, 240);
    });
}

async function deleteNotification(id) {
    if (!confirm('Delete this notification?')) return;

    try {
        await animateRemoveNotification(id);

        await fetch('delete_notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });

        await fetchNotifications();
    } catch (error) {
        console.error('Delete notification failed:', error);
        await fetchNotifications();
    }
}

profileDropdownBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    profileDropdownMenu?.classList.toggle('hidden');
    notifDropdown?.classList.add('hidden');
    mobileMenu?.classList.add('hidden');
});

mobileMenuBtn?.addEventListener('click', function (e) {
    e.stopPropagation();

    mobileMenu?.classList.toggle('hidden');
    notifDropdown?.classList.add('hidden');
    profileDropdownMenu?.classList.add('hidden');
    mobileMenuBtn.classList.toggle('active');

    const expanded = mobileMenuBtn.classList.contains('active');
    mobileMenuBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
});

document.addEventListener('click', function (e) {
    if (!notifBtn?.contains(e.target) && !notifDropdown?.contains(e.target)) {
        notifDropdown?.classList.add('hidden');
    }

    if (!profileDropdownBtn?.contains(e.target) && !profileDropdownMenu?.contains(e.target)) {
        profileDropdownMenu?.classList.add('hidden');
    }

   if (!mobileMenuBtn?.contains(e.target) && !mobileMenu?.contains(e.target)) {
    mobileMenu?.classList.add('hidden');
    mobileMenuBtn?.classList.remove('active');
    mobileMenuBtn?.setAttribute('aria-expanded', 'false');
    }
});

fetchNotifications();
setInterval(fetchNotifications, 10000);
</script>