<?php
require_once 'db_connection.php';

class AttendanceNotificationHandler {
    private $pdo;
    private static $notificationCache = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function handleAttendanceChange($childId, $status) {
        // Create a unique key for this notification
        $notificationKey = $childId . '_' . $status . '_' . date('Y-m-d');
        
        // Check if this exact notification was already sent today
        if (isset(self::$notificationCache[$notificationKey])) {
            return null;
        }
        
        // Get child and parent information
        $stmt = $this->pdo->prepare("
            SELECT c.first_name, c.last_name, c.parent_id
            FROM child c
            WHERE c.child_id = ?
        ");
        $stmt->execute([$childId]);
        $childData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($childData) {
            // Store in cache to prevent duplicate notifications
            self::$notificationCache[$notificationKey] = time();
            
            // Format the notification message
            $action = $status === 'picked' ? 'picked up from school' : 'dropped off at school';
            $message = sprintf(
                '%s %s has been %s',
                $childData['first_name'],
                $childData['last_name'],
                $action
            );
            
            return [
                'parent_id' => $childData['parent_id'],
                'child_id' => $childId,
                'message' => $message,
                'status' => $status,
                'timestamp' => date('h:i A')
            ];
        }
        
        return null;
    }
}
?>