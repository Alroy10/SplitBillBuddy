<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$dbname = " split_bill_buddy";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['group_id'])) {
    $group_id = $_GET['group_id'];
    $sql = "SELECT members FROM `groups` WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $members = [];
    if ($row = $result->fetch_assoc()) {
        $members = array_map('trim', explode(',', $row['members']));
    }

    echo json_encode($members);
    $stmt->close();
}

$conn->close();
?>