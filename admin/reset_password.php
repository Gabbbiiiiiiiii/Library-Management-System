<?php
session_start();
include "../config/database.php";

$error = "";
$message = "";

// ✅ Check if token exists
if (!isset($_GET['token']) || empty($_GET['token'])) {
    die("Invalid reset request.");
}

$token = $_GET['token'];

// ✅ Verify token from database
$sql = "SELECT * FROM users WHERE reset_token = ?";$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Invalid or expired token.");
}

$user = $result->fetch_assoc();

// ✅ If form submitted, update password
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $newPassword = trim($_POST['password']);

    if (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = "UPDATE users 
                   SET password=?, reset_token=NULL, reset_expires=NULL 
                   WHERE id=?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("si", $hashedPassword, $user['id']);
        $stmt->execute();

        $message = "Password successfully updated!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="login-container">
    <h2>Reset Admin Password</h2>

    <?php if (!empty($message)): ?>
        <div class="success"><?php echo $message; ?></div>
        <div style="text-align:center; margin-top:10px;">
            <a href="index.php">Go to Login</a>
        </div>
    <?php else: ?>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>New Password</label>
            <input type="password" name="password" required>

            <button type="submit">Update Password</button>
        </form>

    <?php endif; ?>
</div>

</body>
</html>