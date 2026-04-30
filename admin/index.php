<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Invalid CSRF token.");
    }

    if ($_SESSION['login_attempts'] >= 5) {
        die("Too many failed attempts. Try again later.");
    }

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    // $secret_key = trim($_POST['secret_key']);

    $sql = "SELECT * FROM users WHERE email = ? AND role = 'admin'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (
            password_verify($password, $user['password'])
        ) {
            session_regenerate_id(true);

            $_SESSION['login_attempts'] = 0;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = 'admin';
            $_SESSION['last_activity'] = time();

            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['login_attempts']++;
            $error = "Invalid credentials.";
        }
    } else {
        $_SESSION['login_attempts']++;
        $error = "Admin not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - STI Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="/library-management-system/assets/images/logo1.png">
	<link rel="shortcut icon" href="/library-management-system/assets/images/logo1.png">
        <link rel="apple-touch-icon" href="/library-management-system/assets/images/mobile-logo.png">
    <link rel="manifest" href="/library-management-system/assets/app.webmanifest">
</head>
<body>

<div class="login-container">
    <h2>Admin Login</h2>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

        <!-- <label for="secret_key">Admin Secret Key</label>
        <div class="password-container">
            <input type="password" name="secret_key" id="secret_key" required>
            <button type="button" class="toggle-password" onclick="togglePassword(this, 'secret_key')" aria-label="Show secret key">
                <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path class="eye-outline" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.269 2.943 9.542 7-1.273 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z"/>
                    <circle class="eye-pupil" cx="12" cy="12" r="3" stroke-width="2"/>
                    <path class="eye-slash" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4L20 20"/>
                </svg>
            </button>
        </div> -->

        <label for="email">Email</label>
        <input type="email" name="email" id="email" placeholder="Enter admin email" required>

        <label for="password">Password</label>
        <div class="password-container">
            <input type="password" name="password" id="password" placeholder="Enter password" required>
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

        <div class="login-link">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
    </form>

    <div class="login-link">
        <a href="../index.php">← Back to Home</a>
    </div>
</div>

<script>
function togglePassword(button, inputId) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector(".eye-icon");
    const isHidden = input.type === "password";

    input.type = isHidden ? "text" : "password";
    icon.classList.toggle("is-open", isHidden);

    button.setAttribute(
        "aria-label",
        isHidden ? "Hide value" : "Show value"
    );
}
</script>

</body>
</html>