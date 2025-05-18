<?php
// update_drop_status.php - Handle updating student attendance status to 'drop'
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Check if child_id is provided
if (!isset($_POST['child_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing child ID']);
    exit;
}

$child_id = $_POST['child_id'];
$today = date('Y-m-d');
$current_time = date('H:i:s');

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update attendance status
    $stmt = $pdo->prepare("
        UPDATE attendance 
        SET status = 'drop', 
            drop_time = ? 
        WHERE child_id = ? 
        AND attendance_date = ?
    ");
    
    $success = $stmt->execute([$current_time, $child_id, $today]);
    
    if ($success) {
        // Get updated counts
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN status = 'drop' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                COUNT(*) as total_assigned
            FROM attendance 
            WHERE attendance_date = ?
        ");
        $stmt->execute([$today]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'counts' => $counts,
            'message' => 'Student marked as dropped off'
        ]);
    } else {
        throw new Exception('Failed to update attendance');
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}