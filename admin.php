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

// Fetch users
$sql = "SELECT fullname, username, email, phone FROM users ORDER BY fullname ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Split Bill Buddy</title>
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin:0; background-color:#f0f2f5; }
h1 { color:#333; }

/* Sidebar */
.sidebar {
    position: fixed; left:0; top:0; width:220px; height:100%; background-color:#4CAF50; color:#fff; padding-top:20px;
}
.sidebar h2 { text-align:center; margin-bottom:30px; }
.sidebar a { display:block; color:#fff; padding:12px 20px; text-decoration:none; margin-bottom:5px; border-radius:5px; }
.sidebar a:hover { background-color:#45a049; }

/* Main Content */
.main-content { margin-left:240px; padding:30px; }
.header { background-color:#fff; padding:15px 20px; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.1); margin-bottom:20px; }

/* Table */
table { width:100%; border-collapse:collapse; background-color:#fff; border-radius:10px; overflow:hidden; box-shadow:0 5px 15px rgba(0,0,0,0.1); }
th, td { padding:12px 15px; text-align:left; border-bottom:1px solid #ddd; }
th { background-color:#4CAF50; color:white; text-transform:uppercase; }
tr:hover { background-color:#f1f1f1; }

/* Responsive */
@media (max-width:768px){ .sidebar{ width:100%; height:auto; position:relative; } .main-content{ margin-left:0; } }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="?view=users" style="background-color:#45a049;">Users</a>
    <a href="homepage.html">Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="header">
        <h1>Registered Users</h1>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Phone</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['fullname']); ?></td>
                        <td><?= htmlspecialchars($row['username']); ?></td>
                        <td><?= htmlspecialchars($row['email']); ?></td>
                        <td><?= htmlspecialchars($row['phone']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No users found.</p>
    <?php endif; ?>
</div>

<?php $conn->close(); ?>
</body>
</html>
