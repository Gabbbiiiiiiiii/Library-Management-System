<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/library_helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_check.php';

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
$reservationCreatedWhere = "";

$borrowParams = [];
$returnParams = [];
$reservationCreatedParams = [];

switch ($filter) {
    case 'today':
        $borrowWhere = "WHERE DATE(borrowDate) = CURDATE()";
        $returnWhere = "WHERE DATE(return_date) = CURDATE()";
        $reservationCreatedWhere = "WHERE DATE(reservationDate) = CURDATE()";
        break;

    case 'this_week':
        $borrowWhere = "WHERE YEARWEEK(borrowDate, 1) = YEARWEEK(CURDATE(), 1)";
        $returnWhere = "WHERE YEARWEEK(return_date, 1) = YEARWEEK(CURDATE(), 1)";
        $reservationCreatedWhere = "WHERE YEARWEEK(reservationDate, 1) = YEARWEEK(CURDATE(), 1)";
        break;

    case 'this_month':
        $borrowWhere = "WHERE MONTH(borrowDate) = MONTH(CURDATE()) AND YEAR(borrowDate) = YEAR(CURDATE())";
        $returnWhere = "WHERE MONTH(return_date) = MONTH(CURDATE()) AND YEAR(return_date) = YEAR(CURDATE())";
        $reservationCreatedWhere = "WHERE MONTH(reservationDate) = MONTH(CURDATE()) AND YEAR(reservationDate) = YEAR(CURDATE())";
        break;

    case 'this_year':
        $borrowWhere = "WHERE YEAR(borrowDate) = YEAR(CURDATE())";
        $returnWhere = "WHERE YEAR(return_date) = YEAR(CURDATE())";
        $reservationCreatedWhere = "WHERE YEAR(reservationDate) = YEAR(CURDATE())";
        break;

    case 'custom':
        if ($startDate !== '' && $endDate !== '') {
            $borrowWhere = "WHERE DATE(borrowDate) BETWEEN :start_date AND :end_date";
            $returnWhere = "WHERE DATE(return_date) BETWEEN :start_date AND :end_date";
            $reservationCreatedWhere = "WHERE DATE(reservationDate) BETWEEN :start_date AND :end_date";

            $borrowParams = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            $returnParams = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            $reservationCreatedParams = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];
        } else {
            $filter = 'this_month';
            $borrowWhere = "WHERE MONTH(borrowDate) = MONTH(CURDATE()) AND YEAR(borrowDate) = YEAR(CURDATE())";
            $returnWhere = "WHERE MONTH(return_date) = MONTH(CURDATE()) AND YEAR(return_date) = YEAR(CURDATE())";
            $reservationCreatedWhere = "WHERE MONTH(reservationDate) = MONTH(CURDATE()) AND YEAR(reservationDate) = YEAR(CURDATE())";
        }
        break;

    default:
        $filter = 'this_month';
        $borrowWhere = "WHERE MONTH(borrowDate) = MONTH(CURDATE()) AND YEAR(borrowDate) = YEAR(CURDATE())";
        $returnWhere = "WHERE MONTH(return_date) = MONTH(CURDATE()) AND YEAR(return_date) = YEAR(CURDATE())";
        $reservationCreatedWhere = "WHERE MONTH(reservationDate) = MONTH(CURDATE()) AND YEAR(reservationDate) = YEAR(CURDATE())";
        break;
}

/* ================= RETURN DATE FILTER FOR BORROWINGS TABLE ================= */

$returnBorrowWhere = "";
$returnBorrowParams = [];

switch ($filter) {
    case 'today':
        $returnBorrowWhere = "WHERE DATE(b.returnDate) = CURDATE()";
        break;

    case 'this_week':
        $returnBorrowWhere = "WHERE YEARWEEK(b.returnDate, 1) = YEARWEEK(CURDATE(), 1)";
        break;

    case 'this_month':
        $returnBorrowWhere = "WHERE MONTH(b.returnDate) = MONTH(CURDATE()) AND YEAR(b.returnDate) = YEAR(CURDATE())";
        break;

    case 'this_year':
        $returnBorrowWhere = "WHERE YEAR(b.returnDate) = YEAR(CURDATE())";
        break;

    case 'custom':
        if ($startDate !== '' && $endDate !== '') {
            $returnBorrowWhere = "WHERE DATE(b.returnDate) BETWEEN :start_date AND :end_date";
            $returnBorrowParams = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];
        } else {
            $returnBorrowWhere = "WHERE MONTH(b.returnDate) = MONTH(CURDATE()) AND YEAR(b.returnDate) = YEAR(CURDATE())";
        }
        break;

    default:
        $returnBorrowWhere = "WHERE MONTH(b.returnDate) = MONTH(CURDATE()) AND YEAR(b.returnDate) = YEAR(CURDATE())";
        break;
}

function activeFilterClass(string $value, string $filter): string
{
    return $value === $filter
        ? 'bg-purple-600 text-white shadow-sm ring-2 ring-purple-200'
        : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50';
}

/* ================= SUMMARY CARDS ================= */

$totalBooks = (int)$pdo->query("SELECT COALESCE(SUM(totalCopies), 0) FROM books")->fetchColumn();
$availableCopies = (int)$pdo->query("SELECT COALESCE(SUM(availableCopies), 0) FROM books")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings $borrowWhere");
$stmt->execute($borrowParams);
$totalBorrowings = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM returns $returnWhere");
$stmt->execute($returnParams);
$totalReturns = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations $reservationCreatedWhere");
$stmt->execute($reservationCreatedParams);
$totalReservationsCreated = (int)$stmt->fetchColumn();

$activeBorrowings = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed'")->fetchColumn();
$overdueBorrowings = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'overdue'")->fetchColumn();

/* ================= PENALTY SUMMARY ================= */

$overallPenaltyCollected = (float)$pdo->query("
    SELECT COALESCE(SUM(penalty), 0)
    FROM returns
")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(penalty), 0)
    FROM returns
    $returnWhere
");
$stmt->execute($returnParams);
$totalPenaltyCollected = (float)$stmt->fetchColumn();

$estimatedPendingPenalty = (float)$pdo->query("
    SELECT COALESCE(SUM(GREATEST(DATEDIFF(NOW(), dueDate), 0) * 10), 0)
    FROM borrowings
    WHERE status = 'overdue'
      AND returnDate IS NULL
      AND dueDate IS NOT NULL
")->fetchColumn();

$totalPenaltyCases = (int)$pdo->query("
    SELECT COUNT(*)
    FROM returns
    WHERE penalty > 0
")->fetchColumn();

$totalStudentsWithPenalty = (int)$pdo->query("
    SELECT COUNT(DISTINCT student_id)
    FROM returns
    WHERE penalty > 0
")->fetchColumn();


/* ================= DETAILED BORROWING REPORT ================= */

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.studentName,
        b.student_id,
        COALESCE(u.course, b.course, 'N/A') AS course,
        COALESCE(u.yearlvl, b.yearlvl, 'N/A') AS yearlvl,
        COALESCE(u.contact_number, 'N/A') AS contact_number,
        bk.title AS book_title,
        bk.author AS book_author,
        bk.isbn AS book_isbn,
        b.borrowDate,
        b.dueDate,
        b.returnDate,
        b.status,
        b.penalty
    FROM borrowings b
    LEFT JOIN books bk ON bk.id = b.book_id
    LEFT JOIN users u ON u.student_id = b.student_id
    $borrowWhere
    ORDER BY b.borrowDate DESC, b.id DESC
    LIMIT 20
");
$stmt->execute($borrowParams);
$detailedBorrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= DETAILED RETURNS REPORT ================= */

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.studentName,
        b.student_id,
        COALESCE(u.course, b.course, 'N/A') AS course,
        COALESCE(u.yearlvl, b.yearlvl, 'N/A') AS yearlvl,
        COALESCE(u.contact_number, 'N/A') AS contact_number,
        bk.title AS book_title,
        bk.author AS book_author,
        b.returnDate AS return_date,
        GREATEST(DATEDIFF(b.returnDate, b.dueDate), 0) AS days_late,
        b.penalty
    FROM borrowings b
    LEFT JOIN books bk ON bk.id = b.book_id
    LEFT JOIN users u ON u.student_id = b.student_id
    $returnBorrowWhere
      AND b.returnDate IS NOT NULL
    ORDER BY b.returnDate DESC, b.id DESC
    LIMIT 20
");
$stmt->execute($returnBorrowParams);
$detailedReturns = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= DETAILED OVERDUE REPORT ================= */

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.studentName,
        b.student_id,
        COALESCE(u.course, b.course, 'N/A') AS course,
        COALESCE(u.yearlvl, b.yearlvl, 'N/A') AS yearlvl,
        COALESCE(u.contact_number, 'N/A') AS contact_number,
        bk.title AS book_title,
        bk.author AS book_author,
        b.borrowDate,
        b.dueDate,
        GREATEST(DATEDIFF(NOW(), b.dueDate), 0) AS days_late,
        GREATEST(DATEDIFF(NOW(), b.dueDate), 0) * 10 AS estimated_penalty,
        b.status
    FROM borrowings b
    LEFT JOIN books bk ON bk.id = b.book_id
    LEFT JOIN users u ON u.student_id = b.student_id
    WHERE b.status = 'overdue'
      AND b.returnDate IS NULL
    ORDER BY b.dueDate ASC, b.id ASC
    LIMIT 20
");
$stmt->execute();
$detailedOverdue = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= DETAILED PENALTY REPORT ================= */

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.studentName,
        b.student_id,
        COALESCE(u.course, b.course, 'N/A') AS course,
        COALESCE(u.yearlvl, b.yearlvl, 'N/A') AS yearlvl,
        COALESCE(u.contact_number, 'N/A') AS contact_number,
        bk.title AS book_title,
        bk.author AS book_author,
        b.returnDate AS return_date,
        GREATEST(DATEDIFF(b.returnDate, b.dueDate), 0) AS days_late,
        b.penalty
    FROM borrowings b
    LEFT JOIN books bk ON bk.id = b.book_id
    LEFT JOIN users u ON u.student_id = b.student_id
    $returnBorrowWhere
      AND b.returnDate IS NOT NULL
      AND b.penalty > 0
    ORDER BY b.penalty DESC, b.returnDate DESC
    LIMIT 20
");
$stmt->execute($returnBorrowParams);
$detailedPenalties = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= DETAILED RESERVATIONS REPORT ================= */

$stmt = $pdo->prepare("
    SELECT
        r.id,
        COALESCE(u.fullname, 'Unknown Student') AS studentName,
        r.student_id,
        COALESCE(u.course, 'N/A') AS course,
        COALESCE(u.yearlvl, 'N/A') AS yearlvl,
        COALESCE(u.contact_number, 'N/A') AS contact_number,
        bk.title AS book_title,
        bk.author AS book_author,
        r.reservationDate,
        r.expiryDate,
        r.status
    FROM reservations r
    LEFT JOIN books bk ON bk.id = r.book_id
    LEFT JOIN users u ON u.student_id = r.student_id
    $reservationCreatedWhere
    ORDER BY r.reservationDate DESC, r.id DESC
    LIMIT 20
");
$stmt->execute($reservationCreatedParams);
$detailedReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link rel="icon" type="image/png" href="/assets/images/logo1.png">
    <link rel="shortcut icon" href="/assets/images/logo1.png">
    <link href="../assets/css/output.css" rel="stylesheet">
    
    <style>
    body {
        background: #f3f4f6;
    }

    .report-page {
        max-width: 1489px;
        margin: 0 auto;
        padding: 145px 24px 40px;
    }

    .report-hero {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: white;
        border-radius: 24px;
        padding: 28px;
        margin-bottom: 24px;
        box-shadow: 0 18px 40px rgba(79, 70, 229, 0.20);
    }

    .report-hero h1 {
        font-size: 32px;
        font-weight: 800;
        margin-bottom: 6px;
    }

    .report-hero p {
        color: rgba(255, 255, 255, 0.86);
        font-size: 15px;
    }

    .clean-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        padding: 22px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    }

    .stat-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        padding: 22px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
    }

    .stat-label {
        font-size: 13px;
        font-weight: 600;
        color: #6b7280;
    }

    .stat-value {
        font-size: 30px;
        font-weight: 800;
        margin-top: 12px;
        color: #111827;
    }

    .export-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        padding: 10px 14px;
        color: white;
        font-size: 14px;
        font-weight: 700;
        transition: 0.2s ease;
        white-space: nowrap;
    }

    .export-btn:hover {
        transform: translateY(-1px);
        filter: brightness(0.95);
    }

    .report-table-scroll {
        width: 100%;
        max-height: 420px;
        overflow: auto;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
    }

    .report-table {
        width: 100%;
        min-width: 1100px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 14px;
    }

    .report-table thead th {
        position: sticky;
        top: 0;
        z-index: 5;
        background: #f9fafb;
        color: #374151;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 800;
        padding: 14px 14px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        white-space: nowrap;
    }

    .report-table tbody td {
        padding: 14px;
        border-bottom: 1px solid #f1f5f9;
        color: #374151;
        vertical-align: middle;
        white-space: nowrap;
    }

    .report-table tbody tr:hover {
        background: #f9fafb;
    }

    .report-table tbody tr:last-child td {
        border-bottom: none;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 700;
        background: #eef2ff;
        color: #4338ca;
    }

  
   .report-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.report-card-title {
    font-size: 22px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 4px;
}

.report-card-subtitle {
    font-size: 14px;
    color: #6b7280;
}

.report-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.report-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    padding: 10px 16px;
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    transition: 0.2s ease;
}

.report-action-btn:hover {
    transform: translateY(-1px);
    filter: brightness(0.95);
}

.penalty-btn {
    background: #ca8a04;
}

.penalty-summary-btn {
    background: #ea580c;
}

@media (max-width: 768px) {
    .report-card-header {
        flex-direction: column;
        align-items: stretch;
    }

    .report-actions {
        justify-content: flex-start;
    }

    .report-action-btn {
        width: 100%;
    }
}
       

.report-table thead th {
    padding: 13px 16px;
}

.report-table tbody td {
    padding: 13px 16px;
}


    @media (max-width: 768px) {
        .report-page {
            padding: 130px 14px 28px;
        }

        .report-hero {
            padding: 22px;
            border-radius: 18px;
        }

        .report-hero h1 {
            font-size: 26px;
        }

        .clean-card,
        .stat-card {
            padding: 18px;
            border-radius: 16px;
        }
    }
</style>
</head>

<body class="bg-gray-100">

<?php include 'header.php'; ?>

<main class="report-page">

    <div class="report-hero">
        <h1>Library Reports</h1>
        <p>
            Detailed transaction reports for borrowings, returns, overdue books, penalties, reservations, and library usage.
        </p>
    </div>

    <!-- EXPORT BUTTONS -->
    <div class="clean-card mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Export Reports</h2>

        <div class="flex flex-wrap gap-3">
            <a href="export_reports_excel.php?type=borrowings&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
               class="export-btn bg-purple-600">
                Export Borrowings
            </a>

            <a href="export_reports_excel.php?type=returns&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
               class="export-btn bg-blue-600">
                Export Returns
            </a>

            <a href="export_reports_excel.php?type=overdue&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
               class="export-btn bg-red-600">
                Export Overdue Books
            </a>

            <a href="export_reports_excel.php?type=penalties&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
               class="export-btn bg-yellow-600">
                Export Penalties
            </a>
            
            <a href="export_reports_excel.php?type=penalty_summary&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
               class="report-action-btn penalty-summary-btn">
                Export Summary
            </a>

            <a href="export_reports_excel.php?type=reservations&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
               class="export-btn bg-green-600">
                Export Reservations
            </a>
        </div>
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
                    <input type="date" name="start_date" value="<?= e($startDate) ?>"
                           class="w-full border rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-purple-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" name="end_date" value="<?= e($endDate) ?>"
                           class="w-full border rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-purple-500 focus:outline-none">
                </div>

                <div>
                    <input type="hidden" name="filter" value="custom">
                    <button type="submit"
                            class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-xl px-4 py-2.5 transition">
                        Apply Custom Range
                    </button>
                </div>

                <div>
                    <a href="reports.php"
                       class="block w-full text-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-xl px-4 py-2.5 transition">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <p class="stat-label">Total Book Copies</p>
            <h2 class="stat-value"><?= e($totalBooks) ?></h2>
        </div>

    

        <div class="stat-card">
            <p class="stat-label">Available Copies</p>
            <h2 class="stat-value"><?= e($availableCopies) ?></h2>
        </div>

        <div class="stat-card">
            <p class="stat-label">Borrowings Selected Period</p>
            <h2 class="stat-value"><?= e($totalBorrowings) ?></h2>
        </div>

        <div class="stat-card">
            <p class="stat-label">Returns Selected Period</p>
            <h2 class="stat-value"><?= e($totalReturns) ?></h2>
        </div>

        <div class="stat-card">
            <p class="stat-label">Reservations Selected Period</p>
            <h2 class="stat-value"><?= e($totalReservationsCreated) ?></h2>
        </div>

        <div class="stat-card">
            <p class="stat-label">Active Borrowings Current</p>
           <h2 class="stat-value"><?= e($activeBorrowings) ?></h2>
        </div>

        <div class="stat-card">
            <p class="stat-label">Overdue Books Current</p>
            <h2 class="stat-value"><?= e($overdueBorrowings) ?></h2>
        </div>
    </div>

    <!-- PENALTY CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <p class="text-sm text-gray-500 mb-2">Collected Penalties Selected Period</p>
            <h2 class="text-3xl font-bold text-yellow-600">
                ₱<?= number_format($totalPenaltyCollected, 2) ?>
            </h2>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <p class="text-sm text-gray-500 mb-2">Collected Penalties Overall</p>
            <h2 class="text-3xl font-bold text-yellow-600">
                ₱<?= number_format($overallPenaltyCollected, 2) ?>
            </h2>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <p class="text-sm text-gray-500 mb-2">Estimated Pending Penalties</p>
            <h2 class="text-3xl font-bold text-red-600">
                ₱<?= number_format($estimatedPendingPenalty, 2) ?>
            </h2>
            <p class="text-xs text-gray-500 mt-2">Based on current overdue books.</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <p class="text-sm text-gray-500 mb-2">Students With Penalties</p>
            <h2 class="text-3xl font-bold text-gray-900">
                <?= e($totalStudentsWithPenalty) ?>
            </h2>
            <p class="text-xs text-gray-500 mt-2">
                Total penalty cases: <?= e($totalPenaltyCases) ?>
            </p>
        </div>
    </div>

    <!-- DETAILED REPORTS -->
    <div class="mt-10 space-y-8">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Transaction Reports</h2>
            <p class="text-gray-600 mt-1">
                View actual borrowing, return, overdue, penalty, and reservation records based on the selected period.
            </p>
        </div>

        <!-- BORROWING TRANSACTIONS REPORT -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Borrowing Transactions Report</h2>
                    <p class="text-sm text-gray-500">Latest borrowing records based on the selected period.</p>
                </div>

                <a href="export_reports_excel.php?type=borrowings&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
                   class="rounded-lg bg-purple-600 px-4 py-2 text-white hover:bg-purple-700 text-sm">
                    Export Borrowings
                </a>
            </div>

            <div class="report-table-scroll">
                <table class="report-table">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-gray-600">
                            <th class="py-3 px-3">Student</th>
                            <th class="py-3 px-3">Student ID</th>
                            <th class="py-3 px-3">Course/Year</th>
                            <th class="py-3 px-3">Contact</th>
                            <th class="py-3 px-3">Book</th>
                            <th class="py-3 px-3">Borrow Date</th>
                            <th class="py-3 px-3">Due Date</th>
                            <th class="py-3 px-3">Return Date</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3">Penalty</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($detailedBorrowings)): ?>
                            <tr>
                                <td colspan="10" class="py-4 px-3 text-center text-gray-500">
                                    No borrowing records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detailedBorrowings as $row): ?>
                                <tr class="border-b last:border-b-0">
                                    <td class="py-3 px-3 font-medium"><?= e($row['studentName']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['student_id']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['course']) ?> • <?= e($row['yearlvl']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['contact_number']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['book_title'] ?: 'Unknown Book') ?></td>
                                    <td class="py-3 px-3"><?= formatDateText($row['borrowDate']) ?></td>
                                    <td class="py-3 px-3"><?= formatDateText($row['dueDate']) ?></td>
                                    <td class="py-3 px-3"><?= formatDateText($row['returnDate']) ?></td>
                                    <td>
                                        <span class="status-pill">
                                            <?= e(ucfirst($row['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-yellow-600">
                                        ₱<?= number_format((float)$row['penalty'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RETURNS REPORT -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Returns Report</h2>
                    <p class="text-sm text-gray-500">Actual returned book records based on the selected period.</p>
                </div>

                <a href="export_reports_excel.php?type=returns&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
                   class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 text-sm">
                    Export Returns
                </a>
            </div>

            <div class="report-table-scroll">
                <table class="report-table">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-gray-600">
                            <th class="py-3 px-3">Student</th>
                            <th class="py-3 px-3">Student ID</th>
                            <th class="py-3 px-3">Course/Year</th>
                            <th class="py-3 px-3">Contact</th>
                            <th class="py-3 px-3">Book</th>
                            <th class="py-3 px-3">Return Date</th>
                            <th class="py-3 px-3">Days Late</th>
                            <th class="py-3 px-3">Penalty</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($detailedReturns)): ?>
                            <tr>
                                <td colspan="8" class="py-4 px-3 text-center text-gray-500">
                                    No return records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detailedReturns as $row): ?>
                                <tr class="border-b last:border-b-0">
                                    <td class="py-3 px-3 font-medium"><?= e($row['studentName']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['student_id']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['course']) ?> • <?= e($row['yearlvl']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['contact_number']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['book_title'] ?: 'Unknown Book') ?></td>
                                    <td class="py-3 px-3"><?= formatDateText($row['return_date']) ?></td>
                                    <td class="py-3 px-3"><?= e((int)$row['days_late']) ?></td>
                                    <td class="py-3 px-3 font-semibold text-yellow-600">
                                        ₱<?= number_format((float)$row['penalty'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- OVERDUE BOOKS REPORT -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Overdue Books Report</h2>
                    <p class="text-sm text-gray-500">Students with books that have not been returned yet.</p>
                </div>

                <a href="export_reports_excel.php?type=overdue&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
                   class="rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700 text-sm">
                    Export Overdue
                </a>
            </div>

            <div class="report-table-scroll">
                <table class="report-table">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-gray-600">
                            <th class="py-3 px-3">Student</th>
                            <th class="py-3 px-3">Student ID</th>
                            <th class="py-3 px-3">Course/Year</th>
                            <th class="py-3 px-3">Contact</th>
                            <th class="py-3 px-3">Book</th>
                            <th class="py-3 px-3">Borrow Date</th>
                            <th class="py-3 px-3">Due Date</th>
                            <th class="py-3 px-3">Days Late</th>
                            <th class="py-3 px-3">Estimated Penalty</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($detailedOverdue)): ?>
                            <tr>
                                <td colspan="9" class="py-4 px-3 text-center text-gray-500">
                                    No overdue books found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detailedOverdue as $row): ?>
                                <tr class="border-b last:border-b-0">
                                    <td class="py-3 px-3 font-medium"><?= e($row['studentName']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['student_id']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['course']) ?> • <?= e($row['yearlvl']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['contact_number']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['book_title'] ?: 'Unknown Book') ?></td>
                                    <td class="py-3 px-3"><?= formatDateText($row['borrowDate']) ?></td>
                                    <td class="py-3 px-3"><?= formatDateText($row['dueDate']) ?></td>
                                    <td class="py-3 px-3 font-semibold text-red-600"><?= e((int)$row['days_late']) ?></td>
                                    <td class="py-3 px-3 font-semibold text-yellow-600">
                                        ₱<?= number_format((float)$row['estimated_penalty'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PENALTY REPORT -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="report-card-header">
                <div>
                    <h2 class="report-card-title">Penalty Report</h2>
                    <p class="report-card-subtitle">Detailed penalty records from returned books.</p>
                </div>

                <div class="report-actions">
                    <a href="export_reports_excel.php?type=penalties&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
                       class="report-action-btn penalty-btn">
                        Export Penalties
                    </a>

                    <a href="export_reports_excel.php?type=penalty_summary&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
                       class="report-action-btn penalty-summary-btn">
                        Export Summary
                    </a>
                </div>
            </div>

            <div class="report-table-scroll">
                <table class="report-table">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-gray-600">
                            <th class="py-3 px-3">Student</th>
                            <th class="py-3 px-3">Student ID</th>
                            <th class="py-3 px-3">Course/Year</th>
                            <th class="py-3 px-3">Contact</th>
                            <th class="py-3 px-3">Book</th>
                            <th class="py-3 px-3">Return Date</th>
                            <th class="py-3 px-3">Days Late</th>
                            <th class="py-3 px-3">Penalty</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($detailedPenalties)): ?>
                            <tr>
                                <td colspan="8" class="py-4 px-3 text-center text-gray-500">
                                    No penalty records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detailedPenalties as $row): ?>
                                <tr class="border-b last:border-b-0">
                                    <td class="py-3 px-3 font-medium"><?= e($row['studentName']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['student_id']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['course']) ?> • <?= e($row['yearlvl']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['contact_number']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['book_title'] ?: 'Unknown Book') ?></td>
                                    <td class="py-3 px-3"><?= formatDateText($row['return_date']) ?></td>
                                    <td class="py-3 px-3"><?= e((int)$row['days_late']) ?></td>
                                    <td class="py-3 px-3 font-semibold text-yellow-600">
                                        ₱<?= number_format((float)$row['penalty'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RESERVATIONS REPORT -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Reservations Report</h2>
                    <p class="text-sm text-gray-500">Actual reservation records based on the selected period.</p>
                </div>

                <a href="export_reports_excel.php?type=reservations&filter=<?= urlencode($filter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
                   class="rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700 text-sm">
                    Export Reservations
                </a>
            </div>

            <div class="report-table-scroll">
                <table class="report-table">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-gray-600">
                            <th class="py-3 px-3">Student</th>
                            <th class="py-3 px-3">Student ID</th>
                            <th class="py-3 px-3">Course/Year</th>
                            <th class="py-3 px-3">Contact</th>
                            <th class="py-3 px-3">Book</th>
                            <th class="py-3 px-3">Reservation Date</th>
                            <th class="py-3 px-3">Expiry Date</th>
                            <th class="py-3 px-3">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($detailedReservations)): ?>
                            <tr>
                                <td colspan="8" class="py-4 px-3 text-center text-gray-500">
                                    No reservation records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detailedReservations as $row): ?>
                                <tr class="border-b last:border-b-0">
                                    <td class="py-3 px-3 font-medium"><?= e($row['studentName']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['student_id']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['course']) ?> • <?= e($row['yearlvl']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['contact_number']) ?></td>
                                    <td class="py-3 px-3"><?= e($row['book_title'] ?: 'Unknown Book') ?></td>
                                    <td class="py-3 px-3"><?= formatDateText($row['reservationDate']) ?></td>
                                    <td class="py-3 px-3"><?= formatDateText($row['expiryDate']) ?></td>
                                    <td>
                                        <span class="status-pill">
                                            <?= e(ucfirst($row['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

</body>
</html>