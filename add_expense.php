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

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (!$user_id) {
    header("Location: login.php");
    exit;
}

// Check if editing an existing expense
$edit_expense = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM expenses WHERE expense_id = ? AND group_id IN (SELECT group_id FROM group_members WHERE user_id = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_expense = $result->fetch_assoc();
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $amount = floatval($_POST['amount']);
    $paid_by = $_POST['paid_by'];
    $split_type = $_POST['split_type'];
    $title = $_POST['title'];
    $expense_date = $_POST['expense_date'];
    $group_id = intval($_POST['group_id']);
    $group_name = $_POST['group_name'];
    $custom_value = isset($_POST['custom_value']) && $_POST['custom_value'] !== '' ? floatval($_POST['custom_value']) : null;

    // File upload handling
    $file_path = isset($edit_expense['file_path']) ? $edit_expense['file_path'] : null;
    if (isset($_FILES['expense_file']) && $_FILES['expense_file']['error'] === UPLOAD_ERR_OK) {
        $uploads_dir = "Uploads";
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0777, true);

        $tmp_name = $_FILES["expense_file"]["tmp_name"];
        $name = basename($_FILES["expense_file"]["name"]);
        $file_path = $uploads_dir . "/" . uniqid() . "_" . $name;

        move_uploaded_file($tmp_name, $file_path);
    }

    if (isset($edit_expense)) {
        // Update existing expense
        $sql = "UPDATE expenses SET group_id = ?, group_name = ?, amount = ?, paid_by = ?, split_type = ?, title = ?, expense_date = ?, custom_value = ?, file_path = ? WHERE expense_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssssi", $group_id, $group_name, $amount, $paid_by, $split_type, $title, $expense_date, $custom_value, $file_path, $id);
    } else {
        // Insert new expense
        $sql = "INSERT INTO expenses (group_id, group_name, amount, paid_by, split_type, title, expense_date, custom_value, file_path) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssss", $group_id, $group_name, $amount, $paid_by, $split_type, $title, $expense_date, $custom_value, $file_path);
    }

     if ($stmt->execute()) {
        echo "<script>alert('Expense " . (isset($edit_expense) ? 'updated' : 'added') . " successfully!'); window.location.href='homepage.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
        
}

// Fetch group names for dropdown
$groups = [];
$sql = "SELECT id, group_name FROM `groups` WHERE id IN (SELECT group_id FROM group_members WHERE user_id = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $groups[] = $row;
}
$stmt->close();

// Fetch group members for dropdown (initial load)
$members = [];
$selected_group_id = isset($edit_expense) ? $edit_expense['group_id'] : (isset($_POST['group_id']) ? $_POST['group_id'] : null);
if ($selected_group_id) {
    $sql = "SELECT u.id, u.fullname FROM group_members gm 
            JOIN users u ON gm.user_id = u.id 
            WHERE gm.group_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo isset($edit_expense) ? 'Edit Expense' : 'Add Expense'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f4f8f7;
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem 2rem;
            background: #198754;
            color: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .brand {
            font-size: 2rem;
            font-weight: bold;
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
            cursor: pointer;
            transition: 0.2s;
            font-weight: 600;
        }

        .header-buttons button:hover {
            background: #eaffea;
        }

        .container {
            width: 600px;
            max-width: 90%;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        h2 {
            margin-bottom: 1.5rem;
            color: #198754;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select,
        .file-upload {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #198754;
            box-shadow: 0 0 0 2px rgba(25,135,84,0.2);
        }

        button[type="submit"] {
            background: #198754;
            color: white;
            border: none;
            padding: 0.9rem 1.5rem;
            font-size: 1.1rem;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
            font-weight: 600;
            transition: 0.2s;
        }

        button[type="submit"]:hover {
            background: #146c43;
        }

        .current-file {
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .current-file a {
            color: #198754;
            text-decoration: none;
        }

        .custom-values {
            display: none;
        }

        .custom-values.active {
            display: block;
        }

        @media (max-width: 480px) {
            .container {
                margin: 1rem;
                padding: 1rem;
            }
            h2 {
                font-size: 1.5rem;
            }
            label {
                font-size: 0.9rem;
            }
            input[type="text"],
            input[type="number"],
            input[type="date"],
            select,
            textarea {
                font-size: 0.9rem;
                padding: 0.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <span class="brand">Split Bill Buddy</span>
        <div class="header-buttons">
            <button onclick="location.href='homepage.php'">HOME</button>
            <button onclick="location.href='index.php'">➕Create Group</button>
            <button class="logout" onclick="location.href='logout.php'">🚪Logout</button>
        </div>
    </div>
    <div class="container">
        <h2><?php echo isset($edit_expense) ? 'Edit Expense' : 'Add Expense'; ?></h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="group_id">Group:</label>
                <select id="group_id" name="group_id" required>
                    <option value="">Select a group</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?php echo $group['id']; ?>" data-group-name="<?php echo htmlspecialchars($group['group_name']); ?>" <?php echo (isset($edit_expense) && $edit_expense['group_id'] == $group['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($group['group_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" id="group_name" name="group_name" value="<?php echo isset($edit_expense) ? htmlspecialchars($edit_expense['group_name']) : ''; ?>">

            <div class="form-group">
                <label for="amount">Amount:</label>
                <input type="number" id="amount" name="amount" value="<?php echo isset($edit_expense) ? htmlspecialchars($edit_expense['amount']) : ''; ?>" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="paid_by">Paid By:</label>
                <select id="paid_by" name="paid_by" required>
                    <option value="">Select a member</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?php echo htmlspecialchars($member['fullname']); ?>" <?php echo (isset($edit_expense) && $edit_expense['paid_by'] == $member['fullname']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($member['fullname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="split_type">Split Type:</label>
                <select id="split_type" name="split_type">
                    <option value="equal" <?php echo (isset($edit_expense) && $edit_expense['split_type'] == 'equal') ? 'selected' : ''; ?>>Equal</option>
                    <option value="percentage" <?php echo (isset($edit_expense) && $edit_expense['split_type'] == 'percentage') ? 'selected' : ''; ?>>Percentage</option>
                    <option value="fixed" <?php echo (isset($edit_expense) && $edit_expense['split_type'] == 'fixed') ? 'selected' : ''; ?>>Fixed</option>
                </select>
            </div>

            <div class="form-group custom-values <?php echo (isset($edit_expense) && in_array($edit_expense['split_type'], ['percentage', 'fixed'])) ? 'active' : ''; ?>">
                <label for="custom_value">Custom Value (Percentage/Fixed):</label>
                <input type="number" id="custom_value" name="custom_value" value="<?php echo isset($edit_expense) ? htmlspecialchars($edit_expense['custom_value']) : ''; ?>" step="0.01">
            </div>

            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" id="title" name="title" value="<?php echo isset($edit_expense) ? htmlspecialchars($edit_expense['title']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="expense_date">Expense Date:</label>
                <input type="date" id="expense_date" name="expense_date" value="<?php echo isset($edit_expense) ? htmlspecialchars($edit_expense['expense_date']) : ''; ?>" required max="<?= date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label for="expense_file">Upload Bill/Receipt (optional):</label>
                <input type="file" id="expense_file" name="expense_file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar" class="file-upload">
                <?php if (isset($edit_expense) && !empty($edit_expense['file_path'])): ?>
                    <div class="current-file">
                        Current: <a href="<?php echo htmlspecialchars($edit_expense['file_path']); ?>" target="_blank">View Current File</a>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit"><?php echo isset($edit_expense) ? 'Update Expense' : 'Add Expense'; ?></button>
        </form>
    </div>
</body>
</html>

<script>
document.getElementById('split_type').addEventListener('change', function() {
    const splitType = this.value;
    let customValuesDiv = document.querySelector('.custom-values');

    if (!customValuesDiv) {
        customValuesDiv = document.createElement('div');
        customValuesDiv.className = 'custom-values form-group';
        customValuesDiv.innerHTML = `
            <label for="custom_value">Enter Value:</label>
            <input type="number" id="custom_value" name="custom_value" placeholder="Enter percentage or fixed amount" step="0.01">
        `;
        this.closest('form').insertBefore(customValuesDiv, document.querySelector('button[type="submit"]'));
    }

    if (splitType === 'percentage' || splitType === 'fixed') {
        customValuesDiv.classList.add('active');
    } else {
        customValuesDiv.classList.remove('active');
    }
});

function loadMembers(groupId, selectedMember = null) {
    const paidByDropdown = document.getElementById('paid_by');
    if (!groupId) {
        paidByDropdown.innerHTML = '<option value="">Select a member</option>';
        return;
    }

    fetch(`fetch_members.php?group_id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            paidByDropdown.innerHTML = '<option value="">Select a member</option>';
            data.forEach(member => {
                const option = document.createElement('option');
                option.value = member;
                option.textContent = member;
                if (selectedMember && member === selectedMember) {
                    option.selected = true;
                }
                paidByDropdown.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error fetching members:', error);
            paidByDropdown.innerHTML = '<option value="">Select a member</option>';
        });
}

document.getElementById('group_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const groupName = selectedOption.getAttribute('data-group-name');
    document.getElementById('group_name').value = groupName;

    const groupId = this.value;
    loadMembers(groupId);
});

// On page load for edit expense, load members dynamically
window.addEventListener('DOMContentLoaded', () => {
    const groupIdSelect = document.getElementById('group_id');
    const paidByMember = '<?php echo isset($edit_expense) ? addslashes($edit_expense['paid_by']) : ''; ?>';
    const groupId = groupIdSelect.value;
    if (groupId) {
        loadMembers(groupId, paidByMember);
    }
});
</script>