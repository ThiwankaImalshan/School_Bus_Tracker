<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Validate required fields
if (empty($_POST['password'])) {
    die(json_encode(['success' => false, 'message' => 'Password is required to confirm deletion']));
}

try {
    require_once 'db_connection.php';
    
    // First verify password
    $stmt = $pdo->prepare("SELECT password_hash FROM parent WHERE parent_id = ?");
    $stmt->execute([$_SESSION['parent_id']]);
    $parent = $stmt->fetch();
    
    if (!$parent) {
        die(json_encode(['success' => false, 'message' => 'Parent account not found']));
    }
    
    if (!password_verify($_POST['password'], $parent['password_hash'])) {
        die(json_encode(['success' => false, 'message' => 'Password is incorrect']));
    }
    
    $pdo->beginTransaction();
    
    // Delete dependent records first (child records in other tables)
    
    // Delete children reservations
    $stmt = $pdo->prepare("
        DELETE cr FROM child_reservation cr
        JOIN child c ON cr.child_id = c.child_id
        WHERE c.parent_id = ?
    ");
    $stmt->execute([$_SESSION['parent_id']]);
    
    // Delete attendance records
    $stmt = $pdo->prepare("
        DELETE a FROM attendance a
        JOIN child c ON a.child_id = c.child_id
        WHERE c.parent_id = ?
    ");
    $stmt->execute([$_SESSION['parent_id']]);
    
    // Delete payments
    $stmt = $pdo->prepare("
        DELETE p FROM payment p
        JOIN child c ON p.child_id = c.child_id
        WHERE c.parent_id = ?
    ");
    $stmt->execute([$_SESSION['parent_id']]);
    
    // Delete messages
    $stmt = $pdo->prepare("
        DELETE FROM message 
        WHERE (sender_type = 'parent' AND sender_id = ?) 
        OR (recipient_type = 'parent' AND recipient_id = ?)
    ");
    $stmt->execute([$_SESSION['parent_id'], $_SESSION['parent_id']]);
    
    // Delete notifications
    $stmt = $pdo->prepare("
        DELETE FROM notification 
        WHERE recipient_type = 'parent' AND recipient_id = ?
    ");
    $stmt->execute([$_SESSION['parent_id']]);
    
    // Delete children
    $stmt = $pdo->prepare("DELETE FROM child WHERE parent_id = ?");
    $stmt->execute([$_SESSION['parent_id']]);
    
    // Finally, delete the parent account
    $stmt = $pdo->prepare("DELETE FROM parent WHERE parent_id = ?");
    $result = $stmt->execute([$_SESSION['parent_id']]);
    
    $pdo->commit();
    
    // Clear the session
    session_destroy();
    
    echo json_encode(['success' => $result]);
} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 