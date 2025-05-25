<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all children IDs for current parent
    $stmt = $pdo->prepare("SELECT child_id FROM child WHERE parent_id = ?");
    $stmt->execute([$_SESSION['parent_id']]);
    $childIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($childIds)) {
        die(json_encode(['notifications' => []]));
    }

    // Get recent attendance changes
    $placeholders = str_repeat('?,', count($childIds) - 1) . '?';
    $sql = "SELECT a.*, c.first_name, c.last_name 
            FROM attendance a 
            JOIN child c ON a.child_id = c.child_id 
            WHERE a.child_id IN ($placeholders) 
            AND a.updated_at >= NOW() - INTERVAL 10 SECOND
            AND NOT EXISTS (
                SELECT 1 FROM notification n 
                WHERE n.reference_id = a.attendance_id 
                AND n.notification_type = 'attendance'
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($childIds);
    $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifications = [];
    foreach ($changes as $change) {
        // Create notification record
        $stmt = $pdo->prepare("INSERT INTO notification 
            (recipient_type, recipient_id, child_id, reference_id, title, message, notification_type) 
            VALUES ('parent', ?, ?, ?, 'Attendance Update', ?, 'attendance')");
        
        $message = "Attendance marked as " . ucfirst($change['status']) . 
                  " for " . $change['first_name'] . " " . $change['last_name'];
        
        $stmt->execute([
            $_SESSION['parent_id'],
            $change['child_id'],
            $change['attendance_id'],
            $message
        ]);

        $notifications[] = [
            'title' => 'Attendance Update',
            'message' => $message
        ];
    }

    echo json_encode(['notifications' => $notifications]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
