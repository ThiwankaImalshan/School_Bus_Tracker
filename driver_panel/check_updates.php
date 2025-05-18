<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['driver_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$driver_id = $_SESSION['driver_id'];
$lastUpdate = isset($_POST['lastUpdate']) ? (int)$_POST['lastUpdate'] : 0;
$current_time = time();
$today = date('Y-m-d');

try {
    // Get driver's bus_id
    $stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $bus_id = $stmt->fetchColumn();

    if (!$bus_id) {
        throw new Exception('No bus assigned');
    }

    // Check for attendance changes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as changes
        FROM attendance a
        JOIN child c ON a.child_id = c.child_id
        WHERE c.bus_id = ? 
        AND a.attendance_date = ?
        AND UNIX_TIMESTAMP(a.last_updated) * 1000 > ?
    ");
    $stmt->execute([$bus_id, $today, $lastUpdate]);
    $hasChanges = $stmt->fetchColumn() > 0;

    $response = ['hasChanges' => $hasChanges, 'timestamp' => time() * 1000];

    if ($hasChanges) {
        // Get updated counts
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN status = 'picked' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                COUNT(*) as total_assigned
            FROM attendance a
            JOIN child c ON a.child_id = c.child_id
            WHERE c.bus_id = ? AND a.attendance_date = ?
        ");
        $stmt->execute([$bus_id, $today]);
        $response['counts'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get updated student cards HTML
        ob_start();
        include 'get_student_cards.php';
        $response['studentCards'] = [
            'picked' => ob_get_clean(),
            'toDrop' => ob_get_clean()
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
