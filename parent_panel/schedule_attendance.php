<?php
// Script information for cron setup:
// Recommended cron schedule: 30 5 * * 1-5 
// Command: /usr/bin/php /path/to/schedule_attendance.php
// Purpose: Creates attendance records for school days at 5:30 AM

// Set timezone
date_default_timezone_set('Asia/Colombo');

// Initialize logging
$logFile = __DIR__ . '/attendance_cron.log';
$message = date('Y-m-d H:i:s') . " - Attendance scheduling started\n";
file_put_contents($logFile, $message, FILE_APPEND);

// Include database configuration
require_once 'config.php';

// Check if today is a weekday (1-5, Monday to Friday)
$dayOfWeek = date('N');
if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
    // Get today's date
    $today = date('Y-m-d');
    
    try {
        // Get all active child IDs with bus seat assignments
        $sql = "SELECT c.child_id, cr.seat_id 
                FROM child c 
                JOIN child_reservation cr ON c.child_id = cr.child_id 
                WHERE cr.is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each child, create or update attendance record
        foreach ($children as $child) {
            $insertSql = "INSERT INTO attendance 
                         (child_id, bus_seat_id, attendance_date, status) 
                         VALUES (?, ?, ?, 'pending')
                         ON DUPLICATE KEY UPDATE status = 'pending'";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([$child['child_id'], $child['seat_id'], $today]);
        }
        
        $message = date('Y-m-d H:i:s') . " - Successfully created attendance records for " . count($children) . " children\n";
        file_put_contents($logFile, $message, FILE_APPEND);
        
        echo "Attendance records created successfully for " . count($children) . " children.";
    } catch (PDOException $e) {
        $message = date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $message, FILE_APPEND);
        
        echo "Error creating attendance records: " . $e->getMessage();
    }
    
    $message = date('Y-m-d H:i:s') . " - Attendance scheduling completed\n";
    file_put_contents($logFile, $message, FILE_APPEND);
} else {
    echo "Today is not a weekday. No attendance records created.";
}
?>