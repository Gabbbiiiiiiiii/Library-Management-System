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
$studentName = $_SESSION['fullname'] ?? 'Student';
$studentId   = $_SESSION['student_id'] ?? '—';
$userId      = $_SESSION['user_id'] ?? null;

function navClass($page, $currentPage) {
    return $page === $currentPage
        ? 'text-blue-600 border-b-2 border-blue-600'
        : 'text-gray-700 hover:text-blue-600';
}

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

$totalNotifications = countUnreadStudentNotifications($pdo, $userId, $studentId, $studentName);
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

<header class="fixed top-0 left-0 right-0 bg-white border-b border-gray-200 z-50 shadow-sm">
    <div class="max-w-[1489px] mx-auto px-6">
        <div class="flex items-center justify-between h-20">
            
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center text-white text-2xl font-bold">
                    📖
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 leading-tight">STI College Ormoc</h1>
                    <p class="text-slate-500 text-sm">Library Management System</p>
                </div>
            </div>

            <div class="flex items-center gap-4">

                <!-- Notification Bell -->
                <div class="relative">
                    <button id="notifBtn"
                            type="button"
                            class="relative w-12 h-12 rounded-2xl border border-gray-300 bg-white flex items-center justify-center hover:bg-gray-50 transition">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="w-6 h-6 text-gray-700"
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
     class="hidden absolute top-full mt-3 w-[380px] sm:w-[420px] bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50"
     style="right: -330px;">
                        <!-- class="hidden absolute right-0 mt-3 w-[380px] sm:w-[420px] bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50"> -->
                        
                        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b bg-gray-50">
                            <h3 class="font-semibold text-slate-900 text-base whitespace-nowrap">Notifications</h3>

                            <button id="markAllReadBtn"
                                    type="button"
                                    class="text-sm font-medium text-blue-600 hover:text-blue-700 text-right leading-tight shrink-0">
                                Mark all as read
                            </button>

                            <button id="deleteAllBtn"
                                class="text-sm font-medium text-red-600 hover:text-red-700">
                                Clear
                            </button>

                        </div>

                        <div id="notifList" class="max-h-[420px] overflow-y-auto">
                            <div class="p-4 text-sm text-gray-500">Loading notifications...</div>
                        </div>
                    </div>
                </div>

                <!-- Student Info -->
                <div class="bg-gray-100 rounded-2xl px-4 py-3 flex items-center gap-3">
                    <div class="text-gray-600 text-lg">👤</div>
                    <div class="leading-tight">
                        <p class="font-semibold text-slate-900"><?= e($studentName) ?></p>
                        <p class="text-sm text-slate-500"><?= e($studentId) ?></p>
                    </div>
                </div>

                <!-- Logout -->
                <a href="../auth/logout.php"
                   class="border border-gray-300 rounded-2xl px-6 py-3 font-semibold text-slate-900 hover:bg-gray-50">
                    <span>↩</span> Logout
                </a>
            </div>
        </div>
    </div>

    <!-- ================= NAVIGATION ================= -->
    <div class="border-t bg-gray-50">
        <div class="max-w-[1489px] mx-auto px-6">
            <nav class="flex gap-8 text-sm font-medium">
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

<audio id="notifSound" preload="auto">
    <source src="/library-management-system/assets/notification.mp3" type="audio/mpeg">
</audio>

<script>
const notifBtn = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');
const notifList = document.getElementById('notifList');
const notifBadge = document.getElementById('notifBadge');
const markAllReadBtn = document.getElementById('markAllReadBtn');
const notifSound = document.getElementById('notifSound');
const deleteAllBtn = document.getElementById('deleteAllBtn');

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

        return `
            <div id="notif-item-${Number(item.id)}"
                class="notif-item group flex items-start justify-between gap-3 px-4 py-4 border-b last:border-b-0 hover:bg-gray-50 transition ${bg}">
                
                <!-- LEFT CONTENT -->
                <a href="${escapeHtml(link)}"
                class="flex-1 min-w-0"
                onclick="markSingleNotificationRead(${Number(item.id)})">

                    <p class="font-semibold text-slate-900 text-[15px] leading-6">
                        ${escapeHtml(item.title)}
                    </p>

                    <p class="text-sm text-slate-600 mt-1 leading-6">
                        ${escapeHtml(item.message)}
                    </p>

                    <p class="text-xs text-gray-400 mt-2">
                        ${escapeHtml(formatTimeAgo(item.created_at))}
                    </p>
                </a>

                <!-- RIGHT SIDE -->
                <div class="flex items-center gap-2 shrink-0">

                    <!-- UNREAD DOT -->
                    ${Number(item.is_read) === 0 ? `
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    ` : ''}

                    <!-- DELETE BUTTON (HOVER ONLY) -->
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
            headers: {
                'Content-Type': 'application/json'
            },
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
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({})
        });

        await fetchNotifications();
    } catch (error) {
        console.error('Mark all read failed:', error);
    }
}

notifBtn?.addEventListener('click', async function () {
    notifDropdown.classList.toggle('hidden');
    if (!notifDropdown.classList.contains('hidden')) {
        await fetchNotifications();
    }
});

markAllReadBtn?.addEventListener('click', async function (e) {
    e.preventDefault();
    await markAllNotificationsRead();
});

document.addEventListener('click', function (e) {
    if (!notifBtn?.contains(e.target) && !notifDropdown?.contains(e.target)) {
        notifDropdown?.classList.add('hidden');
    }
});

fetchNotifications();
setInterval(fetchNotifications, 10000);

deleteAllBtn?.addEventListener('click', async function () {
    if (!confirm('Clear all notifications? This cannot be undone.')) return;

    try {
        const items = [...document.querySelectorAll('.notif-item')];
        await Promise.all(items.map((el) => {
            const id = el.id.replace('notif-item-', '');
            return animateRemoveNotification(id);
        }));

        await fetch('delete_notifications.php', {
            method: 'POST'
        });

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
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        });

        await fetchNotifications();
    } catch (error) {
        console.error('Delete notification failed:', error);
        await fetchNotifications();
    }
}

</script>