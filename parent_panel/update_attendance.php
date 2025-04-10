<?php
// Start session and include necessary files
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log incoming request data
error_log('Update attendance request: ' . json_encode($_POST));

// Check if the user is logged in
if (!isset($_SESSION['parent_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Check if required parameters are set
if (!isset($_POST['child_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$childId = (int)$_POST['child_id'];
$status = $_POST['status'];

// Validate status
if (!in_array($status, ['present', 'absent'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Get today's date
    $today = date('Y-m-d');
    
    // Modify status if "present" is clicked
    $actualStatus = $status === 'present' ? 'pending' : $status;
    
    // Check if the attendance record exists
    $checkSql = "SELECT attendance_id FROM attendance WHERE child_id = ? AND attendance_date = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$childId, $today]);
    $attendanceExists = $checkStmt->fetch();
    
    if ($attendanceExists) {
        // Update existing record - only update status
        $sql = "UPDATE attendance SET status = ? WHERE child_id = ? AND attendance_date = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$actualStatus, $childId, $today]);
    } else {
        // Get bus_seat_id
        $seatSql = "SELECT seat_id FROM child_reservation WHERE child_id = ? AND is_active = 1 ORDER BY reservation_date DESC LIMIT 1";
        $seatStmt = $pdo->prepare($seatSql);
        $seatStmt->execute([$childId]);
        $seatData = $seatStmt->fetch();
        $busSeatId = $seatData ? $seatData['seat_id'] : null;
        
        // Insert new record without pickup_time
        $sql = "INSERT INTO attendance (child_id, bus_seat_id, attendance_date, status) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$childId, $busSeatId, $today, $actualStatus]);
    }
    
    // Log success
    error_log('Attendance updated successfully for child ' . $childId);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Database error in update_attendance.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('General error in update_attendance.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>