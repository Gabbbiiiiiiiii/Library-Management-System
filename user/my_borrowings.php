<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/student_login.php");
    exit();
}

$currentPage = 'my_borrowings';

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
$userId = $_SESSION['user_id'] ?? null;
$studentName = $_SESSION['fullname'] ?? 'Student';
$studentId = $_SESSION['student_id'] ?? '';

// /* ================= HELPERS ================= */
// function e($value): string {
//     return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
// }

// function formatDateText($date): string {
//     if (empty($date) || $date === '0000-00-00') {
//         return '—';
//     }
//     return date('M d, Y h:i A', strtotime($date));
// }

// function calculateCurrentPenalty(array $row): float {
//     if (!in_array($row['status'], ['borrowed', 'overdue'], true)) {
//         return (float)($row['penalty'] ?? 0);
//     }

//     if (empty($row['dueDate'])) {
//         return 0.00;
//     }

//     $due = strtotime($row['dueDate']);
//     $today = strtotime(date('Y-m-d H:i:s'));

//     if ($today <= $due) {
//         return 0.00;
//     }

//     $daysLate = (int) floor(($today - $due) / 86400);
//     return $daysLate * 10.00;
// }

/* ================= AUTO UPDATE OVERDUE ================= */
$pdo->exec("
    UPDATE borrowings
    SET status = 'overdue'
    WHERE status = 'borrowed'
      AND dueDate IS NOT NULL
      AND dueDate < NOW()
      AND returnDate IS NULL
");

/* ================= TAB ================= */
$tab = trim($_GET['tab'] ?? 'active');
if (!in_array($tab, ['active', 'history'], true)) {
    $tab = 'active';
}

/* ================= FETCH MY BORROWINGS ================= */
$sql = "
    SELECT 
        b.*,
        bk.title,
        bk.author,
        bk.isbn,
        bk.coverImage
    FROM borrowings b
    LEFT JOIN books bk ON bk.id = b.book_id
    WHERE 1=1
";

$params = [];

if ($userId) {
    $sql .= " AND b.user_id = :user_id";
    $params[':user_id'] = $userId;
} else {
    $sql .= " AND (b.student_id = :student_id OR b.studentName = :student_name)";
    $params[':student_id'] = $studentId;
    $params[':student_name'] = $studentName;
}

$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allBorrowings = $stmt->fetchAll();

/* ================= SPLIT ACTIVE / HISTORY ================= */
$activeBorrowings = array_values(array_filter($allBorrowings, function ($row) {
    return in_array($row['status'], ['borrowed', 'overdue'], true);
}));

$returnedBorrowings = array_values(array_filter($allBorrowings, function ($row) {
    return $row['status'] === 'returned';
}));

usort($returnedBorrowings, function ($a, $b) {
    return strtotime($b['returnDate'] ?? '1970-01-01') <=> strtotime($a['returnDate'] ?? '1970-01-01');
});
?>

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto px-6 pt-40 pb-10">

    <!-- PAGE HEADER -->
    <section class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900">My Borrowings</h1>
        <p class="mt-2 text-slate-600 text-lg">View your current and past book borrowings</p>
    </section>

    <!-- TABS -->
    <section class="mb-8">
        <div class="inline-flex bg-white rounded-2xl p-1 border border-gray-200 shadow-sm gap-1">
            <a href="?tab=active"
               class="px-4 py-2 rounded-xl font-medium transition <?= $tab === 'active' ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' ?>">
                Active (<?= e(count($activeBorrowings)) ?>)
            </a>

            <a href="?tab=history"
               class="px-4 py-2 rounded-xl font-medium transition <?= $tab === 'history' ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' ?>">
                History (<?= e(count($returnedBorrowings)) ?>)
            </a>
        </div>
    </section>

    <!-- ACTIVE -->
    <?php if ($tab === 'active'): ?>
        <section class="space-y-4 mb-8">
            <?php if (empty($activeBorrowings)): ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                    <h3 class="text-2xl font-semibold text-gray-900">No Active Borrowings</h3>
                    <p class="text-gray-600 mt-2">You don't have any books borrowed at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($activeBorrowings as $row): ?>
                    <?php
                        $cover = !empty($row['coverImage'])
                            ? '/library-management-system/admin/' . ltrim($row['coverImage'], '/')
                            : 'https://placehold.co/100x140?text=No+Cover';

                        $isOverdue = isOverdue($row['dueDate'] ?? null) || (($row['status'] ?? '') === 'overdue');
                        $penaltyInfo = calculatePenaltyAdvanced($row['dueDate'] ?? null, nowDateTime());
                        $currentPenalty = $penaltyInfo['penalty'];
                    ?>
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
                        <div class="flex flex-col md:flex-row gap-4">
                            <img
                                src="<?= e($cover) ?>"
                                alt="<?= e($row['title'] ?: 'Book Cover') ?>"
                                onerror="this.src='https://placehold.co/100x140?text=No+Cover'"
                                class="w-20 h-28 object-cover rounded"
                            >

                            <div class="flex-1 space-y-2">
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-lg">
                                        <?= e($row['title'] ?: 'Unknown Book') ?>
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        <?= e($row['author'] ?: 'Unknown Author') ?>
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <div>
                                        <span class="text-gray-500">Borrowed:</span>
                                        <p class="font-medium"><?= e(formatDateText($row['borrowDate'])) ?></p>
                                    </div>

                                    <div>
                                        <span class="text-gray-500">Due Date:</span>
                                        <p class="font-medium <?= $isOverdue ? 'text-red-600' : '' ?>">
                                            <?= e(formatDateText($row['dueDate'])) ?>
                                        </p>
                                    </div>

                                    <?php if ($currentPenalty > 0): ?>
                                        <div>
                                            <span class="text-gray-500">Penalty:</span>
                                            <p class="font-medium text-red-600">₱<?= number_format($currentPenalty, 2) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <?php if ($isOverdue): ?>
                                        <span class="inline-flex items-center rounded-full bg-red-100 text-red-700 px-3 py-1 text-xs font-semibold">
                                            Overdue
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full bg-blue-100 text-blue-700 px-3 py-1 text-xs font-semibold">
                                            Active
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- HISTORY -->
    <?php if ($tab === 'history'): ?>
        <section class="space-y-4 mb-8">
            <?php if (empty($returnedBorrowings)): ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                    <h3 class="text-2xl font-semibold text-gray-900">No Borrowing History</h3>
                    <p class="text-gray-600 mt-2">Your borrowing history will appear here once you return books.</p>
                </div>
            <?php else: ?>
                <?php foreach ($returnedBorrowings as $row): ?>
                    <?php
                        $cover = !empty($row['coverImage'])
                            ? '/library-management-system/admin/' . ltrim($row['coverImage'], '/')
                            : 'https://placehold.co/100x140?text=No+Cover';
                    ?>
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
                        <div class="flex flex-col md:flex-row gap-4">
                            <img
                                src="<?= e($cover) ?>"
                                alt="<?= e($row['title'] ?: 'Book Cover') ?>"
                                onerror="this.src='https://placehold.co/100x140?text=No+Cover'"
                                class="w-20 h-28 object-cover rounded"
                            >

                            <div class="flex-1 space-y-2">
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-lg">
                                        <?= e($row['title'] ?: 'Unknown Book') ?>
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        <?= e($row['author'] ?: 'Unknown Author') ?>
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <div>
                                        <span class="text-gray-500">Borrowed:</span>
                                        <p class="font-medium"><?= e(formatDateText($row['borrowDate'])) ?></p>
                                    </div>

                                    <div>
                                        <span class="text-gray-500">Due Date:</span>
                                        <p class="font-medium"><?= e(formatDateText($row['dueDate'])) ?></p>
                                    </div>

                                    <?php if (!empty($row['returnDate'])): ?>
                                        <div>
                                            <span class="text-gray-500">Returned:</span>
                                            <p class="font-medium"><?= e(formatDateText($row['returnDate'])) ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ((float)$row['penalty'] > 0): ?>
                                        <div>
                                            <span class="text-gray-500">Penalty:</span>
                                            <p class="font-medium text-red-600">₱<?= number_format((float)$row['penalty'], 2) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center rounded-full bg-gray-200 text-gray-700 px-3 py-1 text-xs font-semibold">
                                        Returned
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- PENALTY INFO -->
    <section class="bg-white rounded-2xl border border-gray-200 p-7 shadow-sm">
        <h2 class="text-2xl font-bold text-slate-900 mb-6">Penalty Information</h2>

        <div class="space-y-2 text-sm">
            <p><strong>Late Fee Structure:</strong></p>
            <ul class="list-disc list-inside ml-4 text-gray-600">
                <li>Late by 1-2 hours: ₱2 per hour</li>
                <li>Late by 1 day or more: ₱10 per day</li>
            </ul>
            <p class="text-xs text-gray-500 mt-4">
                Note: Penalties are calculated based on library operating hours (Mon-Sat, 5am-5pm).
                Hours outside of operating times are not counted.
            </p>
        </div>
    </section>

</main>

</body>
</html>