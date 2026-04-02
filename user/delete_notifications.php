<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit();
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=sti_library;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    setLibraryDbTimezone($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    exit();
}

$userId = $_SESSION['user_id'] ?? null;
$studentId = $_SESSION['student_id'] ?? null;
$studentName = $_SESSION['fullname'] ?? null;

/* ✅ REPLACED BLOCK */
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("
        DELETE FROM notifications
        WHERE id = :id
        AND (
            user_id = :user_id
            OR student_id = :student_id
            OR student_name = :student_name
        )
    ");
    $stmt->execute([
        ':id' => $id,
        ':user_id' => $userId,
        ':student_id' => $studentId,
        ':student_name' => $studentName
    ]);
} else {
    $stmt = $pdo->prepare("
        DELETE FROM notifications
        WHERE (
            user_id = :user_id
            OR student_id = :student_id
            OR student_name = :student_name
        )
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':student_id' => $studentId,
        ':student_name' => $studentName
    ]);
}

echo json_encode(['success' => true]);