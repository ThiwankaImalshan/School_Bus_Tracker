<?php
header('Content-Type: application/json');

if (!isset($_FILES['photo']) || !isset($_POST['child_id'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required data']));
}

$childId = $_POST['child_id'];
$file = $_FILES['photo'];
$uploadDir = "../../img/child/";

// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Validate file
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
if (!in_array($file['type'], $allowedTypes)) {
    die(json_encode(['success' => false, 'message' => 'Invalid file type']));
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'card_' . uniqid() . '.' . $ext;
$uploadPath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // Update database
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("UPDATE child SET photo_url = ? WHERE child_id = ?");
        $stmt->execute([$filename, $childId]);
        
        echo json_encode(['success' => true, 'filename' => $filename]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}
