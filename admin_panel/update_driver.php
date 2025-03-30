<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get and sanitize input data
    $driver_id = mysqli_real_escape_string($conn, $_POST['driver_id']);
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $bus_id = $_POST['bus_id'] ? mysqli_real_escape_string($conn, $_POST['bus_id']) : null;
    $age = mysqli_real_escape_string($conn, $_POST['age']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $license_number = mysqli_real_escape_string($conn, $_POST['license_number']);
    $license_expiry_date = mysqli_real_escape_string($conn, $_POST['license_expiry_date']);
    $experience_years = mysqli_real_escape_string($conn, $_POST['experience_years']);

    // Check for duplicate email and license number
    $check_query = "SELECT driver_id FROM driver WHERE 
                    (email = '$email' OR license_number = '$license_number') 
                    AND driver_id != '$driver_id'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        throw new Exception('Email or license number already exists');
    }

    // Update query
    $query = "UPDATE driver SET 
        full_name = '$full_name',
        bus_id = " . ($bus_id ? "'$bus_id'" : "NULL") . ",
        age = '$age',
        email = '$email',
        phone = '$phone',
        license_number = '$license_number',
        license_expiry_date = '$license_expiry_date',
        experience_years = '$experience_years'
    WHERE driver_id = '$driver_id'";

    if (!mysqli_query($conn, $query)) {
        throw new Exception('Failed to update driver: ' . mysqli_error($conn));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Driver updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?> 