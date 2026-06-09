<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "sti_library";

/* mysqli connection */
$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("MySQLi connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

/* PDO connection */
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("PDO connection failed: " . $e->getMessage());
}
?>