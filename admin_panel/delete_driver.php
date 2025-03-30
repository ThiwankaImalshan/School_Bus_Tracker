<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

$data = json_decode(file_get_contents('php://input'), true);
$driver_id = $data['driver_id'] ?? null;

if (!$driver_id) {
    echo json_encode(['success' => false, 'message' => 'No driver ID provided']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM driver WHERE driver_id = ?");
$stmt->bind_param("i", $driver_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
?>
