<?php
session_start();
include "../config/database.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);

    $sql = "SELECT id FROM users WHERE email=? AND role='admin'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $update = "UPDATE users 
                   SET reset_token=?, reset_expires=? 
                   WHERE email=? AND role='admin'";

        $stmt = $conn->prepare($update);
        $stmt->bind_param("sss", $token, $expires, $email);

       if ($stmt->execute()) {
            $message = "Reset link: 
            http://localhost/library-management-system/admin/reset_password.php?token=$token";
        } else {
            $error = "Database update failed.";
        }

    } else {
        $error = "Admin email not found.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="login-container">
    <h2>Forgot Password</h2>

    <?php if ($message): ?>
        <div class="success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Enter Admin Email</label>
        <input type="email" name="email" required>

        <button type="submit">Generate Reset Link</button>
    </form>

    <div style="text-align:center; margin-top:10px;">
        <a href="index.php">Back to Login</a>
    </div>
</div>

</body>
</html>