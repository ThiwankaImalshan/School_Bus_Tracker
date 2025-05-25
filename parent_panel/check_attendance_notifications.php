<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get latest notifications for parent's children
    $stmt = $pdo->prepare("
        SELECT n.*, c.first_name, c.last_name, a.status
        FROM notification n
        JOIN child c ON n.child_id = c.child_id
        LEFT JOIN attendance a ON n.reference_id = a.attendance_id
        WHERE n.recipient_type = 'parent'
        AND n.recipient_id = ?
        AND n.is_read = 0
        AND n.notification_type = 'attendance'
        ORDER BY n.sent_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$_SESSION['parent_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'notifications' => $notifications]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
