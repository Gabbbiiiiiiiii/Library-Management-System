<?php
session_start();
include "../config/database.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $course = trim($_POST['course']);
    $student_id = trim($_POST['student_id']);
    $yearlvl = trim($_POST['yearlvl']);
    $password_input = $_POST['password'];

    $fullname = $firstname . " " . $lastname;

    // INPUT VALIDATION

    if (!preg_match("/^[a-zA-Z\s]+$/", $firstname)) {
        $error = "First name must contain letters only.";
    }

    elseif (!preg_match("/^[a-zA-Z\s]+$/", $lastname)) {
        $error = "Last name must contain letters only.";
    }

    elseif (!preg_match('/^02000[0-9]+$/', $student_id)) {
        $error = "Student ID must start with 02000 and contain numbers only.";
    }

    elseif (strlen($password_input) < 6) {
        $error = "Password must be at least 6 characters.";
    }

    else {

        $password = password_hash($password_input, PASSWORD_DEFAULT);

        $college_courses = ["BSIT", "BSTM", "BSHM"];
        $shs_strands = ["STEM", "ABM", "TVL"];

        $college_years = ["1st Year", "2nd Year", "3rd Year", "4th Year"];
        $shs_years = ["Grade 11", "Grade 12"];

        if (in_array($course, $college_courses) && in_array($yearlvl, $shs_years)) {
            $error = "College courses cannot select Grade 11 or Grade 12.";
        }

        elseif (in_array($course, $shs_strands) && in_array($yearlvl, $college_years)) {
            $error = "Senior High strands cannot select college year levels.";
        }

        if (!$error) {

            $check = $conn->prepare("SELECT id FROM users WHERE student_id=?");
            $check->bind_param("s", $student_id);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "Student ID already registered.";
            }

            else {

                $role = "student";

                $stmt = $conn->prepare("INSERT INTO users 
                (fullname, student_id, course, yearlvl, password, role) 
                VALUES (?, ?, ?, ?, ?, ?)");

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
                }

                else {
                    $error = "Database Error: " . $conn->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Student Registration</title>
<link rel="stylesheet" href="/library-management-system/assets/css/style.css">
</head>

<body>

<div class="register-container">
<h2>Student Registration</h2>

<?php if ($error) echo "<p class='error-message'>$error</p>"; ?>

<form method="POST">

<label>First Name</label>
<input type="text" name="firstname" required pattern="[A-Za-z\s]+" title="Letters only">

<label>Last Name</label>
<input type="text" name="lastname" required pattern="[A-Za-z\s]+" title="Letters only">

<label>Course / Strand</label>
<select name="course" id="course" required onchange="updateYearLevels()">

<option value="">Select Course</option>

<optgroup label="Senior High">
<option value="STEM">STEM</option>
<option value="ABM">ABM</option>
<option value="TVL">TVL</option>
</optgroup>

<optgroup label="College">
<option value="BSIT">BSIT</option>
<option value="BSTM">BSTM</option>
<option value="BSHM">BSHM</option>
</optgroup>

</select>

<label>Student ID</label>
<input type="text" name="student_id" placeholder="02000XXXXXX" required pattern="02000[0-9]+" title="Must start with 02000">

<label>Year / Grade Level</label>
<select name="yearlvl" id="yearlvl" required>
<option value="">Select Year Level</option>
</select>

<label>Password</label>
<input type="password" name="password" required minlength="6">

<button type="submit">Register</button>

<p class="login-link">
Already registered?
<a href="student_login.php">Back to Login</a>
</p>

</form>
</div>

<script>

function updateYearLevels(){

let course = document.getElementById("course").value;
let yearSelect = document.getElementById("yearlvl");

yearSelect.innerHTML = '<option value="">Select Year Level</option>';

let shs = ["STEM","ABM","TVL"];
let college = ["BSIT","BSTM","BSHM"];

if(shs.includes(course)){

yearSelect.innerHTML += '<option value="Grade 11">Grade 11</option>';
yearSelect.innerHTML += '<option value="Grade 12">Grade 12</option>';

}

if(college.includes(course)){

yearSelect.innerHTML += '<option value="1st Year">1st Year</option>';
yearSelect.innerHTML += '<option value="2nd Year">2nd Year</option>';
yearSelect.innerHTML += '<option value="3rd Year">3rd Year</option>';
yearSelect.innerHTML += '<option value="4th Year">4th Year</option>';

}

}

</script>

</body>
</html>