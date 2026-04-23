<?php
// --- PHP BACKEND SECTION ---

session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db = " split_bill_buddy";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// --- AJAX: Fetch security question ---
if (isset($_POST['fetch_question'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $query = "SELECT id, security_question FROM users WHERE username='$username' OR email='$username'";
    $result = mysqli_query($conn, $query);

    header('Content-Type: application/json');
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        echo json_encode(["status" => "ok", "question" => $row['security_question'], "user_id" => $row['id']]);
    } else {
        echo json_encode(["status" => "not_found"]);
    }
    exit();
}

// --- AJAX: Verify answer ---
if (isset($_POST['verify_answer'])) {
    $user_id = $_POST['user_id'];
    $answer = $_POST['answer'];

    $query = "SELECT security_answer FROM users WHERE id='$user_id'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);

    if (password_verify($answer, $row['security_answer'])) {
        echo json_encode(["status" => "correct"]);
    } else {
        echo json_encode(["status" => "wrong"]);
    }
    exit();
}

// --- AJAX: Reset password ---
if (isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    mysqli_query($conn, "UPDATE users SET password='$new_password' WHERE id='$user_id'");
    exit();
}

// --- Registration Handling ---
if (isset($_POST['register'])) {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $question = mysqli_real_escape_string($conn, $_POST['security_question']);
    $answer = $_POST['security_answer'];

    if ($password !== $confirm) {
        echo "<script>alert('Passwords do not match!');</script>";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $hashed_answer = password_hash($answer, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (fullname, username, email, phone, password, security_question, security_answer)
                  VALUES ('$fullname', '$username', '$email', '$phone', '$hashed', '$question', '$hashed_answer')";

        if (mysqli_query($conn, $query)) {
            echo "<script>alert('Registration successful! You can now log in.');</script>";
        } else {
            echo "<script>alert('Error: Username or Email might already exist!');</script>";
        }
    }
}

// --- Login Handling ---
if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    $query = "SELECT * FROM users WHERE username='$username' OR email='$username'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];  // ← CRITICAL: Set session
          
            header("Location: homepage.php");  // Use header() instead of JS
            exit();
        }else {
            echo "<script>alert('Invalid password!');</script>";
        }
    } else {
        echo "<script>alert('User not found!');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Split Bill Buddy</title>
<style>
body { font-family: Arial; 
    background: #d4f7d4; 
    display: flex; 
    justify-content: center; 
    align-items: center; 
    height: 100vh; 
    margin:0; 
    flex-direction: column;}
header, footer { width:100%; 
    background:#006400; 
    color:white; 
    text-align:center; 
    position:fixed; 
    left:0;}
header{top:0;padding:15px 0;font-size:22px;font-weight:bold;}
footer{bottom:0;padding:10px 0;font-size:14px;}
.container {background:#fff;padding:40px;border-radius:10px;box-shadow:0 0 15px rgba(0,0,0,0.15);width:420px;max-height:75vh;overflow-y:auto;margin-top:80px;margin-bottom:60px;}
.form {display:none;flex-direction:column;}
.form.active {display:flex;}
.form label {font-weight:bold;margin-top:10px;text-align:left;}
.form input, .form select {margin:5px 0 15px 0;padding:12px;font-size:16px;border:1px solid #ccc;border-radius:6px;transition:all 0.3s ease;}
.form button {padding:12px;background:#007bff;color:white;border:none;border-radius:6px;cursor:pointer;font-size:16px;margin-top:10px;}
.form button:hover {background:#0056b3;}
#form-toggle{display:flex;justify-content:space-between;margin-bottom:20px;}
#form-toggle button{padding:12px;width:48%;background:#ccc;border:none;border-radius:6px;font-size:16px;transition:all 0.3s ease;}
#form-toggle button.active{background:#007bff;color:white;}
.forgot-password{text-align:right;margin-top:-10px;margin-bottom:10px;font-size:14px;}
.forgot-password a{color:#007bff;text-decoration:none;cursor:pointer;}
</style>
</head>
<body>
<header>Split Bill Buddy</header>
<div class="container">
  <div id="form-toggle">
    <button type="button" id="login-btn" onclick="showForm('login')" class="active">Login</button>
    <button type="button" id="register-btn" onclick="showForm('register')">Register</button>
  </div>

  <!-- Login Form -->
  <form id="login-form" class="form active" method="POST" action="login.php">
    <h2>Login</h2>
    <label>Username / Email</label>
    <input type="text" name="username" placeholder="Enter Username or Email" required/>
    <label>Password</label>
    <input type="password" name="password" placeholder="Enter Password" required/>
    <div class="forgot-password">
      <a href="#" onclick="forgotPassword()">Forgot Password?</a>
    </div>
    <button type="submit" name="login">Login</button>
  </form>

  <!-- Register Form -->
  <form id="register-form" class="form" method="POST">
    <h2>Register</h2>
    <label>Full Name</label>
    <input type="text" name="fullname" required/>
    <label>Username</label>
    <input type="text" name="username" required/>
    <label>Email</label>
    <input type="email" name="email" required/>
    <label>Phone Number</label>
    <input type="tel" name="phone" required/>
    <label>Password</label>
    <input type="password" name="password" required/>
    <label>Confirm Password</label>
    <input type="password" name="confirm_password" required/>
    <label>Security Question</label>
    <select name="security_question" required>
      <option value="What is your pet's name?">What is your pet's name?</option>
      <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
      <option value="What is your favorite color?">What is your favorite color?</option>
    </select>
    <label>Answer</label>
    <input type="text" name="security_answer" required/>
    <button type="submit" name="register">Register</button>
  </form>
</div>
<footer>© 2025 Split Bill Buddy</footer>

<script>
function showForm(type){
  document.getElementById('login-form').classList.toggle('active', type==='login');
  document.getElementById('register-form').classList.toggle('active', type==='register');
  document.getElementById('login-btn').classList.toggle('active', type==='login');
  document.getElementById('register-btn').classList.toggle('active', type==='register');
}

async function forgotPassword() {
    const username = prompt("Enter your username or email:");
    if(!username) return;

    // Fetch security question
    const fd = new FormData();
    fd.append("fetch_question",1);
    fd.append("username",username);
    const res = await fetch("login.php",{method:"POST",body:fd});
    const data = await res.json();

    if(data.status==="ok") {
        const answer = prompt(data.question);
        if(!answer) return;

        // Verify answer
        const verify = new FormData();
        verify.append("verify_answer",1);
        verify.append("user_id",data.user_id);
        verify.append("answer",answer);
        const verifyResp = await fetch("login.php",{method:"POST",body:verify});
        const verifyData = await verifyResp.json();

        if(verifyData.status==="correct") {
            const newPass = prompt("Enter your new password:");
            if(!newPass) return;

            const reset = new FormData();
            reset.append("reset_password",1);
            reset.append("user_id",data.user_id);
            reset.append("new_password",newPass);
            await fetch("login.php",{method:"POST",body:reset});
            alert("Password reset successful! You can now log in.");
        } else {
            alert("Incorrect answer!");
        }
    } else {
        alert("User not found!");
    }
}
</script>
</body>
</html>
