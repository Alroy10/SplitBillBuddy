<?php
session_start();

// === 1. Database Connection ===
$servername = "localhost";
$username = "root";
$password = "";
$dbname = " split_bill_buddy";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// === 2. Get Group ID ===
$group_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($group_id <= 0) {
    header("Location: index.php");
    exit;
}

// === 3. Fetch Group ===
$stmt = $conn->prepare("SELECT * FROM `groups` WHERE id = ?");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();
$group = $result->fetch_assoc();
$stmt->close();

if (!$group) {
    echo "<script>alert('Group not found!'); window.location='index.php';</script>";
    exit;
}

// === 4. Must be logged in ===
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode("join_group.php?id=$group_id"));
    exit;
}

$user_id = $_SESSION['user_id'];

// === 5. Get username (from session or DB) ===
if (!isset($_SESSION['username'])) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $_SESSION['username'] = $row['username'];
    }
    $stmt->close();
}
$username = $_SESSION['username'];

// === 6. Check if already member ===
$stmt = $conn->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->bind_param("ii", $group_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$already_member = $result->num_rows > 0;
$stmt->close();

// === 7. If already member → redirect ===
if ($already_member) {
    echo "<script>
        alert('You are already a member of this group!');
        window.location.href = 'index.php';
    </script>";
    exit;
}

// === 8. Handle AJAX Join Request ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'join') {
    header('Content-Type: application/json');

    // Double-check not already member
    $stmt = $conn->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $group_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already a member']);
        
        exit;
    }
    $stmt->close();

    // Add to group_members
    $stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $group_id, $user_id);
    $success = $stmt->execute();
    error_log("Insert into group_members success: " . ($success ? 'true' : 'false'));
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Joined successfully!']);
    exit;
    //if ($success) {
        
        // Update members string
        $current = $group['members'];
        $new_members = $current ? $current . ', ' . $username : $username;
        // trim any stray spaces
        $new_members = implode(',', array_map('trim', explode(',', $new_members)));
        error_log("New members string: " . $new_members);
        $stmt = $conn->prepare("UPDATE `groups` SET members = ? WHERE id = ?");
        $stmt->bind_param("si", $new_members, $group_id);
        $stmt->execute();
        
        $stmt->close();


    //}

    exit;
}

// === 9. Show Confirm Dialog (Only if not POST) ===
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Group - Split Bill Buddy</title>
    <style>
        body { font-family: Arial; background: #f8fff8; padding: 40px; text-align: center; }
        .card { max-width: 500px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2 { color: #198754; margin-bottom: 15px; }
        p { margin: 12px 0; font-size: 1.1em; }
        .btn { padding: 12px 28px; margin: 10px; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; font-weight: 600; }
        .btn-yes { background: #198754; color: white; }
        .btn-yes:hover { background: #157347; }
        .btn-no { background: #dc3545; color: white; }
        .btn-no:hover { background: #c82333; }
    </style>
</head>
<body>
<div class="card">
    <h2>Join Group?</h2>
    <p><strong><?php echo htmlspecialchars($group['group_name']); ?></strong></p>
    <p>Current Members: <?php echo htmlspecialchars($group['members'] ?: 'None'); ?></p>
    <p>Do you want to join this group?</p>

    <button class="btn btn-yes" onclick="joinGroup()">Yes, Join</button>
    <button class="btn btn-no" onclick="window.location='index.php'">No, Cancel</button>

    <div id="status" style="margin-top: 20px; font-weight: bold; color: green;"></div>
</div>

<script>
function joinGroup() {
    const status = document.getElementById('status');
    status.textContent = 'Joining...';

    fetch('join_group.php?id=<?php echo $group_id; ?>&action=join', {
        method: 'POST',
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            status.style.color = 'green';
            status.textContent = 'Joined successfully!';
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
        } else {
            status.style.color = 'red';
            status.textContent = 'Error: ' + data.message;
        }
    })
}
</script>
</body>
</html>
<?php
$conn->close();
?>