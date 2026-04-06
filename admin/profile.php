<?php
session_start();
$currentPage = 'profile';
require_once __DIR__ . "/../config/database.php";

// =========================
// AUTH CHECK
// =========================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 900) {
    session_unset();
    session_destroy();
    header("Location: index.php?expired=1");
    exit();
}
$_SESSION['last_activity'] = time();

// =========================
// HELPER
// =========================
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// =========================
// GET ADMIN DATA
// =========================
$adminId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    die("Admin account not found.");
}

// =========================
// FLASH MESSAGES
// =========================
$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// =========================
// CHANGE PASSWORD (PRG)
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $_SESSION['error_message'] = "All password fields are required.";
    } elseif (!password_verify($currentPassword, $admin['password'])) {
        $_SESSION['error_message'] = "Current password is incorrect.";
    } elseif (strlen($newPassword) < 8) {
        $_SESSION['error_message'] = "New password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $_SESSION['error_message'] = "New password and confirm password do not match.";
    } elseif (password_verify($newPassword, $admin['password'])) {
        $_SESSION['error_message'] = "New password must be different from the current password.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateStmt = $conn->prepare("
            UPDATE users
            SET password = ?, reset_token = NULL, reset_expires = NULL
            WHERE id = ? AND role = 'admin'
        ");
        $updateStmt->bind_param("si", $hashedPassword, $adminId);

        if ($updateStmt->execute()) {
            $_SESSION['success_message'] = "Password updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update password.";
        }

        $updateStmt->close();
    }

    header("Location: profile.php");
    exit();
}

// =========================
// IMAGE PATHS
// =========================
$profileImage = !empty($admin['profile_image'])
    ? "../" . ltrim($admin['profile_image'], "/")
    : "../assets/images/default-avatar.png";

$coverImage = !empty($admin['cover_image'])
    ? "../" . ltrim($admin['cover_image'], "/")
    : "../assets/images/default-cover.jpg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - STI Library</title>

    <!-- Use your compiled CSS instead of Tailwind CDN to reduce flicker -->
    <link rel="stylesheet" href="../assets/css/output.css">

    <style>
        html {
            overflow-y: scroll;
        }

        .image-shell {
            position: relative;
            overflow: hidden;
            background: #e2e8f0;
        }

        .fade-image {
            opacity: 0;
            transition: opacity 220ms ease;
        }

        .fade-image.is-loaded {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<?php require_once __DIR__ . "/header.php"; ?>

<main class="max-w-7xl mx-auto px-6 pt-36 pb-10">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">

        <!-- LEFT -->
        <div class="xl:col-span-2 space-y-8">

            <!-- COVER / PROFILE -->
            <section class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="image-shell h-56">
                    <img
                        src="<?= e($coverImage) ?>"
                        alt="Cover Image"
                        class="fade-image w-full h-full object-cover"
                        width="1200"
                        height="224"
                        loading="eager"
                        decoding="async"
                        onload="this.classList.add('is-loaded')"
                        onerror="this.onerror=null; this.src='../assets/images/default-cover.jpg'; this.classList.add('is-loaded');"
                    >
                </div>

                <div class="px-8 pb-8 relative">
                    <div class="-mt-16 flex flex-col md:flex-row md:items-end md:justify-between gap-6">
                        <div class="flex items-end gap-5">
                            <div class="image-shell w-32 h-32 rounded-3xl border-4 border-white bg-white shadow-lg overflow-hidden shrink-0">
                                <img
                                    src="<?= e($profileImage) ?>"
                                    alt="Profile Image"
                                    class="fade-image w-full h-full object-cover"
                                    width="128"
                                    height="128"
                                    loading="eager"
                                    decoding="async"
                                    onload="this.classList.add('is-loaded')"
                                    onerror="this.onerror=null; this.src='../assets/images/default-avatar.jpg'; this.classList.add('is-loaded');"
                                >
                            </div>

                            <div class="pb-2 min-w-0">
                                <h1 class="text-3xl font-bold text-slate-900 break-words"><?= e($admin['fullname']) ?></h1>
                                <p class="text-slate-500 mt-1">Administrator</p>
                                <p class="text-sm text-slate-400 mt-1">
                                    Member since <?= e(date('F d, Y', strtotime($admin['created_at']))) ?>
                                </p>
                            </div>
                        </div>

                        <div class="pb-2">
                            <span class="inline-flex items-center px-4 py-2 rounded-xl bg-purple-100 text-purple-700 font-semibold text-sm">
                                Admin Account
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PROFILE INFO -->
            <section class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-slate-900">Profile Information</h2>
                    <p class="text-slate-500 mt-1">All fields below are read-only.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Full Name</label>
                        <input
                            type="text"
                            readonly
                            value="<?= e($admin['fullname']) ?>"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:outline-none"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Email</label>
                        <input
                            type="text"
                            readonly
                            value="<?= e($admin['email']) ?>"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:outline-none"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Role</label>
                        <input
                            type="text"
                            readonly
                            value="<?= e(ucfirst($admin['role'])) ?>"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:outline-none"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Created At</label>
                        <input
                            type="text"
                            readonly
                            value="<?= e(date('F d, Y h:i A', strtotime($admin['created_at']))) ?>"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:outline-none"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Secret Key</label>
                        <input
                            type="text"
                            readonly
                            value="••••••••••••"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:outline-none"
                        >
                    </div>
                </div>
            </section>
        </div>

        <!-- RIGHT -->
        <div class="space-y-8">
            <section class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-slate-900">Reset Password</h2>
                    <p class="text-slate-500 mt-1">Only password can be changed here.</p>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="mb-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-green-700">
                        <?= e($success) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5" novalidate>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Current Password</label>
                        <input
                            type="password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">New Password</label>
                        <input
                            type="password"
                            name="new_password"
                            minlength="8"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Confirm New Password</label>
                        <input
                            type="password"
                            name="confirm_password"
                            minlength="8"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                    </div>

                    <button
                        type="submit"
                        name="change_password"
                        class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 rounded-2xl transition"
                    >
                        Update Password
                    </button>
                </form>
            </section>

            <section class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
                <h2 class="text-xl font-bold text-slate-900 mb-4">Security Notes</h2>

                <div class="space-y-4 text-sm text-slate-600">
                    <div class="flex items-start gap-3">
                        <div class="w-2.5 h-2.5 rounded-full bg-green-500 mt-2"></div>
                        <p>Admin details are read-only for safety.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-2.5 h-2.5 rounded-full bg-blue-500 mt-2"></div>
                        <p>Password is securely updated using password hashing.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-2.5 h-2.5 rounded-full bg-purple-500 mt-2"></div>
                        <p>Reset token fields are cleared after changing password.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

</body>
</html>