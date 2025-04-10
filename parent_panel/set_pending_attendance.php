<?php
require_once 'config/db_connect.php';

// Only run on weekdays (1-5 is Monday to Friday)
$dayOfWeek = date('N');
if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
    // Today's date
    $today = date('Y-m-d');
    
    // Get all active children
    $childrenSql = "SELECT c.child_id, cr.seat_id 
                    FROM child c
                    LEFT JOIN child_reservation cr ON c.child_id = cr.child_id AND cr.is_active = 1
                    WHERE c.is_active = 1";
    $childrenStmt = $pdo->query($childrenSql);
    $children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each child, create a pending attendance record if one doesn't exist
    foreach ($children as $child) {
        // Check if record already exists
        $checkSql = "SELECT attendance_id FROM attendance WHERE child_id = ? AND attendance_date = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$child['child_id'], $today]);
        
        if (!$checkStmt->fetch()) {
            // Insert new pending record
            $insertSql = "INSERT INTO attendance (child_id, bus_seat_id, attendance_date, status) 
                          VALUES (?, ?, ?, 'pending')";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([$child['child_id'], $child['seat_id'], $today]);
        }
    }
    
    echo "Pending attendance records created for " . date('Y-m-d') . "\n";
} else {
    echo "Not a weekday, no attendance records created.\n";
}
?>