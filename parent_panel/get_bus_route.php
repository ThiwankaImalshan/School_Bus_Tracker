<?php
require_once 'db_connection.php';

if (!isset($_GET['date']) || !isset($_GET['bus_id'])) {
    exit(json_encode(['error' => 'Missing parameters']));
}

$date = $_GET['date'];
$bus_id = (int)$_GET['bus_id'];

try {
    // Get all tracking points for the selected date ordered by timestamp
    $stmt = $pdo->prepare("
        SELECT 
            t1.latitude, t1.longitude, t1.timestamp, t1.speed,
            t2.latitude as next_lat, t2.longitude as next_lon,
            t2.timestamp as next_time,
            ROUND(
                (6371 * acos(
                    cos(radians(t1.latitude)) 
                    * cos(radians(t2.latitude))
                    * cos(radians(t2.longitude) - radians(t1.longitude))
                    + sin(radians(t1.latitude))
                    * sin(radians(t2.latitude))
                )), 2
            ) as segment_distance
        FROM bus_tracking t1
        LEFT JOIN bus_tracking t2 ON t2.tracking_id = (
            SELECT MIN(tracking_id) 
            FROM bus_tracking 
            WHERE tracking_id > t1.tracking_id 
            AND bus_id = t1.bus_id 
            AND DATE(timestamp) = DATE(t1.timestamp)
        )
        WHERE t1.bus_id = ? 
        AND DATE(t1.timestamp) = ?
        ORDER BY t1.timestamp ASC
    ");
    
    $stmt->execute([$bus_id, $date]);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate route summary
    $total_distance = 0;
    $total_time = 0;
    $segments = [];

    foreach ($points as $point) {
        if ($point['next_lat'] !== null) {
            $segment = [
                'time' => $point['timestamp'],
                'distance' => $point['segment_distance'],
                'speed' => $point['speed'],
                'duration' => strtotime($point['next_time']) - strtotime($point['timestamp'])
            ];
            
            $segments[] = $segment;
            $total_distance += $point['segment_distance'];
            if ($point['next_time']) {
                $total_time += (strtotime($point['next_time']) - strtotime($point['timestamp']));
            }
        }
    }

    echo json_encode([
        'success' => true,
        'points' => $points,
        'segments' => $segments,
        'summary' => [
            'total_distance' => round($total_distance, 2),
            'total_time' => round($total_time / 60), // in minutes
            'average_speed' => $total_time > 0 ? round(($total_distance / ($total_time / 3600)), 2) : 0 // km/h
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
