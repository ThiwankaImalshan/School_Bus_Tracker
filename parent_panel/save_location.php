<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['parent_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get form data
    $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;
    $child_id = $_POST['child_id'];
    $name = $_POST['name'];
    $coordinates = $_POST['latitude'] . ',' . $_POST['longitude'];
    
    // Check if this is an update or new location
    if ($location_id) {
        // Update existing location
        $stmt = $pdo->prepare("UPDATE pickup_locations 
                              SET name = ?, location = ? 
                              WHERE location_id = ? AND child_id = ?");
        $stmt->execute([$name, $coordinates, $location_id, $child_id]);
    } else {
        // Insert new location
        $stmt = $pdo->prepare("INSERT INTO pickup_locations (child_id, name, location, created_at) 
                              VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$child_id, $name, $coordinates]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
