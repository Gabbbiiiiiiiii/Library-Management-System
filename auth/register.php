<?php
session_start();
require_once __DIR__ . "/../config/database.php";

$error = "";

$firstname = "";
$lastname = "";
$course = "";
$student_id = "";
$yearlvl = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $yearlvl = trim($_POST['yearlvl'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $fullname = trim($firstname . " " . $lastname);

    if (!preg_match("/^[a-zA-Z\s]+$/", $firstname)) {
        $error = "First name must contain letters only.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $lastname)) {
        $error = "Last name must contain letters only.";
    } elseif (!preg_match('/^02000[0-9]+$/', $student_id)) {
        $error = "Student ID must start with 02000 and contain numbers only.";
    } elseif (strlen($password_input) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password_input !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $college_courses = ["BSIT", "BSTM", "BSHM"];
        $shs_strands = ["STEM", "ABM", "TVL"];

        $college_years = ["1st Year", "2nd Year", "3rd Year", "4th Year"];
        $shs_years = ["Grade 11", "Grade 12"];

        if (in_array($course, $college_courses, true) && in_array($yearlvl, $shs_years, true)) {
            $error = "College courses cannot select Grade 11 or Grade 12.";
        } elseif (in_array($course, $shs_strands, true) && in_array($yearlvl, $college_years, true)) {
            $error = "Senior High strands cannot select college year levels.";
        }

        if ($error === "") {
            $check = $conn->prepare("SELECT id FROM users WHERE student_id = ?");
            $check->bind_param("s", $student_id);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "Student ID already registered.";
            } else {
                $password = password_hash($password_input, PASSWORD_DEFAULT);
                $role = "student";

                $stmt = $conn->prepare("
                    INSERT INTO users (fullname, student_id, course, yearlvl, password, role)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "ssssss",
                    $fullname,
                    $student_id,
                    $course,
                    $yearlvl,
                    $password,
                    $role
                );

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Registration successful! Please login.";
                    header("Location: student_login.php");
                    exit();
                } else {
                    $error = "Database Error: " . $conn->error;
                }

                $stmt->close();
            }

            $check->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="register-container">
    <h2>Student Registration</h2>

    <?php if ($error !== ""): ?>
        <p class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="firstname">First Name</label>
        <input
            type="text"
            id="firstname"
            name="firstname"
            required
            pattern="[A-Za-z\s]+"
            title="Letters only"
            value="<?= htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="lastname">Last Name</label>
        <input
            type="text"
            id="lastname"
            name="lastname"
            required
            pattern="[A-Za-z\s]+"
            title="Letters only"
            value="<?= htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="course">Course / Strand</label>
        <select name="course" id="course" required onchange="updateYearLevels()">
            <option value="">Select Course</option>

            <optgroup label="Senior High">
                <option value="STEM" <?= $course === 'STEM' ? 'selected' : '' ?>>STEM</option>
                <option value="ABM" <?= $course === 'ABM' ? 'selected' : '' ?>>ABM</option>
                <option value="TVL" <?= $course === 'TVL' ? 'selected' : '' ?>>TVL</option>
            </optgroup>

            <optgroup label="College">
                <option value="BSIT" <?= $course === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                <option value="BSTM" <?= $course === 'BSTM' ? 'selected' : '' ?>>BSTM</option>
                <option value="BSHM" <?= $course === 'BSHM' ? 'selected' : '' ?>>BSHM</option>
            </optgroup>
        </select>

        <label for="student_id">Student ID</label>
        <input
            type="text"
            id="student_id"
            name="student_id"
            placeholder="02000XXXXXX"
            required
            pattern="02000[0-9]+"
            title="Must start with 02000"
            value="<?= htmlspecialchars($student_id, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="yearlvl">Year / Grade Level</label>
        <select name="yearlvl" id="yearlvl" required>
            <option value="">Select Year Level</option>
        </select>

        <label for="password">Password</label>
        <div class="password-container">
            <input type="password" name="password" id="password" required>
            <button type="button" class="toggle-password" onclick="togglePassword(this, 'password')" aria-label="Show password">
                <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path class="eye-outline" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.269 2.943 9.542 7-1.273 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z"/>
                    <circle class="eye-pupil" cx="12" cy="12" r="3" stroke-width="2"/>
                    <path class="eye-slash" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4L20 20"/>
                </svg>
            </button>
        </div>

        <label for="confirm_password">Confirm Password</label>
        <div class="password-container">
            <input type="password" name="confirm_password" id="confirm_password" required>
            <button type="button" class="toggle-password" onclick="togglePassword(this, 'confirm_password')" aria-label="Show confirm password">
                <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path class="eye-outline" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.269 2.943 9.542 7-1.273 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z"/>
                    <circle class="eye-pupil" cx="12" cy="12" r="3" stroke-width="2"/>
                    <path class="eye-slash" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4L20 20"/>
                </svg>
            </button>
        </div>

        <button type="submit">Register</button>

        <p class="login-link">
            Already registered?
            <a href="student_login.php">Back to Login</a>
        </p>
    </form>
</div>

<script>
function updateYearLevels() {
    const course = document.getElementById("course").value;
    const yearSelect = document.getElementById("yearlvl");
    const selectedYear = <?= json_encode($yearlvl) ?>;

    yearSelect.innerHTML = '<option value="">Select Year Level</option>';

    const shs = ["STEM", "ABM", "TVL"];
    const college = ["BSIT", "BSTM", "BSHM"];

    let levels = [];

    if (shs.includes(course)) {
        levels = ["Grade 11", "Grade 12"];
    } else if (college.includes(course)) {
        levels = ["1st Year", "2nd Year", "3rd Year", "4th Year"];
    }

    levels.forEach(level => {
        const option = document.createElement("option");
        option.value = level;
        option.textContent = level;

        if (level === selectedYear) {
            option.selected = true;
        }

        yearSelect.appendChild(option);
    });
}

document.addEventListener("DOMContentLoaded", updateYearLevels);

function togglePassword(button, inputId) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector(".eye-icon");
    const isHidden = input.type === "password";

    input.type = isHidden ? "text" : "password";
    icon.classList.toggle("is-open", isHidden);

    button.setAttribute(
        "aria-label",
        isHidden ? "Hide password" : "Show password"
    );
}
</script>

</body>
</html>