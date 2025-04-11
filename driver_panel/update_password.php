<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['reset_data'])) {
        $response['message'] = 'Invalid reset session';
        echo json_encode($response);
        exit;
    }

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $driverId = $_SESSION['reset_data']['driver_id'];
        $newPassword = $_POST['new_password'];
        
        // Validate password strength
        if (strlen($newPassword) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }

        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE driver SET password_hash = ? WHERE driver_id = ?");
        $stmt->execute([$passwordHash, $driverId]);

        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Password updated successfully';
            
            // Clear reset session
            unset($_SESSION['reset_data']);
        } else {
            $response['message'] = 'Error updating password';
        }

    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
        error_log('Password update error: ' . $e->getMessage());
    }
}

echo json_encode($response);
