<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_code = $_POST['verification_code'];
    
    if (!isset($_SESSION['verification_code']) || 
        !isset($_SESSION['code_expiry']) || 
        !isset($_SESSION['reset_email'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired']);
        exit;
    }
    
    if (time() > $_SESSION['code_expiry']) {
        echo json_encode(['success' => false, 'message' => 'Verification code has expired']);
        exit;
    }
    
    if ($submitted_code === $_SESSION['verification_code']) {
        $_SESSION['code_verified'] = true;
        echo json_encode(['success' => true, 'message' => 'Code verified successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
