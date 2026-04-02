<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
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
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$userId = $_SESSION['user_id'] ?? null;
$studentId = $_SESSION['student_id'] ?? null;
$studentName = $_SESSION['fullname'] ?? null;

$notifications = fetchStudentNotifications($pdo, $userId, $studentId, $studentName, 10);
$unreadCount = countUnreadStudentNotifications($pdo, $userId, $studentId, $studentName);

echo json_encode([
    'unreadCount' => $unreadCount,
    'notifications' => $notifications
]);