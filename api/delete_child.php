<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in and is a parent
if (!isset($_SESSION['parent_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['child_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Child ID is required']);
    exit;
}

$child_id = intval($_POST['child_id']);
$parent_id = $_SESSION['parent_id'];

// Verify the child belongs to the parent before deletion
$sql = "DELETE FROM children WHERE id = ? AND parent_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $child_id, $parent_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Failed to delete child or unauthorized access']);
}
?> 