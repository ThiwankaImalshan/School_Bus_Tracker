<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Location ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM pickup_locations WHERE location_id = ?");
    $stmt->execute([$_GET['id']]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($location) {
        // Split location string into latitude and longitude
        list($latitude, $longitude) = explode(',', $location['location']);
        $location['latitude'] = $latitude;
        $location['longitude'] = $longitude;
        echo json_encode($location);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Location not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
