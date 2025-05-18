<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['parent_id'])) {
    exit(json_encode(['error' => 'Unauthorized']));
}

$date = $_GET['date'] ?? '';
$child_id = $_GET['child_id'] ?? '';
$type = $_GET['type'] ?? 'all';

if (!$date || !$child_id) {
    exit(json_encode(['error' => 'Missing parameters']));
}

try {
    // Get bus_id from child
    $stmt = $pdo->prepare("SELECT bus_id FROM child WHERE child_id = ? AND parent_id = ?");
    $stmt->execute([$child_id, $_SESSION['parent_id']]);
    $bus_id = $stmt->fetchColumn();

    if (!$bus_id) {
        exit(json_encode(['error' => 'Invalid child']));
    }

    // Get tracking data
    $stmt = $pdo->prepare("
        SELECT bt.*, rs.location as stop_name, rs.sequence_number
        FROM bus_tracking bt
        LEFT JOIN route_stop rs ON bt.route_id = rs.route_id
        WHERE bt.bus_id = ? AND DATE(bt.timestamp) = ?
        ORDER BY bt.timestamp ASC
    ");
    $stmt->execute([$bus_id, $date]);
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get route segments
    $stmt = $pdo->prepare("
        SELECT 
            t1.latitude, t1.longitude, t1.timestamp as time1,
            t2.timestamp as time2, t1.speed,
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
        WHERE t1.bus_id = ? AND DATE(t1.timestamp) = ?
        ORDER BY t1.timestamp ASC
    ");
    $stmt->execute([$bus_id, $date]);
    $segments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'routes' => $routes,
        'segments' => $segments
    ]);
} catch (PDOException $e) {
    exit(json_encode(['error' => 'Database error']));
}
