<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['parent_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not authorized'
    ]);
    exit;
}

// Check if child_id is provided
if (!isset($_GET['child_id']) || !is_numeric($_GET['child_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid child ID'
    ]);
    exit;
}

try {
    // Connect to database
    require_once 'db_connection.php';
    
    // Get child details
    $stmt = $pdo->prepare("
        SELECT first_name, last_name
        FROM child
        WHERE child_id = ? AND parent_id = ?
    ");
    $stmt->execute([$_GET['child_id'], $_SESSION['parent_id']]);
    $child = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($child) {
        echo json_encode([
            'success' => true,
            'name' => $child['first_name'] . ' ' . $child['last_name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Child not found or not authorized'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 