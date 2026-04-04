<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';
require_once "auth_check.php";

$currentPage = 'reports';

/* ================= DATABASE CONNECTION ================= */
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
    die("Database connection failed.");
}

/* ================= AUTO UPDATE OVERDUE ================= */
$pdo->exec("
    UPDATE borrowings
    SET status = 'overdue'
    WHERE status = 'borrowed'
      AND dueDate IS NOT NULL
      AND dueDate < NOW()
      AND returnDate IS NULL
");

/* ================= DATE FILTER ================= */
$filter = $_GET['filter'] ?? 'this_month';
$startDate = trim($_GET['start_date'] ?? '');
$endDate   = trim($_GET['end_date'] ?? '');

$borrowWhere = "";
$returnWhere = "";
$reservationWhere = "";

$borrowParams = [];
$returnParams = [];
$reservationParams = [];

switch ($filter) {
    case 'today':
        $borrowWhere = "WHERE DATE(borrowDate) = CURDATE()";
        $returnWhere = "WHERE DATE(return_date) = CURDATE()";
        $reservationWhere = "WHERE DATE(reservationDate) = CURDATE()";
        break;

    case 'this_week':
        $borrowWhere = "WHERE YEARWEEK(borrowDate, 1) = YEARWEEK(CURDATE(), 1)";
        $returnWhere = "WHERE YEARWEEK(return_date, 1) = YEARWEEK(CURDATE(), 1)";
        $reservationWhere = "WHERE YEARWEEK(reservationDate, 1) = YEARWEEK(CURDATE(), 1)";
        break;

    case 'this_month':
        $borrowWhere = "WHERE MONTH(borrowDate) = MONTH(CURDATE()) AND YEAR(borrowDate) = YEAR(CURDATE())";
        $returnWhere = "WHERE MONTH(return_date) = MONTH(CURDATE()) AND YEAR(return_date) = YEAR(CURDATE())";
        $reservationWhere = "WHERE MONTH(reservationDate) = MONTH(CURDATE()) AND YEAR(reservationDate) = YEAR(CURDATE())";
        break;

    case 'this_year':
        $borrowWhere = "WHERE YEAR(borrowDate) = YEAR(CURDATE())";
        $returnWhere = "WHERE YEAR(return_date) = YEAR(CURDATE())";
        $reservationWhere = "WHERE YEAR(reservationDate) = YEAR(CURDATE())";
        break;

    case 'custom':
        if ($startDate !== '' && $endDate !== '') {
            $borrowWhere = "WHERE DATE(borrowDate) BETWEEN :start_date AND :end_date";
            $returnWhere = "WHERE DATE(return_date) BETWEEN :start_date AND :end_date";
            $reservationWhere = "WHERE DATE(reservationDate) BETWEEN :start_date AND :end_date";

            $borrowParams = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];
            $returnParams = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];
            $reservationParams = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];
        } else {
            $filter = 'this_month';
            $borrowWhere = "WHERE MONTH(borrowDate) = MONTH(CURDATE()) AND YEAR(borrowDate) = YEAR(CURDATE())";
            $returnWhere = "WHERE MONTH(return_date) = MONTH(CURDATE()) AND YEAR(return_date) = YEAR(CURDATE())";
            $reservationWhere = "WHERE MONTH(reservationDate) = MONTH(CURDATE()) AND YEAR(reservationDate) = YEAR(CURDATE())";
        }
        break;

    default:
        $filter = 'this_month';
        $borrowWhere = "WHERE MONTH(borrowDate) = MONTH(CURDATE()) AND YEAR(borrowDate) = YEAR(CURDATE())";
        $returnWhere = "WHERE MONTH(return_date) = MONTH(CURDATE()) AND YEAR(return_date) = YEAR(CURDATE())";
        $reservationWhere = "WHERE MONTH(reservationDate) = MONTH(CURDATE()) AND YEAR(reservationDate) = YEAR(CURDATE())";
        break;
}

function activeFilterClass(string $value, string $filter): string {
    return $value === $filter
        ? 'bg-purple-600 text-white shadow-sm ring-2 ring-purple-200'
        : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50';
}

/* ================= SUMMARY CARDS ================= */

/* Total books/copies - overall */
$stmt = $pdo->query("SELECT COALESCE(SUM(totalCopies), 0) FROM books");
$totalBooks = (int)$stmt->fetchColumn();

/* Unique titles - overall */
$stmt = $pdo->query("SELECT COUNT(*) FROM books");
$uniqueTitles = (int)$stmt->fetchColumn();

/* Available books/copies - overall */
$stmt = $pdo->query("SELECT COALESCE(SUM(availableCopies), 0) FROM books");
$availableCopies = (int)$stmt->fetchColumn();

/* Filtered borrowings */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings $borrowWhere");
$stmt->execute($borrowParams);
$totalBorrowings = (int)$stmt->fetchColumn();

/* Filtered returns */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM returns $returnWhere");
$stmt->execute($returnParams);
$totalReturns = (int)$stmt->fetchColumn();

/* Filtered reservations */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations $reservationWhere");
$stmt->execute($reservationParams);
$totalReservations = (int)$stmt->fetchColumn();

/* Current active borrowings - overall current status */
$stmt = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed'");
$activeBorrowings = (int)$stmt->fetchColumn();

/* Current overdue - overall current status */
$stmt = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'overdue'");
$overdueBorrowings = (int)$stmt->fetchColumn();

/* Total penalties from returns in selected period */
$stmt = $pdo->prepare("SELECT COALESCE(SUM(penalty), 0) FROM returns $returnWhere");
$stmt->execute($returnParams);
$totalPenaltyCollected = (float)$stmt->fetchColumn();

/* ================= MOST BORROWED BOOKS ================= */
$stmt = $pdo->prepare("
    SELECT
        bk.title,
        bk.author,
        bk.category,
        bk.isbn,
        COUNT(br.id) AS times_borrowed
    FROM borrowings br
    LEFT JOIN books bk ON br.book_id = bk.id
    $borrowWhere
    GROUP BY br.book_id, bk.title, bk.author, bk.category, bk.isbn
    ORDER BY times_borrowed DESC, bk.title ASC
    LIMIT 5
");
$stmt->execute($borrowParams);
$topBooks = $stmt->fetchAll();

/* ================= MOST ACTIVE STUDENTS ================= */
$stmt = $pdo->prepare("
    SELECT
        COALESCE(studentName, 'Unknown Student') AS student_name,
        COALESCE(student_id, 'N/A') AS student_id,
        COALESCE(course, 'N/A') AS course,
        COALESCE(yearlvl, 'N/A') AS yearlvl,
        COUNT(id) AS total_borrowings,
        COALESCE(SUM(penalty), 0) AS total_penalty
    FROM borrowings
    $borrowWhere
    GROUP BY studentName, student_id, course, yearlvl
    ORDER BY total_borrowings DESC, student_name ASC
    LIMIT 5
");
$stmt->execute($borrowParams);
$topStudents = $stmt->fetchAll();

/* ================= BORROWINGS BY CATEGORY ================= */
$stmt = $pdo->prepare("
    SELECT
        COALESCE(bk.category, 'Uncategorized') AS category,
        COUNT(br.id) AS total_borrowings
    FROM borrowings br
    LEFT JOIN books bk ON br.book_id = bk.id
    $borrowWhere
    GROUP BY bk.category
    ORDER BY total_borrowings DESC, category ASC
");
$stmt->execute($borrowParams);
$categoryStats = $stmt->fetchAll();

/* ================= RETURNS SUMMARY ================= */
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_returned,
        SUM(CASE WHEN penalty > 0 THEN 1 ELSE 0 END) AS returned_with_penalty,
        SUM(CASE WHEN penalty = 0 THEN 1 ELSE 0 END) AS returned_on_time,
        COALESCE(SUM(days_late), 0) AS total_days_late,
        COALESCE(MAX(penalty), 0) AS highest_penalty
    FROM returns
    $returnWhere
");
$stmt->execute($returnParams);
$returnSummary = $stmt->fetch() ?: [
    'total_returned' => 0,
    'returned_with_penalty' => 0,
    'returned_on_time' => 0,
    'total_days_late' => 0,
    'highest_penalty' => 0
];

/* ================= RESERVATION SUMMARY ================= */
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) AS ready_count,
        SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) AS borrowed_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired_count
    FROM reservations
    $reservationWhere
");
$stmt->execute($reservationParams);
$reservationSummary = $stmt->fetch() ?: [
    'pending_count' => 0,
    'ready_count' => 0,
    'borrowed_count' => 0,
    'cancelled_count' => 0,
    'expired_count' => 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link href="/library-management-system/assets/css/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'header.php'; ?>

<div class="max-w-[1489px] mx-auto px-6 pt-28 pb-10">

    <!-- PAGE HEADER -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Reports</h1>
        <p class="text-gray-600 mt-2 text-lg">View borrowings, returns, reservations, penalties, and library usage.</p>
    </div>

    <div class="mb-6">
    <a href="export_reports_csv.php?filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
       class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700">
        Export Reports CSV
    </a>
    </div>

    <!-- FILTER -->
    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm mb-8">
        <form method="GET" class="space-y-4">
            <div class="flex flex-wrap gap-3">
                <a href="reports.php?filter=today"
                class="px-4 py-2 rounded-xl text-sm font-medium transition <?= activeFilterClass('today', $filter) ?>">
                    Today
                </a>

                <a href="reports.php?filter=this_week"
                class="px-4 py-2 rounded-xl text-sm font-medium transition <?= activeFilterClass('this_week', $filter) ?>">
                    This Week
                </a>

                <a href="reports.php?filter=this_month"
                class="px-4 py-2 rounded-xl text-sm font-medium transition <?= activeFilterClass('this_month', $filter) ?>">
                    This Month
                </a>

                <a href="reports.php?filter=this_year"
                class="px-4 py-2 rounded-xl text-sm font-medium transition <?= activeFilterClass('this_year', $filter) ?>">
                    This Year
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input
                        type="date"
                        name="start_date"
                        value="<?= e($startDate) ?>"
                        class="w-full border rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-purple-500 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input
                        type="date"
                        name="end_date"
                        value="<?= e($endDate) ?>"
                        class="w-full border rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-purple-500 focus:outline-none"
                    >
                </div>

                <div>
                    <input type="hidden" name="filter" value="custom">
                    <button
                        type="submit"
                        class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-xl px-4 py-2.5 transition"
                    >
                        Apply Custom Range
                    </button>
                </div>

                <div>
                    <a
                        href="reports.php"
                        class="block w-full text-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-xl px-4 py-2.5 transition"
                    >
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <p class="text-sm font-medium text-gray-500">Total Book Copies</p>
            <h2 class="text-3xl font-bold text-gray-900 mt-4"><?= e($totalBooks) ?></h2>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <p class="text-sm font-medium text-gray-500">Unique Titles</p>
            <h2 class="text-3xl font-bold text-gray-900 mt-4"><?= e($uniqueTitles) ?></h2>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <p class="text-sm font-medium text-gray-500">Available Copies</p>
            <h2 class="text-3xl font-bold text-green-600 mt-4"><?= e($availableCopies) ?></h2>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <p class="text-sm font-medium text-gray-500">Borrowings (Selected Period)</p>
            <h2 class="text-3xl font-bold text-gray-900 mt-4"><?= e($totalBorrowings) ?></h2>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <p class="text-sm font-medium text-gray-500">Returns (Selected Period)</p>
            <h2 class="text-3xl font-bold text-blue-600 mt-4"><?= e($totalReturns) ?></h2>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <p class="text-sm font-medium text-gray-500">Reservations (Selected Period)</p>
            <h2 class="text-3xl font-bold text-orange-600 mt-4"><?= e($totalReservations) ?></h2>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <p class="text-sm font-medium text-gray-500">Active Borrowings (Current)</p>
            <h2 class="text-3xl font-bold text-purple-600 mt-4"><?= e($activeBorrowings) ?></h2>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <p class="text-sm font-medium text-gray-500">Overdue Books (Current)</p>
            <h2 class="text-3xl font-bold text-red-600 mt-4"><?= e($overdueBorrowings) ?></h2>
        </div>
    </div>

    <!-- SECOND ROW CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <p class="text-sm font-medium text-gray-500">Penalty Collected (Selected Period)</p>
            <h2 class="text-3xl font-bold text-yellow-600 mt-4">₱<?= number_format($totalPenaltyCollected, 2) ?></h2>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <p class="text-sm font-medium text-gray-500">Ready Reservations (Selected Period)</p>
            <h2 class="text-3xl font-bold text-green-600 mt-4"><?= e((int)($reservationSummary['ready_count'] ?? 0)) ?></h2>
        </div>
    </div>

    <!-- TOP BOOKS + TOP STUDENTS -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <h2 class="text-xl font-semibold text-gray-900 mb-5">Most Borrowed Books</h2>

            <?php if (empty($topBooks)): ?>
                <p class="text-gray-500">No borrowing data available for this period.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-gray-500">
                                <th class="py-3 pr-4">Title</th>
                                <th class="py-3 pr-4">Author</th>
                                <th class="py-3 pr-4">Category</th>
                                <th class="py-3 text-right">Borrowed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topBooks as $book): ?>
                                <tr class="border-b last:border-b-0">
                                    <td class="py-3 pr-4 font-medium text-gray-900"><?= e($book['title'] ?: 'Unknown Book') ?></td>
                                    <td class="py-3 pr-4 text-gray-700"><?= e($book['author'] ?: 'Unknown Author') ?></td>
                                    <td class="py-3 pr-4 text-gray-700"><?= e($book['category'] ?: 'Uncategorized') ?></td>
                                    <td class="py-3 text-right font-semibold text-purple-600"><?= e((int)$book['times_borrowed']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <h2 class="text-xl font-semibold text-gray-900 mb-5">Most Active Students</h2>

            <?php if (empty($topStudents)): ?>
                <p class="text-gray-500">No student data available for this period.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($topStudents as $student): ?>
                        <div class="border rounded-xl p-4">
                            <div class="flex justify-between items-start gap-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900"><?= e($student['student_name']) ?></h3>
                                    <p class="text-sm text-gray-500">Student ID: <?= e($student['student_id']) ?></p>
                                    <p class="text-sm text-gray-500"><?= e($student['course']) ?> • <?= e($student['yearlvl']) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-purple-600"><?= e((int)$student['total_borrowings']) ?></p>
                                    <p class="text-xs text-gray-500">Borrowings</p>
                                </div>
                            </div>

                            <div class="mt-3 text-sm text-gray-600">
                                Total Penalty: <span class="font-semibold text-yellow-600">₱<?= number_format((float)$student['total_penalty'], 2) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- LOWER GRID -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <h2 class="text-xl font-semibold text-gray-900 mb-5">Borrowings by Category</h2>

            <?php if (empty($categoryStats)): ?>
                <p class="text-gray-500">No category data available.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php
                    $maxCategory = !empty($categoryStats) ? max(array_column($categoryStats, 'total_borrowings')) : 0;
                    foreach ($categoryStats as $category):
                        $percent = $maxCategory > 0 ? ($category['total_borrowings'] / $maxCategory) * 100 : 0;
                    ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-gray-700"><?= e($category['category']) ?></span>
                                <span class="font-semibold text-gray-900"><?= e((int)$category['total_borrowings']) ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-purple-600 h-2.5 rounded-full" style="width: <?= $percent ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <h2 class="text-xl font-semibold text-gray-900 mb-5">Returns Summary</h2>

            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-600">Returned Books</span>
                    <span class="font-semibold text-gray-900"><?= e((int)$returnSummary['total_returned']) ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Returned On Time</span>
                    <span class="font-semibold text-green-600"><?= e((int)$returnSummary['returned_on_time']) ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Returned With Penalty</span>
                    <span class="font-semibold text-red-600"><?= e((int)$returnSummary['returned_with_penalty']) ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Total Days Late</span>
                    <span class="font-semibold text-orange-600"><?= e((int)$returnSummary['total_days_late']) ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Highest Penalty</span>
                    <span class="font-semibold text-yellow-600">₱<?= number_format((float)$returnSummary['highest_penalty'], 2) ?></span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition">
            <h2 class="text-xl font-semibold text-gray-900 mb-5">Reservation Summary</h2>

            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-600">Pending</span>
                    <span class="font-semibold text-yellow-600"><?= e((int)($reservationSummary['pending_count'] ?? 0)) ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Ready</span>
                    <span class="font-semibold text-green-600"><?= e((int)($reservationSummary['ready_count'] ?? 0)) ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Borrowed</span>
                    <span class="font-semibold text-blue-600"><?= e((int)($reservationSummary['borrowed_count'] ?? 0)) ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Cancelled</span>
                    <span class="font-semibold text-red-600"><?= e((int)($reservationSummary['cancelled_count'] ?? 0)) ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Expired</span>
                    <span class="font-semibold text-gray-700"><?= e((int)($reservationSummary['expired_count'] ?? 0)) ?></span>
                </div>
            </div>
        </div>
    </div>

</div>
</body>
</html>