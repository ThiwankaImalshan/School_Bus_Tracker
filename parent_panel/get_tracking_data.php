<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['parent_id']) || !isset($_GET['bus_id'])) {
    exit(json_encode(['error' => 'Unauthorized']));
}

$bus_id = (int)$_GET['bus_id'];
$query = "SELECT latitude, longitude, timestamp, speed 
          FROM bus_tracking 
          WHERE bus_id = ? 
          AND DATE(timestamp) = CURDATE()
          ORDER BY timestamp ASC";

$stmt = $pdo->prepare($query);
$stmt->execute([$bus_id]);
$tracking_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($tracking_data);
