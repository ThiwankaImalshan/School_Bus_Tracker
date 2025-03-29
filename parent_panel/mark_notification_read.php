<?php
// Start session
session_start();

// Check if parent is logged in
if (!isset($_SESSION['parent_id']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if notification_id is set
if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit;
}

$notification_id = intval($_POST['notification_id']);

// Database connection
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verify the notification belongs to this parent
    $checkStmt = $pdo->prepare("SELECT notification_id FROM notification 
                               WHERE notification_id = ? AND recipient_type = 'parent' AND recipient_id = ?");
    $checkStmt->execute([$notification_id, $_SESSION['parent_id']]);
    
    if ($checkStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
        exit;
    }
    
    // Mark notification as read
    $updateStmt = $pdo->prepare("UPDATE notification SET is_read = 1 WHERE notification_id = ?");
    $updateStmt->execute([$notification_id]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>