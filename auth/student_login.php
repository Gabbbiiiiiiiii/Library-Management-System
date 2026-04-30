<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/database.php";

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/library-management-system/assets/images/logo1.png">
    <link rel="shortcut icon" href="/library-management-system/assets/images/logo1.png">
    <link rel="stylesheet" href="/library-management-system/assets/css/style.css">

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

    .register-link-wrap {
        text-align: center;
        margin-top: 15px;
        font-size: 14px;
    }

    .register-link-wrap p {
        display: inline;
        font-size: 14px;
        color: #333;
    }

    .register-link-btn {
        display: inline-block;
        padding: 8px 10px;
        color: #0a1f44;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
        border-radius: 999px;
        transition: all 0.3s ease;
    }

    .register-link-btn:hover {
        color: var(--accent);
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

        <button type="submit">Sign In</button>
    </form>
    
    <div class="register-link-wrap">
        <p>Don't have an account?</p>
        <a href="register.php" class="register-link-btn">Register</a>
    </div>

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