<?php
// Start session and include necessary files
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log incoming request data
error_log('Update evening route request: ' . json_encode($_POST));

// Check if the user is logged in
if (!isset($_SESSION['parent_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Check if required parameters are set
if (!isset($_POST['child_id']) || !isset($_POST['not_returning'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$childId = (int)$_POST['child_id'];
$notReturning = (bool)$_POST['not_returning'];

try {
    // Get today's date
    $today = date('Y-m-d');
    
    // Check if the attendance record exists
    $checkSql = "SELECT attendance_id FROM attendance WHERE child_id = ? AND attendance_date = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$childId, $today]);
    $attendanceExists = $checkStmt->fetch();
    
    if ($attendanceExists) {
        // Update existing record
        $sql = "UPDATE attendance SET notes = ? WHERE child_id = ? AND attendance_date = ?";
        $stmt = $pdo->prepare($sql);
        $notes = $notReturning ? 'Not returning by bus for evening route' : '';
        $stmt->execute([$notes, $childId, $today]);
    } else {
        // Get bus_seat_id
        $seatSql = "SELECT seat_id FROM child_reservation WHERE child_id = ? AND is_active = 1 ORDER BY reservation_date DESC LIMIT 1";
        $seatStmt = $pdo->prepare($seatSql);
        $seatStmt->execute([$childId]);
        $seatData = $seatStmt->fetch();
        $busSeatId = $seatData ? $seatData['seat_id'] : null;
        
        // Insert new record
        $sql = "INSERT INTO attendance (child_id, bus_seat_id, attendance_date, status, notes) VALUES (?, ?, ?, 'pending', ?)";
        $stmt = $pdo->prepare($sql);
        $notes = $notReturning ? 'Not returning by bus for evening route' : '';
        $stmt->execute([$childId, $busSeatId, $today, $notes]);
    }
    
    // Log success
    error_log('Evening route status updated successfully for child ' . $childId);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Database error in update_evening_route.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('General error in update_evening_route.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>