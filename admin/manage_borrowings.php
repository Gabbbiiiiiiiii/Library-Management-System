<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';
require_once "auth_check.php";

$currentPage = 'manage_borrowings';

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

/* ================= OPTIONAL: AUTO UPDATE OVERDUE ================= */
/* borrowed + due date passed => overdue */
$pdo->exec("
    UPDATE borrowings
    SET status = 'overdue'
    WHERE status = 'borrowed'
      AND dueDate IS NOT NULL
      AND dueDate < NOW()
      AND returnDate IS NULL
");

/* ================= HANDLE RETURN ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_id'])) {

    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die("❌ Invalid CSRF token.");
    }

    $returnId = (int) $_POST['return_id'];

    $stmt = $pdo->prepare("
        SELECT id, book_id, status, dueDate, returnDate
        FROM borrowings
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$returnId]);
    $borrowing = $stmt->fetch();

    if (!$borrowing) {
        die("❌ Borrowing record not found.");
    }

    if ($borrowing['status'] === 'returned' || !empty($borrowing['returnDate'])) {
        header("Location: manage_borrowings.php");
        exit();
    }

    $penalty = 0.00;
    $today = new DateTime();
    $dueDate = !empty($borrowing['dueDate']) ? new DateTime($borrowing['dueDate']) : null;

    if ($dueDate && $today > $dueDate) {
        $daysLate = $dueDate->diff($today)->days;
        $penalty = $daysLate * 5.00; // change this if you want another penalty rate
    }

    $pdo->beginTransaction();

    try {
        $updateBorrowing = $pdo->prepare("
            UPDATE borrowings
            SET status = 'returned',
                returnDate = NOW(),
                penalty = ?
            WHERE id = ?
        ");
        $updateBorrowing->execute([$penalty, $returnId]);

        $updateBook = $pdo->prepare("
            UPDATE books
            SET availableCopies = availableCopies + 1
            WHERE id = ?
        ");
        $updateBook->execute([$borrowing['book_id']]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("❌ Failed to return book.");
    }

    header("Location: manage_borrowings.php");
    exit();
}

/* ================= SEARCH + TAB ================= */
$search = trim($_GET['search'] ?? '');
$tab = trim($_GET['tab'] ?? 'active');

$allowedTabs = ['active', 'overdue', 'returned'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'active';
}

/* ================= COUNTS ================= */
$countActiveStmt = $pdo->query("
    SELECT COUNT(*) 
    FROM borrowings
    WHERE status = 'borrowed'
");
$activeCount = (int) $countActiveStmt->fetchColumn();

$countOverdueStmt = $pdo->query("
    SELECT COUNT(*) 
    FROM borrowings
    WHERE status = 'overdue'
");
$overdueCount = (int) $countOverdueStmt->fetchColumn();

$countTotalStmt = $pdo->query("
    SELECT COUNT(*) 
    FROM borrowings
");
$totalCount = (int) $countTotalStmt->fetchColumn();

$returnedCountStmt = $pdo->query("
    SELECT COUNT(*) 
    FROM borrowings
    WHERE status = 'returned'
");
$returnedCount = (int) $returnedCountStmt->fetchColumn();

/* ================= FILTER BY TAB ================= */
$statusFilter = match ($tab) {
    'active' => 'borrowed',
    'overdue' => 'overdue',
    'returned' => 'returned',
    default => 'borrowed'
};

/* ================= FETCH BORROWINGS ================= */
$sql = "
    SELECT 
        b.id,
        b.book_id,
        b.user_id,
        b.studentName,
        b.student_id,
        b.course,
        b.yearlvl,
        b.borrowDate,
        b.dueDate,
        b.returnDate,
        b.status,
        b.penalty,

        bk.title AS book_title,
        bk.author AS book_author,
        bk.isbn AS book_isbn,
        bk.coverImage AS book_cover,

        u.fullname AS user_fullname,
        u.student_id AS user_student_id,
        u.course AS user_course,
        u.yearlvl AS user_yearlvl

    FROM borrowings b
    LEFT JOIN books bk ON b.book_id = bk.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.status = :status
";

$params = [
    ':status' => $statusFilter
];

if ($search !== '') {
    $sql .= "
        AND (
            b.studentName LIKE :search
            OR b.student_id LIKE :search
            OR u.fullname LIKE :search
            OR u.student_id LIKE :search
            OR bk.title LIKE :search
            OR bk.author LIKE :search
            OR bk.isbn LIKE :search
            OR CAST(b.id AS CHAR) LIKE :search
        )
    ";
    $params[':search'] = "%{$search}%";
}

if ($tab === 'returned') {
    $sql .= " ORDER BY b.returnDate DESC, b.id DESC";
} else {
    $sql .= " ORDER BY b.id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$borrowings = $stmt->fetchAll();

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

// function getStudentDisplayName(array $row): string {
//     if (!empty($row['studentName'])) return $row['studentName'];
//     if (!empty($row['user_fullname'])) return $row['user_fullname'];
//     return 'Unknown Student';
// }

// function getStudentIdValue(array $row): string {
//     if (!empty($row['student_id'])) return $row['student_id'];
//     if (!empty($row['user_student_id'])) return $row['user_student_id'];
//     return '—';
// }

function getCourseValue(array $row): string {
    if (!empty($row['course'])) return $row['course'];
    if (!empty($row['user_course'])) return $row['user_course'];
    return '—';
}

function getYearLevelValue(array $row): string {
    if (!empty($row['yearlvl'])) return $row['yearlvl'];
    if (!empty($row['user_yearlvl'])) return $row['user_yearlvl'];
    return '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowings</title>
    <link href="/library-management-system/assets/css/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'header.php'; ?>

<!-- main page -->
<div class="max-w-[1489px] mx-auto px-6 pt-28 pb-10">

    <!-- PAGE HEADER -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900">All Borrowings</h1>
        <p class="text-gray-600 mt-2 text-lg">View and manage all book borrowings</p>
    </div>
    
    <div class="mb-6">
    <a href="export_borrowings_csv.php"
       class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700">
        Export Borrowings CSV
    </a>
</div>

    <!-- STATS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-7">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-xl font-semibold text-gray-900">Active Borrowings</h3>
                <span class="text-blue-600 text-2xl">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" class="inline w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
            </div>
            <div class="text-4xl font-bold text-gray-900"><?= e($activeCount) ?></div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-xl font-semibold text-gray-900">Overdue</h3>
                <span class="text-red-500 text-2xl">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" class="inline w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
            </div>
            <div class="text-4xl font-bold text-red-600"><?= e($overdueCount) ?></div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-xl font-semibold text-gray-900">Total Borrowings</h3>
                <span class="text-green-600 text-2xl">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" class="inline w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
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
                placeholder="Search by student name, student ID, book title, ISBN, or borrowing ID..."
                class="w-full bg-white border border-gray-200 rounded-xl pl-12 pr-4 py-4 shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
            >
        </div>
    </form>

    <!-- TABS -->
    <div class="mb-8">
        <div class="inline-flex bg-white rounded-2xl p-1 border border-gray-200 shadow-sm gap-1">
            <a href="?tab=active&search=<?= urlencode($search) ?>"
               class="px-5 py-2.5 rounded-xl font-medium transition <?= $tab === 'active' ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' ?>">
                Active (<?= e($activeCount) ?>)
            </a>

            <a href="?tab=overdue&search=<?= urlencode($search) ?>"
               class="px-5 py-2.5 rounded-xl font-medium transition <?= $tab === 'overdue' ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' ?>">
                Overdue (<?= e($overdueCount) ?>)
            </a>

            <a href="?tab=returned&search=<?= urlencode($search) ?>"
               class="px-5 py-2.5 rounded-xl font-medium transition <?= $tab === 'returned' ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' ?>">
                Returned (<?= e($returnedCount) ?>)
            </a>
        </div>
    </div>

    <!-- LIST -->
    <div class="space-y-4">
        <?php if (empty($borrowings)): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                <?php if ($tab === 'active'): ?>
                    <h3 class="text-2xl font-semibold text-gray-900">No Active Borrowings</h3>
                    <p class="text-gray-600 mt-2">
                        <?= $search === '' ? 'There are no active borrowings at the moment.' : 'No active borrowings match your search.' ?>
                    </p>
                <?php elseif ($tab === 'overdue'): ?>
                    <h3 class="text-2xl font-semibold text-gray-900">No Overdue Books</h3>
                    <p class="text-gray-600 mt-2">
                        <?= $search === '' ? 'No books are currently overdue.' : 'No overdue borrowings match your search.' ?>
                    </p>
                <?php else: ?>
                    <h3 class="text-2xl font-semibold text-gray-900">No Returned Books</h3>
                    <p class="text-gray-600 mt-2">
                        <?= $search === '' ? 'No books have been returned yet.' : 'No returned borrowings match your search.' ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <?php foreach ($borrowings as $row): ?>
                <?php
                    $studentName = getStudentDisplayName($row);
                    $studentId = getStudentIdValue($row);
                    $course = getCourseValue($row);
                    $yearlvl = getYearLevelValue($row);

                    $cover = !empty($row['book_cover'])
                        ? $row['book_cover']
                        : 'https://placehold.co/100x140?text=No+Cover';
                ?>

                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex flex-col md:flex-row gap-4">

                        <!-- BOOK COVER -->
                        <div class="shrink-0">
                            <img src="<?= e($cover) ?>"
                                 alt="Book Cover"
                                 class="w-24 h-32 object-cover rounded-lg border">
                        </div>

                        <!-- CONTENT -->
                        <div class="flex-1">
                            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">
                                        <?= e($row['book_title'] ?: 'Unknown Book') ?>
                                    </h3>
                                    <p class="text-gray-600">
                                        <?= e($row['book_author'] ?: 'Unknown Author') ?>
                                    </p>
                                </div>

                                <div>
                                    <?php if ($row['status'] === 'borrowed'): ?>
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                            Active
                                        </span>
                                    <?php elseif ($row['status'] === 'overdue'): ?>
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                            Overdue
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">
                                            Returned
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm mt-4">
                                <div>
                                    <p class="text-gray-500">Student</p>
                                    <p class="font-medium text-gray-900"><?= e($studentName) ?></p>
                                </div>

                                <div>
                                    <p class="text-gray-500">Student ID</p>
                                    <p class="font-medium text-gray-900"><?= e($studentId) ?></p>
                                </div>

                                <div>
                                    <p class="text-gray-500">Course</p>
                                    <p class="font-medium text-gray-900"><?= e($course) ?></p>
                                </div>

                                <div>
                                    <p class="text-gray-500">Year Level</p>
                                    <p class="font-medium text-gray-900"><?= e($yearlvl) ?></p>
                                </div>

                                <div>
                                    <p class="text-gray-500">Borrowing ID</p>
                                    <p class="font-medium text-gray-900">#<?= e($row['id']) ?></p>
                                </div>

                                <div>
                                    <p class="text-gray-500">ISBN</p>
                                    <p class="font-medium text-gray-900"><?= e($row['book_isbn'] ?: '—') ?></p>
                                </div>

                                <div>
                                    <p class="text-gray-500">Borrow Date</p>
                                    <p class="font-medium text-gray-900"><?= e(formatDateText($row['borrowDate'])) ?></p>
                                </div>

                                <div>
                                    <p class="text-gray-500">Due Date</p>
                                    <p class="font-medium <?= $row['status'] === 'overdue' ? 'text-red-600' : 'text-gray-900' ?>">
                                        <?= e(formatDateText($row['dueDate'])) ?>
                                    </p>
                                </div>

                                <?php if ($row['status'] === 'returned'): ?>
                                    <div>
                                        <p class="text-gray-500">Return Date</p>
                                        <p class="font-medium text-green-600"><?= e(formatDateText($row['returnDate'])) ?></p>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <p class="text-gray-500">Penalty</p>
                                    <p class="font-medium <?= ((float)$row['penalty'] > 0) ? 'text-red-600' : 'text-gray-900' ?>">
                                        ₱<?= number_format((float)$row['penalty'], 2) ?>
                                    </p>
                                </div>
                            </div>

                            <?php if ($row['status'] !== 'returned'): ?>
                                <div class="mt-4 flex gap-2">
                                    <form method="POST" onsubmit="return confirm('Mark this book as returned?')">
                                        <input type="hidden" name="token" value="<?= e($_SESSION['token']) ?>">
                                        <input type="hidden" name="return_id" value="<?= e($row['id']) ?>">
                                        <?php if ($row['status'] === 'borrowed'): ?>
    
                                            <!-- ACTIVE BADGE -->
                                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                                Active
                                            </span>

                                        <?php elseif ($row['status'] === 'returned'): ?>

                                            <!-- RETURNED BADGE -->
                                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-200 text-gray-700">
                                                Returned
                                            </span>

                                        <?php elseif ($row['status'] === 'overdue'): ?>

                                            <!-- OVERDUE BADGE -->
                                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                Overdue
                                            </span>

                                        <?php endif; ?>
                                    </form>
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
            window.location.href = 'manage_borrowings.php?tab=' + encodeURIComponent(currentTab);
        }
    });
}
</script>

</body>
</html>