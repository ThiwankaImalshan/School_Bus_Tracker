<?php
require_once 'db_connection.php';

if (!isset($_GET['date']) || !isset($_GET['bus_id'])) {
    exit(json_encode(['error' => 'Missing parameters']));
}

$date = $_GET['date'];
$bus_id = (int)$_GET['bus_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            bt1.latitude, bt1.longitude, bt1.timestamp, bt1.speed,
            bt2.latitude as next_lat, bt2.longitude as next_lon,
            bt2.timestamp as next_time,
            ROUND(
                (6371 * acos(
                    cos(radians(bt1.latitude)) 
                    * cos(radians(bt2.latitude))
                    * cos(radians(bt2.longitude) - radians(bt1.longitude))
                    + sin(radians(bt1.latitude))
                    * sin(radians(bt2.latitude))
                )), 2
            ) as segment_distance
        FROM bus_tracking bt1
        LEFT JOIN bus_tracking bt2 ON bt2.tracking_id = (
            SELECT MIN(tracking_id) 
            FROM bus_tracking 
            WHERE tracking_id > bt1.tracking_id 
            AND bus_id = bt1.bus_id 
            AND DATE(timestamp) = DATE(bt1.timestamp)
        )
        WHERE bt1.bus_id = ? 
        AND DATE(bt1.timestamp) = ?
        ORDER BY bt1.timestamp ASC
    ");
    
    $stmt->execute([$bus_id, $date]);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'points' => $points,
        'segments' => $points
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
