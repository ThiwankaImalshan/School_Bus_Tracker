<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCode = $_POST['verification_code'] ?? '';
    $resetData = $_SESSION['reset_data'] ?? null;

    if (!$resetData) {
        $response['message'] = 'Reset session expired. Please try again.';
    } elseif (time() > $resetData['expires']) {
        $response['message'] = 'Verification code has expired. Please request a new one.';
        unset($_SESSION['reset_data']);
    } elseif ($submittedCode !== $resetData['code']) {
        $response['message'] = 'Invalid verification code.';
    } else {
        $response['success'] = true;
        $response['message'] = 'Code verified successfully.';
    }
}

echo json_encode($response);
