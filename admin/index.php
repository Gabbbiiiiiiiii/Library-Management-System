<?php
session_start();
include "../config/database.php";

// Initialize login attempts
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ✅ CSRF check INSIDE POST only
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    // ✅ Limit login attempts
    if ($_SESSION['login_attempts'] >= 5) {
        die("Too many failed attempts. Try again later.");
    }

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $secret_key = trim($_POST['secret_key']);

    $sql = "SELECT * FROM users WHERE email = ? AND role = 'admin'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

       if (
    password_verify($password, $user['password']) &&
    password_verify($secret_key, $user['secret_key'])
) {

    session_regenerate_id(true); // optional but recommended

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
<html>
<head>
    <title>Admin Login - STI Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="login-container">
    <h2>Admin Login</h2>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <label>Admin Secret Key</label>
<input type="password" name="secret_key" required>
        <label>Email</label>
        <input type="email" name="email" placeholder="Enter admin email" required>

        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password" required>

        <button type="submit">Sign In</button>
        <div style="text-align:center; margin-top:10px;">
    <a href="forgot_password.php">Forgot Password?</a>
</div>
    </form>

    <div style="text-align:center; margin-top:15px;">
        <a href="../index.php" style="font-size:14px; color:#002147; text-decoration:none;">
            ← Back to Home
        </a>
    </div>
</div>

</body>
</html>