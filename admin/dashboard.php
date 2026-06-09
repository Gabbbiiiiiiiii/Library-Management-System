<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once "auth_check.php";

$currentPage = 'dashboard';


try {
    processExpiredReservations($pdo);
} catch (Throwable $e) {
    // Keep dashboard working even if helper fails
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

/* ================= MAIN STATS ================= */

$totalBooks = (int)$pdo->query("
    SELECT COALESCE(SUM(totalCopies), 0)
    FROM books
")->fetchColumn();

$availableBooks = (int)$pdo->query("
    SELECT COALESCE(SUM(availableCopies), 0)
    FROM books
")->fetchColumn();

$totalBorrowed = max(0, $totalBooks - $availableBooks);

$uniqueTitles = (int)$pdo->query("
    SELECT COUNT(*)
    FROM books
")->fetchColumn();

$categories = (int)$pdo->query("
    SELECT COUNT(DISTINCT category)
    FROM books
    WHERE category IS NOT NULL AND category != ''
")->fetchColumn();

$activeBorrowingsCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM borrowings
    WHERE status = 'borrowed'
")->fetchColumn();

$overdueBorrowingsCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM borrowings
    WHERE status = 'overdue'
")->fetchColumn();

$activeReservationsCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status IN ('pending', 'ready')
")->fetchColumn();

$totalReservations = (int)$pdo->query("
    SELECT COUNT(*)
    FROM reservations
")->fetchColumn();

$totalBorrowings = (int)$pdo->query("
    SELECT COUNT(*)
    FROM borrowings
")->fetchColumn();

$totalStudents = (int)$pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'student'
")->fetchColumn();

$collectedPenalties = (float)$pdo->query("
    SELECT COALESCE(SUM(penalty), 0)
    FROM returns
")->fetchColumn();

$estimatedPendingPenalty = (float)$pdo->query("
    SELECT COALESCE(SUM(GREATEST(DATEDIFF(NOW(), dueDate), 0) * 10), 0)
    FROM borrowings
    WHERE status = 'overdue'
      AND returnDate IS NULL
      AND dueDate IS NOT NULL
")->fetchColumn();

$availabilityPercent = $totalBooks > 0
    ? round(($availableBooks / $totalBooks) * 100)
    : 0;

$borrowedPercent = $totalBooks > 0
    ? round(($totalBorrowed / $totalBooks) * 100)
    : 0;

/* ================= OVERDUE WATCHLIST ================= */

$stmt = $pdo->query("
    SELECT
        b.id,
        b.studentName,
        b.student_id,
        COALESCE(u.contact_number, 'No contact') AS contact_number,
        bk.title AS book_title,
        b.borrowDate,
        b.dueDate,
        GREATEST(DATEDIFF(NOW(), b.dueDate), 0) AS days_late
    FROM borrowings b
    LEFT JOIN books bk ON bk.id = b.book_id
    LEFT JOIN users u ON u.student_id = b.student_id
    WHERE b.status = 'overdue'
      AND b.returnDate IS NULL
    ORDER BY b.dueDate ASC, b.id ASC
    LIMIT 5
");
$overdueWatchlist = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ================= RECENT ACTIVITY ================= */

$recentActivity = [];

/* Recent borrowings and returns */
$stmt = $pdo->query("
    SELECT
        studentName,
        borrowDate,
        returnDate
    FROM borrowings
    WHERE borrowDate IS NOT NULL OR returnDate IS NOT NULL
");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (!empty($row['borrowDate'])) {
        $timestamp = strtotime($row['borrowDate']);

        if ($timestamp !== false) {
            $recentActivity[] = [
                'type' => 'borrow',
                'date' => $timestamp,
                'title' => 'Book Borrowed',
                'text' => ($row['studentName'] ?? 'A student') . ' borrowed a book',
            ];
        }
    }

    if (!empty($row['returnDate'])) {
        $timestamp = strtotime($row['returnDate']);

        if ($timestamp !== false) {
            $recentActivity[] = [
                'type' => 'return',
                'date' => $timestamp,
                'title' => 'Book Returned',
                'text' => ($row['studentName'] ?? 'A student') . ' returned a book',
            ];
        }
    }
}

/* Recent reservations */
$stmt = $pdo->query("
    SELECT
        COALESCE(u.fullname, 'A student') AS studentName,
        r.reservationDate
    FROM reservations r
    LEFT JOIN users u ON u.student_id = r.student_id
    WHERE r.reservationDate IS NOT NULL
");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $timestamp = strtotime($row['reservationDate']);

    if ($timestamp !== false) {
        $recentActivity[] = [
            'type' => 'reservation',
            'date' => $timestamp,
            'title' => 'Book Reserved',
            'text' => ($row['studentName'] ?? 'A student') . ' reserved a book',
        ];
    }
}

usort($recentActivity, function ($a, $b) {
    return $b['date'] <=> $a['date'];
});

$recentActivity = array_slice($recentActivity, 0, 8);

/* ================= LOW STOCK BOOKS ================= */

$stmt = $pdo->query("
    SELECT
        title,
        author,
        totalCopies,
        availableCopies
    FROM books
    WHERE availableCopies <= 2
    ORDER BY availableCopies ASC, title ASC
    LIMIT 5
");
$lowStockBooks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

function dashboardActivityStyle(string $type): array
{
    return match ($type) {
        'borrow' => ['bg-blue-100 text-blue-700', 'bg-blue-500'],
        'return' => ['bg-green-100 text-green-700', 'bg-green-500'],
        'reservation' => ['bg-orange-100 text-orange-700', 'bg-orange-500'],
        default => ['bg-gray-100 text-gray-700', 'bg-gray-400'],
    };
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
  <link href="/library-management-system/assets/css/output.css" rel="stylesheet">
   <style>
        body {
            background: #f3f4f6;
        }
        
                    .borrowings-action {
    background: #4f46e5;
}

.borrowings-action:hover {
    background: #4338ca;
}
            
            .reservations-action {
    background: #ea580c;
}

.reservations-action:hover {
    background: #c2410c;
}
            
            .reports-action {
    background: #111827;
}

.reports-action:hover {
    background: #1f2937;
}
    

        .dashboard-page {
            max-width: 1489px;
            margin: 0 auto;
            padding: 145px 24px 40px;
        }

        .dashboard-hero {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 18px 40px rgba(79, 70, 229, 0.20);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .dashboard-hero h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .dashboard-hero p {
            color: rgba(255, 255, 255, 0.86);
            font-size: 15px;
        }

        .hero-date {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 16px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            transition: 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
        }

        .stat-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .stat-label {
            font-size: 13px;
            font-weight: 700;
            color: #6b7280;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: #111827;
            line-height: 1;
        }

        .stat-note {
            margin-top: 10px;
            font-size: 12px;
            color: #6b7280;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dashboard-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .card-title {
            font-size: 20px;
            font-weight: 800;
            color: #111827;
        }

        .card-subtitle {
            color: #6b7280;
            font-size: 14px;
            margin-top: 4px;
        }

        .quick-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 700;
            color: white;
            text-decoration: none;
            transition: 0.2s ease;
            white-space: nowrap;
        }

        .quick-action:hover {
            transform: translateY(-1px);
            filter: brightness(0.95);
        }

        .progress-track {
            width: 100%;
            height: 10px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 999px;
        }

        .activity-box {
            max-height: 420px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .activity-box::-webkit-scrollbar {
            width: 8px;
        }

        .activity-box::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            margin-top: 6px;
            flex-shrink: 0;
        }

        .watch-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .watch-table th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            background: #f9fafb;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .watch-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #374151;
        }

        .watch-table tr:last-child td {
            border-bottom: none;
        }

        .badge-red {
            background: #fee2e2;
            color: #991b1b;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 800;
            display: inline-block;
        }

        .mini-stat {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .mini-stat:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .dashboard-page {
                padding: 130px 14px 28px;
            }

            .dashboard-hero {
                padding: 22px;
                border-radius: 18px;
            }

            .dashboard-hero h1 {
                font-size: 26px;
            }

            .hero-date {
                width: 100%;
                text-align: center;
            }               

            .dashboard-card,
            .stat-card {
                padding: 18px;
                border-radius: 16px;
            }
        }   
    </style>
</head>
<body class="bg-gray-100">
    
<?php include 'header.php'; ?>

<main class="dashboard-page">

    <!-- HERO -->
    <section class="dashboard-hero">
        <div>
            <h1>Admin Dashboard</h1>
            <p>Monitor library activity, overdue books, reservations, availability, and penalties.</p>
        </div>

        <div class="hero-date">
            <?= date('F d, Y') ?>
        </div>
    </section>

    <!-- MAIN STATS -->
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-card-top">
                <p class="stat-label">Total Book Copies</p>
                <div class="stat-icon bg-blue-100 text-blue-600">
                    📚
                </div>
            </div>
            <p class="stat-value"><?= e($totalBooks) ?></p>
            <p class="stat-note"><?= e($uniqueTitles) ?> unique titles</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <p class="stat-label">Available Copies</p>
                <div class="stat-icon bg-green-100 text-green-600">
                    ✓
                </div>
            </div>
            <p class="stat-value"><?= e($availableBooks) ?></p>
            <p class="stat-note"><?= e($availabilityPercent) ?>% of total copies available</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <p class="stat-label">Active Borrowings</p>
                <div class="stat-icon bg-purple-100 text-purple-600">
                    ⏱
                </div>
            </div>
            <p class="stat-value"><?= e($activeBorrowingsCount) ?></p>
            <p class="stat-note"><?= e($totalBorrowings) ?> total borrowing records</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <p class="stat-label">Overdue Books</p>
                <div class="stat-icon bg-red-100 text-red-600">
                    !
                </div>
            </div>
            <p class="stat-value"><?= e($overdueBorrowingsCount) ?></p>
            <p class="stat-note">Books not returned on time</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <p class="stat-label">Active Reservations</p>
                <div class="stat-icon bg-orange-100 text-orange-600">
                    ★
                </div>
            </div>
            <p class="stat-value"><?= e($activeReservationsCount) ?></p>
            <p class="stat-note"><?= e($totalReservations) ?> total reservation records</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <p class="stat-label">Registered Students</p>
                <div class="stat-icon bg-indigo-100 text-indigo-600">
                    👥
                </div>
            </div>
            <p class="stat-value"><?= e($totalStudents) ?></p>
            <p class="stat-note">Student accounts in the system</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <p class="stat-label">Collected Penalties</p>
                <div class="stat-icon bg-yellow-100 text-yellow-600">
                    ₱
                </div>
            </div>
            <p class="stat-value">₱<?= number_format($collectedPenalties, 2) ?></p>
            <p class="stat-note">From returned overdue books</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <p class="stat-label">Pending Penalties</p>
                <div class="stat-icon bg-rose-100 text-rose-600">
                    ₱
                </div>
            </div>
            <p class="stat-value">₱<?= number_format($estimatedPendingPenalty, 2) ?></p>
            <p class="stat-note">Estimated from current overdue books</p>
        </div>
    </section>

    <!-- QUICK ACTIONS -->
    <section class="dashboard-card mb-8">
        <div class="card-header">
            <div>
                <h2 class="card-title">Quick Actions</h2>
                <p class="card-subtitle">Go directly to common admin tasks.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="manage_students.php" class="quick-action bg-purple-600">Manage Students</a>
            <a href="manage_books.php" class="quick-action bg-blue-600">Manage Books</a>
            <a href="manage_borrowings.php" class="quick-action borrowings-action">Borrowings</a>
            <a href="manage_returns.php" class="quick-action bg-green-600">Returns</a>
            <a href="manage_reservations.php" class="quick-action reservations-action">Reservations</a>
            <a href="reports.php" class="quick-action reports-action">View Reports</a>
        </div>
    </section>

    <!-- MAIN DASHBOARD CONTENT -->
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">

        <!-- BOOK AVAILABILITY -->
        <div class="dashboard-card xl:col-span-1">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Book Availability</h2>
                    <p class="card-subtitle">Current copy distribution.</p>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-600">Available Copies</span>
                        <span class="font-bold text-green-600"><?= e($availableBooks) ?></span>
                    </div>

                    <div class="progress-track">
                        <div class="progress-fill bg-green-600" style="width: <?= e((string)$availabilityPercent) ?>%;"></div>
                    </div>

                    <p class="text-xs text-gray-500 mt-2"><?= e($availabilityPercent) ?>% available</p>
                </div>

                <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-600">Borrowed Copies</span>
                        <span class="font-bold text-blue-600"><?= e($totalBorrowed) ?></span>
                    </div>

                    <div class="progress-track">
                        <div class="progress-fill bg-blue-600" style="width: <?= e((string)$borrowedPercent) ?>%;"></div>
                    </div>

                    <p class="text-xs text-gray-500 mt-2"><?= e($borrowedPercent) ?>% currently borrowed</p>
                </div>
            </div>
        </div>

        <!-- LIBRARY STATUS -->
        <div class="dashboard-card xl:col-span-1">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Library Status</h2>
                    <p class="card-subtitle">System-wide operation totals.</p>
                </div>
            </div>

            <div>
                <div class="mini-stat">
                    <span class="text-gray-600">Unique Titles</span>
                    <span class="font-bold text-gray-900"><?= e($uniqueTitles) ?></span>
                </div>

                <div class="mini-stat">
                    <span class="text-gray-600">Categories</span>
                    <span class="font-bold text-gray-900"><?= e($categories) ?></span>
                </div>

                <div class="mini-stat">
                    <span class="text-gray-600">Total Borrowings</span>
                    <span class="font-bold text-gray-900"><?= e($totalBorrowings) ?></span>
                </div>

                <div class="mini-stat">
                    <span class="text-gray-600">Total Reservations</span>
                    <span class="font-bold text-gray-900"><?= e($totalReservations) ?></span>
                </div>
            </div>
        </div>

        <!-- LOW STOCK BOOKS -->
        <div class="dashboard-card xl:col-span-1">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Low Availability Books</h2>
                    <p class="card-subtitle">Books with 2 or fewer available copies.</p>
                </div>
            </div>

            <?php if (empty($lowStockBooks)): ?>
                <p class="text-gray-500 text-sm py-4">No low availability books.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($lowStockBooks as $book): ?>
                        <div class="border rounded-xl p-4">
                            <h3 class="font-bold text-gray-900"><?= e($book['title'] ?: 'Untitled Book') ?></h3>
                            <p class="text-sm text-gray-500"><?= e($book['author'] ?: 'Unknown Author') ?></p>
                            <p class="text-sm mt-2">
                                Available:
                                <span class="font-bold text-red-600"><?= e((int)$book['availableCopies']) ?></span>
                                /
                                <?= e((int)$book['totalCopies']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

 <!-- OVERDUE + RECENT ACTIVITY -->
    <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <!-- OVERDUE WATCHLIST -->
        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Overdue Watchlist</h2>
                    <p class="card-subtitle">Students who need follow-up.</p>
                </div>

                <a href="manage_borrowings.php" class="quick-action bg-red-600">View All</a>
            </div>

            <?php if (empty($overdueWatchlist)): ?>
                <p class="text-gray-500 text-center py-8">No overdue books right now.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="watch-table min-w-[700px]">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Book</th>
                                <th>Contact</th>
                                <th>Days Late</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($overdueWatchlist as $row): ?>
                                <tr>
                                    <td>
                                        <p class="font-bold text-gray-900"><?= e($row['studentName'] ?: 'Unknown Student') ?></p>
                                        <p class="text-xs text-gray-500"><?= e($row['student_id'] ?: 'N/A') ?></p>
                                    </td>

                                    <td><?= e($row['book_title'] ?: 'Unknown Book') ?></td>

                                    <td><?= e($row['contact_number']) ?></td>

                                    <td>
                                        <span class="badge-red">
                                            <?= e((int)$row['days_late']) ?> days
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- RECENT ACTIVITY -->
        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Recent Activity</h2>
                    <p class="card-subtitle">Latest borrowing, return, and reservation actions.</p>
                </div>
            </div>

            <?php if (empty($recentActivity)): ?>
                <p class="text-gray-500 text-center py-8">No recent activity.</p>
            <?php else: ?>
                <div class="activity-box">
                    <?php foreach ($recentActivity as $activity): ?>
                        <?php [$badgeClass, $dotColor] = dashboardActivityStyle($activity['type']); ?>

                        <div class="activity-item">
                            <span class="activity-dot <?= e($dotColor) ?>"></span>

                            <div class="flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs font-bold px-2 py-1 rounded-full <?= e($badgeClass) ?>">
                                        <?= e($activity['title']) ?>
                                    </span>

                                    <span class="text-xs text-gray-500">
                                        <?= timeAgo(date('Y-m-d H:i:s', $activity['date'])) ?>
                                    </span>
                                </div>

                                <p class="text-sm font-medium text-gray-900 mt-2">
                                    <?= e($activity['text']) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</body>
</html>