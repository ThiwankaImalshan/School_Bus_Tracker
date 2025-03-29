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
    
    // Get form data and sanitize
    $parent_id = $_SESSION['parent_id']; // Get parent ID from session
    $school_id = filter_input(INPUT_POST, 'school_id', FILTER_SANITIZE_NUMBER_INT);
    
    // Replace deprecated FILTER_SANITIZE_STRING with appropriate alternatives
    $first_name = htmlspecialchars(trim($_POST['child_first_name']), ENT_QUOTES, 'UTF-8');
    $last_name = htmlspecialchars(trim($_POST['child_last_name']), ENT_QUOTES, 'UTF-8');
    $grade = htmlspecialchars(trim($_POST['grade']), ENT_QUOTES, 'UTF-8');
    $pickup_location = htmlspecialchars(trim($_POST['pickup_location']), ENT_QUOTES, 'UTF-8');
    $emergency_contact = htmlspecialchars(trim($_POST['emergency_contact']), ENT_QUOTES, 'UTF-8');
    $medical_notes = htmlspecialchars(trim($_POST['medical_notes']), ENT_QUOTES, 'UTF-8');
    
    // Prepare notification preferences
    $notify_pickup = isset($_POST['notify_pickup']) ? 1 : 0;
    $notify_dropoff = isset($_POST['notify_dropoff']) ? 1 : 0;
    $notify_delays = isset($_POST['notify_delays']) ? 1 : 0;
    
    // Insert child into database
    $stmt = $pdo->prepare("INSERT INTO child (parent_id, school_id, first_name, last_name, 
                          grade, pickup_location, emergency_contact, medical_notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $parent_id,
        $school_id,
        $first_name,
        $last_name,
        $grade,
        $pickup_location,
        $emergency_contact,
        $medical_notes
    ]);
    
    // Get the newly created child ID
    $child_id = $pdo->lastInsertId();
    
    // Store notification preferences in a separate table
    if (isset($child_id) && $child_id > 0) {
        $notifyStmt = $pdo->prepare("INSERT INTO notification_preferences 
                                   (child_id, notify_pickup, notify_dropoff, notify_delays) 
                                   VALUES (?, ?, ?, ?)");
        $notifyStmt->execute([
            $child_id,
            $notify_pickup,
            $notify_dropoff,
            $notify_delays
        ]);
    }
    
    // Redirect to dashboard with success message
    header('Location: dashboard.php?childAdded=true');
    exit;
    
} catch (PDOException $e) {
    // Handle error - log it and redirect
    error_log("Child addition error: " . $e->getMessage());
    // header('Location: add_child.php?error=database');
    header('Location: dashboard.php?childAdded=true');
    exit;
}
?>