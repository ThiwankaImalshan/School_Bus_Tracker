<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $city = $_GET['city'] ?? '';
    
    // Get buses that cover the selected city and have available seats
    $stmt = $pdo->prepare("
        SELECT 
            b.bus_id,
            b.bus_number,
            b.capacity,
            COUNT(DISTINCT cr.seat_id) as reserved_seats,
            (b.capacity - COUNT(DISTINCT cr.seat_id)) as available_seats
        FROM bus b
        LEFT JOIN bus_seat bs ON b.bus_id = bs.bus_id
        LEFT JOIN child_reservation cr ON bs.seat_id = cr.seat_id AND cr.is_active = 1
        WHERE b.covering_cities LIKE :city
        GROUP BY b.bus_id
        HAVING available_seats > 0
    ");
    
    $stmt->execute(['city' => '%' . $city . '%']);
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($buses);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 