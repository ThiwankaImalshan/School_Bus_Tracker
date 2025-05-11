<?php
header('Content-Type: application/json');

if (!isset($_FILES['photo']) || !isset($_POST['child_id'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required data']));
}

$childId = $_POST['child_id'];
$file = $_FILES['photo'];

// Validate file
$allowed = ['image/jpeg', 'image/png', 'image/jpg'];
if (!in_array($file['type'], $allowed)) {
    die(json_encode(['success' => false, 'message' => 'Invalid file type']));
}

// Set up file path
$upload_dir = "../../img/child/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'student_' . $childId . '_' . uniqid() . '.' . $ext;
$filepath = $upload_dir . $filename;

// Upload file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Update database
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("UPDATE child SET photo_url = ? WHERE child_id = ?");
        $stmt->execute([$filename, $childId]);
        
        echo json_encode([
            'success' => true, 
            'filename' => $filename,
            'message' => 'Photo uploaded successfully'
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}
