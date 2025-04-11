<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCode = $_POST['verification_code'] ?? '';
    $storedCode = $_SESSION['verification_code'] ?? '';

    if ($submittedCode === $storedCode) {
        $response['success'] = true;
        $response['message'] = 'Verification successful';
        $response['step'] = 3;
    } else {
        $response['message'] = 'Invalid verification code';
    }
}

echo json_encode($response);
