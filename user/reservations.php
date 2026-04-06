<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/library_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/student_login.php");
    exit();
}

$currentPage = 'reservations';

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

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['user_id'] ?? null;
$studentName = $_SESSION['fullname'] ?? 'Student';
$studentId = $_SESSION['student_id'] ?? '';

$stmt = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1, read_at = NOW()
    WHERE (
        user_id = :user_id
        OR student_id = :student_id
        OR student_name = :student_name
    )
    AND link = 'reservations.php'
    AND is_read = 0
");
$stmt->execute([
    ':user_id' => $userId,
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);

processExpiredReservations($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die("❌ Invalid CSRF token.");
    }

    $cancelId = (int)($_POST['cancel_id'] ?? 0);

    if ($cancelId < 1) {
        $_SESSION['reservation_error'] = 'Invalid reservation.';
        header("Location: reservations.php");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT id, user_id, student_id, studentName, status
        FROM reservations
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$cancelId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        $_SESSION['reservation_error'] = 'Reservation not found.';
        header("Location: reservations.php");
        exit();
    }

    $isOwner = false;

    if ($userId && !empty($reservation['user_id'])) {
        $isOwner = ((int)$reservation['user_id'] === (int)$userId);
    } else {
        $isOwner = (
            ($studentId !== '' && $reservation['student_id'] === $studentId) ||
            ($studentName !== '' && $reservation['studentName'] === $studentName)
        );
    }

    if (!$isOwner) {
        $_SESSION['reservation_error'] = 'You are not allowed to cancel this reservation.';
        header("Location: reservations.php");
        exit();
    }

    $pdo->beginTransaction();

    try {
        $ok = cancelReservationAndReassign($pdo, $cancelId);

        if (!$ok) {
            $_SESSION['reservation_error'] = 'Only active reservations can be cancelled.';
            $pdo->rollBack();
            header("Location: reservations.php");
            exit();
        }

        $pdo->commit();
        $_SESSION['reservation_success'] = 'Reservation cancelled successfully.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['reservation_error'] = 'Failed to cancel reservation.';
    }

    header("Location: reservations.php");
    exit();
}

$sql = "
    SELECT
        r.*,
        bk.title,
        bk.author,
        bk.isbn,
        bk.coverImage
    FROM reservations r
    LEFT JOIN books bk ON bk.id = r.book_id
    WHERE 1=1
";

$params = [];

if ($userId) {
    $sql .= " AND r.user_id = :user_id";
    $params[':user_id'] = $userId;
} else {
    $sql .= " AND (r.student_id = :student_id OR r.studentName = :student_name)";
    $params[':student_id'] = $studentId;
    $params[':student_name'] = $studentName;
}

$sql .= " ORDER BY r.reservationDate ASC, r.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allReservations = $stmt->fetchAll();

$activeReservations = array_values(array_filter($allReservations, fn($row) => reservationIsActive($row)));
$pastReservations = array_values(array_filter($allReservations, fn($row) => in_array($row['status'], ['borrowed', 'cancelled', 'expired'], true)));

usort($pastReservations, function ($a, $b) {
    $dateCompare = strtotime($b['reservationDate'] ?? '1970-01-01') <=> strtotime($a['reservationDate'] ?? '1970-01-01');

    if ($dateCompare !== 0) {
        return $dateCompare;
    }

    return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
});

$successMessage = $_SESSION['reservation_success'] ?? '';
$errorMessage = $_SESSION['reservation_error'] ?? '';
unset($_SESSION['reservation_success'], $_SESSION['reservation_error']);
?>

<?php include 'header.php'; ?>

<main class="max-w-[1489px] mx-auto px-6 pt-40 pb-10">

    <!-- PAGE HEADER -->
    <section class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900">My Reservations</h1>
        <p class="mt-2 text-slate-600 text-lg">Manage your book reservations</p>
    </section>

    <?php if ($successMessage !== ''): ?>
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- ACTIVE RESERVATIONS -->
    <section class="space-y-4 mb-8">
        <div class="flex items-center space-x-2">
            <span class="text-gray-700 text-lg"></span>
            <h2 class="text-xl font-semibold">Active Reservations</h2>
            <span class="inline-flex items-center rounded-full border border-gray-300 px-3 py-0.5 text-sm">
                <?= e(count($activeReservations)) ?>
            </span>
        </div>

        <?php if (empty($activeReservations)): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-2xl font-semibold text-gray-900">No Active Reservations</h3>
                <p class="text-gray-600 mt-2">
                    You don't have any active reservations at the moment. Visit the Book Catalog to reserve books.
                </p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($activeReservations as $row): ?>
                    <?php
                        $cover = !empty($row['coverImage'])
                            ? '/library-management-system/admin/' . ltrim($row['coverImage'], '/')
                            : 'https://placehold.co/100x140?text=No+Cover';

                        $isExpired = !empty($row['expiryDate']) && strtotime($row['expiryDate']) < time();
                        $timeLeft = !empty($row['expiryDate']) ? strtotime($row['expiryDate']) - time() : null;
                        $isNearExpiry = $timeLeft !== null && $timeLeft > 0 && $timeLeft < 86400;
                    ?>
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
                        <div class="flex space-x-4">
                            <img
                                src="<?= e($cover) ?>"
                                alt="<?= e($row['title'] ?: 'Book Cover') ?>"
                                onerror="this.src='https://placehold.co/100x140?text=No+Cover'"
                                class="w-20 h-28 object-cover rounded"
                            >

                            <div class="flex-1 space-y-2">
                                <div>
                                    <h3 class="font-semibold text-gray-900"><?= e($row['title'] ?: 'Unknown Book') ?></h3>
                                    <p class="text-sm text-gray-600"><?= e($row['author'] ?: 'Unknown Author') ?></p>
                                </div>

                                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                    <div>
                                        <span class="text-gray-500">Reserved:</span>
                                        <p class="font-medium"><?= e(formatDateText($row['reservationDate'])) ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Pickup Until:</span>
                                        <p class="font-medium <?= $isExpired ? 'text-red-600' : '' ?>">
                                            <?= e(formatDateText($row['expiryDate'])) ?>
                                        </p>

                                        <?php if ($isNearExpiry): ?>
                                            <p class="text-xs text-yellow-600 mt-1 font-semibold">
                                                ⚠ Expires today!
                                            </p>
                                        <?php endif; ?>

                                        <p class="text-xs text-gray-500 mt-1">
                                            Library closes at 5:00 PM
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= reservationStatusBadgeClass((string)$row['status']) ?>">
                                        <?= e(reservationStatusLabel((string)$row['status'])) ?>
                                    </span>

                                    <?php if (reservationIsActive($row)): ?>
                                        <form method="POST" onsubmit="return confirm('Cancel this reservation?')">
                                            <input type="hidden" name="token" value="<?= e($_SESSION['token']) ?>">
                                            <input type="hidden" name="cancel_id" value="<?= e($row['id']) ?>">
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-100"
                                            >
                                                Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <?php if (reservationIsReady($row)): ?>
                                    <div class="p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800 flex items-start gap-2">
                                        <span class="text-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.05 4.575a1.575 1.575 0 1 0-3.15 0v3m3.15-3v-1.5a1.575 1.575 0 0 1 3.15 0v1.5m-3.15 0 .075 5.925m3.075.75V4.575m0 0a1.575 1.575 0 0 1 3.15 0V15M6.9 7.575a1.575 1.575 0 1 0-3.15 0v8.175a6.75 6.75 0 0 0 6.75 6.75h2.018a5.25 5.25 0 0 0 3.712-1.538l1.732-1.732a5.25 5.25 0 0 0 1.538-3.712l.003-2.024a.668.668 0 0 1 .198-.471 1.575 1.575 0 1 0-2.228-2.228 3.818 3.818 0 0 0-1.12 2.687M6.9 7.575V12m6.27 4.318A4.49 4.49 0 0 1 16.35 15m.002 0h-.002" />
                                            </svg>
                                        </span> 
                                        <div>
                                            <p class="font-semibold">Ready for Pickup</p>
                                            <p class="text-xs">
                                                Pickup Until <?= e(formatDateText($row['expiryDate'])) ?>
                                            </p>

                                            <?php if ($isNearExpiry): ?>
                                                <p class="text-xs text-yellow-600 font-semibold">
                                                    ⚠ Expires today!
                                                </p>
                                            <?php endif; ?>

                                            <p class="text-xs text-gray-500">
                                                Library closes at 5:00 PM
                                            </p>

                                            <p class="text-xs text-gray-500 mt-1">
                                                <?= e(timeAgo($row['expiryDate'])) ?> remaining
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- PAST RESERVATIONS -->
    <?php if (!empty($pastReservations)): ?>
        <section class="space-y-4 mb-8">
            <div class="flex items-center space-x-2">
                <span class="text-gray-700 text-lg"></span>
                <h2 class="text-xl font-semibold">Past Reservations</h2>
                <span class="inline-flex items-center rounded-full border border-gray-300 px-3 py-0.5 text-sm">
                    <?= e(count($pastReservations)) ?>
                </span>
            </div>

            <div class="space-y-4">
                <?php foreach ($pastReservations as $row): ?>
                    <?php
                        $cover = !empty($row['coverImage'])
                            ? '/library-management-system/admin/' . ltrim($row['coverImage'], '/')
                            : 'https://placehold.co/100x140?text=No+Cover';

                        $isExpired = !empty($row['expiryDate']) && strtotime($row['expiryDate']) < time();
                        $timeLeft = !empty($row['expiryDate']) ? strtotime($row['expiryDate']) - time() : null;
                        $isNearExpiry = $timeLeft !== null && $timeLeft > 0 && $timeLeft < 86400;
                    ?>
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
                        <div class="flex space-x-4">
                            <img
                                src="<?= e($cover) ?>"
                                alt="<?= e($row['title'] ?: 'Book Cover') ?>"
                                onerror="this.src='https://placehold.co/100x140?text=No+Cover'"
                                class="w-20 h-28 object-cover rounded"
                            >

                            <div class="flex-1 space-y-2">
                                <div>
                                    <h3 class="font-semibold text-gray-900"><?= e($row['title'] ?: 'Unknown Book') ?></h3>
                                    <p class="text-sm text-gray-600"><?= e($row['author'] ?: 'Unknown Author') ?></p>
                                </div>

                                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                    <div>
                                        <span class="text-gray-500">Reserved:</span>
                                        <p class="font-medium"><?= e(formatDateText($row['reservationDate'])) ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Pickup Until:</span>
                                        <p class="font-medium <?= $isExpired ? 'text-red-600' : '' ?>">
                                            <?= e(formatDateText($row['expiryDate'])) ?>
                                        </p>

                                        <?php if ($isNearExpiry): ?>
                                            <p class="text-xs text-yellow-600 mt-1 font-semibold">
                                                ⚠ Expires today!
                                            </p>
                                        <?php endif; ?>

                                        <p class="text-xs text-gray-500 mt-1">
                                            Library closes at 5:00 PM
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= reservationStatusBadgeClass((string)$row['status']) ?>">
                                        <?= e(reservationStatusLabel((string)$row['status'])) ?>
                                    </span>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- INFO CARD -->
    <section class="bg-white rounded-2xl border border-gray-200 p-7 shadow-sm">
        <h2 class="text-2xl font-bold text-slate-900 mb-6">Reservation Information</h2>

        <div class="space-y-2 text-sm">
            <p><strong>How Reservations Work:</strong></p>
            <ul class="list-disc list-inside ml-4 text-gray-600">
                <li>Reserve books that are currently unavailable</li>
                <li>You'll be notified when the book becomes available</li>
                <li>Reservations expire after 3 days if not picked up</li>
                <li>When a book is ready, visit the library to borrow it</li>
            </ul>
        </div>
    </section>

</main>

</body>
</html>