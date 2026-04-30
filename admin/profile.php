<?php
$currentPage = 'profile';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/library_helpers.php';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* ================= AUTH CHECK ================= */
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

/* ================= SESSION TIMEOUT ================= */
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 900) {
    session_destroy();
    header("Location: index.php?expired=1");
    exit();
}

$_SESSION['last_activity'] = time();

/* ================= DATABASE ================= */
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

    if (function_exists('setLibraryDbTimezone')) {
        setLibraryDbTimezone($pdo);
    }
} catch (PDOException $e) {
    die("Database error.");
}

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';

unset($_SESSION['success_message'], $_SESSION['error_message']);

$adminId = $_SESSION['user_id'] ?? null;

if (!$adminId) {
    header("Location: index.php");
    exit();
}

/* ================= FETCH ADMIN ================= */
try {
    $stmt = $pdo->prepare("
        SELECT id, fullname, email, role, created_at, profile_image, cover_image, password
        FROM users
        WHERE id = ? AND role = 'admin'
        LIMIT 1
    ");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();

    if (!$admin) {
        die("Admin account not found.");
    }
} catch (PDOException $e) {
    die("Database error.");
}

/* ================= PROFILE IMAGE UPLOAD ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_profile_image'])) {
    if (!empty($_FILES['profile_image']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/profile_images/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = $_FILES['profile_image']['name'];
        $tmpName = $_FILES['profile_image']['tmp_name'];
        $fileSize = $_FILES['profile_image']['size'];

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($ext, $allowedExt)) {
            $error = "Only JPG, JPEG, PNG, and WEBP files are allowed.";
        } elseif (!in_array($mime, $allowedMime)) {
            $error = "Invalid image file type.";
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $error = "Profile image size must not exceed 2MB.";
        } else {
            $newFileName = 'admin_profile_' . $adminId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET profile_image = ? 
                    WHERE id = ? AND role = 'admin'
                ");
                $stmt->execute([$newFileName, $adminId]);

                $_SESSION['profile_image'] = $newFileName;
                $_SESSION['success_message'] = "Profile image updated successfully.";

                header("Location: /library-management-system/admin/profile.php");
                exit();
            } else {
                $error = "Failed to upload profile image.";
            }
        }
    } else {
        $error = "Please select a profile image.";
    }
}

/* ================= COVER IMAGE UPLOAD ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_cover_image'])) {
    if (!empty($_FILES['cover_image']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/cover_images/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = $_FILES['cover_image']['name'];
        $tmpName = $_FILES['cover_image']['tmp_name'];
        $fileSize = $_FILES['cover_image']['size'];

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($ext, $allowedExt)) {
            $error = "Only JPG, JPEG, PNG, and WEBP files are allowed.";
        } elseif (!in_array($mime, $allowedMime)) {
            $error = "Invalid image file type.";
        } elseif ($fileSize > 4 * 1024 * 1024) {
            $error = "Cover image size must not exceed 4MB.";
        } else {
            $newFileName = 'admin_cover_' . $adminId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET cover_image = ? 
                    WHERE id = ? AND role = 'admin'
                ");
                $stmt->execute([$newFileName, $adminId]);

                $_SESSION['cover_image'] = $newFileName;
                $_SESSION['success_message'] = "Cover image updated successfully.";

                header("Location: /library-management-system/admin/profile.php");
                exit();
            } else {
                $error = "Failed to upload cover image.";
            }
        }
    } else {
        $error = "Please select a cover image.";
    }
}

/* ================= CHANGE PASSWORD ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = "All password fields are required.";
    } elseif (!password_verify($currentPassword, $admin['password'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New password and confirm password do not match.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, reset_token = NULL, reset_expires = NULL
            WHERE id = ? AND role = 'admin'
        ");
        $stmt->execute([$hashedPassword, $adminId]);

        $_SESSION['success_message'] = "Password changed successfully.";

        header("Location: /library-management-system/admin/profile.php");
        exit();
    }
}

/* ================= REFRESH ADMIN ================= */
$stmt = $pdo->prepare("
    SELECT id, fullname, email, role, created_at, profile_image, cover_image, password
    FROM users
    WHERE id = ? AND role = 'admin'
    LIMIT 1
");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

$_SESSION['fullname'] = $admin['fullname'];
$_SESSION['email'] = $admin['email'];
$_SESSION['profile_image'] = $admin['profile_image'] ?? null;
$_SESSION['cover_image'] = $admin['cover_image'] ?? null;

$profileImagePath = !empty($admin['profile_image'])
    ? "../uploads/profile_images/" . $admin['profile_image']
    : null;

$coverImagePath = !empty($admin['cover_image'])
    ? "../uploads/cover_images/" . $admin['cover_image']
    : null;

include 'header.php';
?>

<main class="max-w-[1489px] mx-auto px-6 pt-40 pb-10">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-900">My Profile</h1>
        <p class="text-gray-500 mt-1">Overview of your admin profile information</p>
    </div>

    <div class="max-w-7xl mx-auto">
        <?php if (!empty($success)): ?>
            <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-green-700">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- PROFILE CARD -->
        <div class="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-sm">
            <!-- COVER -->
            <div class="relative h-[240px] group">
                <?php if ($coverImagePath): ?>
                    <img src="<?= e($coverImagePath) ?>" alt="Cover Image" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-r from-purple-200 via-slate-200 to-slate-300"></div>
                <?php endif; ?>

                <div class="absolute inset-0 bg-black/10"></div>

                <form method="POST" enctype="multipart/form-data" class="absolute inset-0">
                    <input 
                        type="file" 
                        name="cover_image" 
                        id="coverImageInput" 
                        accept=".jpg,.jpeg,.png,.webp" 
                        class="hidden" 
                        onchange="this.form.submit()"
                    >
                    <input type="hidden" name="upload_cover_image" value="1">

                    <label for="coverImageInput"
                        class="absolute inset-0 cursor-pointer flex items-start justify-end p-5 opacity-0 group-hover:opacity-100 transition">
                        <span class="inline-flex items-center gap-2 rounded-full bg-black/60 px-4 py-2 text-sm font-medium text-white backdrop-blur">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                            </svg>
                            Change Cover
                        </span>
                    </label>
                </form>
            </div>

            <!-- INFO BAR -->
            <div class="relative px-8 pb-8">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between">
                    <div class="flex flex-col md:flex-row md:items-end gap-6">
                        <!-- PROFILE IMAGE -->
                        <div class="relative -mt-16 md:-mt-20 group w-36 h-36 md:w-40 md:h-40">
                            <div class="w-full h-full rounded-full border-[5px] border-white shadow-lg overflow-hidden bg-slate-200">
                                <?php if ($profileImagePath): ?>
                                    <img src="<?= e($profileImagePath) ?>" alt="Profile Image" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-slate-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-white" viewBox="0 0 24 24" fill="currentColor">
                                            <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form method="POST" enctype="multipart/form-data">
                                <input 
                                    type="file" 
                                    name="profile_image" 
                                    id="profileImageInput" 
                                    accept=".jpg,.jpeg,.png,.webp" 
                                    class="hidden" 
                                    onchange="this.form.submit()"
                                >
                                <input type="hidden" name="upload_profile_image" value="1">

                                <label for="profileImageInput"
                                    class="absolute inset-0 rounded-full cursor-pointer bg-black/0 group-hover:bg-black/35 flex items-center justify-center transition">
                                    <span class="opacity-0 group-hover:opacity-100 transition inline-flex items-center justify-center w-12 h-12 rounded-full bg-white/90 shadow">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                                        </svg>
                                    </span>
                                </label>
                            </form>
                        </div>

                        <!-- NAME -->
                        <div class="pb-2">
                            <h1 class="text-3xl md:text-4xl font-bold text-slate-900">
                                <?= e($admin['fullname']) ?>
                            </h1>
                            <p class="mt-1 text-sm text-slate-500">
                                Administrator • STI Library System
                            </p>

                            <!-- <div class="mt-3 flex flex-wrap gap-3 text-sm text-slate-500">
                                <span><?= e($admin['email']) ?></span>
                                <span>•</span>
                                <span>Admin Account</span>
                            </div> -->
                        </div>
                    </div>

                    <!-- ROLE BADGE -->
                    <div class="mt-6 md:mt-0">
                        <span class="inline-flex items-center rounded-full bg-purple-100 px-4 py-2 text-sm font-semibold text-purple-700">
                            <?= e(ucfirst($admin['role'])) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- READ ONLY INFO -->
        <div class="mt-8 bg-white rounded-[26px] border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-200 bg-slate-50">
                <h2 class="text-2xl font-bold text-slate-900">Admin Information</h2>
                <p class="text-slate-500 mt-1">Profile details are read-only</p>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Full Name</label>
                    <input 
                        type="text" 
                        value="<?= e($admin['fullname']) ?>" 
                        disabled 
                        class="w-full rounded-2xl border border-slate-300 bg-slate-100 px-4 py-3.5 text-slate-500"
                    >
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Email</label>
                    <input 
                        type="text" 
                        value="<?= e($admin['email']) ?>" 
                        disabled 
                        class="w-full rounded-2xl border border-slate-300 bg-slate-100 px-4 py-3.5 text-slate-500"
                    >
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Role</label>
                    <input 
                        type="text" 
                        value="<?= e(ucfirst($admin['role'])) ?>" 
                        disabled 
                        class="w-full rounded-2xl border border-slate-300 bg-slate-100 px-4 py-3.5 text-slate-500"
                    >
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Created At</label>
                    <input 
                        type="text" 
                        value="<?= e($admin['created_at']) ?>" 
                        disabled 
                        class="w-full rounded-2xl border border-slate-300 bg-slate-100 px-4 py-3.5 text-slate-500"
                    >
                </div>
            </div>
        </div>

        <!-- PASSWORD -->
        <div class="mt-8 bg-white rounded-[26px] border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-200 bg-slate-50">
                <h2 class="text-2xl font-bold text-slate-900">Reset Password</h2>
                <p class="text-slate-500 mt-1">Change your admin account password securely</p>
            </div>

            <form method="POST" class="p-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Current Password</label>
                        <input 
                            type="password" 
                            name="current_password" 
                            required 
                            autocomplete="current-password"
                            class="w-full rounded-2xl border border-slate-300 px-4 py-3.5 focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">New Password</label>
                        <input 
                            type="password" 
                            name="new_password" 
                            required 
                            autocomplete="new-password"
                            class="w-full rounded-2xl border border-slate-300 px-4 py-3.5 focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Confirm Password</label>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            required 
                            autocomplete="new-password"
                            class="w-full rounded-2xl border border-slate-300 px-4 py-3.5 focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                    </div>
                </div>

                <div class="flex justify-end">
                    <button 
                        type="submit" 
                        name="change_password" 
                        class="rounded-2xl bg-purple-600 px-8 py-3.5 text-white font-semibold hover:bg-purple-700 transition"
                    >
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

</body>
</html>