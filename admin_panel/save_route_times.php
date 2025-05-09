<?php
header('Content-Type: application/json');

// Database connection
$conn = new mysqli("localhost", "root", "", "school_bus_management");

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bus_id = $_POST['bus_id'] ?? null;
    $morning_start = $_POST['morning_start'] ?? null;
    $morning_end = $_POST['morning_end'] ?? null;
    $evening_start = $_POST['evening_start'] ?? null;
    $evening_end = $_POST['evening_end'] ?? null;

    // Validate required fields
    if (!$bus_id || !$morning_start || !$morning_end || !$evening_start || !$evening_end) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit;
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        // Use INSERT ... ON DUPLICATE KEY UPDATE with created_at
        $sql = "INSERT INTO route_times (bus_id, route_type, start_time, end_time, created_at) 
                VALUES (?, ?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE 
                start_time = VALUES(start_time), 
                end_time = VALUES(end_time),
                created_at = CURDATE()";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        // Insert/Update morning route
        $stmt->bind_param('isss', $bus_id, $route_type, $start_time, $end_time);
        $route_type = 'morning';
        $start_time = $morning_start;
        $end_time = $morning_end;
        if (!$stmt->execute()) {
            throw new Exception("Morning route update failed: " . $stmt->error);
        }

        // Insert/Update evening route
        $route_type = 'evening';
        $start_time = $evening_start;
        $end_time = $evening_end;
        if (!$stmt->execute()) {
            throw new Exception("Evening route update failed: " . $stmt->error);
        }

        $stmt->close();
        
        // Commit transaction
        if (!$conn->commit()) {
            throw new Exception("Commit failed");
        }
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } finally {
        $conn->close();
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
