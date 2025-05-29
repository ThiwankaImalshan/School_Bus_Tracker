<?php
session_start();
require_once '../config/database.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if required parameters are present
if (!isset($_POST['child_id']) || !isset($_POST['location_name'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$child_id = intval($_POST['child_id']);
$location_name = $_POST['location_name'];

try {
    // Get today's date
    $today = date('Y-m-d');

    // Update the notes column in the attendance table
    $sql = "UPDATE attendance 
            SET notes = ? 
            WHERE child_id = ? 
            AND attendance_date = ?";
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$location_name, $child_id, $today]);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Drop-off location updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update drop-off location']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>