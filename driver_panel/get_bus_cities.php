<?php
// get_bus_cities.php
require_once 'db_connection.php';

try {
    // Query to get unique starting cities from buses without assigned drivers
    $query = "SELECT DISTINCT starting_location FROM bus b 
              LEFT JOIN driver d ON b.bus_id = d.bus_id 
              WHERE d.bus_id IS NULL 
              AND b.is_active = 1 
              AND b.starting_location IS NOT NULL 
              AND b.starting_location != ''";
    
    $stmt = $pdo->query($query);
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($cities);
} catch (PDOException $e) {
    // Error handling
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}