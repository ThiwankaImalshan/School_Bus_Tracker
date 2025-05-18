<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['driver_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$stmt = $pdo->prepare("SELECT MAX(last_updated) as current_update 
                       FROM attendance 
                       WHERE DATE(attendance_date) = CURDATE()");
$stmt->execute();
$currentUpdate = $stmt->fetch(PDO::FETCH_ASSOC)['current_update'];

// Get last known update from request
$lastKnownUpdate = $_GET['last_update'] ?? null;

header('Content-Type: application/json');
echo json_encode([
    'hasChanges' => $lastKnownUpdate !== $currentUpdate,
    'lastUpdate' => $currentUpdate
]);
