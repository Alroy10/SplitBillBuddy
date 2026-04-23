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
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;


// Fetch expenses for HISTORY (filtered by user's group membership)
$all_history_sql = "SELECT e.expense_id, e.group_id, e.group_name, e.title, e.amount, e.paid_by, e.split_type, e.custom_value, e.file_path, e.expense_date, u.fullname AS paidbyname 
                    FROM expenses e 
                    JOIN group_members g ON e.group_id = g.group_id
                    JOIN users u ON g.user_id = u.id
                    WHERE g.user_id = ?
                    ORDER BY e.group_name";
$all_history_stmt = $conn->prepare($all_history_sql);
$all_history_stmt->bind_param("i", $user_id);
$all_history_stmt->execute();
$all_history_result = $all_history_stmt->get_result();
$all_history_expenses = [];
if ($all_history_result->num_rows > 0) {
    while ($row = $all_history_result->fetch_assoc()) {
        $all_history_expenses[] = $row;
    }
}
$all_history_stmt->close();

// Fetch only groups the user is a member of for dropdown
$groups_sql = "SELECT DISTINCT e.group_name, e.group_id 
               FROM expenses e
               JOIN group_members gm ON e.group_id = gm.group_id
               WHERE gm.user_id = ?
               ORDER BY e.group_name";
$groups_stmt = $conn->prepare($groups_sql);
$groups_stmt->bind_param("i", $user_id);
$groups_stmt->execute();
$groups_result = $groups_stmt->get_result();
$groups = [];
if ($groups_result->num_rows > 0) {
    while ($row = $groups_result->fetch_assoc()) {
        $groups[] = $row;
    }
}
$groups_stmt->close();

// Get selected group from URL or default to "All"
$selected_group = isset($_GET['group']) ? $_GET['group'] : 'all';

// Fetch expenses based on selected group (for Overview/Charts) - restricted to user's groups
if ($selected_group == 'all') {
    $sql = "SELECT e.expense_id, e.group_id, e.group_name, e.title, e.amount, e.paid_by, e.split_type, e.custom_value, e.file_path, e.expense_date, u.fullname AS paidbyname 
            FROM expenses e 
            JOIN group_members g ON e.group_id = g.group_id
            JOIN users u ON g.user_id = u.id
            WHERE g.user_id = ?
            ORDER BY e.group_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    $sql = "SELECT e.expense_id, e.group_id, e.group_name, e.title, e.amount, e.paid_by, e.split_type, e.custom_value, e.file_path, e.expense_date, u.fullname AS paidbyname 
            FROM expenses e 
            JOIN group_members g ON e.group_id = g.group_id
            JOIN users u ON g.user_id = u.id
            WHERE e.group_name = ? AND g.user_id = ?
            ORDER BY e.group_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $selected_group, $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
}

// Fetching group members
function getGroupMembers($conn, $group_id) {
    $stmt = $conn->prepare("SELECT members FROM `groups` WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $memberData = trim($row['members']);
        if (strpos($memberData, ',') !== false) {
            return array_filter(array_map('trim', explode(',', $memberData)));
        } elseif (!empty($memberData)) {
            return [$memberData];
        }
    }
    return [];
}

// Calculate total spent by each person (for the pie chart)
$member_expenses = [];
foreach ($expenses as $expense) {
    $payer = $expense['paid_by'];
    if (!isset($member_expenses[$payer])) {
        $member_expenses[$payer] = 0;
    }
    $member_expenses[$payer] += $expense['amount'];
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Split Bill Buddy - Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.3/jspdf.plugin.autotable.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f3fff3;
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex;
            min-height: 100vh;
        }

        /* VERTICAL NAVIGATION BAR */
        .sidebar {
            width: 250px;
            background: #198754;
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .nav-item {
            display: block;
            padding: 1rem 2rem;
            color: white;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            border-left-color: #fff;
            transform: translateX(5px);
        }

        .nav-item i {
            margin-right: 0.8rem;
            width: 20px;
        }

        /* MAIN CONTENT AREA */
        .main-wrapper {
            margin-left: 250px;
            flex: 1;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem 0;
            background: #fff;
            color: #198754;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border-radius: 12px;
            padding: 1.2rem 2rem;
        }

        .brand {
            font-size: 2rem;
            font-weight: bold;
        }

        .header-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .header-button {
            background: #198754;
            border: none;
            color: white;
            padding: 0.8rem 1.4rem;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.2s;
            font-weight: 600;
        }

        .header-button:hover {
            background: #157347;
        }

        .content-area {
            max-width: 1150px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
            display: none;
        }

        .content-area.active {
            display: block;
        }

        h2 {
            color: #198754;
            margin-bottom: 1rem;
        }

        /* GROUP FILTER DROPDOWN */
        .filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f8fff8;
            border-radius: 8px;
            border: 2px solid #d9f2d9;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .filter-group label {
            font-weight: 600;
            color: #198754;
            font-size: 1.1rem;
        }

        .filter-group select {
            padding: 0.8rem 1.2rem;
            border: 1px solid #d9f2d9;
            border-radius: 6px;
            font-size: 1rem;
            background: #fff;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .filter-group select:hover {
            border-color: #198754;
        }

        .result-count {
            color: #666;
            font-size: 1rem;
        }

        /* TABLE STYLES */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.95rem;
            table-layout: fixed;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e6f4e6;
            word-wrap: break-word;
            vertical-align: top;
        }

        th {
            background: #e8f5e8;
            color: #198754;
            font-weight: 600;
        }

        tr:hover {
            background: #f8fff8;
        }

        td a {
            color: #198754;
            text-decoration: none;
            font-weight: 500;
        }

        td a:hover {
            text-decoration: underline;
        }
        
        /* Column widths */
        th:nth-child(1), td:nth-child(1) { width: 10%; } /* Group Name */
        th:nth-child(2), td:nth-child(2) { width: 15%; } /* Title */
        th:nth-child(3), td:nth-child(3) { width: 10%; } /* Amount */
        th:nth-child(4), td:nth-child(4) { width: 10%; } /* Paid By */
        th:nth-child(5), td:nth-child(5) { width: 10%; } /* Split Type */
        th:nth-child(6), td:nth-child(6) { width: 10%; } /* Receipt */
        th:nth-child(7), td:nth-child(7) { width: 20%; } /* Amount Owed */
        th:nth-child(8), td:nth-child(8) { width: 15%; } /* Actions */
        
        /* Amount Owed column specific styling */
        td:nth-child(7) {
            font-size: 0.85rem;
            line-height: 1.4;
            padding-right: 5px;
        }

        /* CHART CONTAINER */
        .chart-container {
            position: relative;
            margin-left:400px;
            height: 400px;
            margin: 2rem 0;
        }

        /* ACTION BUTTONS */
        .action-btn {
            padding: 0.5rem 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.2s;
            display: inline-block;
            text-align: center;
            text-decoration: none;
            margin-bottom: 5px;
            white-space: nowrap;
            min-width: 60px;
            max-width: 100%;
        }

        .edit-btn {
            background: #ffc107;
            color: white;
            margin-right: 0.5rem;
        }

        .edit-btn:hover {
            background: #e0a800;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
        }

        .delete-btn:hover {
            background: #c82333;
        }
        
        /* Actions column styling */
        td:last-child {
            white-space: nowrap;
            text-align: center;
        }

        .mark-read-btn {
            background: #198754;
            color: white;
        }

        .mark-read-btn:hover {
            background: #157347;
        }

        /* RESPONSIVE ADJUSTMENTS */
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; padding: 1rem 0; }
            .main-wrapper { margin-left: 0; padding: 1rem; }
            .nav-item { padding: 1rem 0.8rem; }
            .header-buttons { gap: 0.6rem; margin-left:12px; }
            .brand { font-size: 1.1rem; }
            .container { max-width: 99vw; padding:12px;}
            h2 { font-size:19px;}
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <nav class="sidebar">
        <a href="#" class="nav-item active" onclick="showContent('overview')"><i>📊</i> Overview</a>
        <a href="#" class="nav-item" onclick="showContent('distribution')"><i>📈</i> Distribution</a>
        <a href="#" class="nav-item" onclick="showContent('history')"><i>📜</i> History</a>
        
    </nav>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Header -->
        <div class="header">
            <span class="brand">💸 Split Bill Buddy</span>
            <div class="header-buttons">
                <button class="header-button" onclick="location.href='index.php'">➕ Create Group</button>
                <button class="header-button" onclick="location.href='add_expense.php'">💰 Add Expense</button>
                
                <button class="header-button" onclick="location.href='<?php echo $is_logged_in ? 'logout.php' : 'login.php'; ?>'">
                    <?php echo $is_logged_in ? '🚪 Logout' : '🚪 Login'; ?>
                </button>
            </div>
        </div>

        <!-- 1. EXPENSE OVERVIEW -->
        <div id="overview" class="content-area active">
            <h2>Expense Overview</h2>
            <div class="filter-container">
                <div class="filter-group">
                    <label for="groupFilter">Filter by Group:</label>
                    <select id="groupFilter" onchange="filterGroup()">
                        <option value="all" <?php echo $selected_group == 'all' ? 'selected' : ''; ?>>All Groups</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo htmlspecialchars($group['group_name']); ?>" <?php echo $selected_group == $group['group_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($group['group_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="result-count">
                    Showing <?php echo count($expenses); ?> expense<?php echo count($expenses) != 1 ? 's' : ''; ?>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th>Title</th>
                        <th>Amount</th>
                        <th>Paid By</th>
                        <th>Split Type</th>
                        <th>Receipt</th>
                        <th>Amount Owed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($expenses)): ?>
                        <?php foreach ($expenses as $expense): ?>
                            <?php
                            $members = getGroupMembers($conn, $expense['group_id']);
                            $group_members_count = count($members);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($expense['group_name']); ?></td>
                                <td><?php echo htmlspecialchars($expense['title']); ?></td>
                                <td>₹<?php echo htmlspecialchars($expense['amount']); ?></td>
                                <td><?php echo htmlspecialchars($expense['paid_by']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($expense['split_type'])); ?></td>
                                <td>
                                    <?php if (!empty($expense['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($expense['file_path']); ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        No receipt
                                    <?php endif; ?>
                                </td>
                                <td class="owed-list">
                                    <?php 
                                    if ($group_members_count > 1) {
                                        if ($expense['split_type'] == 'equal') {
                                            $amount_per_person = $expense['amount'] / $group_members_count;
                                            foreach ($members as $member) {
                                                if (strcasecmp($member, $expense['paid_by']) !== 0 && strcasecmp($member, $expense['paidbyname']) !== 0) {
                                                    echo htmlspecialchars($member) . " owes ₹" . number_format($amount_per_person, 2) . "<br>";
                                                }
                                            }
                                        } elseif ($expense['split_type'] == 'percentage') {
                                            $percentage = $expense['custom_value'];
                                            $amount_owed = ($expense['amount'] * $percentage) / 100;
                                            $share_per_person = $amount_owed / ($group_members_count - 1);
                                            foreach ($members as $member) {
                                                if (strcasecmp($member, $expense['paid_by']) !== 0 && strcasecmp($member, $expense['paidbyname']) !== 0) {
                                                    echo htmlspecialchars($member) . " owes ₹" . number_format($share_per_person, 2) . "<br>";
                                                }
                                            }
                                        } elseif ($expense['split_type'] == 'fixed') {
                                            $amount_owed = $expense['custom_value'];
                                            $share_per_person = $amount_owed / ($group_members_count - 1);
                                            foreach ($members as $member) {
                                                if (strcasecmp($member, $expense['paid_by']) !== 0 && strcasecmp($member, $expense['paidbyname']) !== 0) {
                                                    echo htmlspecialchars($member) . " owes ₹" . number_format($share_per_person, 2) . "<br>";
                                                }
                                            }
                                        } else {
                                            echo 'Split type not recognized';
                                        }
                                    } else {
                                        echo 'Not enough members to split.';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="add_expense.php?id=<?php echo $expense['expense_id']; ?>" class="action-btn edit-btn">Edit</a>
                                    <a href="delete_expense.php?id=<?php echo $expense['expense_id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this expense?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:2rem; color:#666;">No expenses found for selected group.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. EXPENSES DISTRIBUTION (PIE CHART) -->
        <div id="distribution" class="content-area">
            <div class="chart-container">
                <h2 style="text-align:center; color:#198754;">Expense Distribution by Payer</h2>
                <canvas id="expenseChart"></canvas>
            </div>
        </div>

        <!-- 3. HISTORY (ALWAYS SHOWS ALL EXPENSES) -->
        <div id="history" class="content-area">
            <h2>Expense History</h2>
            <div class="filter-container" style="justify-content: flex-start; background: #e8f5e8;">
                <div class="result-count" style="color: #198754; font-weight: 600; font-size: 1.1rem;">
                    Showing ALL <?php echo count($all_history_expenses); ?> expense<?php echo count($all_history_expenses) != 1 ? 's' : ''; ?> (No Filter)
                </div>
            </div>
            <div style="margin-bottom: 1rem; text-align: right;">
                <button class="action-btn" style="background: #198754; color: white; margin-right: 0.5rem;" onclick="exportToCSV()">Export CSV (Excel)</button>
                <button class="action-btn" style="background: #198754; color: white;" onclick="exportToPDF()">Export PDF</button>
            </div>
            <table id="historyTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Group Name</th>
                        <th>Title</th>
                        <th>Amount</th>
                        <th>Paid By</th>
                        <th>Split Type</th>
                        <th>Amount Owed</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_history_expenses)): ?>
                        <?php foreach ($all_history_expenses as $expense): ?>
                            <?php
                            $members = getGroupMembers($conn, $expense['group_id']);
                            $group_members_count = count($members);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($expense['expense_date']); ?></td>
                                <td><?php echo htmlspecialchars($expense['group_name']); ?></td>
                                <td><?php echo htmlspecialchars($expense['title']); ?></td>
                                <td>₹<?php echo htmlspecialchars($expense['amount']); ?></td>
                                <td><?php echo htmlspecialchars($expense['paid_by']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($expense['split_type'])); ?></td>
                                <td class="owed-list">
                                    <?php 
                                    if ($group_members_count > 1) {
                                        if ($expense['split_type'] == 'equal') {
                                            $amount_per_person = $expense['amount'] / $group_members_count;
                                            foreach ($members as $member) {
                                                if (strcasecmp($member, $expense['paid_by']) !== 0 && strcasecmp($member, $expense['paidbyname']) !== 0) {
                                                    echo htmlspecialchars($member) . " owes ₹" . number_format($amount_per_person, 2) . "<br>";
                                                }
                                            }
                                        } elseif ($expense['split_type'] == 'percentage') {
                                            $percentage = $expense['custom_value'];
                                            $amount_owed = ($expense['amount'] * $percentage) / 100;
                                            $share_per_person = $amount_owed / ($group_members_count - 1);
                                            foreach ($members as $member) {
                                                if (strcasecmp($member, $expense['paid_by']) !== 0 && strcasecmp($member, $expense['paidbyname']) !== 0) {
                                                    echo htmlspecialchars($member) . " owes ₹" . number_format($share_per_person, 2) . "<br>";
                                                }
                                            }
                                        } elseif ($expense['split_type'] == 'fixed') {
                                            $amount_owed = $expense['custom_value'];
                                            $share_per_person = $amount_owed / ($group_members_count - 1);
                                            foreach ($members as $member) {
                                                if (strcasecmp($member, $expense['paid_by']) !== 0 && strcasecmp($member, $expense['paidbyname']) !== 0) {
                                                    echo htmlspecialchars($member) . " owes ₹" . number_format($share_per_person, 2) . "<br>";
                                                }
                                            }
                                        } else {
                                            echo 'Split type not recognized';
                                        }
                                    } else {
                                        echo 'Not enough members to split.';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($expense['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($expense['file_path']); ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        No receipt
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:2rem; color:#666;">No history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        
    </div>

    <script>
        // Filter function for group dropdown (ONLY for Overview)
        function filterGroup() {
            const selectedGroup = document.getElementById('groupFilter').value;
            const url = new URL(window.location);
            if (selectedGroup === 'all') {
                url.searchParams.delete('group');
            } else {
                url.searchParams.set('group', selectedGroup);
            }
            window.location.href = url.toString();
        }

        // Navigation function
        function showContent(page) {
            // Hide all content areas
            const contents = document.querySelectorAll('.content-area');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active from all nav items
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => item.classList.remove('active'));
            
            // Show selected content
            document.getElementById(page).classList.add('active');
            
            // Add active to clicked nav item
            event.target.classList.add('active');
            
            // Initialize charts when their tabs are shown
            if (page === 'distribution') initPieChart();
        }

        // PIE CHART
        function initPieChart() {
            const ctx = document.getElementById('expenseChart').getContext('2d');
            if (window.pieChart) window.pieChart.destroy();
            const expenseData = {
                labels: <?php echo json_encode(array_keys($member_expenses)); ?>,
                datasets: [{
                    label: 'Total Paid',
                    data: <?php echo json_encode(array_values($member_expenses)); ?>,
                    backgroundColor: [
                        '#198754', '#ffb84d', '#4da6ff', '#ff6666', '#b366ff', '#66cc99'
                    ],
                    hoverOffset: 8
                }]
            };
            window.pieChart = new Chart(ctx, {
                type: 'pie',
                data: expenseData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#198754', font: { size: 14 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ": ₹" + context.formattedValue;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Clean owed amount string helper
        function cleanOwedText(text) {
            return text
                .replace(/<br>/g, ' | ')
                .replace(/\s+/g, ' ')
                .replace(/¹/g, '')
                .replace(/&amp;/g, '&')
                .replace(/&/g, '&')
                .replace(/[\u200B-\u200D\uFEFF]/g, '')
                .replace(/[^\x00-\x7F]/g, 'Rs.')
                .trim();
        }

        // Export to CSV
        function exportToCSV() {
            let csv = [];
            const headers = ['Date', 'Group Name', 'Title', 'Amount', 'Paid By', 'Split Type', 'Amount Owed', 'Receipt'];
            csv.push(headers.join(','));

            const rows = document.querySelectorAll('#historyTable tbody tr');
            for (let row of rows) {
                let cols = row.querySelectorAll('td');
                let owedRaw = cols[6].innerText;
                let owedClean = cleanOwedText(owedRaw);
                let rowData = [
                    cols[0].innerText,
                    cols[1].innerText,
                    cols[2].innerText,
                    cols[3].innerText.replace(/₹/g, ''),
                    cols[4].innerText,
                    cols[5].innerText,
                    owedClean,
                    cols[7].innerText
                ];
                csv.push(rowData.map(item => `"${item.replace(/"/g, '""')}"`).join(','));
            }

            let csvContent = '\uFEFF' + csv.join('\n');
            let csvFile = new Blob([csvContent], { type: 'text/csv;charset=utf-8' });
            let url = URL.createObjectURL(csvFile);
            let a = document.createElement('a');
            a.href = url;
            a.download = 'expense_history.csv';
            a.click();
            URL.revokeObjectURL(url);
        }

        // Export to PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({
                putOnlyUsedFonts: true,
                floatPrecision: 16
            });
            const tableData = [];
            const headers = ['Date', 'Group Name', 'Title', 'Amount', 'Paid By', 'Split Type', 'Amount Owed', 'Receipt'];

            const rows = document.querySelectorAll('#historyTable tbody tr');
            for (let row of rows) {
                let cols = row.querySelectorAll('td');
                let owedRaw = cols[6].innerText;
                let owedClean = cleanOwedText(owedRaw);
                let rowData = [
                    cols[0].innerText,
                    cols[1].innerText,
                    cols[2].innerText,
                    cols[3].innerText.replace(/₹/g, ''),
                    cols[4].innerText,
                    cols[5].innerText,
                    owedClean,
                    cols[7].innerText
                ];
                tableData.push(rowData);
            }

            doc.text('Expense History', 14, 20);
            doc.autoTable({
                startY: 30,
                head: [headers],
                body: tableData,
                theme: 'striped',
                headStyles: {
                    fillColor: [25, 135, 84],
                    textColor: [255, 255, 255],
                    fontStyle: 'bold'
                },
                styles: {
                    fontSize: 10,
                    cellPadding: 2,
                    textColor: [0, 0, 0]
                },
                columnStyles: {
                    3: { halign: 'right' },
                    6: { cellWidth: 60, halign: 'left' }
                }
            });
            doc.save('expense_history.pdf');
        }

        // Initialize pie chart on load
        document.addEventListener('DOMContentLoaded', function() {
            initPieChart();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>