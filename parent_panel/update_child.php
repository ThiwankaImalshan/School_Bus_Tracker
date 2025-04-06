<?php
session_start();
header('Content-Type: application/json');

// Check for authentication
if (!isset($_SESSION['parent_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Validate child_id parameter
if (!isset($_POST['child_id']) || !is_numeric($_POST['child_id'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid child ID']));
}

// Validate required fields
if (empty($_POST['first_name']) || empty($_POST['last_name'])) {
    die(json_encode(['success' => false, 'message' => 'Name fields are required']));
}

try {
    // Database connection
    require_once '../db_connection.php';
    
    // First check if this child belongs to the logged-in parent
    $checkStmt = $pdo->prepare("SELECT parent_id FROM child WHERE child_id = ?");
    $checkStmt->execute([$_POST['child_id']]);
    $child = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$child || $child['parent_id'] != $_SESSION['parent_id']) {
        die(json_encode(['success' => false, 'message' => 'Not authorized to update this child']));
    }
    
    // Update the child record
    $stmt = $pdo->prepare("
        UPDATE child 
        SET first_name = ?, 
            last_name = ?, 
            grade = ?,
            emergency_contact = ?,
            medical_notes = ?
        WHERE child_id = ?
    ");
    
    $result = $stmt->execute([
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['grade'],
        $_POST['emergency_contact'],
        $_POST['medical_notes'],
        $_POST['child_id']
    ]);

    echo json_encode(['success' => $result]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 