<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['reset_token']) || !isset($_SESSION['reset_token']['email'])) {
        $response['message'] = 'Invalid reset session';
        echo json_encode($response);
        exit;
    }

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $email = $_SESSION['reset_token']['email'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_new_password'];

        if ($newPassword !== $confirmPassword) {
            $response['message'] = 'Passwords do not match';
            echo json_encode($response);
            exit;
        }

        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE parent SET password_hash = ? WHERE email = ?");
        $stmt->execute([$passwordHash, $email]);

        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Password updated successfully';
            $response['step'] = 4;
            
            // Clear reset session
            unset($_SESSION['reset_token']);
            unset($_SESSION['verification_code']);
        } else {
            $response['message'] = 'Error updating password';
        }

    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

echo json_encode($response);
