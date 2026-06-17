<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection
$conn = new mysqli("localhost", "root", "", "blog_site");
if ($conn->connect_error) die("DB Connection Failed");

$auth_error = "";
$auth_success = "";

// ---------------- USER LOGOUT ----------------
if (isset($_GET['user_logout'])) {
    unset($_SESSION['user_logged_in']);
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_email']);
    header("Location: index.php");
    exit;
}

// ---------------- USER REGISTRATION ----------------
if (isset($_POST['register_trigger'])) {
    $name = trim($conn->real_escape_string($_POST['reg_name'] ?? ''));
    $email = trim($conn->real_escape_string($_POST['reg_email'] ?? ''));
    $pass = $_POST['reg_pass'] ?? '';
    $conf_pass = $_POST['reg_conf_pass'] ?? '';

    if (empty($name) || empty($email) || empty($pass) || empty($conf_pass)) {
        $auth_error = "Please fill in all registration fields.";
    } elseif ($pass !== $conf_pass) {
        $auth_error = "Passwords do not match!";
    } elseif (strlen($pass) < 6) {
        $auth_error = "Password must be at least 6 characters long.";
    } else {
        // Check if email already exists
        $check_email = $conn->query("SELECT id FROM users WHERE email = '$email'");
        if ($check_email && $check_email->num_rows > 0) {
            $auth_error = "This email is already registered.";
        } else {
            // Securely hash the password using bcrypt
            $hashed_password = password_hash($pass, PASSWORD_DEFAULT);
            $register_sql = "INSERT INTO users (name, email, password) VALUES ('$name', '$email', '$hashed_password')";
            
            if ($conn->query($register_sql)) {
                $auth_success = "Registration successful! You can now log in below.";
            } else {
                $auth_error = "Registration failed. Database error.";
            }
        }
    }
}

// ---------------- USER LOGIN ----------------
if (isset($_POST['login_trigger'])) {
    $email = trim($conn->real_escape_string($_POST['login_email'] ?? ''));
    $pass = $_POST['login_pass'] ?? '';

    if (empty($email) || empty($pass)) {
        $auth_error = "Please fill in all login fields.";
    } else {
        $user_res = $conn->query("SELECT * FROM users WHERE email = '$email'");
        if ($user_res && $user_res->num_rows > 0) {
            $user_data = $user_res->fetch_assoc();
            
            // Verify password using native bcrypt matching
            if (password_verify($pass, $user_data['password'])) {
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id'] = $user_data['id'];
                $_SESSION['user_name'] = $user_data['name'];
                $_SESSION['user_email'] = $user_data['email'];
                
                header("Location: index.php");
                exit;
            } else {
                $auth_error = "Incorrect password entry.";
            }
        } else {
            $auth_error = "No user account found matching that email.";
        }
    }
}