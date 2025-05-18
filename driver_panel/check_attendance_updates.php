<?php
require_once 'db_connection.php';
session_start();

if (!isset($_SESSION['driver_id'])) {
    exit(json_encode(['error' => 'Not authenticated']));
}

$driver_id = $_SESSION['driver_id'];
$today = date('Y-m-d');

// Get last updated timestamp from request
$lastUpdate = isset($_GET['last_update']) ? $_GET['last_update'] : 0;

// Check for any updates in attendance since last check
$stmt = $pdo->prepare("
    SELECT a.*, bs.seat_id, c.first_name, c.last_name,
    CONCAT(LEFT(c.first_name, 1), '. ', c.last_name) as short_name
    FROM attendance a 
    JOIN bus_seat bs ON a.bus_seat_id = bs.seat_id
    JOIN child c ON a.child_id = c.child_id
    JOIN driver d ON bs.bus_id = d.bus_id
    WHERE d.driver_id = ? 
    AND a.attendance_date = ?
    AND a.last_updated > FROM_UNIXTIME(?)
");

$stmt->execute([$driver_id, $today, $lastUpdate]);
$updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($updates)) {
    echo json_encode([
        'status' => 'updated',
        'data' => $updates,
        'timestamp' => time()
    ]);
} else {
    echo json_encode([
        'status' => 'no_change',
        'timestamp' => time()
    ]);
}
