<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';
require_once "auth_check.php";

$currentPage = 'dashboard';


// function timeAgo($datetime) {
//     $time = time() - strtotime($datetime);

//     if ($time < 60) return 'Just now';

//     if ($time < 3600) {
//         $minutes = floor($time / 60);
//         return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
//     }

//     if ($time < 86400) {
//         $hours = floor($time / 3600);
//         return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
//     }

//     if ($time < 604800) {
//         $days = floor($time / 86400);
//         return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
//     }

//     return date("M d, Y h:i A", strtotime($datetime));
// }

/* ================= DATABASE CONNECTION ================= */
$pdo = new PDO("mysql:host=localhost;dbname=sti_library", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
setLibraryDbTimezone($pdo);

/* ================= FETCH DATA ================= */

// Books
$stmt = $pdo->query("SELECT * FROM books");
$books = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Borrowings
$stmt = $pdo->query("SELECT * FROM borrowings");
$borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Reservations
$stmt = $pdo->query("SELECT * FROM reservations");
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];


/* ======================= */
/* CALCULATIONS */
/* ======================= */

$totalBooks = array_sum(array_column($books, 'totalCopies'));
$availableBooks = array_sum(array_column($books, 'availableCopies'));

$activeBorrowings = array_filter($borrowings, function ($b) {
    return isset($b['status']) && $b['status'] === 'borrowed';
});

$overdueBorrowings = array_filter($activeBorrowings, function ($b) {
    return isset($b['dueDate']) && strtotime($b['dueDate']) < time();
});

$activeReservations = array_filter($reservations, function ($r) {
    return isset($r['status']) &&
        ($r['status'] === 'pending' || $r['status'] === 'ready');
});

$totalPenalties = array_sum(array_map(function ($b) {
    return $b['penalty'] ?? 0;
}, $borrowings));


/* ======================= */
/* RECENT ACTIVITY */
/* ======================= */

$recentActivity = [];

// Borrowings
$stmt = $pdo->query("
    SELECT studentName, borrowDate, returnDate
    FROM borrowings
    WHERE borrowDate IS NOT NULL OR returnDate IS NOT NULL
");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    if (!empty($b['borrowDate'])) {
        $timestamp = strtotime($b['borrowDate']);
        if ($timestamp !== false) {
            $recentActivity[] = [
                'type' => 'borrow',
                'date' => $timestamp,
                'text' => ($b['studentName'] ?? 'A student') . ' borrowed a book',
            ];
        }
    }

    if (!empty($b['returnDate'])) {
        $timestamp = strtotime($b['returnDate']);
        if ($timestamp !== false) {
            $recentActivity[] = [
                'type' => 'return',
                'date' => $timestamp,
                'text' => ($b['studentName'] ?? 'A student') . ' returned a book',
            ];
        }
    }
}

// Reservations
$stmt = $pdo->query("
    SELECT studentName, reservationDate
    FROM reservations
    WHERE reservationDate IS NOT NULL
");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (!empty($r['reservationDate'])) {
        $timestamp = strtotime($r['reservationDate']);
        if ($timestamp !== false) {
            $recentActivity[] = [
                'type' => 'reservation',
                'date' => $timestamp,
                'text' => ($r['studentName'] ?? 'A student') . ' reserved a book',
            ];
        }
    }
}

// Sort newest first, but reservation first if same time
usort($recentActivity, function ($a, $b) {
    if ($b['date'] !== $a['date']) {
        return $b['date'] <=> $a['date'];
    }

    $priority = [
        'reservation' => 3,
        'borrow'      => 2,
        'return'      => 1
    ];

    return ($priority[$b['type']] ?? 0) <=> ($priority[$a['type']] ?? 0);
});

$recentActivity = array_slice($recentActivity, 0, 10);

/* ======================= */
/* OTHER STATS */
/* ======================= */

$uniqueTitles = count($books);
$categories = count(array_unique(array_column($books, 'category')));
$totalBorrowed = $totalBooks - $availableBooks;

$availabilityPercent = $totalBooks > 0
    ? round(($availableBooks / $totalBooks) * 100)
    : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
  <link href="/library-management-system/assets/css/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    
<?php include 'header.php'; ?>

<!-- ================= PAGE CONTENT ================= -->
<div class="max-w-7xl mx-auto px-6 pt-32 pb-10">

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">
        Admin Dashboard
    </h1>
    <p class="text-gray-500 mt-1">
        Overview of library operations
    </p>
</div>

<!-- ================= STATS GRID ================= -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">

<?php
$stats = [
    [
        "Total Books",
        $totalBooks,
        '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
        </svg>',
        "bg-blue-100 text-blue-600"
    ],
    [
        "Available Books",
        $availableBooks,
        '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17l6-6 4 4 8-8"/>
        </svg>',
        "bg-green-100 text-green-600"
    ],
    [
        "Active Borrowings",
        count($activeBorrowings),
        '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <circle cx="12" cy="12" r="10" stroke-width="2"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
        </svg>',
        "bg-purple-100 text-purple-600"
    ],
    [
        "Overdue Books",
        count($overdueBorrowings),
        '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86l-7.4 12.8A1 1 0 003.74 18h16.52a1 1 0 00.85-1.54l-7.4-12.8a1 1 0 00-1.72 0z"/>
        </svg>',
        "bg-red-100 text-red-600"
    ],
    [
        "Active Reservations",
        count($activeReservations),
        '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75V16.5L12 14.25 7.5 16.5V3.75m9 0H18A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6A2.25 2.25 0 0 1 6 3.75h1.5m9 0h-9" />
        </svg>',
        "bg-orange-100 text-orange-600"
    ],
    [
        "Total Penalties",
        "₱" . number_format($totalPenalties, 2),
        '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10v10"/>
        </svg>',
        "bg-yellow-100 text-yellow-600"
    ],
];

foreach ($stats as $stat):
?>
    <div class="bg-white rounded-2xl shadow-sm border p-6 hover:shadow-lg hover:-translate-y-1 transition duration-300">
        
        <div class="flex justify-between items-center mb-6">
            <p class="text-sm text-gray-600 font-medium">
                <?= htmlspecialchars($stat[0]) ?>
            </p>

            <div class="w-6 h-6 rounded-xl flex items-center justify-center <?= $stat[3] ?>">
                <?= str_replace('w-6 h-6', 'w-6 h-6 stroke-current', $stat[2]) ?>
            </div>
        </div>

        <p class="text-3xl font-bold text-gray-900">
            <?= htmlspecialchars($stat[1]) ?>
        </p>

    </div>
<?php endforeach; ?>

</div>

<!-- ================= RECENT ACTIVITY ================= -->
<div class="bg-white p-6 rounded-2xl shadow-sm border mb-8">
    <h2 class="text-lg font-semibold mb-6">Recent Activity</h2>

    <?php if (empty($recentActivity)): ?>
        <p class="text-gray-500 text-center py-8">No recent activity</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($recentActivity as $activity): ?>
                <?php
                    $dotColor = 'bg-gray-300';

                    if ($activity['type'] === 'borrow') {
                        $dotColor = 'bg-blue-500';
                    } elseif ($activity['type'] === 'reservation') {
                        $dotColor = 'bg-orange-500';
                    } elseif ($activity['type'] === 'return') {
                        $dotColor = 'bg-green-500';
                    }
                ?>

                <div class="flex items-start gap-4 border-b pb-3 last:border-b-0">
                    <span class="w-2 h-2 rounded-full mt-2 shrink-0 <?= $dotColor ?>"></span>

                    <div>
                        <p class="text-sm font-medium text-gray-800">
                            <?= htmlspecialchars($activity['text']) ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?= timeAgo(date('Y-m-d H:i:s', $activity['date'])) ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ================= QUICK STATS ================= -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <!-- Book Availability -->
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold mb-4">Book Availability</h2>

        <div class="flex justify-between">
            <span>Total Copies</span>
            <span><?= $totalBooks ?></span>
        </div>

        <div class="flex justify-between">
            <span>Available</span>
            <span class="text-green-600"><?= $availableBooks ?></span>
        </div>

        <div class="flex justify-between">
            <span>Borrowed</span>
            <span class="text-blue-600"><?= $totalBorrowed ?></span>
        </div>

        <div class="w-full bg-gray-200 rounded-full h-2 mt-4">
            <div class="bg-green-600 h-2 rounded-full"
                 style="width: <?= $availabilityPercent ?>%;"></div>
        </div>

        <p class="text-xs text-center mt-2">
            <?= $availabilityPercent ?>% available
        </p>
    </div>

    <!-- Library Status -->
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold mb-4">Library Status</h2>

        <div class="flex justify-between">
            <span>Unique Titles</span>
            <span><?= $uniqueTitles ?></span>
        </div>

        <div class="flex justify-between">
            <span>Categories</span>
            <span><?= $categories ?></span>
        </div>

        <div class="flex justify-between">
            <span>Total Borrowings</span>
            <span><?= count($borrowings) ?></span>
        </div>

        <div class="flex justify-between">
            <span>Total Reservations</span>
            <span><?= count($reservations) ?></span>
        </div>
    </div>

</div>
</div> <!-- End Page Content Wrapper -->
</body>
</html>