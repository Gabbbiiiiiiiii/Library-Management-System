<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';
require_once "auth_check.php";

$currentPage = 'manage_reservations';

/* ================= CSRF TOKEN ================= */
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

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

/* ================= SHARED RESERVATION WORKFLOW ================= */
processExpiredReservations($pdo);

/* ================= HANDLE CANCEL ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die("❌ Invalid CSRF token.");
    }

    $cancelId = (int)($_POST['cancel_id'] ?? 0);
    $currentTab = trim($_POST['current_tab'] ?? 'active');

    if ($cancelId < 1) {
        die("❌ Invalid reservation.");
    }

    $pdo->beginTransaction();

    try {
        $ok = cancelReservationAndReassign($pdo, $cancelId);

        if (!$ok) {
            $pdo->rollBack();
            header("Location: manage_reservations.php?tab=" . urlencode($currentTab));
            exit();
        }

        $pdo->commit();
        $_SESSION['reservation_success'] = 'Reservation cancelled successfully.';
    } catch (Exception $e) {
        $pdo->rollBack();
        die("❌ Failed to cancel reservation.");
    }

    header("Location: manage_reservations.php?tab=" . urlencode($currentTab));
    exit();
}

/* ================= HANDLE PICKUP (BORROW RESERVED BOOK) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pickup_id'])) {
    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die("❌ Invalid CSRF token.");
    }

    if (!isLibraryOpen()) {
    $_SESSION['reservation_error'] = libraryClosedMessage();
    header("Location: manage_reservations.php?tab=ready");
    exit();
    }

    $pickupId = (int)($_POST['pickup_id'] ?? 0);

    if ($pickupId < 1) {
        die("❌ Invalid reservation.");
    }

    $stmt = $pdo->prepare("
        SELECT r.*, bk.title AS book_title
        FROM reservations r
        LEFT JOIN books bk ON bk.id = r.book_id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute([$pickupId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        die("❌ Reservation not found.");
    }

    if (($reservation['status'] ?? '') !== 'ready') {
        die("❌ Only ready reservations can be marked as borrowed.");
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM borrowings
        WHERE user_id = ?
          AND status IN ('borrowed', 'overdue')
    ");
    $stmt->execute([$reservation['user_id']]);

    if ((int)$stmt->fetchColumn() > 0) {
        $_SESSION['reservation_error'] = 'Student must return current borrowed book before claiming reservation.';
        header("Location: manage_reservations.php?tab=ready");
        exit();
    }

    $pdo->beginTransaction();

    try {
        $borrowDate = nowDateTime();
        $dueDate = nextBorrowDueDateTime($borrowDate);

        $insertBorrowing = $pdo->prepare("
            INSERT INTO borrowings (
                book_id,
                user_id,
                studentName,
                student_id,
                course,
                yearlvl,
                borrowDate,
                dueDate,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'borrowed')
        ");

        $insertBorrowing->execute([
            $reservation['book_id'],
            $reservation['user_id'] ?: null,
            $reservation['studentName'] ?: null,
            $reservation['student_id'] ?: null,
            $reservation['course'] ?: null,
            $reservation['yearlvl'] ?: null,
            $borrowDate,
            $dueDate
        ]);

        $updateReservation = $pdo->prepare("
            UPDATE reservations
            SET status = 'borrowed'
            WHERE id = ?
        ");
        $updateReservation->execute([$reservation['id']]);

        $pdo->commit();
        $_SESSION['reservation_success'] = 'Reserved book has been released to the student successfully.';
    } catch (Exception $e) {
        $pdo->rollBack();
        die("❌ Failed to release reserved book: " . $e->getMessage());
    }

    header("Location: manage_reservations.php?tab=completed");
    exit();
}

/* ================= SEARCH + TAB ================= */
$search = trim($_GET['search'] ?? '');
$tab = trim($_GET['tab'] ?? 'active');

$allowedTabs = ['active', 'pending', 'ready', 'completed'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'active';
}

/* ================= COUNTS ================= */
$activeCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status IN ('pending', 'ready')
")->fetchColumn();

$pendingCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'pending'
")->fetchColumn();

$readyCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'ready'
")->fetchColumn();

$completedCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status IN ('borrowed', 'cancelled', 'expired')
")->fetchColumn();

$totalCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM reservations
")->fetchColumn();

/* ================= STATUS FILTER ================= */
$whereStatus = match ($tab) {
    'active' => "r.status IN ('pending', 'ready')",
    'pending' => "r.status = 'pending'",
    'ready' => "r.status = 'ready'",
    'completed' => "r.status IN ('borrowed', 'cancelled', 'expired')",
    default => "r.status IN ('pending', 'ready')"
};

/* ================= FETCH RESERVATIONS ================= */
$sql = "
    SELECT
        r.*,
        bk.title AS book_title,
        bk.author AS book_author,
        bk.isbn AS book_isbn,
        bk.coverImage AS book_cover,
        u.fullname AS user_fullname,
        u.student_id AS user_student_id,
        u.course AS user_course,
        u.yearlvl AS user_yearlvl
    FROM reservations r
    LEFT JOIN books bk ON bk.id = r.book_id
    LEFT JOIN users u ON u.id = r.user_id
    WHERE {$whereStatus}
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            r.studentName LIKE :search
            OR r.student_id LIKE :search
            OR u.fullname LIKE :search
            OR u.student_id LIKE :search
            OR bk.title LIKE :search
            OR CAST(r.id AS CHAR) LIKE :search
        )
    ";
    $params[':search'] = "%{$search}%";
}

if ($tab === 'completed') {
    $sql .= " ORDER BY r.reservationDate DESC, r.id DESC";
} else {
    $sql .= " ORDER BY r.reservationDate ASC, r.id ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$successMessage = $_SESSION['reservation_success'] ?? '';
$errorMessage = $_SESSION['reservation_error'] ?? '';

unset($_SESSION['reservation_success'], $_SESSION['reservation_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reservations</title>
    <link href="/library-management-system/assets/css/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'header.php'; ?>

<div class="max-w-[1489px] mx-auto px-6 pt-28 pb-10">
    
    <!-- PAGE HEADER -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Manage Reservations</h1>
        <p class="text-gray-600 mt-2 text-lg">View and manage all book reservations</p>
    </div>

    <div class="mb-6">
    <a href="export_reservations_csv.php"
       class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700">
        Export Reservations CSV
    </a>
</div>

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
    

    <!-- STATS -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-lg font-semibold text-gray-900">Active Reservations</h3>
            <span class="text-blue-600 text-xl">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor" class="inline w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
            </span>
        </div>
        <div class="text-4xl font-bold text-gray-900"><?= e($activeCount) ?></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-lg font-semibold text-gray-900">Ready for Pickup</h3>
            <span class="text-green-600 text-xl">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor" class="inline w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
            </span>
        </div>
        <div class="text-4xl font-bold text-green-600"><?= e($readyCount) ?></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-lg font-semibold text-gray-900">Total Reservations</h3>
            <span class="text-purple-600 text-xl">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor" class="inline w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
            </span>
        </div>
        <div class="text-4xl font-bold text-gray-900"><?= e($totalCount) ?></div>
    </div>
</div>

    <!-- SEARCH -->
    <form method="GET" class="mb-6">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg">⌕</span>
            <input
                type="text"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Search by student name or ID..."
                class="w-full bg-white border border-gray-200 rounded-xl pl-12 pr-4 py-3 shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
            >
        </div>
    </form>

    <!-- TABS -->
    <div class="mb-8">
        <div class="inline-flex bg-white rounded-2xl p-1 border border-gray-200 shadow-sm gap-1">
            <a href="?tab=active&search=<?= urlencode($search) ?>"
               class="px-4 py-2 rounded-xl font-medium transition <?= $tab === 'active' ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' ?>">
                Active (<?= e($activeCount) ?>)
            </a>

            <a href="?tab=pending&search=<?= urlencode($search) ?>"
               class="px-4 py-2 rounded-xl font-medium transition <?= $tab === 'pending' ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' ?>">
                Pending (<?= e($pendingCount) ?>)
            </a>

            <a href="?tab=ready&search=<?= urlencode($search) ?>"
               class="px-4 py-2 rounded-xl font-medium transition <?= $tab === 'ready' ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' ?>">
                Ready (<?= e($readyCount) ?>)
            </a>

            <a href="?tab=completed&search=<?= urlencode($search) ?>"
               class="px-4 py-2 rounded-xl font-medium transition <?= $tab === 'completed' ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' ?>">
                Completed (<?= e($completedCount) ?>)
            </a>
        </div>
    </div>

    <!-- LIST -->
    <div class="space-y-4">
        <?php if (empty($reservations)): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                <?php if ($tab === 'active'): ?>
                    <h3 class="text-2xl font-semibold text-gray-900">No Active Reservations</h3>
                    <p class="text-gray-600 mt-2">
                        <?= $search === '' ? 'There are no active reservations at the moment.' : 'No reservations match your search.' ?>
                    </p>
                <?php elseif ($tab === 'pending'): ?>
                    <h3 class="text-2xl font-semibold text-gray-900">No Pending Reservations</h3>
                    <p class="text-gray-600 mt-2">
                        <?= $search === '' ? 'There are no pending reservations.' : 'No pending reservations match your search.' ?>
                    </p>
                <?php elseif ($tab === 'ready'): ?>
                    <h3 class="text-2xl font-semibold text-gray-900">No Books Ready</h3>
                    <p class="text-gray-600 mt-2">
                        <?= $search === '' ? 'No books are ready for pickup.' : 'No ready reservations match your search.' ?>
                    </p>
                <?php else: ?>
                    <h3 class="text-2xl font-semibold text-gray-900">No Completed Reservations</h3>
                    <p class="text-gray-600 mt-2">
                        <?= $search === '' ? 'No completed reservations yet.' : 'No completed reservations match your search.' ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <?php foreach ($reservations as $row): ?>
                <?php
                    $studentName = getStudentDisplayName($row);
                    $studentId = getStudentIdValue($row);
                    $cover = !empty($row['book_cover'])
                        ? '/library-management-system/admin/' . ltrim($row['book_cover'], '/')
                        : 'https://placehold.co/100x140?text=No+Cover';
                    $isExpired = !empty($row['expiryDate']) && strtotime($row['expiryDate']) < time();
                    $timeLeft = !empty($row['expiryDate']) ? strtotime($row['expiryDate']) - time() : null;
                    $isNearExpiry = $timeLeft !== null && $timeLeft > 0 && $timeLeft < 86400;
                ?>

                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="shrink-0">
                            <img
                                src="<?= e($cover) ?>"
                                alt="Book Cover"
                                class="w-20 h-28 object-cover rounded border"
                            >
                        </div>

                        <div class="flex-1">
                            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        <?= e($row['book_title'] ?: 'Unknown Book') ?>
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        <?= e($row['book_author'] ?: 'Unknown Author') ?>
                                    </p>
                                </div>

                                <div>
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= reservationStatusBadgeClass((string)$row['status']) ?>">
                                        <?= e(reservationStatusLabel((string)$row['status'])) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3 text-sm mt-4">
                                <div>
                                    <span class="text-gray-500">Student:</span>
                                    <p class="font-medium text-gray-900"><?= e($studentName) ?></p>
                                </div>

                                <div>
                                    <span class="text-gray-500">Student ID:</span>
                                    <p class="font-medium text-gray-900"><?= e($studentId) ?></p>
                                </div>

                                <div>
                                    <span class="text-gray-500">Reserved:</span>
                                    <p class="font-medium text-gray-900"><?= e(formatDateText($row['reservationDate'])) ?></p>
                                </div>

                                <div>
                                    <span class="text-gray-500">Pickup Until:</span>
                                    <p class="font-medium <?= $isExpired ? 'text-red-600' : 'text-gray-900' ?>">
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

                                <div>
                                    <span class="text-gray-500">Status:</span>
                                    <div class="mt-1">
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= reservationStatusBadgeClass((string)$row['status']) ?>">
                                            <?= e(reservationStatusLabel((string)$row['status'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <?php if (in_array($row['status'], ['pending', 'ready'], true)): ?>
                                   <form method="POST" onsubmit="return confirm('Cancel reservation for <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>?')">
                                        <input type="hidden" name="token" value="<?= e($_SESSION['token']) ?>">
                                        <input type="hidden" name="cancel_id" value="<?= e($row['id']) ?>">
                                        <input type="hidden" name="current_tab" value="<?= e($tab) ?>">
                                        <button type="submit"
                                                class="border px-3 py-1.5 rounded-lg hover:bg-red-100 text-red-600">
                                            Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($row['status'] === 'ready'): ?>
                                    <form method="POST" onsubmit="return confirm('Release this reserved book to the student?')">
                                        <input type="hidden" name="token" value="<?= e($_SESSION['token']) ?>">
                                        <input type="hidden" name="pickup_id" value="<?= e($row['id']) ?>">
                                        <button type="submit"
                                                class="bg-purple-600 text-white px-3 py-1.5 rounded-lg hover:bg-purple-700">
                                            Confirm Pickup
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <?php if ($row['status'] === 'ready'): ?>
                                <div class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">
                                    
                                    <p class="flex items-center gap-2 font-medium text-green-900">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5 text-green-700">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
    Book is ready for pickup!
</p>
                                    <p class="mt-1 text-xs">
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
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>

<script>
const searchInput = document.querySelector('input[name="search"]');

if (searchInput) {
    searchInput.addEventListener('input', function () {
        const currentTab = new URLSearchParams(window.location.search).get('tab') || 'active';

        if (this.value.trim() === '') {
            window.location.href = 'manage_reservations.php?tab=' + encodeURIComponent(currentTab);
        }
    });
}
</script>

</body>
</html>