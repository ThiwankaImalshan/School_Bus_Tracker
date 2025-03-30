<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

$data = json_decode(file_get_contents('php://input'), true);

try {
    $stmt = $pdo->prepare("
        UPDATE bus 
        SET bus_number = ?, 
            license_plate = ?, 
            capacity = ?, 
            is_active = ?,
            starting_location = ?,
            covering_cities = ?
        WHERE bus_id = ?
    ");
    
    $result = $stmt->execute([
        $data['bus_number'],
        $data['license_plate'],
        $data['capacity'],
        $data['is_active'],
        $data['starting_location'],
        $data['covering_cities'],
        $data['bus_id']
    ]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update bus']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>