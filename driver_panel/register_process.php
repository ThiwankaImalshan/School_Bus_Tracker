<?php
// register_process.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connection.php';

// Enable error logging
function logError($message) {
    error_log($message, 3, 'registration_errors.log');
}

// Capture raw POST data for debugging
file_put_contents('post_data.log', print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logError('Invalid request method');
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Validate input data
$required_fields = ['full_name', 'email', 'phone', 'bus_city', 'bus', 'password', 'confirm_password'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    logError('Missing fields: ' . implode(', ', $missing_fields));
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing or empty fields',
        'missing_fields' => $missing_fields
    ]);
    exit;
}

// Additional validation
if ($_POST['password'] !== $_POST['confirm_password']) {
    logError('Password mismatch');
    http_response_code(400);
    echo json_encode(['error' => 'Passwords do not match']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM driver WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Email already registered');
    }

    // Generate a temporary license number if not provided
    $license_number = 'LIC-' . strtoupper(substr(md5(uniqid()), 0, 8));

    // Hash password
    $password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Prepare driver insert query
    $query = "INSERT INTO driver 
              (bus_id, full_name, email, password_hash, phone, 
               license_number, license_expiry_date, experience_years, age, joined_date) 
              VALUES 
              (:bus_id, :full_name, :email, :password_hash, :phone, 
               :license_number, :license_expiry_date, :experience_years, :age, :joined_date)";

    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        ':bus_id' => $_POST['bus'],
        ':full_name' => $_POST['full_name'],
        ':email' => $_POST['email'],
        ':password_hash' => $password_hash,
        ':phone' => $_POST['phone'],
        ':license_number' => $license_number,
        ':license_expiry_date' => date('Y-m-d', strtotime('+1 year')), // Example expiry
        ':experience_years' => 0, // Default value
        ':age' => null, // Optional field
        ':joined_date' => date('Y-m-d')
    ]);

    // Log any execution errors
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        logError('Execution error: ' . print_r($errorInfo, true));
        throw new Exception('Failed to insert driver: ' . $errorInfo[2]);
    }

    // Commit transaction
    $pdo->commit();

    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Driver registered successfully',
        'driver_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();

    // Log the full exception
    logError('Exception: ' . $e->getMessage());
    logError('Trace: ' . $e->getTraceAsString());

    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
exit;