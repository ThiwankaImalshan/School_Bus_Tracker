<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Validate required fields
if (empty($_POST['full_name'])) {
    die(json_encode(['success' => false, 'message' => 'Full name is required']));
}

try {
    require_once 'db_connection.php';
    
    $stmt = $pdo->prepare("
        UPDATE parent 
        SET full_name = ?, 
            phone = ?, 
            home_address = ?
        WHERE parent_id = ?
    ");
    
    $result = $stmt->execute([
        $_POST['full_name'],
        $_POST['phone'],
        $_POST['home_address'],
        $_SESSION['parent_id']
    ]);

    echo json_encode(['success' => $result]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 