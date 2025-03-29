<?php
// db_connection.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root';     // Replace with your actual database username
$password = '';     // Replace with your actual database password

try {
    // Create PDO connection with error mode set to exception
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ensure persistent connections
    $pdo->setAttribute(PDO::ATTR_PERSISTENT, true);
} catch (PDOException $e) {
    // Log detailed error information
    error_log('Database Connection Error: ' . $e->getMessage(), 3, 'db_connection_error.log');
    
    // Die with a generic error message (in production, you might want to handle this more gracefully)
    die("Database connection failed. Please check the logs.");
}