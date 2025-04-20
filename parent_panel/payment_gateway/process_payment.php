<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['parent_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Include database connection file
require_once '../conn.php';

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
    $stmt = $pdo->prepare("SELECT child_id FROM child WHERE child_id = ? AND parent_id = ?");
    $stmt->bindParam(1, $child_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $parent_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) === 0) {
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
if (preg_match('/^([a-zA-Z]+)$/', $month_covered, $matches)) {
    $month = date_parse($matches[1]);
    $year = date('Y');
    $month_covered = sprintf("%04d-%02d-01", $year, $month['month']);
} else if (preg_match('/^([a-zA-Z]+)\s+(\d{4})$/', $month_covered, $matches)) {
    $month = date_parse($matches[1]);
    $year = $matches[2];
    $month_covered = sprintf("%04d-%02d-01", $year, $month['month']);
} else {
    // Default to current month
    $month_covered = date('Y-m-01');
}

// Check if payment for this month and child already exists
try {
    $stmt = $pdo->prepare("SELECT payment_id FROM payment WHERE child_id = ? AND month_covered = ? AND status = 'completed'");
    $stmt->bindParam(1, $child_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $month_covered, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) > 0) {
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
    $stmt = $pdo->prepare("INSERT INTO payment (child_id, amount, payment_date, payment_method, transaction_id, status, description, month_covered) VALUES (?, ?, CURDATE(), ?, ?, 'completed', ?, ?)");
    $stmt->bindParam(1, $child_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $amount, PDO::PARAM_STR);
    $stmt->bindParam(3, $payment_method, PDO::PARAM_STR);
    $stmt->bindParam(4, $transaction_id, PDO::PARAM_STR);
    $stmt->bindParam(5, $description, PDO::PARAM_STR);
    $stmt->bindParam(6, $month_covered, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $payment_id = $pdo->lastInsertId();
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