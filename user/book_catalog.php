<?php
session_start();
require_once __DIR__ . '/../includes/library_helpers.php';

function highlight($text, $search) {
    if (!$search) return e($text);
    return preg_replace(
        '/(' . preg_quote($search, '/') . ')/i',
        '<span class="bg-yellow-200 px-1 rounded">$1</span>',
        e($text)
    );
}

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

function redirectCatalogWithMessage(string $message, string $type = 'error_message'): void
{
    $_SESSION[$type] = $message;
    header("Location: book_catalog.php");
    exit();
}

// /* ================= HELPERS ================= */
// function e($value): string {
//     return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
// }

// function todayDate(): string {
//     return date('Y-m-d H:i:s');
// }

// function tomorrowDate(): string {
//     return date('Y-m-d 08:59:59', strtotime('+1 day')); // due
// }

/* ================= MESSAGES ================= */
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

/* ================= HANDLE BORROW ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {

    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die("❌ Invalid CSRF token.");
    }

    if (!isLibraryOpen()) {
        redirectCatalogWithMessage(libraryClosedMessage());
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

    // 🚫 CHECK IF USER ALREADY HAS A BORROWED BOOK
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM borrowings
        WHERE user_id = ?
        AND status IN ('borrowed', 'overdue')
    ");
    $stmt->execute([$userId]);

    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error_message'] = "You can only borrow 1 book at a time. Return your current book first.";
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
        $borrowDate = nowDateTime();
        $dueDate = nextBorrowDueDateTime($borrowDate);

        $insertBorrowing->execute([
            $bookId,
            $userId,
            $studentName,
            $studentId,
            $borrowDate,
            $dueDate
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

    if (!isLibraryOpen()) {
    redirectCatalogWithMessage(libraryClosedMessage());
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

// 🚫 CHECK IF USER IS CURRENTLY BORROWING THIS SAME BOOK
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM borrowings
        WHERE user_id = ?
          AND book_id = ?
          AND status IN ('borrowed', 'overdue')
    ");
    $stmt->execute([$userId, $bookId]);

    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error_message'] = "You cannot reserve the book you are currently borrowing.";
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

    // 🚫 NEW RULE: ONLY 1 RESERVATION PER USER
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reservations
        WHERE user_id = ?
        AND status IN ('pending', 'ready')
    ");
    $stmt->execute([$userId]);

    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error_message'] = "You can only have 1 active reservation.";
        header("Location: book_catalog.php");
        exit();
    }

    $reservationDate = nowDateTime();
    $expiryDate = nextReservationExpiryDateTime(3, $reservationDate);

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
        $reservationDate,
        $expiryDate
    ]);

//     createNotification(
//     $pdo,
//     $userId,
//     $studentId,
//     $studentName,
//     'general',
//     'Reservation Submitted',
//     'You reserved "' . ($book['title'] ?? 'a book') . '". We will notify you when it is ready.',
//     'reservations.php'
// );

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

<main class="max-w-[1489px] mx-auto px-4 sm:px-6 pt-28 sm:pt-32 md:pt-40 pb-8 sm:pb-10">

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

    <?php
    $libraryOpenNow = isLibraryOpen();
    $libraryClosedText = libraryClosedMessage();
    ?>

    <!-- BOOK GRID -->
    <?php if (empty($books)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center shadow-sm">
                <span class="text-gray-700 text-xl mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" class="inline w-9 h-9">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                </span>
            <p class="text-slate-500 text-lg">No books found matching your search.</p>
        </div>
    <?php else: ?>
    <section class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        <?php foreach ($books as $book): ?>
                <?php
                    $base = '/library-management-system/admin/';
                    $cover = !empty($book['coverImage'])
                        ? $base . ltrim($book['coverImage'], '/')
                        : 'https://placehold.co/400x520?text=No+Cover';
                    $isAvailable = (int)$book['availableCopies'] > 0;
                    $detailsModalId = 'detailsModal' . (int)$book['id'];
                    $confirmModalId = 'confirmModal' . (int)$book['id'];
                    
                ?>
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-lg transition-all hover:-translate-y-1">
    <!-- COVER -->
    <div
        class="relative aspect-[3/4] group cursor-pointer overflow-hidden"
        onclick="openBookModal('<?= e($detailsModalId) ?>')"
    >
        <img
            src="<?= e($cover) ?>"
            onerror="this.src='https://placehold.co/400x520?text=No+Cover'"
            alt="<?= e($book['title']) ?>"
            class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
        >

        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition duration-300 flex items-center justify-center">
            <span class="text-white text-sm font-semibold tracking-wide px-4 py-2 rounded-full backdrop-blur-md border border-white/20 bg-white/10">
                <?= $isAvailable ? 'View & Borrow' : 'View & Reserve' ?>
            </span>
        </div>

        <div class="absolute top-3 right-3 z-10">
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= $isAvailable ? 'bg-blue-950 text-white' : 'bg-red-300 text-red-800' ?>">
                <?= $isAvailable ? 'Available' : 'Not Available' ?>
            </span>
        </div>
    </div>

    <!-- CARD INFO -->
<div class="p-3">
    <h3 class="text-sm font-semibold leading-snug min-h-[40px]">
            <?= e($book['title']) ?>
        </h3>
        <p class="mt-1 text-xs text-slate-600"> <?= e($book['author']) ?></p>

        <div class="mt-4 text-sm text-slate-500 space-y-1">
            <!-- <p>ISBN: <?= e($book['isbn']) ?></p> -->
            <p>Category: <?= e($book['category']) ?></p>
            <p>Copies: <?= e($book['availableCopies']) ?>/<?= e($book['totalCopies']) ?></p>
        </div>
    </div>
</div>

<!-- DETAILS MODAL -->
<div id="<?= e($detailsModalId) ?>" class="fixed inset-0 hidden z-50">
    <div class="absolute inset-0 bg-black/50" onclick="closeBookModal('<?= e($detailsModalId) ?>')"></div>

    <div class="relative flex min-h-screen items-center justify-center p-3 sm:p-4">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl overflow-hidden max-h-[92vh] flex flex-col">
            <div class="border-b px-4 sm:px-6 py-4 shrink-0">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900">Book Details</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Review the book information before you continue.
                </p>
            </div>

            <div class="p-4 sm:p-6 overflow-y-auto">
                <div class="flex flex-col sm:flex-row gap-4 sm:gap-6">
                    <img
                        src="<?= e($cover) ?>"
                        alt="<?= e($book['title']) ?>"
                        class="w-28 h-40 sm:w-40 sm:h-56 object-cover rounded-xl border mx-auto sm:mx-0 shrink-0"
                    >

                    <div class="flex-1 min-w-0">
                        <h3 class="text-xl sm:text-2xl font-bold text-slate-900 break-words">
                            <?= e($book['title']) ?>
                        </h3>
                        <p class="mt-1 text-sm text-slate-600 break-words"><?= e($book['author']) ?></p>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-slate-600">
                            <p class="break-words">
                                <span class="font-semibold text-slate-800">ISBN:</span>
                                <?= e($book['isbn']) ?>
                            </p>
                            <p class="break-words">
                                <span class="font-semibold text-slate-800">Category:</span>
                                <?= e($book['category']) ?>
                            </p>
                            <p>
                                <span class="font-semibold text-slate-800">Copies:</span>
                                <?= e($book['availableCopies']) ?>/<?= e($book['totalCopies']) ?>
                            </p>
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-slate-800">Status:</span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $isAvailable ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                    <?= $isAvailable ? 'Available' : 'Not Available' ?>
                                </span>
                            </p>
                        </div>

                        <?php if (!empty($book['description'])): ?>
                            <div class="mt-5">
                                <h4 class="text-sm font-semibold text-slate-800 mb-2">Description</h4>
                                <div class="text-sm text-gray-700 leading-relaxed max-h-40 overflow-y-auto pr-1 sm:pr-2 break-words">
                                    <?= nl2br(e($book['description'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t px-4 sm:px-6 py-4 shrink-0">
                <button
                    type="button"
                    onclick="closeBookModal('<?= e($detailsModalId) ?>')"
                    class="w-full sm:w-auto rounded-lg border px-4 py-2 hover:bg-gray-100"
                >
                    Close
                </button>

                <?php if ($libraryOpenNow): ?>
                    <button
                        type="button"
                        onclick="closeBookModal('<?= e($detailsModalId) ?>'); openBookModal('<?= e($confirmModalId) ?>')"
                        class="w-full sm:w-auto rounded-lg px-4 py-2 text-white font-semibold <?= $isAvailable ? 'bg-blue-600 hover:bg-blue-700' : 'bg-orange-500 hover:bg-orange-600' ?>"
                    >
                        <?= $isAvailable ? 'Borrow Book' : 'Reserve Book' ?>
                    </button>
                <?php else: ?>
                    <button
                        type="button"
                        disabled
                        class="w-full sm:w-auto rounded-lg bg-gray-300 px-4 py-2 text-gray-600 cursor-not-allowed"
                        title="<?= e($libraryClosedText) ?>"
                    >
                        Library Closed
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!-- CONFIRM MODAL -->
<div id="<?= e($confirmModalId) ?>" class="fixed inset-0 hidden z-50">
    <div class="absolute inset-0 bg-black/50" onclick="closeBookModal('<?= e($confirmModalId) ?>')"></div>

    <div class="relative flex min-h-screen items-center justify-center p-3 sm:p-4">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl overflow-hidden max-h-[92vh] flex flex-col">
            <div class="border-b px-4 sm:px-6 py-4 shrink-0">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900">
                    <?= $isAvailable ? 'Confirm Borrow' : 'Confirm Reservation' ?>
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    <?= $isAvailable
                        ? 'Are you sure you want to borrow this book?'
                        : 'Are you sure you want to reserve this book?' ?>
                </p>
            </div>

            <div class="p-4 sm:p-6 overflow-y-auto">
                <div class="flex flex-col sm:flex-row gap-4">
                    <img
                        src="<?= e($cover) ?>"
                        alt="<?= e($book['title']) ?>"
                        class="w-24 h-32 sm:w-20 sm:h-28 object-cover rounded border mx-auto sm:mx-0 shrink-0"
                    >
                    <div class="min-w-0">
                        <h4 class="font-semibold text-gray-900 text-base sm:text-lg break-words">
                            <?= e($book['title']) ?>
                        </h4>
                        <p class="text-sm text-gray-600 break-words"><?= e($book['author']) ?></p>
                        <p class="text-xs text-gray-500 mt-2 break-words">ISBN: <?= e($book['isbn']) ?></p>
                    </div>
                </div>

                <?php if ($isAvailable): ?>
                    <div class="mt-4 rounded-lg bg-blue-50 p-4 text-sm">
                        <p class="font-semibold text-blue-900">Borrowing Terms:</p>
                        <ul class="list-disc list-inside mt-2 text-blue-800 space-y-1">
                            <li>Borrowing period: 1 day</li>
                            <li>Return by: Next day at 08:59 AM</li>
                            <li>Late fee: ₱2 per hour after 8:59 AM until 5:00 PM only</li>
                            <li>Starting the next day: ₱10 per day</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t px-4 sm:px-6 py-4 shrink-0">
                <button
                    type="button"
                    onclick="closeBookModal('<?= e($confirmModalId) ?>')"
                    class="w-full sm:w-auto rounded-lg border px-4 py-2 hover:bg-gray-100"
                >
                    Cancel
                </button>

                <?php if ($libraryOpenNow): ?>
                    <form method="POST" class="w-full sm:w-auto">
                        <input type="hidden" name="token" value="<?= e($_SESSION['token']) ?>">
                        <input type="hidden" name="book_id" value="<?= e($book['id']) ?>">
                        <input type="hidden" name="action" value="<?= $isAvailable ? 'borrow' : 'reserve' ?>">

                        <button
                            type="submit"
                            class="w-full sm:w-auto rounded-lg <?= $isAvailable ? 'bg-blue-600 hover:bg-blue-700' : 'bg-orange-500 hover:bg-orange-600' ?> px-4 py-2 text-white"
                        >
                            Confirm
                        </button>
                    </form>
                <?php else: ?>
                    <button
                        type="button"
                        disabled
                        class="w-full sm:w-auto rounded-lg bg-gray-300 px-4 py-2 text-gray-600 cursor-not-allowed"
                        title="<?= e($libraryClosedText) ?>"
                    >
                        Library Closed
                    </button>
                <?php endif; ?>
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

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('[id^="detailsModal"], [id^="confirmModal"]').forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });
    }
});

</script>

</body>
</html>