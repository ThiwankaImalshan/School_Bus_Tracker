<?php
// login_process.php
session_start();
require_once 'db_connection.php';

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate email
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: log_in.php");
        exit();
    }

    try {
        // Prepare SQL to prevent SQL injection
        $stmt = $pdo->prepare("SELECT driver_id, email, password_hash FROM driver WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        // Fetch the driver
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify password
        if ($driver && password_verify($password, $driver['password_hash'])) {
            // Password is correct, start a new session
            $_SESSION['driver_id'] = $driver['driver_id'];
            $_SESSION['driver_email'] = $driver['email'];
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            // Invalid credentials
            $_SESSION['error'] = "Invalid email or password.";
            header("Location: log_in.php");
            exit();
        }
    } catch(PDOException $e) {
        // Log error or handle appropriately
        $_SESSION['error'] = "An error occurred. Please try again.";
        header("Location: log_in.php");
        exit();
    }
}