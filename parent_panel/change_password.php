<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify current password
    $stmt = $pdo->prepare("SELECT password_hash FROM parent WHERE parent_id = ?");
    $stmt->execute([$_SESSION['parent_id']]);
    $user = $stmt->fetch();

    if (!password_verify($_POST['current_password'], $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    // Update password
    $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE parent SET password_hash = ? WHERE parent_id = ?");
    $stmt->execute([$new_password_hash, $_SESSION['parent_id']]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 