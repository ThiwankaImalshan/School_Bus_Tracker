<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

$bus_id = $_GET['id'] ?? null;

if (!$bus_id) {
    echo json_encode(['error' => 'No bus ID provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM bus WHERE bus_id = ?");
    $stmt->execute([$bus_id]);
    $bus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bus) {
        echo json_encode($bus);
                        } else {
        echo json_encode(['error' => 'Bus not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>