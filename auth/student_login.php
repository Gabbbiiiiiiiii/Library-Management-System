<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $student_id = trim($_POST['student_id']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE student_id = ? AND role = 'student'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['role'] = 'student';
            $_SESSION['last_activity'] = time();

            header("Location: ../user/student_dashboard.php");
            exit();

        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Student not found.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .password-container {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            user-select: none;
        }
    </style>
</head>
<body>

<div class="login-container">
    <h2>Student Login</h2>

    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
        
    <?php
    if (isset($_SESSION['success'])) {
        echo "<p id='successMessage' class='success-message'>" . $_SESSION['success'] . "</p>";
        unset($_SESSION['success']);
    }
    ?>

    <form method="POST">
        <label>Student ID</label>
        <input type="text" name="student_id" required>

        <label>Password</label>
        <div class="password-container">
            <input type="password" name="password" id="password" required>
            <span class="toggle-password" onclick="togglePassword()">👁️</span>
        </div>

        <button type="submit">Sign In</button>
    </form>

    <div style="text-align:center; margin-top:15px;">
        <a href="../index.php" style="font-size:14px; color:#002147; text-decoration:none;">
            ← Back to Home
        </a>
    </div>
</div>

<script>
setTimeout(function(){
    let msg = document.getElementById("successMessage");
    if(msg){
        msg.style.transition = "opacity 0.5s";
        msg.style.opacity = "0";
        setTimeout(()=>msg.remove(),500);
    }
}, 3000);

function togglePassword() {
    let passwordInput = document.getElementById("password");
    if(passwordInput.type === "password"){
        passwordInput.type = "text";
    } else {
        passwordInput.type = "password";
    }
}
</script>

</body>
</html>