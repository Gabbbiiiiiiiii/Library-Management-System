<?php
session_start();
require_once "auth_check.php";

$currentPage = 'dashboard';

/* ================= DATABASE CONNECTION ================= */
$pdo = new PDO("mysql:host=localhost;dbname=sti_library", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
    return isset($b['status']) && $b['status'] === 'active';
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

// Returns
foreach ($borrowings as $b) {
    if (!empty($b['returnDate'])) {
        $recentActivity[] = [
            'type' => 'return',
            'date' => strtotime($b['returnDate']),
            'text' => ($b['studentName'] ?? 'A student') . ' returned a book',
        ];
    }
}

// Active borrows
foreach ($borrowings as $b) {
    if (isset($b['status']) && $b['status'] === 'active') {
        $recentActivity[] = [
            'type' => 'borrow',
            'date' => strtotime($b['borrowDate']),
            'text' => ($b['studentName'] ?? 'A student') . ' borrowed a book',
        ];
    }
}

// Reservations
foreach ($reservations as $r) {
    if (!empty($r['reservationDate'])) {
        $recentActivity[] = [
            'type' => 'reservation',
            'date' => strtotime($r['reservationDate']),
            'text' => ($r['studentName'] ?? 'A student') . ' reserved a book',
        ];
    }
}

// Sort by latest date
usort($recentActivity, function ($a, $b) {
    return $b['date'] <=> $a['date'];
});

// Get latest 10
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
<div class="max-w-7xl mx-auto p-6 mt-36">

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
    ["Total Books", $totalBooks, "📘", "bg-blue-100 text-blue-600"],
    ["Available Books", $availableBooks, "📈", "bg-green-100 text-green-600"],
    ["Active Borrowings", count($activeBorrowings), "⏱", "bg-purple-100 text-purple-600"],
    ["Overdue Books", count($overdueBorrowings), "⚠", "bg-red-100 text-red-600"],
    ["Active Reservations", count($activeReservations), "📌", "bg-orange-100 text-orange-600"],
    ["Total Penalties", "₱" . number_format($totalPenalties, 2), "👥", "bg-yellow-100 text-yellow-600"],
];

foreach ($stats as $stat):
?>
    <div class="bg-white rounded-2xl shadow-sm border p-6 hover:shadow-md transition">
        
        <div class="flex justify-between items-center mb-6">
            <p class="text-sm text-gray-600 font-medium">
                <?= htmlspecialchars($stat[0]) ?>
            </p>

            <div class="w-10 h-10 rounded-xl flex items-center justify-center <?= $stat[3] ?>">
                <span class="text-lg"><?= $stat[2] ?></span>
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
            <div class="flex justify-between items-center border-b pb-3">
                <div>
                    <p class="text-sm font-medium text-gray-800">
                        <?= htmlspecialchars($activity['text']) ?>
                    </p>
                    <p class="text-xs text-gray-500">
                        <?= date("M d, Y h:i A", $activity['date']) ?>
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