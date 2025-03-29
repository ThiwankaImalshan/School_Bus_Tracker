<?php
// fetch_schools.php
header('Content-Type: application/json');

// Database connection details
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root';
$password = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Prepare and execute query to fetch schools
    $stmt = $pdo->query("SELECT school_id, name, location FROM school ORDER BY name");
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return schools as JSON
    echo json_encode($schools);
} catch(PDOException $e) {
    // Return error if connection fails
    echo json_encode([
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ]);
}