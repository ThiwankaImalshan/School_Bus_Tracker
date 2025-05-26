<?php
require_once '../config/database.php';

// Set timezone
date_default_timezone_set('Asia/Colombo');

// Get today's date
$today = date('Y-m-d');

try {
    // Log execution time
    error_log("Auto attendance check running at: " . date('Y-m-d H:i:s'));

    // Always check and create records if they don't exist for today
    $recordsCreated = createAttendanceRecords($pdo);
    
    // Check if MySQL Event Scheduler is running and log status
    $eventStatus = $pdo->query("SHOW VARIABLES LIKE 'event_scheduler'")->fetch(PDO::FETCH_ASSOC);
    $eventSchedulerStatus = $eventStatus ? $eventStatus['Value'] : 'UNKNOWN';
    
    error_log("Event Scheduler Status: " . $eventSchedulerStatus);
    error_log("Records created for today ($today): " . $recordsCreated);

    // Only output JSON if called via AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode([
            'success' => true, 
            'message' => 'Attendance system checked and updated',
            'records_created' => $recordsCreated,
            'date' => $today,
            'event_scheduler' => $eventSchedulerStatus
        ]);
    }

} catch (PDOException $e) {
    error_log("Auto attendance error: " . $e->getMessage());
    
    // Only output JSON if called via AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode([
            'success' => false, 
            'message' => 'Error checking attendance system: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    error_log("General error in auto attendance: " . $e->getMessage());
    
    // Only output JSON if called via AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode([
            'success' => false, 
            'message' => 'System error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Check if attendance records exist for today
 */
function checkTodayAttendance($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        error_log("Existing attendance records for today: " . $count);
        return $count > 0;
    } catch (PDOException $e) {
        error_log("Error checking today's attendance: " . $e->getMessage());
        return false;
    }
}

/**
 * Create attendance records for all active children who don't have records for today
 */
function createAttendanceRecords($pdo) {
    global $today;

    try {
        // Start transaction for data consistency
        $pdo->beginTransaction();

        // Get all active children who need attendance records for today
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

        $recordsCreated = 0;

        // Insert pending attendance records for each child
        if (!empty($children)) {
            $insertSql = "INSERT INTO attendance (
                            child_id, 
                            bus_seat_id, 
                            attendance_date, 
                            status, 
                            notification_sent, 
                            last_updated, 
                            updated_at
                          ) VALUES (?, ?, ?, 'pending', 0, NOW(), NOW())";
            
            $insertStmt = $pdo->prepare($insertSql);

            foreach ($children as $child) {
                try {
                    $insertStmt->execute([
                        $child['child_id'],
                        $child['seat_id'],
                        $today
                    ]);
                    $recordsCreated++;
                    
                    error_log("Created attendance record for child_id: " . $child['child_id'] . 
                             ", seat_id: " . $child['seat_id'] . ", date: " . $today);
                             
                } catch (PDOException $e) {
                    error_log("Error creating attendance record for child_id " . $child['child_id'] . ": " . $e->getMessage());
                }
            }
        }

        // Commit transaction
        $pdo->commit();
        
        // Log summary
        error_log("Auto attendance creation completed. Records created: $recordsCreated, Active children processed: " . count($children));
        
        return $recordsCreated;

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollback();
        error_log("Database error in createAttendanceRecords: " . $e->getMessage());
        return 0;
    } catch (Exception $e) {
        // Rollback transaction on any error
        $pdo->rollback();
        error_log("General error in createAttendanceRecords: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get summary of today's attendance status
 */
function getAttendanceSummary($pdo) {
    try {
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = 'picked' THEN 1 ELSE 0 END) as picked,
                    SUM(CASE WHEN status = 'drop' THEN 1 ELSE 0 END) as dropped
                FROM attendance 
                WHERE attendance_date = CURDATE()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting attendance summary: " . $e->getMessage());
        return null;
    }
}

// Optional: If called directly via AJAX, show summary
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $summary = getAttendanceSummary($pdo);
    if ($summary) {
        error_log("Today's Attendance Summary - Total: {$summary['total_records']}, Pending: {$summary['pending']}, Present: {$summary['present']}, Absent: {$summary['absent']}, Picked: {$summary['picked']}, Dropped: {$summary['dropped']}");
    }
}
?>