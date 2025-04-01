<?php
session_start();
header('Content-Type: application/json');

// Check for authentication
if (!isset($_SESSION['parent_id'])) {
    die(json_encode(['error' => 'Not authorized']));
}

// Validate child_id parameter
if (!isset($_GET['child_id']) || !is_numeric($_GET['child_id'])) {
    die(json_encode(['error' => 'Invalid child ID']));
}

try {
    // Modified database connection path
    require_once '../../db_connection.php';
    
    // For debug purposes, check if the connection works
    if (!isset($pdo)) {
        die(json_encode(['error' => 'Database connection not established']));
    }
    
    // Prepare and execute query to get child data
    $stmt = $pdo->prepare("
        SELECT * FROM child 
        WHERE child_id = ? AND parent_id = ?
    ");
    $stmt->execute([$_GET['child_id'], $_SESSION['parent_id']]);
    $child = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if child exists and belongs to the parent
    if (!$child) {
        die(json_encode(['error' => 'Child not found or not authorized']));
    }
    
    // Return child data as JSON
    echo json_encode($child);
} catch(PDOException $e) {
    // Return error message
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 