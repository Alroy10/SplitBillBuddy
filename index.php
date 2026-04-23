<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = " split_bill_buddy";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch groups where the user is a member
$user_id = $_SESSION['user_id'];
$groups = [];
$result = $conn->query("
    SELECT g.* 
    FROM `groups` g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = $user_id
    ORDER BY g.created_at DESC
");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Split Bill Buddy | Create Groups</title>
    <style>
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem 2rem;
            background: #198754;
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            /* position:fixed;
            top:0;
            width: 100%; */
            flex-wrap:wrap;

        }
        .brand {
            font-size: 2rem;
            font-weight: bold;
            background: #198754;
            color: white;
        }
        .header-buttons {
            display: flex;
            gap: 0.75rem;
        
            
        }
        .header-buttons button {
            background: #fff;
            border: none;
            color: #198754;
            padding: 0.8rem 1.4rem;
            border-radius: 5px;
            font-size: 1rem;
            
            transition: 0.2s;
            font-weight: 600;
            
        }
        .header-buttons button:hover {
            background: #eaffea;
        }
        body { 
            font-family: Arial, sans-serif; 
            background-color: #e6ffe6; 
            margin: 0;
        }
        #noGroupsMsg { c
            olor: #555; 
            font-style: italic;
        }
        #container { 
            border: 1px solid #ccc; 
            background-color: #fff; 
            border-radius: 10px; 
            max-width: 80%; 
            margin-left: auto; 
            margin-right: auto; 
            padding: 20px; 
        }
        h2 { text-align: center; }
        form { 
            display: flex; 
            flex-direction: column; 
        }
        label { margin-top: 10px; }
        input[type="text"] {
            padding: 8px; 
            margin-top: 5px; 
            border: 1px solid #ccc; 
            border-radius: 5px; 
        }
        input[type="text"]:focus { 
            border-color: #28a745; 
            outline: none; 
            background-color: #e6ffe6; 
        }
        button { 
            margin-top: 20px;
             padding: 10px; 
             background-color: #28a745; 
             color: white; 
             border: none; 
             border-radius: 5px; 
             cursor: pointer; 
            }
        button:hover { background-color: #218838; }
        a { color: white; text-decoration: none; }
        span { display: inline-block; margin: 0 5px; font-size: small; padding: 5px 10px; border: 1px solid #2d8b55; border-radius: 5px; background-color: #e6ffe6; color: #2d8b55; }
        footer { text-align: center; margin-top: 20px; font-size: 0.6em; color: #fff; background-color: #28a745; padding: 10px; width: 100%; bottom: 0; }
        .group { border: 1px solid #ccc; background-color: #fff; border-radius: 10px; max-width: 80%; margin-left: auto; margin-right: auto; padding: 20px; margin-bottom: 20px; color: #02883a; }
        /* #groupscontainer{
            margin-top: 100px;
        } */
        .groupItem { border: 1px solid black; padding: 10px; margin: 10px 0; background-color: #f9f9f9; border-radius: 5px; }
        #shareSection { margin-top: 10px; padding: 10px; border: 1px dashed grey; border-radius: 5px; overflow-x: auto; }
        #logout { background-color: #dc3545; float: right; margin-top: 0; }
        #logout:hover { background-color: #c82333; }
        .button-group {
    display: flex;
    justify-content: center;
    margin-top: 16px;
}

.btn-home {
    display: inline-block;
    padding: 10px 20px;
    background-color: #4f46e5;
    color: #ffffff;
    text-decoration: none;
    border-radius: 4px;
    font-size: 16px;
    font-weight: 600;
    transition: background-color 0.2s;
    margin: 0;
    text-align: center;
}
.btn-home:hover {
    background-color: #4338ca;
}

    </style>
</head>
<body>
<div class="header">
    <span class="brand">💸 Split Bill Buddy</span>
    <div class="header-buttons">
      <button onclick="location.href='homepage.php'">HOME</button>
      <button onclick="location.href='add_expense.php'">💰 Add Expense</button>
      <button class="logout" onclick="location.href='logout.php'">🚪 Logout</button>
    </div>
  </div>
    <div id="container">
        <div id="groupscontainer">
            <h3>Your Groups</h3>
            <p id="noGroupsMsg" <?php echo count($groups) > 0 ? 'style="display:none;"' : ''; ?>>No groups yet!</p>
            <div id="groupsList" class="group">
                <?php foreach ($groups as $group): ?>
                    <div class="groupItem">
                        <strong><?php echo htmlspecialchars($group['group_name']); ?></strong><br>
                        Members: <?php echo htmlspecialchars($group['members']); ?>
                        <div id="shareSection">
                            <p>Share with members</p>
                            <p id="grouplink">
                                <i><?php echo htmlspecialchars("http://localhost/final_splittbill/final_splittbill/join_group.php?id=" . $group['id']); ?></i>
                            </p>
                            <button class="copylink" 
                                    data-link="<?php echo htmlspecialchars("http://localhost/final_splittbill/final_splittbill/join_group.php?id=" . $group['id']); ?>">
                                Copy Link
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <hr>
        <h3>Create a New Group</h3>
        <form id="groupForm" >
            <label for="group-name">Group Name:</label>
            <input type="text" id="group-name" name="group-name" required>
            <br><br>
            
            <label for="member-names">Member Names (comma separated):</label>
            <input type="text" id="member-names" name="member-names" required>
            <br><br>

            <div id="groupmembers"></div>
            <button type="submit" id="creategroup">Create Group</button>
            <div class="button-group">
</div>

        </form>
    </div>
    <footer>Copyrights @ splitbillbuddy</footer>

    <script src="script.js"></script>
</body>
</html>
