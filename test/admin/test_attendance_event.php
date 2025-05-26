<?php
require_once '../config/database.php';

try {
    echo "<h2>Event Test Results</h2>";

    // 1. Check Event Status
    $eventStatus = $pdo->query("SHOW EVENTS WHERE Name = 'create_daily_attendance'")->fetch(PDO::FETCH_ASSOC);
    echo "<h3>1. Event Configuration:</h3>";
    echo "Event exists: " . ($eventStatus ? "Yes" : "No") . "<br>";
    if ($eventStatus) {
        echo "Status: " . $eventStatus['Status'] . "<br>";
        // Calculate next execution time (tomorrow at 5 AM)
        $nextExecution = date('Y-m-d H:i:s', strtotime('tomorrow 05:00:00'));
        echo "Next execution: " . $nextExecution . "<br>";
    }

    // 2. Check Today's Records
    $today = date('Y-m-d');
    $recordsQuery = $pdo->prepare("
        SELECT COUNT(*) as count, 
               MIN(updated_at) as first_record,
               MAX(updated_at) as last_record
        FROM attendance 
        WHERE DATE(attendance_date) = ?
    ");
    $recordsQuery->execute([$today]);
    $records = $recordsQuery->fetch(PDO::FETCH_ASSOC);

    echo "<h3>2. Today's Attendance Records:</h3>";
    echo "Total records created today: " . $records['count'] . "<br>";
    echo "First record created at: " . $records['first_record'] . "<br>";
    echo "Last record created at: " . $records['last_record'] . "<br>";

    // 3. Check Missing Records
    $missingQuery = $pdo->prepare("
        SELECT c.child_id, c.first_name, c.last_name 
        FROM child c 
        LEFT JOIN attendance a ON c.child_id = a.child_id 
            AND DATE(a.attendance_date) = ?
        WHERE a.attendance_id IS NULL
    ");
    $missingQuery->execute([$today]);
    $missingRecords = $missingQuery->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>3. Missing Attendance Records:</h3>";
    if (empty($missingRecords)) {
        echo "All students have attendance records for today.<br>";
    } else {
        echo "Students missing attendance records:<br>";
        foreach ($missingRecords as $student) {
            echo "- " . $student['first_name'] . " " . $student['last_name'] . "<br>";
        }
    }

    // 4. Trigger Manual Creation
    if (!empty($missingRecords)) {
        echo "<br><form method='post' action='create_missing_attendance.php'>";
        echo "<button type='submit' style='padding:10px; background:#ffb73d; color:white; border:none; border-radius:5px;'>";
        echo "Create Missing Records Now</button></form>";
    }

} catch (PDOException $e) {
    echo "<h3>Error:</h3>";
    echo "Database error: " . htmlspecialchars($e->getMessage());
}
