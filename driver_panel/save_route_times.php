<?php
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['driver_id'])) {
    die(json_encode(['success' => false, 'error' => 'Not authenticated']));
}

try {
    $driver_id = $_POST['driver_id'];
    $route_date = $_POST['route_date'];
    $morning_start = $_POST['morning_start'];
    $morning_end = $_POST['morning_end'];
    $evening_start = $_POST['evening_start'];
    $evening_end = $_POST['evening_end'];

    // Check if entry exists for today
    $stmt = $pdo->prepare("SELECT id FROM driver_route_times WHERE driver_id = ? AND route_date = ?");
    $stmt->execute([$driver_id, $route_date]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing entry
        $stmt = $pdo->prepare("UPDATE driver_route_times SET 
            morning_start = ?, morning_end = ?, 
            evening_start = ?, evening_end = ? 
            WHERE driver_id = ? AND route_date = ?");
        $stmt->execute([
            $morning_start, $morning_end,
            $evening_start, $evening_end,
            $driver_id, $route_date
        ]);
    } else {
        // Insert new entry
        $stmt = $pdo->prepare("INSERT INTO driver_route_times 
            (driver_id, route_date, morning_start, morning_end, evening_start, evening_end) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $driver_id, $route_date,
            $morning_start, $morning_end,
            $evening_start, $evening_end
        ]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
