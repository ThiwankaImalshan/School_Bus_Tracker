<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

try {
    $driver_id = $_SESSION['driver_id'];
    $today = date('Y-m-d');

    // Get driver's bus_id
    $stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $bus_id = $stmt->fetchColumn();

    if (!$bus_id) {
        throw new Exception('No bus assigned');
    }

    // Get all picked up students who haven't been dropped off
    $stmt = $pdo->prepare("
        SELECT 
            c.*, 
            s.name AS school_name, 
            p.full_name AS parent_name,
            a.status
        FROM attendance a
        JOIN child c ON a.child_id = c.child_id
        JOIN school s ON c.school_id = s.school_id
        JOIN parent p ON c.parent_id = p.parent_id
        WHERE a.attendance_date = ?
        AND a.status = 'picked'
        AND c.bus_id = ?
        ORDER BY c.first_name
    ");
    $stmt->execute([$today, $bus_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'students' => $students
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
