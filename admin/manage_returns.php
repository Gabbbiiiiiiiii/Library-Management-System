<?php
session_start();
require_once "auth_check.php";

$currentPage = 'manage_returns';

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
} catch (PDOException $e) {
    die("Database connection failed.");
}

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

function getDaysLate(?string $dueDate, ?string $returnDate = null): int {
    if (empty($dueDate)) {
        return 0;
    }

    $due = new DateTime($dueDate);
    $ret = $returnDate ? new DateTime($returnDate) : new DateTime();

    if ($ret <= $due) {
        return 0;
    }

    return (int) $due->diff($ret)->days;
}

function calculatePenalty(?string $dueDate, ?string $returnDate = null, float $ratePerDay = 5.00): float {
    $daysLate = getDaysLate($dueDate, $returnDate);
    return $daysLate * $ratePerDay;
}

/* ================= HANDLE RETURN ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_id'])) {
    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die("❌ Invalid CSRF token.");
    }

    $returnId = (int) $_POST['return_id'];

    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            bk.title AS book_title,
            bk.author AS book_author,
            bk.isbn AS book_isbn,
            bk.coverImage AS book_cover,
            u.fullname AS user_fullname,
            u.student_id AS user_student_id,
            u.course AS user_course,
            u.yearlvl AS user_yearlvl
        FROM borrowings b
        LEFT JOIN books bk ON bk.id = b.book_id
        LEFT JOIN users u ON u.id = b.user_id
        WHERE b.id = ?
        LIMIT 1
    ");
    $stmt->execute([$returnId]);
    $borrowing = $stmt->fetch();

    if (!$borrowing) {
        die("❌ Borrowing record not found.");
    }

    if ($borrowing['status'] === 'returned') {
        header("Location: manage_returns.php");
        exit();
    }

    $studentName = $borrowing['studentName'] ?: ($borrowing['user_fullname'] ?: 'Unknown Student');
    $studentId   = $borrowing['student_id'] ?: ($borrowing['user_student_id'] ?: null);
    $course      = $borrowing['course'] ?: ($borrowing['user_course'] ?: null);
    $yearlvl     = $borrowing['yearlvl'] ?: ($borrowing['user_yearlvl'] ?: null);

    $daysLate = getDaysLate($borrowing['dueDate'], date('Y-m-d'));
    $penalty  = calculatePenalty($borrowing['dueDate'], date('Y-m-d'));
    $remarks  = $daysLate > 0 ? "Returned {$daysLate} day(s) late" : "Returned on time";

    $pdo->beginTransaction();

    try {
        $insertReturn = $pdo->prepare("
            INSERT INTO returns (
                borrowing_id,
                book_id,
                user_id,
                student_name,
                student_id,
                course,
                yearlvl,
                borrow_date,
                due_date,
                return_date,
                days_late,
                penalty,
                remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)
        ");

        $insertReturn->execute([
            $borrowing['id'],
            $borrowing['book_id'],
            $borrowing['user_id'] ?: null,
            $studentName,
            $studentId,
            $course,
            $yearlvl,
            $borrowing['borrowDate'],
            $borrowing['dueDate'],
            $daysLate,
            $penalty,
            $remarks
        ]);

        $updateBorrowing = $pdo->prepare("
            UPDATE borrowings
            SET status = 'returned',
                returnDate = CURDATE(),
                penalty = ?
            WHERE id = ?
        ");
        $updateBorrowing->execute([$penalty, $borrowing['id']]);

        $updateBook = $pdo->prepare("
            UPDATE books
            SET availableCopies = availableCopies + 1
            WHERE id = ?
        ");
        $updateBook->execute([$borrowing['book_id']]);

        $pdo->commit();

        $_SESSION['success_message'] = 'Book returned successfully.';
    } catch (Exception $e) {
        $pdo->rollBack();
        die("❌ Failed to process return.");
    }

    header("Location: manage_returns.php");
    exit();
}

/* ================= AUTO UPDATE OVERDUE ================= */
$pdo->exec("
    UPDATE borrowings
    SET status = 'overdue'
    WHERE status = 'borrowed'
      AND dueDate IS NOT NULL
      AND dueDate < CURDATE()
      AND returnDate IS NULL
");

/* ================= SEARCH ================= */
$search = trim($_GET['search'] ?? '');

/* ================= FETCH ACTIVE BORROWINGS ================= */
/* Based on your TS logic, this page processes active borrowings only. */
$sql = "
    SELECT
        b.*,
        bk.title AS book_title,
        bk.author AS book_author,
        bk.isbn AS book_isbn,
        bk.coverImage AS book_cover,
        u.fullname AS user_fullname,
        u.student_id AS user_student_id,
        u.course AS user_course,
        u.yearlvl AS user_yearlvl
    FROM borrowings b
    LEFT JOIN books bk ON bk.id = b.book_id
    LEFT JOIN users u ON u.id = b.user_id
    WHERE b.status IN ('borrowed', 'overdue')
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            b.studentName LIKE :search
            OR b.student_id LIKE :search
            OR u.fullname LIKE :search
            OR u.student_id LIKE :search
            OR bk.title LIKE :search
            OR bk.isbn LIKE :search
            OR CAST(b.id AS CHAR) LIKE :search
        )
    ";
    $params[':search'] = "%{$search}%";
}

$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$borrowings = $stmt->fetchAll();

$successMessage = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Returns</title>
    <link href="/library-management-system/assets/css/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'header.php'; ?>

<div class="max-w-7xl mx-auto p-6 mt-36">

    <!-- HEADER -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Process Returns</h1>
        <p class="text-gray-600 mt-2 text-lg">Process book returns and calculate penalties</p>
    </div>

    <!-- SUCCESS MESSAGE -->
    <?php if ($successMessage !== ''): ?>
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <!-- SEARCH -->
    <form method="GET" class="mb-8">
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

    <!-- ACTIVE BORROWINGS -->
    <div class="space-y-4">
        <?php if (empty($borrowings)): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm text-center text-gray-500">
                <?= $search === '' ? 'No active borrowings to process' : 'No borrowings match your search' ?>
            </div>
        <?php else: ?>

            <?php foreach ($borrowings as $row): ?>
                <?php
                    $studentName = $row['studentName'] ?: ($row['user_fullname'] ?: 'Unknown Student');
                    $studentId   = $row['student_id'] ?: ($row['user_student_id'] ?: '—');
                    $cover       = !empty($row['book_cover']) ? $row['book_cover'] : 'https://placehold.co/80x112?text=No+Cover';
                    $isOverdue   = getDaysLate($row['dueDate']) > 0;
                    $penalty     = calculatePenalty($row['dueDate']);
                    $daysLate    = getDaysLate($row['dueDate']);
                    $modalId     = 'returnModal' . (int)$row['id'];
                ?>

                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex flex-col md:flex-row gap-4">
                        <img
                            src="<?= e($cover) ?>"
                            alt="Book Cover"
                            class="w-20 h-28 object-cover rounded border"
                        >

                        <div class="flex-1">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-lg"><?= e($row['book_title'] ?: 'Unknown Book') ?></h3>
                                    <p class="text-sm text-gray-600"><?= e($row['book_author'] ?: 'Unknown Author') ?></p>
                                </div>

                                <?php if ($isOverdue): ?>
                                    <span class="inline-flex rounded-full bg-red-100 text-red-700 px-3 py-1 text-xs font-semibold">
                                        Overdue
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-3 text-sm mt-4">
                                <div>
                                    <span class="text-gray-500">Student:</span>
                                    <p class="font-medium text-gray-900"><?= e($studentName) ?></p>
                                </div>

                                <div>
                                    <span class="text-gray-500">Student ID:</span>
                                    <p class="font-medium text-gray-900"><?= e($studentId) ?></p>
                                </div>

                                <div>
                                    <span class="text-gray-500">Borrowed:</span>
                                    <p class="font-medium text-gray-900"><?= e(formatDateText($row['borrowDate'])) ?></p>
                                </div>

                                <div>
                                    <span class="text-gray-500">Due Date:</span>
                                    <p class="font-medium <?= $isOverdue ? 'text-red-600' : 'text-gray-900' ?>">
                                        <?= e(formatDateText($row['dueDate'])) ?>
                                    </p>
                                </div>

                                <?php if ($penalty > 0): ?>
                                    <div>
                                        <span class="text-gray-500">Current Penalty:</span>
                                        <p class="font-medium text-red-600">₱<?= number_format($penalty, 2) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button
                                type="button"
                                onclick="openReturnModal('<?= e($modalId) ?>')"
                                class="mt-4 inline-flex items-center rounded-lg bg-purple-600 px-4 py-2 text-white hover:bg-purple-700 transition"
                            >
                                Process Return
                            </button>
                        </div>
                    </div>
                </div>

                <!-- MODAL -->
                <div id="<?= e($modalId) ?>" class="fixed inset-0 hidden z-50">
                    <div class="absolute inset-0 bg-black/50" onclick="closeReturnModal('<?= e($modalId) ?>')"></div>

                    <div class="relative flex min-h-screen items-center justify-center p-4">
                        <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl overflow-hidden">
                            <div class="border-b px-6 py-4">
                                <h2 class="text-xl font-bold text-gray-900">Process Book Return</h2>
                                <p class="text-sm text-gray-600 mt-1">Confirm the return of this book and apply any penalties</p>
                            </div>

                            <div class="p-6 space-y-4">
                                <div class="flex gap-4">
                                    <img
                                        src="<?= e($cover) ?>"
                                        alt="Book Cover"
                                        class="w-20 h-28 object-cover rounded border"
                                    >
                                    <div>
                                        <h4 class="font-semibold text-gray-900"><?= e($row['book_title'] ?: 'Unknown Book') ?></h4>
                                        <p class="text-sm text-gray-600"><?= e($row['book_author'] ?: 'Unknown Author') ?></p>
                                        <p class="text-xs text-gray-500 mt-2">ISBN: <?= e($row['book_isbn'] ?: '—') ?></p>
                                    </div>
                                </div>

                                <div class="border-t pt-4 space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Student:</span>
                                        <span class="font-medium"><?= e($studentName) ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Student ID:</span>
                                        <span class="font-medium"><?= e($studentId) ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Borrow Date:</span>
                                        <span class="font-medium"><?= e(formatDateText($row['borrowDate'])) ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Due Date:</span>
                                        <span class="font-medium"><?= e(formatDateText($row['dueDate'])) ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Return Date:</span>
                                        <span class="font-medium"><?= e(date('M d, Y')) ?></span>
                                    </div>
                                </div>

                                <?php if ($penalty > 0): ?>
                                    <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium text-red-900">Penalty Amount:</span>
                                            <span class="text-2xl font-bold text-red-600">₱<?= number_format($penalty, 2) ?></span>
                                        </div>
                                        <p class="mt-2 text-xs text-red-700">
                                            Book was returned <?= e($daysLate) ?> day(s) late
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                                        <p class="font-medium text-green-900">No penalty - returned on time!</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex justify-end gap-3 border-t px-6 py-4">
                                <button
                                    type="button"
                                    onclick="closeReturnModal('<?= e($modalId) ?>')"
                                    class="rounded-lg border px-4 py-2 hover:bg-gray-100"
                                >
                                    Cancel
                                </button>

                                <form method="POST">
                                    <input type="hidden" name="token" value="<?= e($_SESSION['token']) ?>">
                                    <input type="hidden" name="return_id" value="<?= e($row['id']) ?>">
                                    <button
                                        type="submit"
                                        class="rounded-lg bg-purple-600 px-4 py-2 text-white hover:bg-purple-700"
                                    >
                                        Confirm Return
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function openReturnModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
}

function closeReturnModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
}

const searchInput = document.querySelector('input[name="search"]');
if (searchInput) {
    searchInput.addEventListener('input', function () {
        if (this.value.trim() === '') {
            window.location.href = 'manage_returns.php';
        }
    });
}
</script>

</body>
</html>