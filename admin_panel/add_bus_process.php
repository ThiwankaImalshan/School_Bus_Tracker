<?php
// Start session
session_start();

// Database connection
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Validate input
    $bus_number = trim($_POST['bus_number']);
    $license_plate = trim($_POST['license_plate']);
    $seat_capacity = intval($_POST['seat_capacity']);
    $starting_location = trim($_POST['starting_location']);
    $covering_cities = trim($_POST['covering_cities']);
    $schools = isset($_POST['schools']) ? $_POST['schools'] : [];
    
    // Validate required fields
    if (empty($bus_number) || empty($license_plate) || $seat_capacity <= 0) {
        throw new Exception("Missing or invalid required fields");
    }
    
    // Insert bus details
    $busStmt = $pdo->prepare("INSERT INTO bus (
        bus_number, 
        license_plate, 
        capacity, 
        is_active, 
        starting_location, 
        covering_cities
    ) VALUES (?, ?, ?, 1, ?, ?)");
    
    $busStmt->execute([
        $bus_number, 
        $license_plate, 
        $seat_capacity, 
        $starting_location, 
        $covering_cities
    ]);
    
    // Get the newly inserted bus ID
    $bus_id = $pdo->lastInsertId();
    
    // Insert bus schools
    if (!empty($schools)) {
        $busSchoolStmt = $pdo->prepare("INSERT INTO bus_school (bus_id, school_id) VALUES (?, ?)");
        foreach ($schools as $school_id) {
            $busSchoolStmt->execute([$bus_id, $school_id]);
        }
    }
    
    // Generate bus seats
    $seatStmt = $pdo->prepare("INSERT INTO bus_seat (bus_id, seat_number, seat_type) VALUES (?, ?, ?)");
    $seat_types = ['window', 'middle', 'aisle'];
    
    for ($i = 1; $i <= $seat_capacity; $i++) {
        // Alternate seat types
        $seat_type = $seat_types[($i - 1) % 3];
        $seat_number = 'Seat ' . $i;
        
        $seatStmt->execute([
            $bus_id, 
            $seat_number, 
            $seat_type
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Redirect with success message
    header('Location: dashboard.php?success=1');
    exit;
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error or handle appropriately
    error_log("Database error: " . $e->getMessage());
    
    // Redirect with error message
    header('Location: add_bus.php?error=' . urlencode($e->getMessage()));
    exit;
} catch (Exception $e) {
    // Handle other exceptions
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error or handle appropriately
    error_log("Error: " . $e->getMessage());
    
    // Redirect with error message
    header('Location: add_bus.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>