<?php
// Set proper JSON headers first, before any output
header('Content-Type: application/json');

// Enable error reporting but log to file instead of output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

try {
    require_once 'db_connection.php';

    if (!isset($_GET['id'])) {
        throw new Exception('Driver ID is required');
    }

    $driver_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    $query = "SELECT * FROM driver WHERE driver_id = '$driver_id'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        throw new Exception('Database query failed: ' . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) > 0) {
        $driver = mysqli_fetch_assoc($result);
        echo json_encode([
            'success' => true,
            'driver' => $driver
        ]);
    } else {
        throw new Exception('Driver not found');
    }

} catch (Exception $e) {
    error_log($e->getMessage());
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