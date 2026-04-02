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

/* ================= DATABASE CONNECTION ================= */
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

/* ================= CSRF TOKEN ================= */
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

/* ================= SESSION DATA ================= */
$userId = $_SESSION['user_id'] ?? null;
$studentName = $_SESSION['fullname'] ?? 'Student';
$studentId = $_SESSION['student_id'] ?? '';

/* ================= MARK NOTIFICATIONS READ ================= */
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

/* ================= HELPERS ================= */
// function e($value): string {
//     return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
// }

// function formatDateText($date): string {
//     if (empty($date) || $date === '0000-00-00') {
//         return '—';
//     }
//     return date('M d, Y h:i A', strtotime($date));
// }

function statusBadgeClass(string $status): string {
    return match ($status) {
        'pending' => 'bg-gray-100 text-gray-700',
        'ready' => 'bg-blue-100 text-blue-700',
        'borrowed' => 'bg-gray-200 text-gray-700',
        'cancelled' => 'bg-red-100 text-red-700',
        'expired' => 'bg-red-100 text-red-700',
        default => 'bg-gray-100 text-gray-700',
    };
}

function statusLabel(string $status): string {
    return match ($status) {
        'pending' => 'Pending',
        'ready' => 'Ready to Pick Up',
        'borrowed' => 'Borrowed',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        default => ucfirst($status),
    };
}

/* ================= AUTO EXPIRE ================= */
$pdo->exec("
    UPDATE reservations
    SET status = 'expired'
    WHERE status IN ('pending', 'ready')
      AND expiryDate IS NOT NULL
      AND expiryDate < NOW()
");

/* ================= HANDLE CANCEL ================= */
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
        SELECT id, status, user_id, student_id, studentName
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

    if (!in_array($reservation['status'], ['pending', 'ready'], true)) {
        $_SESSION['reservation_error'] = 'Only active reservations can be cancelled.';
        header("Location: reservations.php");
        exit();
    }

    $stmt = $pdo->prepare("
        UPDATE reservations
        SET status = 'cancelled'
        WHERE id = ?
    ");
    $stmt->execute([$cancelId]);

    $_SESSION['reservation_success'] = 'Reservation cancelled successfully.';
    header("Location: reservations.php");
    exit();
}

/* ================= FETCH MY RESERVATIONS ================= */
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

/* ================= SPLIT ACTIVE / PAST ================= */
$activeReservations = array_values(array_filter($allReservations, function ($row) {
    return in_array($row['status'], ['pending', 'ready'], true);
}));

$pastReservations = array_values(array_filter($allReservations, function ($row) {
    return in_array($row['status'], ['borrowed', 'cancelled', 'expired'], true);
}));

usort($pastReservations, function ($a, $b) {
    $dateCompare = strtotime($b['reservationDate'] ?? '1970-01-01') 
                 <=> strtotime($a['reservationDate'] ?? '1970-01-01');

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

<main class="max-w-7xl mx-auto px-6 pt-40 pb-10">

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

                        $isExpired = !empty($row['expiryDate']) && strtotime(date('Y-m-d H:i:s')) > strtotime($row['expiryDate']);
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
                                        <span class="text-gray-500">Expires:</span>
                                        <p class="font-medium <?= $isExpired ? 'text-red-600' : '' ?>">
                                            <?= e(formatDateText($row['expiryDate'])) ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= statusBadgeClass((string)$row['status']) ?>">
                                        <?= e(statusLabel((string)$row['status'])) ?>
                                    </span>

                                    <?php if (in_array($row['status'], ['pending', 'ready'], true)): ?>
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

                                <?php if ($row['status'] === 'ready'): ?>
                                    <div class="p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800 flex items-start gap-2">
                                        <span class="text-lg">📚</span>
                                        <div>
                                            <p class="font-semibold">Ready for Pickup</p>
                                            <p class="text-xs">Pick up before <?= e(formatDateText($row['expiryDate'])) ?></p>

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

                        $isExpired = !empty($row['expiryDate']) && strtotime(date('Y-m-d H:i:s')) > strtotime($row['expiryDate']);
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
                                        <span class="text-gray-500">Expires:</span>
                                        <p class="font-medium <?= $isExpired ? 'text-red-600' : '' ?>">
                                            <?= e(formatDateText($row['expiryDate'])) ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= statusBadgeClass((string)$row['status']) ?>">
                                        <?= e(statusLabel((string)$row['status'])) ?>
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