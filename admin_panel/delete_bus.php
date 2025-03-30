<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

$data = json_decode(file_get_contents('php://input'), true);
$bus_id = $data['bus_id'] ?? null;

if (!$bus_id) {
    echo json_encode(['success' => false, 'message' => 'No bus ID provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM bus WHERE bus_id = ?");
    $result = $stmt->execute([$bus_id]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete bus']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>