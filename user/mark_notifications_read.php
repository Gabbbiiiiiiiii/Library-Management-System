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

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = isset($input['id']) ? (int)$input['id'] : 0;

if ($notificationId > 0) {
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1,
            read_at = NOW()
        WHERE id = :id
          AND (
                user_id = :user_id
                OR student_id = :student_id
                OR student_name = :student_name
          )
    ");
    $stmt->execute([
        ':id' => $notificationId,
        ':user_id' => $userId,
        ':student_id' => $studentId,
        ':student_name' => $studentName
    ]);
} else {
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1,
            read_at = NOW()
        WHERE (
            user_id = :user_id
            OR student_id = :student_id
            OR student_name = :student_name
        )
        AND is_read = 0
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':student_id' => $studentId,
        ':student_name' => $studentName
    ]);
}

echo json_encode(['success' => true]);