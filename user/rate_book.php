<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pdo = new PDO(
        "mysql:host=localhost;dbname=sti_library;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    $bookId = (int)($_POST['book_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $userId = $_SESSION['user_id'] ?? 0;

    if ($bookId && $rating >= 1 && $rating <= 5 && $userId) {

        // prevent duplicate rating → update instead
        $stmt = $pdo->prepare("
            INSERT INTO ratings (book_id, user_id, rating)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating)
        ");

        $stmt->execute([$bookId, $userId, $rating]);

        echo "success";
    }
}