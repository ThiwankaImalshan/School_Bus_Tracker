<?php
// get_available_buses.php
require_once 'db_connection.php';

try {
    // Check if starting_location is provided
    if (!isset($_GET['starting_location']) || empty($_GET['starting_location'])) {
        throw new Exception('Starting location not specified');
    }

    $starting_location = $_GET['starting_location'];

    // Query to get buses in the specified starting location without an assigned driver
    $query = "SELECT b.bus_id, b.bus_number, b.license_plate FROM bus b 
              LEFT JOIN driver d ON b.bus_id = d.bus_id 
              WHERE b.starting_location = :starting_location 
              AND d.bus_id IS NULL 
              AND b.is_active = 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['starting_location' => $starting_location]);
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($buses);
} catch (Exception $e) {
    // Error handling
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}