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
        $_SESSION['error_message'] = 'You do not have permission to delete this child';
        header('Location: dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error verifying child ownership';
    header('Location: dashboard.php');
    exit;
}

// Start transaction
$pdo->beginTransaction();

try {
    // Delete child reservations first (foreign key constraint)
    $deleteReservationsStmt = $pdo->prepare("
        DELETE FROM child_reservation 
        WHERE child_id = :child_id
    ");
    $deleteReservationsStmt->execute(['child_id' => $_POST['child_id']]);
    
    // Delete child attendance records (foreign key constraint)
    $deleteAttendanceStmt = $pdo->prepare("
        DELETE FROM attendance 
        WHERE child_id = :child_id
    ");
    $deleteAttendanceStmt->execute(['child_id' => $_POST['child_id']]);
    
    // Delete child record
    $deleteChildStmt = $pdo->prepare("
        DELETE FROM child 
        WHERE child_id = :child_id AND parent_id = :parent_id
    ");
    
    $deleteChildStmt->execute([
        'child_id' => $_POST['child_id'],
        'parent_id' => $_SESSION['parent_id']
    ]);
    
    if ($deleteChildStmt->rowCount() > 0) {
        // Commit transaction
        $pdo->commit();
        $_SESSION['success_message'] = 'Child deleted successfully';
    } else {
        // Rollback transaction
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Failed to delete child';
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error deleting child: ' . $e->getMessage();
}

// Redirect back to dashboard
header('Location: dashboard.php');
exit; 