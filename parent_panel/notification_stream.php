<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['parent_id'])) {
    exit();
}

// Function to send SSE
function sendSSE($data) {
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Get parent's children
$stmt = $pdo->prepare("SELECT child_id FROM child WHERE parent_id = ?");
$stmt->execute([$_SESSION['parent_id']]);
$children = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($children)) {
    exit();
}

// Initialize notification cache
$notificationCache = [];
$lastCheck = date('Y-m-d H:i:s', strtotime('-1 minute'));

while (true) {
    // Check for new attendance changes
    $childIds = implode(',', $children);
    $query = "SELECT a.*, c.first_name, c.last_name 
              FROM attendance a 
              JOIN child c ON a.child_id = c.child_id 
              WHERE a.child_id IN ($childIds) 
              AND (a.status = 'picked' OR a.status = 'drop')
              AND (
                (a.status = 'picked' AND a.pickup_time > ?) 
                OR 
                (a.status = 'drop' AND a.drop_time > ?)
              )";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$lastCheck, $lastCheck]);
    $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($changes)) {
        foreach ($changes as $change) {
            // Create unique key for this notification
            $notificationKey = $change['child_id'] . '_' . $change['status'] . '_' . date('Y-m-d');
            
            // Only send if not already sent today
            if (!isset($notificationCache[$notificationKey])) {
                $notificationCache[$notificationKey] = time();
                
                $message = sprintf(
                    '%s %s has been %s',
                    $change['first_name'],
                    $change['last_name'],
                    $change['status'] === 'picked' ? 'picked up from school' : 'dropped off at school'
                );
                
                sendSSE([
                    'type' => 'attendance',
                    'status' => $change['status'],
                    'message' => $message,
                    'child_id' => $change['child_id'],
                    'timestamp' => date('h:i A')
                ]);
            }
        }
    }

    // Clean up old cache entries (older than 24 hours)
    foreach ($notificationCache as $key => $time) {
        if (time() - $time > 86400) {
            unset($notificationCache[$key]);
        }
    }

    $lastCheck = date('Y-m-d H:i:s');
    
    // Wait for 5 seconds before next check
    sleep(5);
    
    // Clear stat cache to prevent PHP from caching the connection status
    clearstatcache();
    
    // Check if client is still connected
    if (connection_status() != CONNECTION_NORMAL) {
        break;
    }
}
?>
