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

try {
    // Database connection
    require_once '../db_connection.php';
    
    // First check if this child belongs to the logged-in parent
    $checkStmt = $pdo->prepare("SELECT parent_id FROM child WHERE child_id = ?");
    $checkStmt->execute([$_POST['child_id']]);
    $child = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$child || $child['parent_id'] != $_SESSION['parent_id']) {
        die(json_encode(['success' => false, 'message' => 'Not authorized to delete this child']));
    }
    
    $pdo->beginTransaction();

    // First delete from child_reservation if exists
    $stmt = $pdo->prepare("DELETE FROM child_reservation WHERE child_id = ?");
    $stmt->execute([$_POST['child_id']]);
    
    // Delete from attendance if exists
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE child_id = ?");
    $stmt->execute([$_POST['child_id']]);
    
    // Finally delete the child
    $stmt = $pdo->prepare("DELETE FROM child WHERE child_id = ?");
    $result = $stmt->execute([$_POST['child_id']]);

    $pdo->commit();
    echo json_encode(['success' => $result]);
} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 