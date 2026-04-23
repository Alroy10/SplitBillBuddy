<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = " split_bill_buddy";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "DELETE FROM expenses WHERE expense_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: homepage.php");
        exit();
    } else {
        echo "Error deleting expense: " . $conn->error;
    }
    $stmt->close();
} else {
    echo "Invalid request.";
}

$conn->close();
?>