<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Library Shared Helpers
|--------------------------------------------------------------------------
| Reusable helpers for:
| - timezone
| - escaping
| - date formatting
| - relative time
| - overdue checks
| - penalty calculation
| - student ownership checks
|--------------------------------------------------------------------------
*/

if (!defined('LIBRARY_HELPERS_LOADED')) {
    define('LIBRARY_HELPERS_LOADED', true);

    date_default_timezone_set('Asia/Manila');

    function setLibraryDbTimezone(PDO $pdo): void
    {
        $pdo->exec("SET time_zone = '+08:00'");
    }

    function e($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    function formatDateText(?string $date, string $format = 'M d, Y h:i A'): string
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '—';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '—';
        }

        return date($format, $timestamp);
    }

    function timeAgo(?string $datetime): string
    {
        if (empty($datetime)) {
            return '—';
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '—';
        }

        $diff = time() - $timestamp;

        if ($diff < 0) {
            return formatDateText($datetime);
        }

        if ($diff < 60) {
            return 'Just now';
        }

        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
        }

        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
        }

        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
        }

        return formatDateText($datetime);
    }

    function nowDateTime(): string
    {
        return date('Y-m-d H:i:s');
    }

    function nextBorrowDueDateTime(): string
    {
        return date('Y-m-d 08:59:59', strtotime('+1 day'));
    }

    function nextReservationExpiryDateTime(int $days = 3): string {
        $date = new DateTime();

        // If past 5PM, start from tomorrow
        if ((int)$date->format('H') >= 17) {
            $date->modify('+1 day');
        }

        $date->modify("+{$days} days");
        $date->setTime(17, 0, 0);

        return $date->format('Y-m-d H:i:s');
    }

    function isOverdue(?string $dueDate, ?string $returnDate = null): bool
    {
        if (empty($dueDate)) {
            return false;
        }

        $dueTs = strtotime($dueDate);
        if ($dueTs === false) {
            return false;
        }

        $compareTs = $returnDate ? strtotime($returnDate) : time();
        if ($compareTs === false) {
            $compareTs = time();
        }

        return $compareTs > $dueTs;
    }

    function getDaysLateAdvanced(?string $dueDate, ?string $returnDate = null): int
    {
        if (empty($dueDate)) {
            return 0;
        }

        $due = new DateTime($dueDate);
        $ret = $returnDate ? new DateTime($returnDate) : new DateTime();

        if ($ret <= $due) {
            return 0;
        }

        $dueDay = $due->format('Y-m-d');
        $retDay = $ret->format('Y-m-d');

        if ($dueDay === $retDay) {
            return 0;
        }

        return (new DateTime($dueDay))->diff(new DateTime($retDay))->days;
    }

    function calculatePenaltyAdvanced(?string $dueDate, ?string $returnDate = null): array
    {
        if (empty($dueDate)) {
            return [
                'penalty' => 0.00,
                'remarks' => 'No due date',
                'hoursLate' => 0,
                'daysLate' => 0,
                'mode' => 'none'
            ];
        }

        $due = new DateTime($dueDate);
        $ret = $returnDate ? new DateTime($returnDate) : new DateTime();

        if ($ret <= $due) {
            return [
                'penalty' => 0.00,
                'remarks' => 'Returned on time',
                'hoursLate' => 0,
                'daysLate' => 0,
                'mode' => 'none'
            ];
        }

        $dueDay = $due->format('Y-m-d');
        $retDay = $ret->format('Y-m-d');

        // Same day: ₱2 per hour, capped at 5:00 PM
        if ($dueDay === $retDay) {
            $closing = new DateTime($dueDay . ' 17:00:00');

            if ($ret > $closing) {
                $ret = $closing;
            }

            $secondsLate = $ret->getTimestamp() - $due->getTimestamp();
            $hoursLate = max(1, (int) ceil($secondsLate / 3600));

            return [
                'penalty' => $hoursLate * 2.00,
                'remarks' => $hoursLate . ' hour' . ($hoursLate === 1 ? '' : 's') . ' late',
                'hoursLate' => $hoursLate,
                'daysLate' => 0,
                'mode' => 'hourly'
            ];
        }

        // Next day or more: ₱10 per day
        $daysLate = (new DateTime($dueDay))->diff(new DateTime($retDay))->days;

        return [
            'penalty' => $daysLate * 10.00,
            'remarks' => $daysLate . ' day' . ($daysLate === 1 ? '' : 's') . ' late',
            'hoursLate' => 0,
            'daysLate' => $daysLate,
            'mode' => 'daily'
        ];
    }

    function getStudentDisplayName(array $row): string
    {
        if (!empty($row['studentName'])) {
            return (string) $row['studentName'];
        }

        if (!empty($row['user_fullname'])) {
            return (string) $row['user_fullname'];
        }

        if (!empty($row['fullname'])) {
            return (string) $row['fullname'];
        }

        return 'Unknown Student';
    }

    function getStudentIdValue(array $row): string
    {
        if (!empty($row['student_id'])) {
            return (string) $row['student_id'];
        }

        if (!empty($row['user_student_id'])) {
            return (string) $row['user_student_id'];
        }

        return '—';
    }

    function reservationIsReady(array $row): bool
    {
        return isset($row['status']) && $row['status'] === 'ready';
    }

    function reservationIsActive(array $row): bool
    {
        return isset($row['status']) && in_array($row['status'], ['pending', 'ready'], true);
    }

    function borrowingIsActive(array $row): bool
    {
        return isset($row['status']) && in_array($row['status'], ['borrowed', 'overdue'], true);
    }
}

function createNotification(
    PDO $pdo,
    ?int $userId,
    ?string $studentId,
    ?string $studentName,
    string $type,
    string $title,
    string $message,
    ?string $link = null
): void {
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            student_id,
            student_name,
            type,
            title,
            message,
            link,
            is_read,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
    ");

    $stmt->execute([
        $userId,
        $studentId,
        $studentName,
        $type,
        $title,
        $message,
        $link
    ]);
}

function fetchStudentNotifications(PDO $pdo, ?int $userId, ?string $studentId, ?string $studentName, int $limit = 10): array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM notifications
        WHERE (
            user_id = :user_id
            OR student_id = :student_id
            OR student_name = :student_name
        )
        ORDER BY is_read ASC, created_at DESC, id DESC
        LIMIT {$limit}
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':student_id' => $studentId,
        ':student_name' => $studentName
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function countUnreadStudentNotifications(PDO $pdo, ?int $userId, ?string $studentId, ?string $studentName): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
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

    return (int)$stmt->fetchColumn();
}

function notificationExistsToday(PDO $pdo, ?int $userId, ?string $studentId, ?string $studentName, string $type, string $title): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE (
            user_id = :user_id
            OR student_id = :student_id
            OR student_name = :student_name
        )
        AND type = :type
        AND title = :title
        AND DATE(created_at) = CURDATE()
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':student_id' => $studentId,
        ':student_name' => $studentName,
        ':type' => $type,
        ':title' => $title
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function reservationStatusBadgeClass(string $status): string
{
    return match ($status) {
        'pending' => 'bg-gray-100 text-gray-700',
        'ready' => 'bg-blue-100 text-blue-700',
        'borrowed' => 'bg-gray-200 text-gray-700',
        'cancelled' => 'bg-red-100 text-red-700',
        'expired' => 'bg-red-100 text-red-700',
        default => 'bg-gray-100 text-gray-700',
    };
}

function reservationStatusLabel(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'ready' => 'Ready to Pick Up',
        'borrowed' => 'Borrowed',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        default => ucfirst($status),
    };
}

function promoteNextPendingReservation(PDO $pdo, int $bookId): bool
{
    $nextStmt = $pdo->prepare("
        SELECT id, user_id, student_id, studentName
        FROM reservations
        WHERE book_id = ?
          AND status = 'pending'
        ORDER BY reservationDate ASC, id ASC
        LIMIT 1
    ");
    $nextStmt->execute([$bookId]);
    $nextReservation = $nextStmt->fetch(PDO::FETCH_ASSOC);

    if (!$nextReservation) {
        return false;
    }

    $newExpiryDate = nextReservationExpiryDateTime(3);

    $readyStmt = $pdo->prepare("
        UPDATE reservations
        SET status = 'ready',
            expiryDate = ?
        WHERE id = ?
    ");
    $readyStmt->execute([$newExpiryDate, $nextReservation['id']]);

    $notifyUserId = !empty($nextReservation['user_id']) ? (int)$nextReservation['user_id'] : null;
    $notifyStudentId = $nextReservation['student_id'] ?: null;
    $notifyStudentName = $nextReservation['studentName'] ?: null;

    if (!notificationExistsToday(
        $pdo,
        $notifyUserId,
        $notifyStudentId,
        $notifyStudentName,
        'reservation_ready',
        'Book Ready for Pickup'
    )) {
        createNotification(
            $pdo,
            $notifyUserId,
            $notifyStudentId,
            $notifyStudentName,
            'reservation_ready',
            'Book Ready for Pickup',
            'Your reserved book is now available and ready for pickup.',
            'reservations.php'
        );
    }

    return true;
}

function releaseReservedBookCopy(PDO $pdo, int $bookId): void
{
    $bookUpdate = $pdo->prepare("
        UPDATE books
        SET availableCopies = availableCopies + 1
        WHERE id = ?
          AND availableCopies < totalCopies
    ");
    $bookUpdate->execute([$bookId]);
}

function expireReservationById(PDO $pdo, int $reservationId): void
{
    $stmt = $pdo->prepare("
        SELECT id, book_id, status
        FROM reservations
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        return;
    }

    if (!in_array($reservation['status'], ['pending', 'ready'], true)) {
        return;
    }

    $updateExpired = $pdo->prepare("
        UPDATE reservations
        SET status = 'expired'
        WHERE id = ?
    ");
    $updateExpired->execute([$reservationId]);

    if ($reservation['status'] === 'ready') {
        $promoted = promoteNextPendingReservation($pdo, (int)$reservation['book_id']);

        if (!$promoted) {
            releaseReservedBookCopy($pdo, (int)$reservation['book_id']);
        }
    }
}

function processExpiredReservations(PDO $pdo): void
{
    $expireStmt = $pdo->prepare("
        SELECT id
        FROM reservations
        WHERE status IN ('pending', 'ready')
          AND expiryDate IS NOT NULL
          AND expiryDate < NOW()
        ORDER BY expiryDate ASC, id ASC
    ");
    $expireStmt->execute();
    $expiredReservations = $expireStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expiredReservations as $row) {
        $pdo->beginTransaction();

        try {
            expireReservationById($pdo, (int)$row['id']);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

function cancelReservationAndReassign(PDO $pdo, int $reservationId): bool
{
    $stmt = $pdo->prepare("
        SELECT id, book_id, status
        FROM reservations
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        return false;
    }

    if (!in_array($reservation['status'], ['pending', 'ready'], true)) {
        return false;
    }

    $update = $pdo->prepare("
        UPDATE reservations
        SET status = 'cancelled'
        WHERE id = ?
    ");
    $update->execute([$reservationId]);

    if ($reservation['status'] === 'ready') {
        $promoted = promoteNextPendingReservation($pdo, (int)$reservation['book_id']);

        if (!$promoted) {
            releaseReservedBookCopy($pdo, (int)$reservation['book_id']);
        }
    }

    return true;
}