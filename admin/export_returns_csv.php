<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';
require_once "auth_check.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

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

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=returns_' . date('Y-m-d_H-i-s') . '.csv');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'ID',
    'Borrowing ID',
    'Student Name',
    'Student ID',
    'Course',
    'Year Level',
    'Borrow Date',
    'Due Date',
    'Return Date',
    'Days Late',
    'Penalty',
    'Remarks'
]);

$stmt = $pdo->query("
    SELECT
        id,
        borrowing_id,
        student_name,
        student_id,
        course,
        yearlvl,
        borrow_date,
        due_date,
        return_date,
        days_late,
        penalty,
        remarks
    FROM returns
    ORDER BY id DESC
");

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['id'],
        $row['borrowing_id'],
        $row['student_name'],
        $row['student_id'],
        $row['course'],
        $row['yearlvl'],
        $row['borrow_date'],
        $row['due_date'],
        $row['return_date'],
        $row['days_late'],
        $row['penalty'],
        $row['remarks']
    ]);
}

fclose($output);
exit;