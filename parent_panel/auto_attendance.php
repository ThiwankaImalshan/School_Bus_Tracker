<?php
require_once '../config/database.php';

// Set timezone
date_default_timezone_set('Asia/Colombo');

// Get today's date
$today = date('Y-m-d');

try {
    // Log execution time
    error_log("Auto attendance check running at: " . date('Y-m-d H:i:s'));

    // Check if MySQL Event Scheduler is running
    $eventStatus = $pdo->query("SHOW VARIABLES LIKE 'event_scheduler'")->fetch(PDO::FETCH_ASSOC);
    
    // If Event Scheduler is OFF or no records exist for today, create them
    if ($eventStatus['Value'] === 'OFF' || !checkTodayAttendance($pdo)) {
        createAttendanceRecords($pdo);
    }

    // echo json_encode(['success' => true, 'message' => 'Attendance system checked']);
} catch (PDOException $e) {
    error_log("Auto attendance error: " . $e->getMessage());
    // echo json_encode(['success' => false, 'message' => 'Error checking attendance system']);
}

function checkTodayAttendance($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()");
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
}

function createAttendanceRecords($pdo) {
    global $today;

    // Get all active children who need attendance records
    $sql = "SELECT cr.child_id, cr.seat_id 
            FROM child_reservation cr 
            WHERE cr.is_active = 1 
            AND NOT EXISTS (
                SELECT 1 FROM attendance a 
                WHERE a.child_id = cr.child_id 
                AND a.attendance_date = ?
            )";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$today]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Insert pending attendance records for each child
    if (!empty($children)) {
        $insertSql = "INSERT INTO attendance (child_id, bus_seat_id, attendance_date, status) 
                      VALUES (?, ?, ?, 'pending')";
        $insertStmt = $pdo->prepare($insertSql);

        foreach ($children as $child) {
            $insertStmt->execute([
                $child['child_id'],
                $child['seat_id'],
                $today
            ]);
        }
    }

    // echo json_encode(['success' => true, 'message' => 'Attendance records created', 'count' => count($children)]);
}
