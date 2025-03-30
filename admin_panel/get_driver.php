<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

$driver_id = $_GET['id'] ?? null;

if (!$driver_id) {
    echo json_encode(['error' => 'No driver ID provided']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM driver WHERE driver_id = ?");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

echo json_encode($driver);
?>
