<?php
// Start session
session_start();

// Check if parent is logged in
if (!isset($_SESSION['parent_id']) || !$_SESSION['logged_in']) {
    // Redirect to login page if not logged in
    header('Location: login.html');
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect back to dashboard if not POST request
    header('Location: dashboard.php');
    exit;
}

// Check if child_id is provided
if (!isset($_POST['child_id']) || empty($_POST['child_id'])) {
    $_SESSION['error_message'] = 'Child ID is required';
    header('Location: dashboard.php');
    exit;
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database connection failed';
    header('Location: dashboard.php');
    exit;
}

// Verify this child belongs to the logged-in parent
try {
    $checkStmt = $pdo->prepare("SELECT * FROM child WHERE child_id = :child_id AND parent_id = :parent_id");
    $checkStmt->execute([
        'child_id' => $_POST['child_id'],
        'parent_id' => $_SESSION['parent_id']
    ]);
    
    if ($checkStmt->rowCount() === 0) {
        $_SESSION['error_message'] = 'You do not have permission to update this child';
        header('Location: dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error verifying child ownership';
    header('Location: dashboard.php');
    exit;
}

// Sanitize input data
$childId = intval($_POST['child_id']);
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$grade = trim($_POST['grade'] ?? '');
$schoolId = !empty($_POST['school_id']) ? intval($_POST['school_id']) : null;
$busId = !empty($_POST['bus_id']) ? intval($_POST['bus_id']) : null;
$emergencyContact = trim($_POST['emergency_contact'] ?? '');
$medicalNotes = trim($_POST['medical_notes'] ?? '');

// Validate required fields
if (empty($firstName) || empty($lastName)) {
    $_SESSION['error_message'] = 'First name and last name are required';
    header('Location: dashboard.php');
    exit;
}

// Update child information
try {
    $updateStmt = $pdo->prepare("
        UPDATE child 
        SET first_name = :first_name,
            last_name = :last_name,
            grade = :grade,
            school_id = :school_id,
            bus_id = :bus_id,
            emergency_contact = :emergency_contact,
            medical_notes = :medical_notes
        WHERE child_id = :child_id AND parent_id = :parent_id
    ");
    
    $updateStmt->execute([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'grade' => $grade,
        'school_id' => $schoolId,
        'bus_id' => $busId,
        'emergency_contact' => $emergencyContact,
        'medical_notes' => $medicalNotes,
        'child_id' => $childId,
        'parent_id' => $_SESSION['parent_id']
    ]);
    
    if ($updateStmt->rowCount() > 0) {
        $_SESSION['success_message'] = 'Child information updated successfully';
    } else {
        $_SESSION['info_message'] = 'No changes were made';
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error updating child information: ' . $e->getMessage();
}

// Redirect back to dashboard
header('Location: dashboard.php');
exit; 