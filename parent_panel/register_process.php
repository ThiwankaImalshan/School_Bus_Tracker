<?php
// Database connection setup
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root'; // Replace with your actual database username
$password = ''; // Replace with your actual database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get form data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate inputs
$errors = [];

if (empty($name)) {
    $errors[] = 'Full name is required.';
}

if (empty($email)) {
    $errors[] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (empty($address)) {
    $errors[] = 'Address is required.';
}

if (empty($password)) {
    $errors[] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long.';
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
}

// If there are validation errors, return them
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
    exit;
}

// Check if email already exists
try {
    $stmt = $pdo->prepare("SELECT email FROM parent WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email address is already registered. Please use a different email or login to your account.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking email: ' . $e->getMessage()]);
    exit;
}

// Extract phone number from POST if available, otherwise set to empty string
// In a real implementation, you might want to add phone validation
$phone = trim($_POST['phone'] ?? '');

// All validations passed, insert new parent
try {
    $stmt = $pdo->prepare("
        INSERT INTO parent (full_name, email, password_hash, phone, home_address, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    // Hash the password for secure storage
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt->execute([$name, $email, $password_hash, $phone, $address]);
    $parent_id = $pdo->lastInsertId();
    
    // Create a session for the newly registered user
    session_start();
    $_SESSION['user_id'] = $parent_id;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_type'] = 'parent';
    
    echo json_encode([
        'success' => true, 
        'message' => 'Registration successful. Redirecting to dashboard...',
        'user_id' => $parent_id
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
    exit;
}
?>