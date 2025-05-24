<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['parent_id'])) {
    header('Location: login.php');
    exit;
}

$parent_id = $_SESSION['parent_id'];

// Get children's bus and driver information
$stmt = $pdo->prepare("
    SELECT DISTINCT b.bus_id, b.bus_number, d.driver_id, d.full_name as driver_name 
    FROM child c
    JOIN bus b ON c.bus_id = b.bus_id
    JOIN driver d ON d.bus_id = b.bus_id
    WHERE c.parent_id = ?
");
$stmt->execute([$parent_id]);
$buses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Driver</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fbbf24 0%, #ea580c 100%);
            min-height: 100vh;
        }
        .glass-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .chat-message {
            border-radius: 1rem;
            padding: 0.75rem 1rem;
            margin: 0.5rem 0;
            max-width: 80%;
        }
        .chat-message.sent {
            background-color: #fef3c7;
            margin-left: auto;
            border-top-right-radius: 0.25rem;
        }
        .chat-message.received {
            background-color: #f3f4f6;
            margin-right: auto;
            border-top-left-radius: 0.25rem;
        }
        .chat-input {
            border-radius: 1rem;
            padding: 1rem;
            margin: 1rem;
            background: white;
        }
        .chat-header {
            border-top-left-radius: 1.5rem;
            border-top-right-radius: 1.5rem;
        }
        .chat-footer {
            position: sticky;
            bottom: 0;
            background: white;
            z-index: 10;
        }
        .message-form {
            opacity: 1;
            transition: all 0.3s ease;
        }
        .message-form:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .chat-container {
            height: calc(100vh - 12rem);
            max-height: 800px;
            min-height: 500px;
            display: flex;
            flex-direction: column;
        }
        .chat-messages {
            height: calc(100% - 8rem);
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: white;
        }
    </style>
</head>
<body class="min-h-screen">
    <nav class="bg-white/90 backdrop-blur-sm text-gray-800 shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-bold text-yellow-900">Safe To School</h1>
            </div>
            <div class="flex items-center space-x-6">
                <a href="dashboard.php" class="bg-yellow-900 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition duration-300 shadow-md hover:shadow-lg">Dashboard</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        <div class="glass-container shadow-enhanced chat-container flex flex-col">
            <?php if (count($buses) > 1): ?>
            <div class="p-4 border-b border-gray-200">
                <label class="block text-sm font-medium text-gray-700 mb-1">Select Bus</label>
                <select id="bus-select" class="w-full rounded-lg border-gray-300 focus:border-yellow-500 focus:ring focus:ring-yellow-200 focus:ring-opacity-50">
                    <?php foreach ($buses as $bus): ?>
                    <option value="<?php echo $bus['bus_id']; ?>" 
                            data-driver="<?php echo htmlspecialchars($bus['driver_name']); ?>">
                        Bus <?php echo htmlspecialchars($bus['bus_number']); ?> - 
                        Driver: <?php echo htmlspecialchars($bus['driver_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Update the chat header -->
            <div class="p-4 border-b border-gray-200 flex items-center justify-between bg-white">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                        <span class="text-yellow-800 font-medium">
                            <?php echo substr($buses[0]['driver_name'], 0, 2); ?>
                        </span>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold heading-brown" id="chat-title">
                            Chat with <?php echo htmlspecialchars($buses[0]['driver_name']); ?>
                        </h2>
                        <p class="text-sm text-gray-500">Bus <?php echo htmlspecialchars($buses[0]['bus_number']); ?></p>
                    </div>
                </div>
                <button id="clear-chat" 
                        onclick="clearChat()"
                        class="px-3 py-1 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors">
                    Clear Chat
                </button>
            </div>
            
            <div id="chat-messages" class="chat-messages flex-1 overflow-y-auto p-4 space-y-2 bg-white">
                <div class="flex justify-center">
                    <span class="text-sm text-gray-500">Start chatting with your bus driver</span>
                </div>
            </div>

            <div class="chat-footer p-4 border-t border-gray-200">
                <form id="chat-form" onsubmit="return false;" class="message-form flex items-end gap-2">
                    <div class="flex-1">
                        <textarea id="message-input"
                               class="w-full rounded-lg border-gray-300 focus:border-yellow-500 focus:ring focus:ring-yellow-200 focus:ring-opacity-50 resize-none"
                               placeholder="Type your message..."
                               rows="2"></textarea>
                    </div>
                    <button id="send-message"
                            class="px-6 py-3 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors flex items-center gap-2">
                        <span>Send</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </main>

    <footer class="bg-white/80 backdrop-blur-sm text-gray-800 py-4 border-t border-gray-200">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; <?php echo date('Y'); ?> School Bus Tracking System - Sri Lanka</p>
        </div>
    </footer>

    <script src="../includes/chat.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const firstBus = <?php echo $buses[0]['bus_id']; ?>;
            Chat.init(firstBus, 'parent', <?php echo $parent_id; ?>);

            const busSelect = document.getElementById('bus-select');
            if (busSelect) {
                busSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const driverName = selectedOption.dataset.driver;
                    
                    document.getElementById('chat-title').textContent = 'Chat with ' + driverName;
                    document.getElementById('chat-messages').innerHTML = '';
                    Chat.busId = this.value;
                    Chat.lastMessageId = 0;
                    Chat.fetchMessages();
                });
            }
        });

        // Handle enter key in message input
        document.getElementById('message-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('send-message').click();
            }
        });

        function clearChat() {
            if (confirm('Are you sure you want to clear all messages? This cannot be undone.')) {
                fetch('../includes/chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'clear',
                        bus_id: Chat.busId,
                        user_id: Chat.userId,
                        user_type: Chat.userType
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('chat-messages').innerHTML = '';
                        Chat.lastMessageId = 0;
                    }
                })
                .catch(error => console.error('Error clearing chat:', error));
            }
        }
    </script>
</body>
</html>
