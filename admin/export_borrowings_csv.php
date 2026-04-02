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
header('Content-Disposition: attachment; filename=borrowings_' . date('Y-m-d_H-i-s') . '.csv');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'ID',
    'Student Name',
    'Student ID',
    'Course',
    'Year Level',
    'Book Title',
    'Author',
    'ISBN',
    'Borrow Date',
    'Due Date',
    'Return Date',
    'Status',
    'Penalty'
]);

$stmt = $pdo->query("
    SELECT
        b.id,
        b.studentName,
        b.student_id,
        b.course,
        b.yearlvl,
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
    ORDER BY b.id DESC
");

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['id'],
        $row['studentName'],
        $row['student_id'],
        $row['course'],
        $row['yearlvl'],
        $row['book_title'],
        $row['book_author'],
        $row['book_isbn'],
        $row['borrowDate'],
        $row['dueDate'],
        $row['returnDate'],
        $row['status'],
        $row['penalty']
    ]);
}

fclose($output);
exit;