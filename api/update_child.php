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

// Verify the child belongs to the parent
$check_sql = "SELECT id FROM children WHERE id = ? AND parent_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $child_id, $parent_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access to this child record']);
    exit;
}

// Update child information
$sql = "UPDATE children SET 
        name = ?, 
        grade = ?, 
        school_id = ?, 
        route_id = ?, 
        special_notes = ?
        WHERE id = ? AND parent_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "sisssii",
    $_POST['name'],
    $_POST['grade'],
    $_POST['school_id'],
    $_POST['route_id'],
    $_POST['special_notes'],
    $child_id,
    $parent_id
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update child details']);
}
?> 