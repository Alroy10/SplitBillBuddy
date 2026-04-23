<?php
ob_start(); // Start output buffering
session_start();

// === CRITICAL: NO OUTPUT BEFORE JSON ===
header('Content-Type: application/json');

// Enable error reporting (but don't display in production)
ini_set('display_errors', 0);  // ← CHANGE TO 0
error_reporting(E_ALL);

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean(); // Clear any output
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = " split_bill_buddy";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

// Get data from POST
$group_name = trim($_POST['group-name'] ?? '');
$members_str = trim($_POST['member-names'] ?? '');
$user_id = $_SESSION['user_id'];

// Validate
if (empty($group_name) || empty($members_str)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing group name or members']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Insert group
    $stmt = $conn->prepare("INSERT INTO `groups` (user_id, group_name, members) VALUES (?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("iss", $user_id, $group_name, $members_str);
    $stmt->execute();
    $new_group_id = $conn->insert_id;
    $stmt->close();

    // Add members (including creator)
    $members = array_map('trim', explode(',', $members_str));
    if (!isset($_SESSION['username'])) {
        $uresult = $conn->query("SELECT username FROM users WHERE id = $user_id");
        $_SESSION['username'] = $uresult->fetch_assoc()['username'] ?? 'user';
    }
    array_unshift($members, $_SESSION['username']);
    $members = array_unique($members);

    foreach ($members as $member_name) {
        if (empty($member_name)) continue;

        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $member_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user_row = $result->fetch_assoc()) {
            $member_id = $user_row['id'];
            $stmt2 = $conn->prepare("INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)");
            $stmt2->bind_param("ii", $new_group_id, $member_id);
            $stmt2->execute();
            $stmt2->close();
        }
        $stmt->close();
    }

    $conn->commit();

    // === ONLY JSON OUTPUT ===
    ob_end_clean(); // Clear buffer
    echo json_encode([
        'success' => true,
        'message' => 'Group created!',
        'group' => [
            'id' => $new_group_id,
            'group_name' => $group_name,
            'members' => $members_str
        ]
    ]);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}

$conn->close();
?>