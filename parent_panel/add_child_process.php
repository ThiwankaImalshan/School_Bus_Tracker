<?php
// Start session
session_start();

// Check if parent is logged in
if (!isset($_SESSION['parent_id']) || !$_SESSION['logged_in']) {
    // Redirect to login page if not logged in
    header('Location: login.html');
    exit;
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add_child.php');
    exit;
}

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
    
    // Insert child information
    $stmt = $pdo->prepare("
        INSERT INTO child (
            parent_id, school_id, bus_id, first_name, last_name,
            grade, pickup_location, medical_notes, emergency_contact
        ) VALUES (
            :parent_id, :school_id, :bus_id, :first_name, :last_name,
            :grade, :pickup_location, :medical_notes, :emergency_contact
        )
    ");
    
    $stmt->execute([
        'parent_id' => $_SESSION['parent_id'],
        'school_id' => $_POST['school_id'],
        'bus_id' => $_POST['bus_id'],
        'first_name' => $_POST['child_first_name'],
        'last_name' => $_POST['child_last_name'],
        'grade' => $_POST['grade'],
        'pickup_location' => $_POST['pickup_location'],
        'medical_notes' => $_POST['medical_notes'] ?? '',
        'emergency_contact' => $_POST['emergency_contact']
    ]);
    
    $child_id = $pdo->lastInsertId();
    
    // Find an available seat that isn't reserved
    $seatStmt = $pdo->prepare("
        SELECT bs.seat_id
        FROM bus_seat bs
        LEFT JOIN child_reservation cr ON bs.seat_id = cr.seat_id AND cr.is_active = 1
        WHERE bs.bus_id = :bus_id 
        AND bs.is_reserved = 0
        AND cr.seat_id IS NULL
        LIMIT 1
    ");
    
    $seatStmt->execute(['bus_id' => $_POST['bus_id']]);
    $seat = $seatStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($seat) {
        // Update bus_seat table to mark the seat as reserved
        $updateSeatStmt = $pdo->prepare("
            UPDATE bus_seat 
            SET is_reserved = 1 
            WHERE seat_id = :seat_id
        ");
        
        $updateSeatStmt->execute([
            'seat_id' => $seat['seat_id']
        ]);
        
        // Create seat reservation in child_reservation table
        $reserveStmt = $pdo->prepare("
            INSERT INTO child_reservation (
                seat_id, child_id, reservation_date, is_active
            ) VALUES (
                :seat_id, :child_id, CURDATE(), 1
            )
        ");
        
        $reserveStmt->execute([
            'seat_id' => $seat['seat_id'],
            'child_id' => $child_id
        ]);
        
        $pdo->commit();
        header('Location: dashboard.php?success=1');
    } else {
        // No available seats
        $pdo->rollBack();
        header('Location: add_child.php?error=' . urlencode('No available seats in this bus'));
        exit;
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    header('Location: add_child.php?error=' . urlencode($e->getMessage()));
}
?>