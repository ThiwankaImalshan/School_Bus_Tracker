<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data['action'] === 'send') {
        try {
            $stmt = $pdo->prepare("INSERT INTO chat_messages 
                (bus_id, sender_id, sender_type, message, receiver_id) 
                VALUES (?, ?, ?, ?, ?)");
                
            $result = $stmt->execute([
                $data['bus_id'],
                $data['sender_id'],
                $data['sender_type'],
                $data['message'],
                $data['receiver_id'] ?? null
            ]);
            
            if ($result) {
                // Get the inserted message details
                $stmt = $pdo->prepare("
                    SELECT m.*, 
                        CASE 
                            WHEN m.sender_type = 'parent' THEN p.full_name
                            WHEN m.sender_type = 'driver' THEN d.full_name
                        END as sender_name
                    FROM chat_messages m
                    LEFT JOIN parent p ON m.sender_type = 'parent' AND m.sender_id = p.parent_id
                    LEFT JOIN driver d ON m.sender_type = 'driver' AND m.sender_id = d.driver_id
                    WHERE m.id = LAST_INSERT_ID()
                ");
                $stmt->execute();
                $message = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'message' => $message]);
                exit;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error']);
            exit;
        }
    }

    // Add clear chat handler
    if ($data['action'] === 'clear') {
        try {
            $stmt = $pdo->prepare("
                DELETE FROM chat_messages 
                WHERE bus_id = ? 
                AND ((sender_id = ? AND sender_type = ?) 
                OR (receiver_id = ? AND sender_type != ?))
            ");
            
            $result = $stmt->execute([
                $data['bus_id'],
                $data['user_id'],
                $data['user_type'],
                $data['user_id'],
                $data['user_type']
            ]);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error']);
            exit;
        }
    }
}

// Handle message fetching with improved query
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch') {
    $bus_id = $_GET['bus_id'];
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    $user_type = $_GET['user_type'];
    $user_id = $_GET['user_id'];
    $parent_id = $_GET['parent_id'] ?? null;

    try {
        $query = "
            SELECT m.*, 
                CASE 
                    WHEN m.sender_type = 'parent' THEN p.full_name
                    WHEN m.sender_type = 'driver' THEN d.full_name
                END as sender_name
            FROM chat_messages m
            LEFT JOIN parent p ON m.sender_type = 'parent' AND m.sender_id = p.parent_id
            LEFT JOIN driver d ON m.sender_type = 'driver' AND m.sender_id = d.driver_id
            WHERE m.bus_id = ? AND m.id > ?
        ";

        $params = [$bus_id, $last_id];

        if ($user_type === 'parent') {
            $query .= " AND (m.sender_id = ? OR m.sender_type = 'driver')";
            $params[] = $user_id;
        } else {
            $query .= " AND ((m.sender_type = 'driver' AND m.sender_id = ?) 
                        OR (m.sender_type = 'parent' AND m.sender_id = ?))";
            $params[] = $user_id;
            $params[] = $parent_id;
        }

        $query .= " ORDER BY m.created_at ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
}

// Add new handler for getting unread counts
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['action'] === 'get_unread_counts') {
    $bus_id = $_GET['bus_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT sender_id, COUNT(*) as unread_count
            FROM chat_messages
            WHERE bus_id = ? 
            AND sender_type = 'parent'
            AND is_read = 0
            GROUP BY sender_id
        ");
        $stmt->execute([$bus_id]);
        
        $counts = [];
        while ($row = $stmt->fetch()) {
            $counts[$row['sender_id']] = (int)$row['unread_count'];
        }
        
        echo json_encode($counts);
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['error' => 'Database error']);
        exit;
    }
}

echo json_encode(['error' => 'Invalid request']);
