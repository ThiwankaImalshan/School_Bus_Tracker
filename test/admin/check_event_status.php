<?php
require_once '../config/database.php';

try {
    // Check if Event Scheduler is running
    $eventSchedulerStatus = $pdo->query("SHOW VARIABLES LIKE 'event_scheduler'")->fetch(PDO::FETCH_ASSOC);
    
    // Get details of our specific event
    $eventDetails = $pdo->query("SHOW EVENTS WHERE Name = 'create_daily_attendance'")->fetch(PDO::FETCH_ASSOC);
    
    $status = [
        'event_scheduler' => $eventSchedulerStatus['Value'],
        'event_exists' => !empty($eventDetails),
        'event_details' => $eventDetails,
        'last_executed' => null,
        'next_execution' => null
    ];
    
    // If event exists, get its execution details using updated_at instead of last_updated
    if (!empty($eventDetails)) {
        // Get last execution time
        $lastExecution = $pdo->query("
            SELECT MAX(updated_at) as last_run 
            FROM attendance 
            WHERE DATE(updated_at) = CURDATE()
        ")->fetch(PDO::FETCH_ASSOC);
        
        $status['last_executed'] = $lastExecution['last_run'];
        $status['next_execution'] = date('Y-m-d H:i:s', strtotime('tomorrow 05:00:00'));
    }

    header('Content-Type: application/json');
    echo json_encode($status, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
