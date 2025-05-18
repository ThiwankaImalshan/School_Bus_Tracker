<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$lastUpdate = isset($_POST['lastUpdate']) ? (int)($_POST['lastUpdate'] / 1000) : 0;
$route = $_POST['route'] ?? '';
$driver_id = $_SESSION['driver_id'];
$today = date('Y-m-d');

try {
    // Get driver's bus_id
    $stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $bus_id = $stmt->fetchColumn();

    // Get updated students since last check
    $stmt = $pdo->prepare("
        SELECT 
            c.child_id,
            c.first_name,
            c.last_name,
            c.grade,
            c.photo_url,
            c.emergency_contact,
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
    ");
    
    $stmt->execute([$bus_id, $today, $lastUpdate]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current counts
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'picked' THEN 1 ELSE 0 END) as picked_count,
            SUM(CASE WHEN status = 'drop' THEN 1 ELSE 0 END) as drop_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            COUNT(*) as total_count
        FROM attendance 
        WHERE attendance_date = ? 
        AND child_id IN (SELECT child_id FROM child WHERE bus_id = ?)
    ");
    
    $stmt->execute([$today, $bus_id]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'students' => $students,
        'counts' => [
            'present_count' => $route === 'morning' ? $counts['picked_count'] : $counts['drop_count'],
            'absent_count' => $counts['absent_count'],
            'total_assigned' => $counts['total_count']
        ],
        'timestamp' => time() * 1000
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
