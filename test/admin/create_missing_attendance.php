<?php
require_once '../config/database.php';

try {
    $today = date('Y-m-d');
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Find all active students missing attendance records
    $stmt = $pdo->prepare("
        INSERT INTO attendance (child_id, bus_seat_id, attendance_date, status)
        SELECT 
            cr.child_id,
            cr.seat_id,
            ?,
            'pending'
        FROM child_reservation cr
        WHERE cr.is_active = 1
        AND NOT EXISTS (
            SELECT 1 
            FROM attendance a 
            WHERE a.child_id = cr.child_id 
            AND a.attendance_date = ?
        )
    ");
    
    $stmt->execute([$today, $today]);
    $recordsCreated = $stmt->rowCount();
    
    // Create notifications for parents
    if ($recordsCreated > 0) {
        $notifyStmt = $pdo->prepare("
            INSERT INTO notification (recipient_type, recipient_id, child_id, title, message, notification_type)
            SELECT 
                'parent',
                c.parent_id,
                c.child_id,
                'Attendance Record Created',
                CONCAT('Please mark attendance for ', c.first_name, ' for ', DATE_FORMAT(?, '%d/%m/%Y')),
                'info'
            FROM child c
            JOIN attendance a ON c.child_id = a.child_id
            WHERE DATE(a.attendance_date) = ?
            AND a.status = 'pending'
        ");
        
        $notifyStmt->execute([$today, $today]);
    }
    
    $pdo->commit();
    
    // Redirect back with success message
    header('Location: test_attendance_event.php?success=1&created=' . $recordsCreated);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error creating attendance records: " . $e->getMessage());
    header('Location: test_attendance_event.php?error=1&message=' . urlencode($e->getMessage()));
}
