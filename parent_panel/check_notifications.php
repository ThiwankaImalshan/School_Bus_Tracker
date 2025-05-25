<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all children for current parent
    $stmt = $pdo->prepare("SELECT child_id FROM child WHERE parent_id = ?");
    $stmt->execute([$_SESSION['parent_id']]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($children)) {
        die(json_encode(['notifications' => []]));
    }
    
    // Get latest attendance changes
    $placeholders = str_repeat('?,', count($children) - 1) . '?';
    $sql = "SELECT 
                a.attendance_id,
                a.child_id,
                a.status,
                a.attendance_date,
                c.first_name,
                c.last_name,
                NOT EXISTS (
                    SELECT 1 FROM notification n 
                    WHERE n.reference_id = a.attendance_id 
                    AND n.notification_type = 'attendance'
                ) as needs_notification
            FROM attendance a
            JOIN child c ON a.child_id = c.child_id
            WHERE a.child_id IN ($placeholders)
            AND a.attendance_date = CURRENT_DATE()
            AND a.created_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($children);
    $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $notifications = [];
    
    foreach ($changes as $change) {
        if ($change['needs_notification']) {
            // Create notification
            $message = "Attendance marked as " . ucfirst($change['status']) . 
                      " for " . $change['first_name'] . " " . $change['last_name'];
                      
            $stmt = $pdo->prepare("INSERT INTO notification 
                (recipient_type, recipient_id, child_id, reference_id, title, message, notification_type) 
                VALUES ('parent', ?, ?, ?, 'Attendance Update', ?, 'attendance')");
                
            $stmt->execute([
                $_SESSION['parent_id'],
                $change['child_id'],
                $change['attendance_id'],
                $message
            ]);
            
            $notifications[] = [
                'title' => 'Attendance Update',
                'message' => $message,
                'type' => 'attendance'
            ];
        }
    }
    
    echo json_encode(['notifications' => $notifications]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
