<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/student_login.php");
    exit();
}

$currentPage = 'student_dashboard';

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

/* ================= SESSION DATA ================= */
$studentName = $_SESSION['fullname'] ?? 'Student';
$studentId   = $_SESSION['student_id'] ?? '';
$userId      = $_SESSION['user_id'] ?? null;

/* ================= AUTO UPDATE OVERDUE ================= */
$pdo->exec("
    UPDATE borrowings
    SET status = 'overdue'
    WHERE status = 'borrowed'
      AND dueDate IS NOT NULL
      AND dueDate < NOW()
      AND returnDate IS NULL
");

/* ================= CREATE OVERDUE NOTIFICATIONS ================= */
$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.dueDate,
        bk.title
    FROM borrowings b
    LEFT JOIN books bk ON bk.id = b.book_id
    WHERE (
        b.user_id = :user_id
        OR b.student_id = :student_id
        OR b.studentName = :student_name
    )
    AND b.status = 'overdue'
    AND b.returnDate IS NULL
    ORDER BY b.dueDate ASC, b.id ASC
");
$stmt->execute([
    ':user_id' => $userId,
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);

$overdueRows = $stmt->fetchAll();

foreach ($overdueRows as $row) {
    $bookTitle = $row['title'] ?: 'Unknown Book';

    $title = 'Book Overdue';
    $message = 'Your borrowed book "' . $bookTitle . '" is overdue. Please return it as soon as possible to avoid additional penalties.';

    // one notification per overdue book per day
    $existsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE (
            user_id = :user_id
            OR student_id = :student_id
            OR student_name = :student_name
        )
        AND type = :type
        AND title = :title
        AND message = :message
        AND DATE(created_at) = CURDATE()
    ");
    $existsStmt->execute([
        ':user_id' => $userId,
        ':student_id' => $studentId,
        ':student_name' => $studentName,
        ':type' => 'overdue',
        ':title' => $title,
        ':message' => $message
    ]);

    if ((int)$existsStmt->fetchColumn() === 0) {
        createNotification(
            $pdo,
            $userId,
            $studentId,
            $studentName,
            'overdue',
            $title,
            $message,
            'my_borrowings.php?tab=active'
        );
    }
}

/* ================= COUNTS ================= */
$activeBorrowings = 0;
$activeReservations = 0;
$overdueBooks = 0;
$currentBorrowings = [];
$readyReservations = [];

/* Active Borrowings */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM borrowings
    WHERE (
        user_id = :user_id
        OR student_id = :student_id
        OR studentName = :student_name
    )
    AND status = 'borrowed'
");
$stmt->execute([
    ':user_id' => $userId,
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);
$activeBorrowings = (int)$stmt->fetchColumn();

/* Overdue Books */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM borrowings
    WHERE (
        user_id = :user_id
        OR student_id = :student_id
        OR studentName = :student_name
    )
    AND status = 'overdue'
");
$stmt->execute([
    ':user_id' => $userId,
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);
$overdueBooks = (int)$stmt->fetchColumn();

/* Active Reservations */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations
    WHERE (
        user_id = :user_id
        OR student_id = :student_id
        OR studentName = :student_name
    )
    AND status IN ('pending', 'ready')
");
$stmt->execute([
    ':user_id' => $userId,
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);
$activeReservations = (int)$stmt->fetchColumn();

/* Ready for Pickup Reservations */
$stmt = $pdo->prepare("
    SELECT
        r.*,
        bk.title,
        bk.author,
        bk.coverImage
    FROM reservations r
    LEFT JOIN books bk ON bk.id = r.book_id
    WHERE (
        r.user_id = :user_id
        OR r.student_id = :student_id
        OR r.studentName = :student_name
    )
    AND r.status = 'ready'
    ORDER BY r.expiryDate ASC, r.id ASC
");
$stmt->execute([
    ':user_id' => $userId,
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);
$readyReservations = $stmt->fetchAll();

/* Current Borrowings List */
$stmt = $pdo->prepare("
    SELECT 
        b.*,
        bk.title,
        bk.author,
        bk.isbn,
        bk.coverImage
    FROM borrowings b
    LEFT JOIN books bk ON bk.id = b.book_id
    WHERE (
        b.user_id = :user_id
        OR b.student_id = :student_id
        OR b.studentName = :student_name
    )
    AND b.status IN ('borrowed', 'overdue')
    ORDER BY b.borrowDate DESC, b.id DESC
");
$stmt->execute([
    ':user_id' => $userId,
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);
$currentBorrowings = $stmt->fetchAll();
?>

<?php include 'header.php'; ?>

<main class="max-w-[1489px] mx-auto px-6 pt-40 pb-10">

    <!-- WELCOME -->
    <section class="mb-8">
        <h1 class="text-5xl font-bold text-slate-900 leading-tight">
            Welcome back, <?= e($studentName) ?>!
        </h1>
        <p class="mt-3 text-3xl text-slate-600">
            Student ID: <?= e($studentId ?: '—') ?>
        </p>
    </section>

    <!-- READY FOR PICKUP BANNER -->
    <?php if (!empty($readyReservations)): ?>
        <section class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-green-900">📚 Ready for Pickup</h2>
                    <p class="mt-1 text-green-800">
                        You have <?= count($readyReservations) ?> reservation<?= count($readyReservations) > 1 ? 's' : '' ?> ready to pick up.
                    </p>

                    <div class="mt-4 space-y-2">
                        <?php foreach ($readyReservations as $reservation): ?>
                            <div class="rounded-xl border border-green-200 bg-white px-4 py-3">
                                <p class="font-semibold text-slate-900">
                                    <?= e($reservation['title'] ?: 'Unknown Book') ?>
                                </p>
                                <p class="text-sm text-slate-600">
                                    <?= e($reservation['author'] ?: 'Unknown Author') ?>
                                </p>
                                <p class="mt-1 text-sm text-green-800">
                                    Pick up before: <?= e(formatDateText($reservation['expiryDate'])) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <a href="reservations.php"
                   class="shrink-0 rounded-xl bg-green-600 px-4 py-2 text-white hover:bg-green-700">
                    View Reservations
                </a>
            </div>
        </section>
    <?php endif; ?>

    <!-- OVERDUE WARNING BANNER -->
    <?php if ($overdueBooks > 0): ?>
        <section class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-red-900">⚠ Overdue Warning</h2>
                    <p class="mt-1 text-red-800">
                        You currently have <?= e($overdueBooks) ?> overdue book<?= $overdueBooks > 1 ? 's' : '' ?>.
                    </p>
                    <p class="mt-2 text-sm text-red-700">
                        Please return overdue books as soon as possible to avoid additional penalties.
                    </p>
                </div>

                <a href="my_borrowings.php?tab=active"
                   class="shrink-0 rounded-xl bg-red-600 px-4 py-2 text-white hover:bg-red-700">
                    View Borrowings
                </a>
            </div>
        </section>
    <?php endif; ?>

   <!-- ================= STATS ================= -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    
    <div class="bg-white rounded-2xl border border-gray-200 p-7 shadow-sm">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-xl font-semibold text-slate-900">Active Borrowings</h3>

            <!-- REPLACED -->
            <span class="text-blue-600 text-2xl">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" class="w-6 h-6 inline">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </span>
        </div>

        <div class="text-5xl font-bold text-slate-900"><?= e($activeBorrowings) ?></div>
        <p class="mt-2 text-slate-500 text-lg">Currently borrowed books</p>
    </div>


    <div class="bg-white rounded-2xl border border-gray-200 p-7 shadow-sm">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-xl font-semibold text-slate-900">Active Reservations</h3>

            <!-- REPLACED -->
            <span class="text-green-600 text-2xl">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" class="w-6 h-6 inline">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.5 3.75V16.5L12 14.25 7.5 16.5V3.75m9 0H18A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6A2.25 2.25 0 0 1 6 3.75h1.5m9 0h-9" />
                </svg>
            </span>
        </div>

        <div class="text-5xl font-bold text-slate-900"><?= e($activeReservations) ?></div>
        <p class="mt-2 text-slate-500 text-lg">Reserved books</p>
    </div>


    <div class="bg-white rounded-2xl border border-gray-200 p-7 shadow-sm">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-xl font-semibold text-slate-900">Overdue Books</h3>

            <!-- REPLACED -->
            <span class="text-red-500 text-2xl">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" class="w-6 h-6 inline">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </span>
        </div>

        <div class="text-5xl font-bold text-red-600"><?= e($overdueBooks) ?></div>
        <p class="mt-2 text-slate-500 text-lg">Books past due date</p>
    </div>

</section>

    <!-- CURRENT BORROWINGS -->
    <section class="bg-white rounded-2xl border border-gray-200 p-7 shadow-sm mb-8">
        <h2 class="text-2xl font-bold text-slate-900">Current Borrowings</h2>
        <p class="mt-2 text-slate-500 text-lg">Books you currently have borrowed</p>

        <?php if (empty($currentBorrowings)): ?>
            <div class="h-52 flex items-center justify-center text-3xl text-slate-500">
                No active borrowings
            </div>
        <?php else: ?>
            <div class="mt-6 space-y-4">
                <?php foreach ($currentBorrowings as $row): ?>
                    <?php
                        $cover = !empty($row['coverImage']) 
                            ? '/library-management-system/admin/' . ltrim($row['coverImage'], '/')
                            : 'https://placehold.co/90x125?text=No+Cover';

                        $isOverdueRow = (($row['status'] ?? '') === 'overdue') || isOverdue($row['dueDate'] ?? null, $row['returnDate'] ?? null);
                        $penaltyInfo = calculatePenaltyAdvanced($row['dueDate'] ?? null, nowDateTime());
                        $currentPenalty = $penaltyInfo['penalty'];
                    ?>
                    <div class="border border-gray-200 rounded-2xl p-4 flex flex-col md:flex-row gap-4">
                        <img src="<?= e($cover) ?>"
                             alt="Book Cover"
                             class="w-24 h-32 object-cover rounded-xl border border-gray-200">

                        <div class="flex-1">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-slate-900">
                                        <?= e($row['title'] ?: 'Unknown Book') ?>
                                    </h3>
                                    <p class="text-slate-600"><?= e($row['author'] ?: 'Unknown Author') ?></p>
                                </div>

                                <?php if ($isOverdueRow): ?>
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                        Overdue
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                        Borrowed
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 text-sm">
                                <div>
                                    <p class="text-slate-500">ISBN</p>
                                    <p class="font-medium text-slate-900"><?= e($row['isbn'] ?: '—') ?></p>
                                </div>
                                <div>
                                    <p class="text-slate-500">Borrow Date</p>
                                    <p class="font-medium text-slate-900"><?= e(formatDateText($row['borrowDate'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-slate-500">Due Date</p>
                                    <p class="font-medium <?= $isOverdueRow ? 'text-red-600' : 'text-slate-900' ?>">
                                        <?= e(formatDateText($row['dueDate'])) ?>
                                    </p>
                                </div>
                            </div>

                            <?php if ($currentPenalty > 0): ?>
                                <div class="mt-4 text-red-600 font-medium">
                                    Current Penalty: ₱<?= number_format((float)$currentPenalty, 2) ?>
                                </div>
                                <p class="mt-1 text-sm text-red-500">
                                    <?= e($penaltyInfo['remarks']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- LIBRARY INFO -->
    <section class="bg-white rounded-2xl border border-gray-200 p-7 shadow-sm">
        <h2 class="text-2xl font-bold text-slate-900 mb-8">Library Information</h2>

        <div class="space-y-4 text-lg text-slate-900">
            <p><span class="font-bold">Borrowing Period:</span> 1 day only</p>
            <p><span class="font-bold">Due Time:</span> 8:59 AM next day</p>
            <p><span class="font-bold">Library Hours:</span> Monday - Saturday, 5:00 AM - 5:00 PM</p>

            <div>
                <p class="font-bold mb-2">Late Fees:</p>
                <ul class="list-disc pl-8 text-slate-700 space-y-1">
                    <li>Same-day late return after 8:59 AM: ₱2 per hour</li>
                    <li>Hourly penalty only counts until 5:00 PM</li>
                    <li>Starting the next day: ₱10 per day</li>
                </ul>
            </div>
        </div>
    </section>

</main>

</body>
</html>