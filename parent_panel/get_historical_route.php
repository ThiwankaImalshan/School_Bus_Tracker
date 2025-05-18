<?php
session_start();
require_once 'db_connection.php';

// Verify user is logged in
if (!isset($_SESSION['parent_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING);
$bus_id = filter_input(INPUT_GET, 'bus_id', FILTER_SANITIZE_NUMBER_INT);

if (!$date || !$bus_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid parameters']));
}

try {
    // Get route times
    $stmt = $pdo->prepare("
        SELECT 
            morning_start, morning_end, 
            evening_start, evening_end 
        FROM route_settings 
        WHERE bus_id = ?
    ");
    $stmt->execute([$bus_id]);
    $times = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$times) {
        // Default times if not set
        $times = [
            'morning_start' => 300,  // 5:00 AM
            'morning_end' => 720,    // 12:00 PM
            'evening_start' => 720,  // 12:00 PM
            'evening_end' => 1020    // 5:00 PM
        ];
    }

    // Get morning route data
    $stmt = $pdo->prepare("
        SELECT latitude, longitude, timestamp, speed
        FROM bus_tracking 
        WHERE bus_id = ? 
        AND DATE(timestamp) = ?
        AND (
            HOUR(timestamp) * 60 + MINUTE(timestamp)
            BETWEEN ? AND ?
        )
        ORDER BY timestamp ASC
    ");
    $stmt->execute([$bus_id, $date, $times['morning_start'], $times['morning_end']]);
    $morning_route = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get evening route data
    $stmt->execute([$bus_id, $date, $times['evening_start'], $times['evening_end']]);
    $evening_route = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'morning' => $morning_route,
        'evening' => $evening_route
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database error']));
}
