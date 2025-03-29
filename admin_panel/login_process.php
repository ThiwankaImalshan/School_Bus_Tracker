<?php
// Start session
session_start();

// Database connection parameters
$db_host = 'localhost';
$db_name = 'school_bus_management';
$db_user = 'root'; // Replace with your database username
$db_pass = ''; // Replace with your database password

// Function to sanitize user input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'error_type' => '',
    'redirect' => ''
];

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize the input
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = "Invalid email format";
        $response['error_type'] = "email_format";
        http_response_code(400); // Bad request
        echo json_encode($response);
        exit;
    }

    // Validate password (not empty)
    if (empty($password)) {
        $response['message'] = "Password cannot be empty";
        $response['error_type'] = "password_empty";
        http_response_code(400); // Bad request
        echo json_encode($response);
        exit;
    }

    try {
        // Connect to the database
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Prepare and execute the query to get admin data
        $stmt = $pdo->prepare("SELECT admin_id, full_name, email, password_hash, role, is_active FROM admin WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($admin = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Verify if the account is active
            if (!$admin['is_active']) {
                $response['message'] = "Account is inactive. Please contact support.";
                $response['error_type'] = "account_inactive";
                http_response_code(403); // Forbidden
                echo json_encode($response);
                exit;
            }
            
            // Verify the password
            if (password_verify($password, $admin['password_hash'])) {
                // Password is correct, update last login time
                $updateStmt = $pdo->prepare("UPDATE admin SET last_login = NOW() WHERE admin_id = ?");
                $updateStmt->execute([$admin['admin_id']]);
                
                // Set session data
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // Set a secure, HTTP-only session cookie
                $session_name = session_name();
                $secure = true; // Set to false if not using HTTPS
                $httponly = true;
                setcookie($session_name, session_id(), 0, '/', '', $secure, $httponly);
                
                // Login successful
                $response['success'] = true;
                $response['message'] = "Login successful";
                $response['redirect'] = "dashboard.php";
                http_response_code(200); // OK
                
                echo json_encode($response);
                exit;
            } else {
                // Password is incorrect
                $response['message'] = "Incorrect password";
                $response['error_type'] = "password_incorrect";
                http_response_code(401); // Unauthorized
                echo json_encode($response);
                exit;
            }
        } else {
            // User not found
            $response['message'] = "Email not found";
            $response['error_type'] = "email_not_found";
            http_response_code(401); // Unauthorized
            echo json_encode($response);
            exit;
        }
    } catch (PDOException $e) {
        // Log error but don't expose details to the user
        error_log("Login Error: " . $e->getMessage());
        $response['message'] = "A system error occurred. Please try again later.";
        $response['error_type'] = "system_error";
        http_response_code(500); // Internal server error
        echo json_encode($response);
        exit;
    }
} else {
    // Not a POST request
    $response['message'] = "Invalid request method";
    $response['error_type'] = "invalid_method";
    http_response_code(405); // Method not allowed
    echo json_encode($response);
    exit;
}
?>