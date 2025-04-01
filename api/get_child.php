<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in and is a parent
if (!isset($_SESSION['parent_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Child ID is required']);
    exit;
}

$child_id = intval($_GET['id']);
$parent_id = $_SESSION['parent_id'];

// Fetch child data ensuring it belongs to the logged-in parent
$sql = "SELECT c.*, s.school_name, r.route_number 
        FROM children c 
        LEFT JOIN schools s ON c.school_id = s.id
        LEFT JOIN bus_routes r ON c.route_id = r.id
        WHERE c.id = ? AND c.parent_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $child_id, $parent_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Child not found']);
    exit;
}

$child = $result->fetch_assoc();
echo json_encode($child);
?> 