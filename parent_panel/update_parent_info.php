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

    $stmt = $pdo->prepare("
        UPDATE parent 
        SET full_name = :full_name,
            phone = :phone,
            home_address = :home_address
        WHERE parent_id = :parent_id
    ");

    $stmt->execute([
        'full_name' => $_POST['full_name'],
        'phone' => $_POST['phone'],
        'home_address' => $_POST['home_address'],
        'parent_id' => $_SESSION['parent_id']
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 