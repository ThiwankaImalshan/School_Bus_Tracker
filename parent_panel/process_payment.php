<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['parent_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Include database connection file
require_once '../includes/db_connection.php';

// Get raw POST data
$postData = file_get_contents('php://input');
$data = json_decode($postData, true);

// Check if required fields are provided
if (!isset($data['child_id']) || !isset($data['amount']) || !isset($data['month_covered']) || !isset($data['transaction_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Extract data
$child_id = intval($data['child_id']);
$amount = floatval($data['amount']);
$month_covered = $data['month_covered'];
$transaction_id = $data['transaction_id'];
$payment_method = isset($data['payment_method']) ? $data['payment_method'] : 'Credit Card';
$description = isset($data['description']) ? $data['description'] : 'Monthly school bus fee';
$parent_id = $_SESSION['parent_id'];

// Validate child belongs to the parent
try {
    $stmt = $conn->prepare("SELECT child_id FROM child WHERE child_id = ? AND parent_id = ?");
    $stmt->bind_param("ii", $child_id, $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Child does not belong to the logged-in parent']);
        exit;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Format the month_covered to ensure it's a valid date
// If it's in format "January 2024", convert to "2024-01-01"
if (preg_match('/^([a-zA-Z]+)\s+(\d{4})$/', $month_covered, $matches)) {
    $month = date_parse($matches[1]);
    $year = $matches[2];
    $month_covered = sprintf("%04d-%02d-01", $year, $month['month']);
} else {
    // Try to parse the date as is
    $date = date_parse($month_covered);
    if ($date['error_count'] > 0) {
        // If parsing fails, use current month
        $month_covered = date('Y-m-01');
    } else {
        // Format the date to YYYY-MM-01
        $month_covered = sprintf("%04d-%02d-01", $date['year'], $date['month']);
    }
}

// Check if payment for this month and child already exists
try {
    $stmt = $conn->prepare("SELECT payment_id FROM payment WHERE child_id = ? AND month_covered = ? AND status = 'completed'");
    $stmt->bind_param("is", $child_id, $month_covered);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Payment for this month already exists']);
        exit;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Insert the payment record
try {
    $stmt = $conn->prepare("INSERT INTO payment (child_id, amount, payment_date, payment_method, transaction_id, status, description, month_covered) VALUES (?, ?, CURDATE(), ?, ?, 'completed', ?, ?)");
    $stmt->bind_param("idssss", $child_id, $amount, $payment_method, $transaction_id, $description, $month_covered);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $payment_id = $stmt->insert_id;
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Payment processed successfully',
            'payment_id' => $payment_id,
            'transaction_id' => $transaction_id
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to insert payment record']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 