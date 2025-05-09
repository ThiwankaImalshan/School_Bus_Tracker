<?php
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['driver_id'])) {
    header('Location: login.php');
    exit;
}

$driver_id = $_SESSION['driver_id'];

// Get driver's bus_id
$stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ?");
$stmt->execute([$driver_id]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);
$bus_id = $driver['bus_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $morning_start = (int)$_POST['morning_start_hour'] * 60 + (int)$_POST['morning_start_minute'];
    $morning_end = (int)$_POST['morning_end_hour'] * 60 + (int)$_POST['morning_end_minute'];
    $evening_start = (int)$_POST['evening_start_hour'] * 60 + (int)$_POST['evening_start_minute'];
    $evening_end = (int)$_POST['evening_end_hour'] * 60 + (int)$_POST['evening_end_minute'];

    $stmt = $pdo->prepare("UPDATE route_settings SET 
        morning_start = ?, morning_end = ?, 
        evening_start = ?, evening_end = ?
        WHERE bus_id = ?");
    
    if ($stmt->execute([$morning_start, $morning_end, $evening_start, $evening_end, $bus_id])) {
        $_SESSION['success'] = "Route times updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update route times.";
    }
    
    header('Location: route_settings.php');
    exit;
}

// Get current settings
$stmt = $pdo->prepare("SELECT * FROM route_settings WHERE bus_id = ?");
$stmt->execute([$bus_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

function minutesToTime($minutes) {
    $hour = floor($minutes / 60);
    $minute = $minutes % 60;
    return ['hour' => $hour, 'minute' => $minute];
}

$morning_start = minutesToTime($settings['morning_start']);
$morning_end = minutesToTime($settings['morning_end']);
$evening_start = minutesToTime($settings['evening_start']);
$evening_end = minutesToTime($settings['evening_end']);
?>
