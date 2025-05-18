<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$driver_id = $_SESSION['driver_id'];
$route_type = $_POST['route_type'] ?? '';
$today = date('Y-m-d');

try {
    // Get bus_id
    $stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $bus_id = $stmt->fetchColumn();

    if (!$bus_id) {
        throw new Exception('No bus assigned');
    }

    // Get students based on route type
    $stmt = $pdo->prepare("
        SELECT 
            c.*, 
            s.name AS school_name,
            p.full_name AS parent_name,
            a.status,
            a.pickup_time,
            a.drop_time
        FROM attendance a
        JOIN child c ON a.child_id = c.child_id
        JOIN school s ON c.school_id = s.school_id
        JOIN parent p ON c.parent_id = p.parent_id
        WHERE a.attendance_date = ?
        AND c.bus_id = ?
        AND (
            (? = 'morning' AND a.status = 'picked')
            OR
            (? = 'evening' AND a.status = 'picked')
        )
        ORDER BY c.first_name
    ");
    
    $stmt->execute([$today, $bus_id, $route_type, $route_type]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get counts
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE 
                WHEN ? = 'morning' AND status = 'picked' THEN 1
                WHEN ? = 'evening' AND status = 'drop' THEN 1
                ELSE 0 
            END) as present_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            COUNT(*) as total_assigned
        FROM attendance
        WHERE attendance_date = ?
        AND child_id IN (SELECT child_id FROM child WHERE bus_id = ?)
    ");
    
    $stmt->execute([$route_type, $route_type, $today, $bus_id]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'students' => $students,
        'counts' => $counts
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
