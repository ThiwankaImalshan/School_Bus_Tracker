<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['driver_id'])) {
    exit(json_encode(['error' => 'Unauthorized']));
}

$driver_id = $_SESSION['driver_id'];
$current_time = isset($_POST['device_time']) ? $_POST['device_time'] : date('H:i:s');

try {
    // Get bus_id
    $stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $bus_id = $stmt->fetchColumn();

    if (!$bus_id) {
        exit(json_encode(['error' => 'No bus assigned']));
    }

    // Get latest location
    $stmt = $pdo->prepare("
        SELECT latitude, longitude, speed, timestamp 
        FROM bus_tracking 
        WHERE bus_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 1
    ");
    $stmt->execute([$bus_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get current route status
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN TIME(?) BETWEEN morning_start AND morning_end THEN 'morning'
                WHEN TIME(?) BETWEEN evening_start AND evening_end THEN 'evening'
                ELSE 'none'
            END as current_route
        FROM route_settings 
        WHERE bus_id = ?
    ");
    $stmt->execute([$current_time, $current_time, $bus_id]);
    $route_status = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get updates for stops
    $updates = [];
    if ($route_status['current_route'] === 'morning') {
        $stmt = $pdo->prepare("
            SELECT child_id, status 
            FROM attendance 
            WHERE bus_id = ? 
            AND attendance_date = CURDATE()
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT child_id, status 
            FROM attendance 
            WHERE bus_id = ? 
            AND attendance_date = CURDATE()
            AND status = 'drop'
        ");
    }
    $stmt->execute([$bus_id]);
    $updates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        'success' => true,
        'location' => $location,
        'route_status' => $route_status,
        'updates' => $updates,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    exit(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}
