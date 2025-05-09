<?php
require_once 'conn.php';

$date = $_GET['date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'morning';

// Set time ranges
$morning_start = 5 * 60;  // 5:00 AM
$morning_end = 12 * 60;   // 12:00 PM
$evening_start = 12 * 60; // 12:00 PM
$evening_end = 17 * 60;   // 5:00 PM

$time_range = $type === 'morning' ? 
    [$morning_start, $morning_end] : 
    [$evening_start, $evening_end];

$stmt = $pdo->prepare("
    SELECT 
        t1.latitude as lat1, t1.longitude as lon1,
        t1.timestamp as time1,
        t2.latitude as lat2, t2.longitude as lon2,
        t2.timestamp as time2,
        t1.speed,
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
    JOIN bus_tracking t2 ON t2.tracking_id = t1.tracking_id + 1
    WHERE t1.bus_id = ? 
    AND DATE(t1.timestamp) = ?
    AND (
        HOUR(t1.timestamp) * 60 + MINUTE(t1.timestamp) 
        BETWEEN ? AND ?
    )
    ORDER BY t1.timestamp DESC
    LIMIT 10
");

$stmt->execute([$_SESSION['bus_id'], $date, $time_range[0], $time_range[1]]);
$segments = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'segments' => $segments]);
