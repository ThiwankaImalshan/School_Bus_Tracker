<?php
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_GET['bus_id'])) {
    echo json_encode(['success' => false, 'error' => 'Bus ID is required']);
    exit;
}

$bus_id = $_GET['bus_id'];
$today = date('Y-m-d');

try {
    // Check if times exist for today
    $query = "SELECT route_type, start_time, end_time 
              FROM route_times 
              WHERE bus_id = ? AND DATE(created_at) = CURRENT_DATE()";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$bus_id]);
    $times = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($times)) {
        // Insert default times for today
        $defaultTimes = [
            ['morning', '05:00:00', '12:00:00'],
            ['evening', '12:00:00', '17:00:00']
        ];
        
        $insertQuery = "INSERT INTO route_times (bus_id, route_type, start_time, end_time, created_at) 
                       VALUES (?, ?, ?, ?, CURRENT_DATE())";
        
        $stmt = $pdo->prepare($insertQuery);
        foreach ($defaultTimes as $time) {
            $stmt->execute([$bus_id, $time[0], $time[1], $time[2]]);
        }
        
        // Return default times
        echo json_encode([
            'success' => true,
            'times' => [
                'morning' => ['start' => '05:00', 'end' => '12:00'],
                'evening' => ['start' => '12:00', 'end' => '17:00']
            ]
        ]);
    } else {
        // Format existing times
        $response = [
            'morning' => null,
            'evening' => null
        ];
        
        foreach ($times as $time) {
            $response[$time['route_type']] = [
                'start' => substr($time['start_time'], 0, 5),
                'end' => substr($time['end_time'], 0, 5)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'times' => $response
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
