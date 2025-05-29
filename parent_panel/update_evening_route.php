<?php
// Initialize session if not already started
session_start();

// Database connection
require_once('../config/database.php');

// Check if required parameters are present
if (!isset($_POST['child_id']) || !isset($_POST['notes'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get parameters
$childId = intval($_POST['child_id']);
$notes = $_POST['notes'];
$today = date('Y-m-d');

try {
    // Update the attendance record for today
    $sql = "UPDATE attendance 
            SET notes = ? 
            WHERE child_id = ? AND attendance_date = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$notes, $childId, $today]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Evening route status updated successfully']);
    } else {
        // If no rows were affected, check if we need to insert a new record
        $checkSql = "SELECT * FROM attendance WHERE child_id = ? AND attendance_date = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$childId, $today]);
        
        if ($checkStmt->rowCount() === 0) {
            // Insert new attendance record
            $insertSql = "INSERT INTO attendance (child_id, attendance_date, notes) VALUES (?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([$childId, $today, $notes]);
            
            echo json_encode(['success' => true, 'message' => 'Evening route status created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update evening route status']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>