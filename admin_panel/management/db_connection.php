<?php
// Database connection parameters
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// Create DSN for PDO
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// Set PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Connect to database using PDO
try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // If connection fails, show error message
    die("Connection failed: " . $e->getMessage());
} 