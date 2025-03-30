<?php
// Add this at the top of db_connection.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root';
$password = '';

// Create mysqli connection
$conn = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Set charset to utf8mb4
mysqli_set_charset($conn, 'utf8mb4');

// Optional: Create PDO connection if needed
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("PDO connection failed: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}
?>