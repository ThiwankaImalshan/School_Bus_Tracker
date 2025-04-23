<?php
// update_drop_status.php - Handle updating student attendance status to 'drop'
session_start();
require_once 'db_connection.php';

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if child_id is provided
if (!isset($_POST['child_id']) || !is_numeric($_POST['child_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid child ID']);
    exit;
}

$child_id = (int)$_POST['child_id'];
$driver_id = $_SESSION['driver_id'];
$today = date('Y-m-d');
$now = date('H:i:s');

try {
    // First verify the driver is assigned to the bus that this child is on
    $stmt = $pdo->prepare("
        SELECT c.bus_id, d.bus_id as driver_bus_id 
        FROM child c
        JOIN driver d ON d.driver_id = ?
        WHERE c.child_id = ?
    ");
    $stmt->execute([$driver_id, $child_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result || $result['bus_id'] != $result['driver_bus_id']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You are not authorized to update this child\'s status']);
        exit;
    }
    
    // Update the attendance status to 'drop' and record the drop time
    $stmt = $pdo->prepare("
        UPDATE attendance 
        SET status = 'drop', drop_time = ? 
        WHERE child_id = ? AND attendance_date = ?
    ");
    $stmt->execute([$now, $child_id, $today]);
    
    if ($stmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Student marked as dropped off successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No attendance record found for this student today']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 