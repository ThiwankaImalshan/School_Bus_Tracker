<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['parent_id']) || !isset($_GET['bus_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$bus_id = (int)$_GET['bus_id'];
$last_update = isset($_GET['last_update']) ? $_GET['last_update'] : '';

$query = "SELECT bt.*, COUNT(*) as total_points,
          ROUND(SUM(
              CASE 
                  WHEN bt2.latitude IS NOT NULL THEN
                      6371 * acos(
                          cos(radians(bt.latitude)) 
                          * cos(radians(bt2.latitude))
                          * cos(radians(bt2.longitude) - radians(bt.longitude))
                          + sin(radians(bt.latitude))
                          * sin(radians(bt2.latitude))
                      )
                  ELSE 0
              END
          ), 2) as distance_traveled
          FROM bus_tracking bt
          LEFT JOIN bus_tracking bt2 ON bt2.tracking_id = bt.tracking_id + 1
          WHERE bt.bus_id = ? 
          AND DATE(bt.timestamp) = CURDATE()
          " . ($last_update ? " AND bt.timestamp > ?" : "") . "
          GROUP BY bt.bus_id";

$params = $last_update ? [$bus_id, $last_update] : [$bus_id];
$stmt = $pdo->prepare($query);
$stmt->execute($params);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

// Get route points
$points_query = "SELECT latitude, longitude, timestamp, speed 
                 FROM bus_tracking 
                 WHERE bus_id = ? 
                 AND DATE(timestamp) = CURDATE()
                 ORDER BY timestamp ASC";

$stmt = $pdo->prepare($points_query);
$stmt->execute([$bus_id]);
$points = $stmt->fetchAll(PDO::FETCH_ASSOC);

$response = [
    'stats' => $data,
    'points' => $points,
    'last_update' => date('Y-m-d H:i:s')
];

header('Content-Type: application/json');
echo json_encode($response);
