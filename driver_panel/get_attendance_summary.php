<?php
require_once 'db_connection.php';
session_start();

if (!isset($_SESSION['driver_id'])) {
    exit(json_encode(['error' => 'Not authenticated']));
}

$driver_id = $_SESSION['driver_id'];
$today = date('Y-m-d');
$current_route = $_GET['route'] ?? 'none';

// Get driver's bus_id
$stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ?");
$stmt->execute([$driver_id]);
$bus_id = $stmt->fetchColumn();

if (!$bus_id) {
    exit(json_encode(['error' => 'No bus assigned']));
}

// Get attendance summary
$stmt = $pdo->prepare("SELECT 
    SUM(CASE WHEN a.status = 'picked' AND ? = 'morning' THEN 1 
             WHEN a.status = 'drop' AND ? = 'evening' THEN 1 
             ELSE 0 END) as count_status,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN a.status IN ('pending', 'picked', 'drop') THEN 1 ELSE 0 END) as total_assigned
    FROM bus_seat bs
    LEFT JOIN attendance a ON bs.seat_id = a.bus_seat_id AND a.attendance_date = ?
    WHERE bs.bus_id = ?");
$stmt->execute([$current_route, $current_route, $today, $bus_id]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'summary' => [
        'count_status' => intval($summary['count_status'] ?? 0),
        'absent_count' => intval($summary['absent_count'] ?? 0),
        'total_assigned' => intval($summary['total_assigned'] ?? 0)
    ]
]);
