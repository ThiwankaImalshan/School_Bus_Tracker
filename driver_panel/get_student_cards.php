<?php
if (!isset($bus_id) || !isset($today)) {
    exit;
}

// Get picked up students for morning route
$stmt = $pdo->prepare("
    SELECT c.*, s.name AS school_name, p.full_name AS parent_name, a.status 
    FROM attendance a
    JOIN child c ON a.child_id = c.child_id
    JOIN school s ON c.school_id = s.school_id
    JOIN parent p ON c.parent_id = p.parent_id
    WHERE a.attendance_date = ? 
    AND a.status = 'picked'
    AND c.bus_id = ?
");
$stmt->execute([$today, $bus_id]);
$students = $stmt->fetchAll();

foreach ($students as $student) {
    $photoUrl = !empty($student['photo_url']) ? 
        "../img/child/" . $student['photo_url'] : 
        "../img/default-avatar.png";
    
    include 'student_card_template.php';
}
