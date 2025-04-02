<?php
// Start session
session_start();

// Check if parent is logged in
if (!isset($_SESSION['parent_id']) || !$_SESSION['logged_in']) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Check if child_id is provided
if (!isset($_GET['child_id']) || empty($_GET['child_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Child ID is required']);
    exit;
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get child details
try {
    $childStmt = $pdo->prepare("
        SELECT 
            c.child_id, c.first_name, c.last_name, c.grade, c.medical_notes, 
            c.pickup_location, c.emergency_contact, c.school_id, c.bus_id,
            s.name as school_name,
            b.bus_number
        FROM child c
        LEFT JOIN school s ON c.school_id = s.school_id
        LEFT JOIN bus b ON c.bus_id = b.bus_id
        WHERE c.child_id = :child_id AND c.parent_id = :parent_id
    ");
    
    $childStmt->execute([
        'child_id' => $_GET['child_id'],
        'parent_id' => $_SESSION['parent_id']
    ]);
    
    $child = $childStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$child) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Child not found or not authorized to access']);
        exit;
    }
    
    // Return success with child data
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'child' => $child]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error fetching child details: ' . $e->getMessage()]);
    exit;
} 