<?php
// session_start();
require_once 'conn.php';

// Get bus_id for the selected child
$child_id = $_SESSION['selected_child_id'] ?? null;
$stmt = $pdo->prepare("SELECT bus_id FROM child WHERE child_id = ?");
$stmt->execute([$child_id]);
$bus_id = $stmt->fetchColumn();
?>

<div id="chatContainer" class="fixed bottom-4 right-4 w-96 bg-white rounded-lg shadow-xl z-50">
    <!-- Chat Header -->
    <div class="bg-yellow-500 text-white p-4 rounded-t-lg flex justify-between items-center">
        <h3 class="font-semibold">Chat with Driver</h3>
        <button onclick="toggleChat()" class="focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Chat Messages -->
    <div id="chatMessages" class="h-96 overflow-y-auto p-4 space-y-4">
        <!-- Messages will be loaded here -->
    </div>

    <!-- Chat Input -->
    <div class="p-4 border-t">
        <form id="chatForm" class="flex space-x-2">
            <input type="hidden" id="busId" value="<?php echo $bus_id; ?>">
            <input type="text" id="messageInput" 
                   class="flex-1 border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-500" 
                   placeholder="Type a message...">
            <button type="submit" 
                    class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition-colors">
                Send
            </button>
        </form>
    </div>
</div>

<script>
let isVisible = true;

function toggleChat() {
    const container = document.getElementById('chatContainer');
    if (isVisible) {
        container.style.transform = 'translateY(calc(100% - 4rem))';
    } else {
        container.style.transform = 'translateY(0)';
    }
    isVisible = !isVisible;
}

// SSE for real-time messages
const evtSource = new EventSource('chat_sse.php');
evtSource.onmessage = function(event) {
    const message = JSON.parse(event.data);
    appendMessage(message);
};

function appendMessage(message) {
    const chatMessages = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `flex ${message.sender_type === 'parent' ? 'justify-end' : 'justify-start'}`;
    messageDiv.innerHTML = `
        <div class="${message.sender_type === 'parent' ? 
            'bg-yellow-100 text-yellow-800' : 
            'bg-gray-100 text-gray-800'} 
            rounded-lg px-4 py-2 max-w-[75%]">
            <p class="text-sm">${message.message}</p>
            <span class="text-xs text-gray-500">${new Date(message.created_at).toLocaleTimeString()}</span>
        </div>
    `;
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

document.getElementById('chatForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const message = document.getElementById('messageInput').value;
    const busId = document.getElementById('busId').value;

    try {
        const response = await fetch('send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message, bus_id: busId })
        });
        
        if (response.ok) {
            document.getElementById('messageInput').value = '';
        }
    } catch (error) {
        console.error('Error sending message:', error);
    }
});
</script>
