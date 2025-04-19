<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get the request body
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['location_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Location ID is required']);
    exit;
}

try {
    // Check if location exists and belongs to the parent's child
    $stmt = $pdo->prepare("
        SELECT pl.* 
        FROM pickup_locations pl 
        JOIN child c ON pl.child_id = c.child_id 
        WHERE pl.location_id = ? AND c.parent_id = ?
    ");
    $stmt->execute([$data['location_id'], $_SESSION['parent_id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Location not found or unauthorized']);
        exit;
    }

    // Delete the location
    $stmt = $pdo->prepare("DELETE FROM pickup_locations WHERE location_id = ?");
    $result = $stmt->execute([$data['location_id']]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to delete location');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
