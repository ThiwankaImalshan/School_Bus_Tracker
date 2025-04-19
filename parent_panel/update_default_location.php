<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['parent_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $location_id = $data['location_id'];
    $child_id = $data['child_id'];

    // Get location from pickup_locations
    $stmt = $pdo->prepare("SELECT location FROM pickup_locations WHERE location_id = ? AND child_id = ?");
    $stmt->execute([$location_id, $child_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($location) {
        // Start transaction
        $pdo->beginTransaction();

        // Update child table with new pickup location
        $stmt = $pdo->prepare("UPDATE child SET pickup_location = ? WHERE child_id = ?");
        $stmt->execute([$location['location'], $child_id]);

        // Reset all is_default flags for this child's locations
        $stmt = $pdo->prepare("UPDATE pickup_locations SET is_default = 0 WHERE child_id = ?");
        $stmt->execute([$child_id]);

        // Set new default location
        $stmt = $pdo->prepare("UPDATE pickup_locations SET is_default = 1 WHERE location_id = ?");
        $stmt->execute([$location_id]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Location not found');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
