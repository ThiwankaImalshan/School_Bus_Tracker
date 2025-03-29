<?php
// Start session for user authentication
session_start();

// Database connection parameters
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get email and password from form
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    // Authenticate as a parent
    $stmt = $pdo->prepare("SELECT parent_id, full_name, email, password_hash, phone FROM parent WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists and verify password
    if ($user && password_verify($password, $user['password_hash'])) {
        // Update last login time
        $updateStmt = $pdo->prepare("UPDATE parent SET last_login = NOW() WHERE parent_id = ?");
        $updateStmt->execute([$user['parent_id']]);

        // Set session variables
        $_SESSION['parent_id'] = $user['parent_id'];
        $_SESSION['parent_name'] = $user['full_name'];
        $_SESSION['parent_email'] = $user['email'];
        $_SESSION['parent_phone'] = $user['phone'];
        $_SESSION['logged_in'] = true;

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => 'dashboard.php'
        ]);
    } else {
        // Invalid credentials
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    }
} else {
    // Not a POST request
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>