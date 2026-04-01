<?php
session_start();
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
} catch (PDOException $e) {
    die("Database connection failed.");
}

/* ================= SESSION DATA ================= */
$studentName = $_SESSION['fullname'] ?? 'Student';
$studentId   = $_SESSION['student_id'] ?? '';

/* ================= AUTO UPDATE OVERDUE ================= */
$pdo->exec("
    UPDATE borrowings
    SET status = 'overdue'
    WHERE status = 'borrowed'
      AND dueDate IS NOT NULL
      AND dueDate < CURDATE()
      AND returnDate IS NULL
");

/* ================= COUNTS ================= */
$activeBorrowings = 0;
$activeReservations = 0;
$overdueBooks = 0;
$currentBorrowings = [];

/* Active Borrowings */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM borrowings
    WHERE (
        student_id = :student_id
        OR studentName = :student_name
    )
    AND status = 'borrowed'
");
$stmt->execute([
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);
$activeBorrowings = (int)$stmt->fetchColumn();

/* Overdue Books */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM borrowings
    WHERE (
        student_id = :student_id
        OR studentName = :student_name
    )
    AND status = 'overdue'
");
$stmt->execute([
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);
$overdueBooks = (int)$stmt->fetchColumn();

/* Active Reservations */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations
    WHERE (
        student_id = :student_id
        OR studentName = :student_name
    )
    AND status IN ('pending', 'ready')
");
$stmt->execute([
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);
$activeReservations = (int)$stmt->fetchColumn();

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
        b.student_id = :student_id
        OR b.studentName = :student_name
    )
    AND b.status IN ('borrowed', 'overdue')
    ORDER BY b.borrowDate DESC, b.id DESC
");
$stmt->execute([
    ':student_id' => $studentId,
    ':student_name' => $studentName
]);
$currentBorrowings = $stmt->fetchAll();

/* ================= HELPERS ================= */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDateText($date): string {
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }
    return date('M d, Y', strtotime($date));
}
?>

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto px-6 pt-40 pb-10">

    <!-- WELCOME -->
    <section class="mb-8">
        <h1 class="text-5xl font-bold text-slate-900 leading-tight">
            Welcome back, <?= e($studentName) ?>!
        </h1>
        <p class="mt-3 text-3xl text-slate-600">
            Student ID: <?= e($studentId ?: '—') ?>
        </p>
    </section>

    <!-- STATS -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-2xl border border-gray-200 p-7 shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-xl font-semibold text-slate-900">Active Borrowings</h3>
                <span class="text-blue-600 text-2xl">◔</span>
            </div>
            <div class="text-5xl font-bold text-slate-900"><?= e($activeBorrowings) ?></div>
            <p class="mt-2 text-slate-500 text-lg">Currently borrowed books</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-7 shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-xl font-semibold text-slate-900">Active Reservations</h3>
                <span class="text-green-600 text-2xl">▣</span>
            </div>
            <div class="text-5xl font-bold text-slate-900"><?= e($activeReservations) ?></div>
            <p class="mt-2 text-slate-500 text-lg">Reserved books</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-7 shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-xl font-semibold text-slate-900">Overdue Books</h3>
                <span class="text-red-500 text-2xl">!</span>
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
                    <?php $cover = !empty($row['coverImage']) 
                    ? '/library-management-system/admin/' . ltrim($row['coverImage'], '/')
                    : 'https://placehold.co/90x125?text=No+Cover'; ?>
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

                                <?php if ($row['status'] === 'overdue'): ?>
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
                                    <p class="font-medium <?= $row['status'] === 'overdue' ? 'text-red-600' : 'text-slate-900' ?>">
                                        <?= e(formatDateText($row['dueDate'])) ?>
                                    </p>
                                </div>
                            </div>

                            <?php if ((float)$row['penalty'] > 0): ?>
                                <div class="mt-4 text-red-600 font-medium">
                                    Penalty: ₱<?= number_format((float)$row['penalty'], 2) ?>
                                </div>
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
            <p><span class="font-bold">Library Hours:</span> Monday - Saturday, 5:00 AM - 5:00 PM</p>

            <div>
                <p class="font-bold mb-2">Late Fees:</p>
                <ul class="list-disc pl-8 text-slate-700 space-y-1">
                    <li>1-2 hours late: ₱2 per hour</li>
                    <li>1 day or more late: ₱10 per day</li>
                </ul>
            </div>
        </div>
    </section>

</main>

</body>
</html>