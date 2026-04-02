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
header('Content-Disposition: attachment; filename=reservations_' . date('Y-m-d_H-i-s') . '.csv');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'ID',
    'Student Name',
    'Student ID',
    'Book Title',
    'Author',
    'ISBN',
    'Reservation Date',
    'Expiry Date',
    'Status'
]);

$stmt = $pdo->query("
    SELECT
        r.id,
        r.studentName,
        r.student_id,
        bk.title AS book_title,
        bk.author AS book_author,
        bk.isbn AS book_isbn,
        r.reservationDate,
        r.expiryDate,
        r.status
    FROM reservations r
    LEFT JOIN books bk ON bk.id = r.book_id
    ORDER BY r.id DESC
");

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['id'],
        $row['studentName'],
        $row['student_id'],
        $row['book_title'],
        $row['book_author'],
        $row['book_isbn'],
        $row['reservationDate'],
        $row['expiryDate'],
        $row['status']
    ]);
}

fclose($output);
exit;