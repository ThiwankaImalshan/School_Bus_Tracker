<?php
// session_start();
require_once 'db_connection.php';

// Get bus_id for the driver using the existing driver table
$driver_id = $_SESSION['driver_id'] ?? null;
if ($driver_id) {
    $stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ? AND bus_id IS NOT NULL");
    $stmt->execute([$driver_id]);
    $bus_id = $stmt->fetchColumn() ?: null;
} else {
    $bus_id = null;
}

// Check if bus_id exists
if (!$bus_id) {
    echo '<div class="text-red-500">Error: No active bus assignment found.</div>';
    exit;
}
?>

<div id="chatContainer" class="fixed bottom-4 right-4 w-96 bg-white rounded-lg shadow-xl z-50">
    <!-- Chat Header -->
    <div class="bg-yellow-500 text-white p-4 rounded-t-lg flex justify-between items-center">
        <h3 class="font-semibold">Chat with Parents</h3>
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
// ... Same JavaScript code as parent_panel/chat.php but change sender_type to 'driver' ...
</script>
