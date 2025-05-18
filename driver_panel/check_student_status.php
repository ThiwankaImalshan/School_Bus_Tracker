<?php
session_start();
require_once 'db_connection.php';
date_default_timezone_set('Asia/Colombo');

header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$driver_id = $_SESSION['driver_id'];
$route_type = $_POST['route'] ?? '';
$lastUpdate = isset($_POST['lastUpdate']) ? (int)($_POST['lastUpdate'] / 1000) : 0;
$today = date('Y-m-d');

try {
    // Get bus_id for the driver
    $stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $bus_id = $stmt->fetchColumn();

    if (!$bus_id) throw new Exception('No bus assigned');

    // Get updated students since last check
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            s.name AS school_name,
            p.full_name AS parent_name,
            a.status,
            UNIX_TIMESTAMP(a.last_updated) as update_timestamp
        FROM attendance a
        JOIN child c ON a.child_id = c.child_id
        JOIN school s ON c.school_id = s.school_id
        JOIN parent p ON c.parent_id = p.parent_id
        WHERE c.bus_id = ?
        AND a.attendance_date = ?
        AND UNIX_TIMESTAMP(a.last_updated) > ?
        AND (
            (? = 'morning' AND a.status = 'picked') OR
            (? = 'evening' AND a.status IN ('picked', 'drop'))
        )
        ORDER BY a.last_updated DESC
    ");
    
    $stmt->execute([$bus_id, $today, $lastUpdate, $route_type, $route_type]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current counts
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            COUNT(*) as total
        FROM attendance a
        JOIN child c ON a.child_id = c.child_id
        WHERE a.attendance_date = ?
        AND c.bus_id = ?
    ");
    
    $status = $route_type === 'morning' ? 'picked' : 'drop';
    $stmt->execute([$status, $today, $bus_id]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'students' => $students,
        'counts' => $counts,
        'timestamp' => time() * 1000
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
