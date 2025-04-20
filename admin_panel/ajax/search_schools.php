<?php
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

try {
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    
    if (strlen($term) < 2) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT school_id, name, location_map 
                          FROM school 
                          WHERE name LIKE ? 
                          ORDER BY name 
                          LIMIT 5");
    $stmt->execute(['%' . $term . '%']);
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($schools);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Search failed']);
}
