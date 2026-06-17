<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$currentPage = 'manage_students';

require_once __DIR__ . '/../includes/library_helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_check.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection failed. PDO variable \$pdo was not found.");
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* =========================
   BLOCK / UNBLOCK STUDENT
========================= */

if (isset($_GET['block'])) {
    $id = (int) $_GET['block'];

    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_blocked = 1 
        WHERE id = ? 
        AND role = 'student'
    ");
    $stmt->execute([$id]);

    header("Location: manage_students.php");
    exit();
}

if (isset($_GET['unblock'])) {
    $id = (int) $_GET['unblock'];

    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_blocked = 0 
        WHERE id = ? 
        AND role = 'student'
    ");
    $stmt->execute([$id]);

    header("Location: manage_students.php");
    exit();
}

/* =========================
   FETCH STUDENTS
========================= */

$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? '');

$filterWhere = '';

if ($filter === 'blocked') {
    $filterWhere = " AND is_blocked = 1 ";
} elseif ($filter === 'active') {
    $filterWhere = " AND is_blocked = 0 ";
}

$sql = "
    SELECT 
        id,
        fullname,
        student_id,
        course,
        yearlvl,
        contact_number,
        profile_image,
        created_at,
        is_blocked
    FROM users
    WHERE role = 'student'
    $filterWhere
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            fullname LIKE ?
            OR student_id LIKE ?
            OR course LIKE ?
            OR yearlvl LIKE ?
            OR contact_number LIKE ?
        )
    ";

    $search_param = "%" . $search . "%";

    $params = [
        $search_param,
        $search_param,
        $search_param,
        $search_param,
        $search_param
    ];
}

$sql .= " ORDER BY fullname ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students | Library Admin Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind needed because your header.php uses Tailwind classes -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: #f4f6f9;
            margin: 0;
            font-family: Arial, sans-serif;
            color: #111827;
        }
        
        .student-default-img {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #cbd5e1;
            border: 1px solid #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .student-default-icon {
            width: 26px;
            height: 26px;
            color: white;
        }

        .page-content {
            padding: 165px 26px 35px;
            max-width: 1489px;
            margin: 0 auto;
        }

        .page-title {
            margin-bottom: 24px;
        }

        .page-title h2 {
            font-size: 30px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 6px;
        }

        .page-title p {
            color: #6b7280;
            margin: 0;
            font-size: 15px;
        }

        .students-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
            padding: 24px;
        }

        .note {
            background: #fff7ed;
            color: #9a3412;
            border-left: 5px solid #f97316;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .search-box {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .search-box input {
            width: 360px;
            max-width: 100%;
            padding: 11px 13px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            outline: none;
            font-size: 14px;
        }

        .search-box input:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
        }

        .search-box select {
            padding: 11px 13px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            outline: none;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .search-box select:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
        }

        .search-box button,
        .clear-btn {
            padding: 11px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .search-box button {
            background: #7c3aed;
            color: #fff;
        }

        .search-box button:hover {
            background: #6d28d9;
        }

        .clear-btn {
            background: #f3f4f6;
            color: #374151;
            display: inline-block;
        }

        .clear-btn:hover {
            background: #e5e7eb;
        }

        .student-count {
            color: #6b7280;
            font-size: 14px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: #f9fafb;
            color: #374151;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 13px 14px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            vertical-align: middle;
            color: #111827;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        .student-img {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            object-fit: cover;
            background: #e5e7eb;
            border: 1px solid #d1d5db;
        }

        .student-name {
            font-weight: 700;
            color: #111827;
        }

        .muted {
            color: #9ca3af;
        }

        .badge {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
        }

        .active {
            background: #dcfce7;
            color: #166534;
        }

        .blocked {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            padding: 8px 11px;
            border-radius: 9px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .btn-block {
            background: #dc2626;
        }

        .btn-block:hover {
            background: #b91c1c;
        }

        .btn-unblock {
            background: #16a34a;
        }

        .btn-unblock:hover {
            background: #15803d;
        }

        .no-data {
            text-align: center;
            padding: 26px;
            color: #6b7280;
        }

        @media (max-width: 768px) {
            .page-content {
                padding: 145px 14px 25px;
            }

            .students-card {
                padding: 16px;
            }

            .page-title h2 {
                font-size: 24px;
            }

            .search-box,
            .search-box input,
            .search-box button,
            .clear-btn {
                width: 100%;
            }

            .student-count {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<?php require_once __DIR__ . '/header.php'; ?>

<main class="page-content">
    <div class="page-title">
        <h2>Manage Students</h2>
        <p>View student contact information, account status, and block students when necessary.</p>
    </div>

    <div class="students-card">
        <div class="note">
            This page shows important student information for admin contact purposes, especially when a borrowed book has not been returned.
        </div>

        <div class="top-bar">
            <form method="GET" class="search-box">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search by name, student ID, course, year, contact"
                    value="<?php echo e($search); ?>"
                >

                <select name="filter" onchange="this.form.submit()">
                    <option value="">All Students</option>
                    <option value="blocked" <?php echo ($filter === 'blocked') ? 'selected' : ''; ?>>
                        Blocked Students
                    </option>
                    <option value="active" <?php echo ($filter === 'active') ? 'selected' : ''; ?>>
                        Active Students
                    </option>
                </select>

                <button type="submit">Search</button>

                <?php if ($search !== '' || $filter !== ''): ?>
                    <a href="manage_students.php" class="clear-btn">Clear</a>
                <?php endif; ?>
            </form>

            <div class="student-count">
                Total Students: <strong><?php echo count($students); ?></strong>
            </div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Full Name</th>
                        <th>Student ID</th>
                        <th>Course</th>
                        <th>Year Level</th>
                        <th>Contact Number</th>
                        <th>Date Registered</th>
                        <th>Status</th>
                        <th>Admin Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $row): ?>
                            <tr>
                                <td>
                                    <?php
                                        $profileImagePath = !empty($row['profile_image'])
                                            ? "../uploads/profile_images/" . $row['profile_image']
                                            : null;
                                    ?>

                                    <?php if ($profileImagePath): ?>
                                        <img 
                                            src="<?php echo e($profileImagePath); ?>" 
                                            class="student-img" 
                                            alt="Profile"
                                        >
                                    <?php else: ?>
                                        <div class="student-default-img">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="student-default-icon" viewBox="0 0 24 24" fill="currentColor">
                                                <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="student-name">
                                        <?php echo e($row['fullname'] ?? '—'); ?>
                                    </span>
                                </td>

                                <td><?php echo e($row['student_id'] ?? '—'); ?></td>

                                <td><?php echo e($row['course'] ?? '—'); ?></td>

                                <td><?php echo e($row['yearlvl'] ?? '—'); ?></td>

                                <td>
                                    <?php if (!empty($row['contact_number'])): ?>
                                        <?php echo e($row['contact_number']); ?>
                                    <?php else: ?>
                                        <span class="muted">No contact number</span>
                                    <?php endif; ?>
                                </td>

                              

                                <td>
                                    <?php echo formatDateText($row['created_at'] ?? null, 'M d, Y'); ?>
                                </td>

                                <td>
                                    <?php if ((int)($row['is_blocked'] ?? 0) === 1): ?>
                                        <span class="badge blocked">Blocked</span>
                                    <?php else: ?>
                                        <span class="badge active">Active</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="actions">

                                        <?php if ((int)($row['is_blocked'] ?? 0) === 1): ?>
                                            <a 
                                                class="btn btn-unblock" 
                                                href="manage_students.php?unblock=<?php echo (int)$row['id']; ?>"
                                                onclick="return confirm('Are you sure you want to unblock this student?');"
                                            >
                                                Unblock
                                            </a>
                                        <?php else: ?>
                                            <a 
                                                class="btn btn-block" 
                                                href="manage_students.php?block=<?php echo (int)$row['id']; ?>"
                                                onclick="return confirm('Are you sure you want to block this student?');"
                                            >
                                                Block
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-data">
                                No students found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>
