<?php
session_start();
$isbnError = $_SESSION['isbn_error'] ?? '';
$openModalOnLoad = $_SESSION['open_modal'] ?? false;
$modalMode = $_SESSION['modal_mode'] ?? 'Add';

unset($_SESSION['isbn_error'], $_SESSION['open_modal'], $_SESSION['modal_mode']);

unset($_SESSION['isbn_error'], $_SESSION['open_modal']);
require_once "auth_check.php";

$currentPage = 'manage_books';

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

/* ================= HANDLE DELETE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {

    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die("❌ Invalid CSRF token.");
    }

    $deleteId = (int) $_POST['delete_id'];

    $checkBorrow = $pdo->prepare("
        SELECT COUNT(*) FROM borrowings
        WHERE book_id = ? AND status = 'borrowed'
    ");
    $checkBorrow->execute([$deleteId]);

    if ($checkBorrow->fetchColumn() > 0) {
        die("❌ Cannot delete book because it is currently borrowed.");
    }

    $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
    $stmt->execute([$deleteId]);

    header("Location: manage_books.php");
    exit();
}

/* ================= HANDLE ADD / EDIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {

    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die("❌ Invalid CSRF token.");
    }

    /* ===== GET DATA SAFELY ===== */
    $id = $_POST['id'] ?? null;

    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $publisher = trim($_POST['publisher'] ?? '');
    $year = (int) ($_POST['yearPublished'] ?? 0);
    $total = (int) ($_POST['totalCopies'] ?? 0);
    $available = (int) ($_POST['availableCopies'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $cover = null;

    /* ===== REQUIRED VALIDATION ===== */
    if ($title === '' || $author === '' || $isbn === '') {
        die("❌ Title, author, and ISBN are required.");
    }

    /* ===== ISBN CLEAN + VALIDATE ===== */
    $isbn = str_replace(['-', ' '], '', $isbn);

    if (!preg_match('/^(97[89]\d{10}|\d{9}[\dX])$/', $isbn)) {
        die("❌ Invalid ISBN.");
    }

  /* ===== DUPLICATE ISBN ===== */
    $check = $pdo->prepare("SELECT id FROM books WHERE isbn = ?");
    $check->execute([$isbn]);
    $existing = $check->fetch();

    if ($existing && (string)$existing['id'] !== (string)$id) {
        $_SESSION['isbn_error'] = "❌ ISBN already exists.";
        $_SESSION['open_modal'] = true;
        $_SESSION['modal_mode'] = $id ? 'Edit' : 'Add';
        header("Location: manage_books.php");
        exit();
    }

    /* ===== YEAR VALIDATION ===== */
    $currentYear = date("Y");
    if ($year < 1800 || $year > $currentYear) {
        die("❌ Invalid publication year.");
    }

    /* ===== COPIES VALIDATION ===== */
    if ($total < 1) {
        die("❌ Total copies must be at least 1.");
    }

    if ($available < 0 || $available > $total) {
        die("❌ Invalid available copies.");
    }

    /* ===== BORROW CHECK (EDIT ONLY) ===== */
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM borrowings
            WHERE book_id = ? AND status = 'borrowed'
        ");
        $stmt->execute([$id]);
        $borrowed = (int) $stmt->fetchColumn();

        if ($total < $borrowed) {
            die("❌ Total copies cannot be less than borrowed.");
        }

        if ($available > ($total - $borrowed)) {
            die("❌ Available copies exceed allowed.");
        }
    }

    /* ===== FILE UPLOAD ===== */
    if (!empty($_FILES['coverFile']['name'])) {

        $file = $_FILES['coverFile'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            die("❌ Upload error.");
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowedTypes)) {
            die("❌ Invalid image type.");
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            die("❌ Image must be under 2MB.");
        }

        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }

        $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", $file['name']);
        $target = "uploads/" . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            die("❌ Upload failed.");
        }

        $cover = $target;

    } elseif (!empty($_POST['coverImage'])) {
        $cover = $_POST['coverImage'];

    } elseif ($id) {
        $stmt = $pdo->prepare("SELECT coverImage FROM books WHERE id = ?");
        $stmt->execute([$id]);
        $cover = $stmt->fetchColumn();
    }

    /* ===== INSERT OR UPDATE ===== */
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE books SET
                title=?, author=?, isbn=?, category=?, publisher=?,
                yearPublished=?, totalCopies=?, availableCopies=?,
                description=?, coverImage=?
            WHERE id=?
        ");
        $stmt->execute([
            $title, $author, $isbn, $category, $publisher,
            $year, $total, $available,
            $description, $cover, $id
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO books
            (title, author, isbn, category, publisher, yearPublished,
             totalCopies, availableCopies, description, coverImage)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title, $author, $isbn, $category, $publisher,
            $year, $total, $available,
            $description, $cover
        ]);
    }

    header("Location: manage_books.php");
    exit();
}

/* ================= PAGINATION ================= */
$limit = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* ================= SEARCH ================= */
$search = trim($_GET['search'] ?? '');
$searchTerm = "%$search%";

/* ================= COUNT ================= */
if ($search === '') {
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM books");
} else {
    $totalStmt = $pdo->prepare("
        SELECT COUNT(*) FROM books
        WHERE title LIKE :s OR author LIKE :s OR isbn LIKE :s
    ");
    $totalStmt->execute([':s' => $searchTerm]);
}

$totalBooks = $totalStmt->fetchColumn();
$totalPages = max(1, ceil($totalBooks / $limit));

$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

/* ================= FETCH ================= */
if ($search === '') {

    $stmt = $pdo->prepare("
        SELECT * FROM books
        ORDER BY id DESC
        LIMIT :l OFFSET :o
    ");

} else {

    $stmt = $pdo->prepare("
        SELECT * FROM books
        WHERE title LIKE :s OR author LIKE :s OR isbn LIKE :s
        ORDER BY id DESC
        LIMIT :l OFFSET :o
    ");

    $stmt->bindValue(':s', $searchTerm, PDO::PARAM_STR);
}

$stmt->bindValue(':l', $limit, PDO::PARAM_INT);
$stmt->bindValue(':o', $offset, PDO::PARAM_INT);
$stmt->execute();

$books = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Books</title>
   <link href="/library-management-system/assets/css/output.css" rel="stylesheet">

  
</head>

<body class="bg-gray-100">

<?php include 'header.php'; ?>

<div class="max-w-[1489px] mx-auto px-6 pt-28 pb-10">
    

<!-- TITLE + ADD BUTTON -->
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Manage Books</h1>
        <p class="text-gray-600">Add, edit, or remove books from the library</p>
    </div>

 <!-- Add Book button -->
<button onclick="openModal('Add')"
        class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
    + Add Book
</button>
</div>

<!-- SEARCH -->
<form method="GET" class="mb-6 flex gap-2">
    <input type="text" name="search"
           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="Search by title, author, or ISBN..."
           class="w-full p-3 rounded-lg border focus:ring-2 focus:ring-purple-500">

    <button type="submit"
            class="px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
        Search
    </button>
</form>

<!-- BOOK LIST -->
<div class="space-y-4">
<?php if (empty($books)): ?>
    <div class="bg-white p-6 rounded-xl shadow text-center">
        <p class="text-gray-500 text-lg">
            📚 No books found<?= $search !== '' ? ' for "' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '"' : '' ?>.
        </p>
    </div>
<?php else: ?>
    <?php foreach ($books as $book): ?>
        <div class="bg-white p-4 rounded-xl shadow flex gap-4">

            <img src="<?= htmlspecialchars($book['coverImage'] ?: 'https://placehold.co/100x150?text=No+Cover', ENT_QUOTES, 'UTF-8') ?>"
                 class="w-20 h-28 object-cover rounded"
                 alt="Book Cover">

            <div class="flex-1">

                <div class="flex justify-between">
                    <div>
                        <h3 class="font-semibold text-lg"><?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="text-gray-600"><?= htmlspecialchars($book['author'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <span class="px-3 py-1 rounded-full text-xs font-medium <?= $book['availableCopies'] > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' ?>">
                        <?= $book['availableCopies'] > 0 ? 'Available' : 'Not Available' ?>
                    </span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mt-3">
                    <div><strong>ISBN:</strong> <?= htmlspecialchars($book['isbn'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Category:</strong> <?= htmlspecialchars($book['category'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Publisher:</strong> <?= htmlspecialchars($book['publisher'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Year:</strong> <?= htmlspecialchars($book['yearPublished'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Total:</strong> <?= htmlspecialchars($book['totalCopies'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Available:</strong>
                        <span class="text-green-600"><?= htmlspecialchars($book['availableCopies'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div><strong>Borrowed:</strong>
                        <span class="text-blue-600">
                            <?= htmlspecialchars((string)($book['totalCopies'] - $book['availableCopies']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button onclick='editBook(<?= json_encode($book, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'
                            class="border px-3 py-1 rounded hover:bg-gray-100">
                        Edit
                    </button>

                    <form method="POST" onsubmit="return confirm('Delete this book?')" class="inline">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="delete_id" value="<?= htmlspecialchars($book['id'], ENT_QUOTES, 'UTF-8') ?>">

                        <button type="submit"
                                class="border px-3 py-1 rounded hover:bg-red-100 text-red-600">
                            Delete
                        </button>
                    </form>
                </div>

            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<div class="flex gap-2 mt-6">

<?php for ($i=1; $i <= $totalPages; $i++): ?>

<a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
class="px-3 py-1 border rounded
<?= $i == $page ? 'bg-purple-600 text-white' : '' ?>">

<?= $i ?>

</a>

<?php endfor; ?>

</div>

</div>
<!-- ================= MODAL ================= -->
<div id="bookModal" class="fixed inset-0 hidden z-50">
    
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal()"></div>

    <!-- Wrapper -->
    <div class="relative flex items-center justify-center min-h-screen p-4">
        
        <!-- Modal -->
        <div class="bg-white w-full max-w-3xl max-h-[90vh] rounded-2xl shadow-xl flex flex-col overflow-hidden">
            
            <!-- HEADER -->
            <div class="flex items-center justify-between px-6 py-4 border-b bg-white shrink-0">
                <div>
                    <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">
                        Add New Book
                    </h2>
                    <p class="text-gray-500 text-sm">
                        Add a new book to the library
                    </p>
                </div>

                <button onclick="closeModal()"
                        class="text-gray-400 hover:text-gray-700 text-2xl">
                    &times;
                </button>
            </div>

            <!-- SCROLLABLE BODY -->
            <div class="flex-1 overflow-y-auto p-6 min-h-0">

                <!-- FORM -->
                <form id="bookForm" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                    <input type="hidden" name="id" id="bookId">

                    <!-- TITLE -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="title" id="title" required
                            class="w-full bg-gray-100 border border-gray-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <!-- AUTHOR -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Author <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="author" id="author" required
                            class="w-full bg-gray-100 border border-gray-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <!-- ISBN -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                ISBN <span class="text-red-500">*</span>
                            </label>

                            <input type="text"
                                name="isbn"
                                id="isbn"
                                required
                                pattern="^(97[89]\d{10}|\d{9}[\dX])$"
                                title="Enter a valid ISBN-10 or ISBN-13"
                                placeholder="e.g. 9783161484100"
                                class="w-full bg-gray-100 border rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>

                    <!-- CATEGORY -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Category
                        </label>
                        <input type="text" name="category" id="category"
                            placeholder="Select category"
                            class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <!-- PUBLISHER -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Publisher
                        </label>
                        <input type="text" name="publisher" id="publisher"
                            class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <!-- YEAR -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Year Published
                        </label>
                        <input type="number" name="yearPublished" id="yearPublished"
                            value="<?= date('Y') ?>" min="1800" max="<?= date('Y') ?>"
                            required
                            class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <!-- TOTAL COPIES -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Total Copies
                        </label>
                        <input type="number" name="totalCopies" id="totalCopies" min="1" value="1" required
                            class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <!-- AVAILABLE COPIES -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Available Copies
                        </label>
                        <input type="number" name="availableCopies" id="availableCopies" min="0"
                            value="1" required
                            class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <!-- DESCRIPTION -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Description
                        </label>
                        <textarea name="description" id="description" rows="4"
                            class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                    </div>

                    <!-- COVER IMAGE -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Cover Image
                        </label>

                        <input type="file" name="coverFile" id="coverFile"
                            accept="image/*"
                            onchange="previewImage(event)"
                            class="hidden">

                        <button type="button"
                                onclick="document.getElementById('coverFile').click()"
                                class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                            📁 Choose Image
                        </button>

                        <input type="hidden" name="coverImage" id="coverImage">

                        <div class="mt-4">
                            <img id="imagePreview"
                                src="https://placehold.co/128x160?text=No+Cover"
                                class="w-32 h-40 object-cover rounded-lg border shadow mt-2"
                                alt="Cover Preview">
                        </div>
                    </div>
                </form>

            </div>

            <!-- FOOTER -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-white shrink-0">
                <button onclick="closeModal()"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                    Cancel
                </button>

                <button type="submit" form="bookForm"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    Add Book
                </button>
            </div>

        </div>
    </div>
</div>

<script>

//search functionality: if user clears the search input, redirect to clean URL
const searchInput = document.querySelector('input[name="search"]');
let timeout;

if (searchInput) {
    searchInput.addEventListener('input', function () {
        clearTimeout(timeout);

        timeout = setTimeout(() => {
            const params = new URLSearchParams(window.location.search);
            const currentCategory = params.get('category') || 'all';

            if (this.value.trim() === '') {
                // Reset search but keep category
                window.location.href = 'manage_books.php?category=' + encodeURIComponent(currentCategory);
            } else {
                // Live search
                window.location.href =
                    'manage_books.php?search=' + encodeURIComponent(this.value) +
                    '&category=' + encodeURIComponent(currentCategory);
            }
        }, 500); // adjust delay if needed
    });
}


// --- MODAL LOGIC ---
function openModal(mode = 'Add') {
    const modal = document.getElementById('bookModal');
    const form = document.getElementById('bookForm');
    const titleEl = document.getElementById('modalTitle');
    const submitBtn = document.querySelector('button[form="bookForm"]');

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    
    if (mode === 'Add') {
        titleEl.innerText = 'Add New Book';
        submitBtn.innerText = 'Add Book';

        form.reset();

        document.getElementById('bookId').value = '';
        document.getElementById('totalCopies').value = '1';
        document.getElementById('availableCopies').value = '1';
        document.getElementById('yearPublished').value = new Date().getFullYear();
        document.getElementById('imagePreview').src = 'https://placehold.co/128x160?text=No+Cover';
        document.getElementById('coverFile').value = '';
        document.getElementById('coverImage').value = '';
    }
}
function closeModal() {
    document.body.classList.remove('overflow-hidden');
    document.getElementById('bookModal').classList.add('hidden');
}

// --- EDIT LOGIC ---
function editBook(book) {
    openModal('Edit');

    // Change modal text
    document.getElementById('modalTitle').innerText = 'Edit Book';
    document.querySelector('button[form="bookForm"]').innerText = 'Save Book';

    // Fill inputs manually (SAFE & ACCURATE)
    document.getElementById('bookId').value = book.id ?? '';
    document.getElementById('title').value = book.title ?? '';
    document.getElementById('author').value = book.author ?? '';
    document.getElementById('isbn').value = book.isbn ?? '';
    document.getElementById('category').value = book.category ?? '';
    document.getElementById('publisher').value = book.publisher ?? '';
    document.getElementById('yearPublished').value = book.yearPublished ?? '';
    document.getElementById('totalCopies').value = book.totalCopies ?? 1;
    document.getElementById('availableCopies').value = book.availableCopies ?? 1;
    document.getElementById('description').value = book.description ?? '';

    // Cover image handling
    document.getElementById('coverImage').value = book.coverImage ?? '';
    document.getElementById('imagePreview').src =
        book.coverImage && book.coverImage !== ''
        ? book.coverImage
        : 'https://placehold.co/128x160?text=No+Cover';

    // Reset file input (important)
    document.getElementById('coverFile').value = '';
}

// --- COPIES VALIDATION ---
document.addEventListener("DOMContentLoaded", () => {
    const totalInput = document.getElementById('totalCopies');
    const availableInput = document.getElementById('availableCopies');

    if (totalInput && availableInput) {
        totalInput.addEventListener('input', () => {
            const total = Number(totalInput.value);
            availableInput.max = total;
            // If user lowers total below available, force available to match
            if (Number(availableInput.value) > total) {
                availableInput.value = total;
            }
        });

        availableInput.addEventListener('input', () => {
            const total = Number(totalInput.value);
            // Don't let available exceed total
            if (Number(availableInput.value) > total) {
                availableInput.value = total;
            }
        });
    }
});

// --- LOCAL IMAGE PREVIEW ---
function previewImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('imagePreview');
    const coverImgInput = document.getElementById('coverImage');
    
    // 1. Handle "Cancel" or "No File Selected"
    if (!file) {
        // Reset to your specific 80x96 placeholder if they cancel
        preview.src = 'https://placehold.co/80x96?text=No+Cover';
        if(coverImgInput) coverImgInput.value = '';
        return;
    }

    // 2. Type validation
    if (!file.type.startsWith('image/')) {
        alert("❌ Only image files are allowed.");
        event.target.value = ''; // Clear the input
        preview.src = 'https://placehold.co/80x96?text=No+Cover';
        return;
    }

    // 3. File size validation (Optional but recommended for "Small" UI)
    if (file.size > 2 * 1024 * 1024) { // 2MB
        alert("❌ Image is too large. Please choose a file under 2MB.");
        event.target.value = '';
        return;
    }

    // 4. Generate Preview
    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        
        // Reset the hidden URL field because we are using a manual upload
        if(coverImgInput) coverImgInput.value = '';
    };
    reader.readAsDataURL(file);
}

// --- OPEN MODAL ON ISBN ERROR ---
<?php if (!empty($isbnError) && $openModalOnLoad): ?>
window.addEventListener('DOMContentLoaded', function () {
    alert(<?= json_encode($isbnError) ?>);
    openModal(<?= json_encode($modalMode) ?>);
});
<?php endif; ?>
</script>

</body>
</html>