<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/student_login.php");
    exit();
}

$currentPage = 'book_catalog';

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

/* ================= CSRF TOKEN ================= */
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

/* ================= SESSION DATA ================= */
$userId = $_SESSION['user_id'] ?? null;
$studentName = $_SESSION['fullname'] ?? 'Student';
$studentId = $_SESSION['student_id'] ?? '';

/* ================= HELPERS ================= */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function todayDate(): string {
    return date('Y-m-d');
}

function tomorrowDate(): string {
    return date('Y-m-d', strtotime('+1 day'));
}

/* ================= MESSAGES ================= */
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

/* ================= HANDLE BORROW ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {

    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die("❌ Invalid CSRF token.");
    }

    $bookId = (int)($_POST['book_id'] ?? 0);

    if ($bookId < 1 || !$userId) {
        $_SESSION['error_message'] = "Invalid borrow request.";
        header("Location: book_catalog.php");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM books
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();

    if (!$book) {
        $_SESSION['error_message'] = "Book not found.";
        header("Location: book_catalog.php");
        exit();
    }

    if ((int)$book['availableCopies'] <= 0) {
        $_SESSION['error_message'] = "This book is not currently available.";
        header("Location: book_catalog.php");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM borrowings
        WHERE user_id = ?
          AND book_id = ?
          AND status IN ('borrowed', 'overdue')
    ");
    $stmt->execute([$userId, $bookId]);
    $alreadyBorrowed = (int)$stmt->fetchColumn() > 0;

    if ($alreadyBorrowed) {
        $_SESSION['error_message'] = "You already have this book borrowed.";
        header("Location: book_catalog.php");
        exit();
    }

    $pdo->beginTransaction();

    try {
        $insertBorrowing = $pdo->prepare("
            INSERT INTO borrowings (
                book_id,
                user_id,
                studentName,
                student_id,
                borrowDate,
                dueDate,
                returnDate,
                status,
                penalty
            ) VALUES (?, ?, ?, ?, ?, ?, NULL, 'borrowed', 0.00)
        ");
        $insertBorrowing->execute([
            $bookId,
            $userId,
            $studentName,
            $studentId,
            todayDate(),
            tomorrowDate()
        ]);

        $updateBook = $pdo->prepare("
            UPDATE books
            SET availableCopies = availableCopies - 1
            WHERE id = ?
              AND availableCopies > 0
        ");
        $updateBook->execute([$bookId]);

        $pdo->commit();

        $_SESSION['success_message'] = "Book borrowed successfully! Please pick it up at the library.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Unable to borrow book.";
    }

    header("Location: book_catalog.php");
    exit();
}

/* ================= HANDLE RESERVE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reserve') {

    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die("❌ Invalid CSRF token.");
    }

    $bookId = (int)($_POST['book_id'] ?? 0);

    if ($bookId < 1 || !$userId) {
        $_SESSION['error_message'] = "Invalid reservation request.";
        header("Location: book_catalog.php");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM books
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();

    if (!$book) {
        $_SESSION['error_message'] = "Book not found.";
        header("Location: book_catalog.php");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reservations
        WHERE user_id = ?
          AND book_id = ?
          AND status IN ('pending', 'ready')
    ");
    $stmt->execute([$userId, $bookId]);
    $alreadyReserved = (int)$stmt->fetchColumn() > 0;

    if ($alreadyReserved) {
        $_SESSION['error_message'] = "You already have a reservation for this book.";
        header("Location: book_catalog.php");
        exit();
    }

    $stmt = $pdo->prepare("
        INSERT INTO reservations (
            book_id,
            user_id,
            studentName,
            student_id,
            reservationDate,
            expiryDate,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $bookId,
        $userId,
        $studentName,
        $studentId,
        todayDate(),
        date('Y-m-d', strtotime('+3 days'))
    ]);

    $_SESSION['success_message'] = "Book reserved successfully! You will be notified when it is available.";
    header("Location: book_catalog.php");
    exit();
}

/* ================= SEARCH / FILTER ================= */
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? 'all');

$sql = "SELECT * FROM books WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (title LIKE :search OR author LIKE :search OR isbn LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($category !== '' && $category !== 'all') {
    $sql .= " AND category = :category";
    $params[':category'] = $category;
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

/* ================= CATEGORIES ================= */
$categoryStmt = $pdo->query("
    SELECT DISTINCT category
    FROM books
    WHERE category IS NOT NULL AND category <> ''
    ORDER BY category ASC
");
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto px-6 pt-40 pb-10">

    <!-- PAGE HEADER -->
    <section class="mb-8">
        <h1 class="text-4xl font-bold text-slate-900">Book Catalog</h1>
        <p class="mt-2 text-slate-600 text-lg">Browse and search for books</p>
    </section>

    <!-- ALERTS -->
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

    <!-- SEARCH + FILTER -->
    <form method="GET" class="mb-8 flex flex-col md:flex-row gap-4">
        <div class="flex-1 relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg">⌕</span>
            <input
                type="text"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Search by title, author, or ISBN..."
                class="w-full bg-white border border-gray-200 rounded-xl pl-12 pr-4 py-3 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>

        <select
            name="category"
            class="w-full md:w-56 bg-white border border-gray-200 rounded-xl px-4 py-3 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
            <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                    <?= e($cat) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button
            type="submit"
            class="bg-blue-600 text-white rounded-xl px-5 py-3 hover:bg-blue-700"
        >
            Search
        </button>
    </form>

    <!-- BOOK GRID -->
    <?php if (empty($books)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center shadow-sm">
            <div class="text-5xl mb-4">📚</div>
            <p class="text-slate-500 text-lg">No books found matching your search.</p>
        </div>
    <?php else: ?>
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($books as $book): ?>
                <?php
                    $base = '/library-management-system/admin/';
                    $cover = !empty($book['coverImage'])
                        ? $base . ltrim($book['coverImage'], '/')
                        : 'https://placehold.co/400x520?text=No+Cover';
                    $isAvailable = (int)$book['availableCopies'] > 0;
                    $modalId = 'bookModal' . (int)$book['id'];
                    
                ?>
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-lg transition-shadow">
                    <div class="relative aspect-[3/4]">
                        <img src="<?= e($cover) ?>"
                             onerror="this.src='https://placehold.co/400x520?text=No+Cover'"
                            alt="<?= e($book['title']) ?>"
                            class="w-full h-full object-cover"
                        >
                        <div class="absolute top-3 right-3">
                            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= $isAvailable ? 'bg-blue-950 text-white' : 'bg-gray-300 text-gray-800' ?>">
                                <?= $isAvailable ? 'Available' : 'Not Available' ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-4">
                        <h3 class="text-xl font-semibold text-slate-900 leading-snug min-h-[56px]">
                            <?= e($book['title']) ?>
                        </h3>
                        <p class="mt-1 text-slate-600"><?= e($book['author']) ?></p>

                        <div class="mt-4 text-sm text-slate-500 space-y-1">
                            <p>ISBN: <?= e($book['isbn']) ?></p>
                            <p>Category: <?= e($book['category']) ?></p>
                            <p>Copies: <?= e($book['availableCopies']) ?>/<?= e($book['totalCopies']) ?></p>
                        </div>

                        <button
                            type="button"
                            onclick="openBookModal('<?= e($modalId) ?>')"
                            class="mt-5 w-full bg-blue-950 text-white rounded-xl px-4 py-3 font-semibold hover:bg-blue-900"
                        >
                            <?= $isAvailable ? '📖 Borrow' : 'Reserve' ?>
                        </button>
                    </div>
                </div>

                <!-- MODAL -->
                <div id="<?= e($modalId) ?>" class="fixed inset-0 hidden z-50">
                    <div class="absolute inset-0 bg-black/50" onclick="closeBookModal('<?= e($modalId) ?>')"></div>

                    <div class="relative flex min-h-screen items-center justify-center p-4">
                        <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl overflow-hidden">
                            <div class="border-b px-6 py-4">
                                <h2 class="text-xl font-bold text-gray-900">
                                    <?= $isAvailable ? 'Borrow Book' : 'Reserve Book' ?>
                                </h2>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?= $isAvailable ? 'Are you sure you want to borrow this book?' : 'Are you sure you want to reserve this book?' ?>
                                </p>
                            </div>

                            <div class="p-6">
                                <div class="flex gap-4">
                                    <img
                                        src="<?= e($cover) ?>"
                                        alt="<?= e($book['title']) ?>"
                                        class="w-20 h-28 object-cover rounded border"
                                    >
                                    <div>
                                        <h4 class="font-semibold text-gray-900"><?= e($book['title']) ?></h4>
                                        <p class="text-sm text-gray-600"><?= e($book['author']) ?></p>
                                        <p class="text-xs text-gray-500 mt-2">ISBN: <?= e($book['isbn']) ?></p>
                                    </div>
                                </div>

                                <?php if ($isAvailable): ?>
                                    <div class="mt-4 rounded-lg bg-blue-50 p-4 text-sm">
                                        <p class="font-semibold text-blue-900">Borrowing Terms:</p>
                                        <ul class="list-disc list-inside mt-2 text-blue-800 space-y-1">
                                            <li>Borrowing period: 1 day</li>
                                            <li>Return by: <?= e(date('m/d/Y', strtotime('+1 day'))) ?></li>
                                            <li>Late fee: ₱2 per hour (1-2 hrs), ₱10 per day</li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex justify-end gap-3 border-t px-6 py-4">
                                <button
                                    type="button"
                                    onclick="closeBookModal('<?= e($modalId) ?>')"
                                    class="rounded-lg border px-4 py-2 hover:bg-gray-100"
                                >
                                    Cancel
                                </button>

                                <form method="POST">
                                    <input type="hidden" name="token" value="<?= e($_SESSION['token']) ?>">
                                    <input type="hidden" name="book_id" value="<?= e($book['id']) ?>">
                                    <input type="hidden" name="action" value="<?= $isAvailable ? 'borrow' : 'reserve' ?>">

                                    <button
                                        type="submit"
                                        class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                                    >
                                        Confirm
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        </section>
    <?php endif; ?>

</main>

<script>
function openBookModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
}

function closeBookModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
}

// Auto-reload page when search input is cleared
const searchInput = document.querySelector('input[name="search"]');
let timeout;

if (searchInput) {
    searchInput.addEventListener('input', function () {
        clearTimeout(timeout);

        timeout = setTimeout(() => {
            const params = new URLSearchParams(window.location.search);
            const currentCategory = params.get('category') || 'all';

            if (this.value.trim() === '') {
                // reset search but keep category
                window.location.href = 'book_catalog.php?category=' + encodeURIComponent(currentCategory);
            } else {
                // live search
                window.location.href =
                    'book_catalog.php?search=' + encodeURIComponent(this.value) +
                    '&category=' + encodeURIComponent(currentCategory);
            }
        }, 500); // 0.5s delay
    });
}
</script>

</body>
</html>