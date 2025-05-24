<?php
session_start();
require_once '../includes/config.php';

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header('Location: login.php');
    exit;
}

$driver_id = $_SESSION['driver_id'];

// Get driver's bus information
$stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ?");
$stmt->execute([$driver_id]);
$driver = $stmt->fetch();

if (!$driver || !$driver['bus_id']) {
    echo "No bus assigned";
    exit;
}

// Update query to group children by parent
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        p.parent_id, 
        p.full_name,
        GROUP_CONCAT(c.first_name) as children_names,
        COUNT(c.child_id) as child_count,
        (SELECT COUNT(*) FROM chat_messages cm 
         WHERE cm.sender_type = 'parent' 
         AND cm.sender_id = p.parent_id 
         AND cm.is_read = 0) as unread_count
    FROM parent p 
    JOIN child c ON p.parent_id = c.parent_id
    WHERE c.bus_id = ?
    GROUP BY p.parent_id, p.full_name
    ORDER BY p.full_name
");
$stmt->execute([$driver['bus_id']]);
$parents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Chat</title>
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
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .heading-brown {
            color: #92400e;
        }
        .chat-bubble {
            position: relative;
            margin: 8px 0;
            max-width: 80%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .chat-bubble::after {
            content: '';
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            border: 8px solid transparent;
        }
        .chat-bubble-received::after {
            left: -16px;
            border-right-color: #f3f4f6;
        }
        .chat-bubble-sent::after {
            right: -16px;
            border-left-color: #f59e0b;
        }
        .parent-active {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
        }
        .shadow-enhanced {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .chat-container {
            height: calc(100vh - 12rem);
            max-height: 800px;
            min-height: 500px;
        }
        .chat-messages {
            height: calc(100% - 8rem);
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
        <div class="flex gap-4">
            <!-- Parents List -->
            <div class="w-1/4 glass-container shadow-enhanced chat-container">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold heading-brown">Children</h2>
                    <div class="mt-2 relative">
                        <input type="text" 
                               id="parent-search"
                               placeholder="Search child or parent..."
                               class="w-full px-3 py-2 rounded-lg border-gray-200 focus:border-yellow-500 focus:ring focus:ring-yellow-200 focus:ring-opacity-50">
                    </div>
                </div>
                <div id="parents-list" class="overflow-y-auto h-[calc(100%-6rem)]">
                    <?php foreach($parents as $parent): ?>
                    <button onclick="selectParent(<?php echo $parent['parent_id']; ?>, '<?php echo htmlspecialchars($parent['full_name']); ?>')"
                            data-parent-id="<?php echo $parent['parent_id']; ?>"
                            data-parent-name="<?php echo htmlspecialchars($parent['full_name']); ?>"
                            data-children-count="<?php echo $parent['child_count']; ?>"
                            class="parent-item w-full p-4 text-left hover:bg-gray-50 border-b border-gray-100 transition-colors">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                                <span class="text-yellow-800 font-medium"><?php echo substr($parent['full_name'], 0, 2); ?></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <div class="font-medium text-gray-800 truncate">
                                        <?php echo htmlspecialchars($parent['full_name']); ?>
                                    </div>
                                    <div class="unread-count-badge"></div>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo $parent['child_count']; ?> 
                                    <?php echo $parent['child_count'] > 1 ? 'Children' : 'Child'; ?>
                                </div>
                            </div>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="flex-1 glass-container shadow-enhanced chat-container flex flex-col">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div id="selected-parent-avatar" class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                            <span class="text-yellow-800">?</span>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold heading-brown" id="chat-title">Select a parent to start chatting</h2>
                            <p class="text-sm text-gray-500" id="chat-subtitle"></p>
                        </div>
                    </div>
                    <button id="clear-chat" 
                            onclick="clearChat()"
                            class="px-3 py-1 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors hidden">
                        Clear Chat
                    </button>
                </div>
                
                <div id="chat-messages" class="chat-messages overflow-y-auto p-4 space-y-2">
                    <div class="flex justify-center">
                        <span class="text-sm text-gray-500">Select a parent from the list to start chatting</span>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-200">
                    <form id="chat-form" onsubmit="return false;" class="flex items-end gap-2">
                        <div class="flex-1">
                            <textarea id="message-input"
                                   class="w-full rounded-lg border-gray-300 focus:border-yellow-500 focus:ring focus:ring-yellow-200 focus:ring-opacity-50 resize-none"
                                   placeholder="Type your message..."
                                   rows="2"
                                   disabled></textarea>
                        </div>
                        <button id="send-message"
                                class="px-6 py-3 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                                disabled>
                            <span>Send</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-white/80 backdrop-blur-sm text-gray-800 py-4 border-t border-gray-200">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; <?php echo date('Y'); ?> School Bus Tracking System - Sri Lanka</p>
        </div>
    </footer>

    <script>
        let currentParentId = null;
        
        function selectParent(parentId, parentName) {
            currentParentId = parentId;
            document.getElementById('chat-title').textContent = 'Chat with ' + parentName;
            document.getElementById('message-input').disabled = false;
            document.getElementById('send-message').disabled = false;
            document.getElementById('chat-messages').innerHTML = '';
            Chat.lastMessageId = 0;
            Chat.currentParentId = parentId;
            Chat.fetchMessages();
            document.getElementById('clear-chat').classList.remove('hidden');
        }

        function clearChat() {
            if (!currentParentId) return;
            
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

        // Enhanced parent and child search functionality
        document.getElementById('parent-search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.parent-item').forEach(item => {
                const parentName = item.querySelector('.font-medium').textContent.toLowerCase();
                const childInfo = item.querySelector('.text-gray-500').textContent.toLowerCase();
                // Extract child name from "Child: [name]" format
                const childName = childInfo.split(':')[1]?.trim().toLowerCase() || '';
                
                // Search both parent and child names
                if (parentName.includes(searchTerm) || childName.includes(searchTerm)) {
                    item.style.display = '';
                    // Highlight matching text if needed
                    if (searchTerm.length > 0) {
                        item.querySelector('.font-medium').classList.add('text-yellow-600');
                        item.querySelector('.text-gray-500').classList.add('text-yellow-600');
                    } else {
                        item.querySelector('.font-medium').classList.remove('text-yellow-600');
                        item.querySelector('.text-gray-500').classList.remove('text-yellow-600');
                    }
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Update search functionality with debounce
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Enhanced search function
        const searchParents = debounce(function(searchTerm) {
            searchTerm = searchTerm.toLowerCase();
            document.querySelectorAll('.parent-item').forEach(item => {
                const parentName = item.dataset.parentName.toLowerCase();
                const childrenCount = parseInt(item.dataset.childrenCount);
                let shouldShow = false;

                // Search by parent name
                if (parentName.includes(searchTerm)) {
                    shouldShow = true;
                    highlightText(item.querySelector('.font-medium'), parentName, searchTerm);
                }

                // Search by number of children (e.g., "2 children")
                const childrenText = `${childrenCount} ${childrenCount > 1 ? 'children' : 'child'}`.toLowerCase();
                if (childrenText.includes(searchTerm)) {
                    shouldShow = true;
                    highlightText(item.querySelector('.text-gray-500'), childrenText, searchTerm);
                }

                item.style.display = shouldShow ? '' : 'none';
            });
        }, 300);

        // Highlight matching text
        function highlightText(element, text, searchTerm) {
            if (!element) return;
            const highlightedText = text.replace(
                new RegExp(searchTerm, 'gi'),
                match => `<span class="bg-yellow-200">${match}</span>`
            );
            element.innerHTML = highlightedText;
        }

        // Update unread count badges
        function updateUnreadCounts() {
            fetch('../includes/chat_handler.php?action=get_unread_counts&bus_id=' + Chat.busId)
                .then(response => response.json())
                .then(data => {
                    document.querySelectorAll('.parent-item').forEach(item => {
                        const parentId = item.dataset.parentId;
                        const badge = item.querySelector('.unread-count-badge');
                        const count = data[parentId] || 0;
                        
                        if (count > 0) {
                            badge.innerHTML = `
                                <span class="bg-yellow-500 text-white text-xs px-2 py-1 rounded-full">
                                    ${count}
                                </span>
                            `;
                        } else {
                            badge.innerHTML = '';
                        }
                    });
                });
        }

        // Add event listeners
        document.getElementById('parent-search').addEventListener('input', (e) => {
            searchParents(e.target.value);
        });

        // Update unread counts periodically
        setInterval(updateUnreadCounts, 5000);
        updateUnreadCounts(); // Initial update

        // Handle enter key in message input
        document.getElementById('message-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('send-message').click();
            }
        });
    </script>
    <script src="../includes/chat.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Chat.init(<?php echo $driver['bus_id']; ?>, 'driver', <?php echo $driver_id; ?>);
        });
    </script>
</body>
</html>
