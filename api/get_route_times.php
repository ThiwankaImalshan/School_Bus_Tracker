<?php
header('Content-Type: application/json');
require_once '../db_connection.php';

if (!isset($_GET['bus_id'])) {
    echo json_encode(['error' => 'Bus ID is required']);
    exit;
}

$bus_id = $_GET['bus_id'];

try {
    $stmt = $pdo->prepare("SELECT route_type, start_time, end_time 
                          FROM route_times 
                          WHERE bus_id = :bus_id");
    $stmt->execute([':bus_id' => $bus_id]);
    $times = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'morning' => null,
        'evening' => null
    ];

    foreach ($times as $time) {
        $response[$time['route_type']] = [
            'start' => $time['start_time'],
            'end' => $time['end_time']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $response
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching route times'
    ]);
}
