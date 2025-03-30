<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

$driver_id = $_POST['driver_id'] ?? null;
$is_new = empty($driver_id);

if ($is_new) {
    $stmt = $conn->prepare("INSERT INTO driver (bus_id, full_name, email, phone, license_number, 
        license_expiry_date, experience_years, age, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_DATE)");
} else {
    $stmt = $conn->prepare("UPDATE driver SET bus_id=?, full_name=?, email=?, phone=?, license_number=?, 
        license_expiry_date=?, experience_years=?, age=? WHERE driver_id=?");
}

$bus_id = empty($_POST['bus_id']) ? null : $_POST['bus_id'];
$stmt->bind_param("isssssii" . ($is_new ? "" : "i"),
    $bus_id,
    $_POST['full_name'],
    $_POST['email'],
    $_POST['phone'],
    $_POST['license_number'],
    $_POST['license_expiry_date'],
    $_POST['experience_years'],
    $_POST['age'],
    $driver_id
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
?>
