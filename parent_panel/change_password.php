<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Validate required fields
if (empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
    die(json_encode(['success' => false, 'message' => 'All fields are required']));
}

// Validate password match
if ($_POST['new_password'] !== $_POST['confirm_password']) {
    die(json_encode(['success' => false, 'message' => 'New passwords do not match']));
}

try {
    require_once 'db_connection.php';
    
    // First verify current password
    $stmt = $pdo->prepare("SELECT password_hash FROM parent WHERE parent_id = ?");
    $stmt->execute([$_SESSION['parent_id']]);
    $parent = $stmt->fetch();
    
    if (!$parent) {
        die(json_encode(['success' => false, 'message' => 'Parent account not found']));
    }
    
    if (!password_verify($_POST['current_password'], $parent['password_hash'])) {
        die(json_encode(['success' => false, 'message' => 'Current password is incorrect']));
    }
    
    // Update password
    $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        UPDATE parent 
        SET password_hash = ? 
        WHERE parent_id = ?
    ");
    
    $result = $stmt->execute([
        $new_password_hash,
        $_SESSION['parent_id']
    ]);

    echo json_encode(['success' => $result]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 