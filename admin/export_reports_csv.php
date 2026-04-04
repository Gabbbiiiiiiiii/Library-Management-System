<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';
require_once "auth_check.php";

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

/* ================= REPORT DATA ================= */

/* Summary */
$totalBooks = (int)$pdo->query("SELECT COALESCE(SUM(totalCopies), 0) FROM books")->fetchColumn();
$uniqueTitles = (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$availableCopies = (int)$pdo->query("SELECT COALESCE(SUM(availableCopies), 0) FROM books")->fetchColumn();
$activeBorrowings = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed'")->fetchColumn();
$overdueBorrowings = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'overdue'")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings $borrowWhere");
$stmt->execute($borrowParams);
$totalBorrowings = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM returns $returnWhere");
$stmt->execute($returnParams);
$totalReturns = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations $reservationWhere");
$stmt->execute($reservationParams);
$totalReservations = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(penalty), 0) FROM returns $returnWhere");
$stmt->execute($returnParams);
$totalPenaltyCollected = (float)$stmt->fetchColumn();

/* Top books */
$stmt = $pdo->prepare("
    SELECT
        bk.title,
        bk.author,
        COALESCE(bk.category, 'Uncategorized') AS category,
        COUNT(br.id) AS times_borrowed
    FROM borrowings br
    LEFT JOIN books bk ON br.book_id = bk.id
    $borrowWhere
    GROUP BY br.book_id, bk.title, bk.author, bk.category
    ORDER BY times_borrowed DESC, bk.title ASC
    LIMIT 10
");
$stmt->execute($borrowParams);
$topBooks = $stmt->fetchAll();

/* Top students */
$stmt = $pdo->prepare("
    SELECT
        COALESCE(studentName, 'Unknown Student') AS student_name,
        COALESCE(student_id, '—') AS student_id,
        COALESCE(course, '—') AS course,
        COALESCE(yearlvl, '—') AS yearlvl,
        COUNT(id) AS total_borrowings,
        COALESCE(SUM(penalty), 0) AS total_penalty
    FROM borrowings
    $borrowWhere
    GROUP BY studentName, student_id, course, yearlvl
    ORDER BY total_borrowings DESC, student_name ASC
    LIMIT 10
");
$stmt->execute($borrowParams);
$topStudents = $stmt->fetchAll();

/* Category usage */
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

/* Returns summary */
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
$returnSummary = $stmt->fetch();

/* Reservation summary */
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
$reservationSummary = $stmt->fetch();

/* ================= CSV OUTPUT ================= */
$filename = 'library_reports_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

$output = fopen('php://output', 'w');

function csvText($value): string {
    return (string)($value ?? '');
}

function csvExcelText($value): string {
    return "\t" . (string)($value ?? '');
}

/* Title */
fputcsv($output, ['STI Library Reports']);
fputcsv($output, ['Filter', $filter]);

if ($filter === 'custom') {
    fputcsv($output, ['Start Date', $startDate]);
    fputcsv($output, ['End Date', $endDate]);
}

fputcsv($output, []);

/* Summary */
fputcsv($output, ['SUMMARY']);
fputcsv($output, ['Total Book Copies', $totalBooks]);
fputcsv($output, ['Unique Titles', $uniqueTitles]);
fputcsv($output, ['Available Copies', $availableCopies]);
fputcsv($output, ['Borrowings (Selected Period)', $totalBorrowings]);
fputcsv($output, ['Returns (Selected Period)', $totalReturns]);
fputcsv($output, ['Reservations (Selected Period)', $totalReservations]);
fputcsv($output, ['Active Borrowings (Current)', $activeBorrowings]);
fputcsv($output, ['Overdue Books (Current)', $overdueBorrowings]);
fputcsv($output, ['Penalty Collected (Selected Period)', number_format($totalPenaltyCollected, 2, '.', '')]);

fputcsv($output, []);

/* Most Borrowed Books */
fputcsv($output, ['MOST BORROWED BOOKS']);
fputcsv($output, ['Title', 'Author', 'Category', 'Times Borrowed']);
foreach ($topBooks as $book) {
    fputcsv($output, [
        csvText($book['title']),
        csvText($book['author']),
        csvText($book['category']),
        (int)$book['times_borrowed']
    ]);
}

fputcsv($output, []);

/* Most Active Students */
fputcsv($output, ['MOST ACTIVE STUDENTS']);
fputcsv($output, ['Student Name', 'Student ID', 'Course', 'Year Level', 'Total Borrowings', 'Total Penalty']);
foreach ($topStudents as $student) {
    fputcsv($output, [
        csvText($student['student_name']),
        csvExcelText($student['student_id']), // keep as text in Excel
        csvText($student['course']),
        csvText($student['yearlvl']),
        (int)$student['total_borrowings'],
        number_format((float)$student['total_penalty'], 2, '.', '')
    ]);
}

fputcsv($output, []);

/* Borrowings by Category */
fputcsv($output, ['BORROWINGS BY CATEGORY']);
fputcsv($output, ['Category', 'Total Borrowings']);
foreach ($categoryStats as $category) {
    fputcsv($output, [
        csvText($category['category']),
        (int)$category['total_borrowings']
    ]);
}

fputcsv($output, []);

/* Returns Summary */
fputcsv($output, ['RETURNS SUMMARY']);
fputcsv($output, ['Returned Books', (int)($returnSummary['total_returned'] ?? 0)]);
fputcsv($output, ['Returned On Time', (int)($returnSummary['returned_on_time'] ?? 0)]);
fputcsv($output, ['Returned With Penalty', (int)($returnSummary['returned_with_penalty'] ?? 0)]);
fputcsv($output, ['Total Days Late', (int)($returnSummary['total_days_late'] ?? 0)]);
fputcsv($output, ['Highest Penalty', number_format((float)($returnSummary['highest_penalty'] ?? 0), 2, '.', '')]);

fputcsv($output, []);

/* Reservation Summary */
fputcsv($output, ['RESERVATION SUMMARY']);
fputcsv($output, ['Pending', (int)($reservationSummary['pending_count'] ?? 0)]);
fputcsv($output, ['Ready', (int)($reservationSummary['ready_count'] ?? 0)]);
fputcsv($output, ['Borrowed', (int)($reservationSummary['borrowed_count'] ?? 0)]);
fputcsv($output, ['Cancelled', (int)($reservationSummary['cancelled_count'] ?? 0)]);
fputcsv($output, ['Expired', (int)($reservationSummary['expired_count'] ?? 0)]);

fclose($output);
exit;