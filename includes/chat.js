const Chat = {
    pollInterval: 3000,
    lastMessageId: 0,
    busId: null,
    userType: null,
    userId: null,
    currentParentId: null,

    init(busId, userType, userId) {
        this.busId = busId;
        this.userType = userType;
        this.userId = userId;
        this.setupEventListeners();
        this.startRealtimePolling();
    },

    setupEventListeners() {
        const sendBtn = document.getElementById('send-message');
        const messageInput = document.getElementById('message-input');

        sendBtn?.addEventListener('click', () => this.sendMessage());
        messageInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
    },

    async sendMessage() {
        const input = document.getElementById('message-input');
        const message = input.value.trim();
        if (!message) return;

        const messageData = {
            action: 'send',
            message: message,
            bus_id: this.busId,
            sender_type: this.userType,
            sender_id: this.userId,
            receiver_id: this.currentParentId
        };

        try {
            const response = await fetch('../includes/chat_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(messageData)
            });

            const result = await response.json();
            
            if (result.success) {
                input.value = '';
                // Immediately add message to chat
                this.addMessageToChat({
                    id: result.message.id,
                    message: messageData.message,
                    sender_type: messageData.sender_type,
                    sender_id: messageData.sender_id,
                    sender_name: 'You',
                    created_at: new Date().toISOString()
                });
                this.scrollToBottom();
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    },

    addMessageToChat(message) {
        const chatContainer = document.getElementById('chat-messages');
        const isOwnMessage = message.sender_type === this.userType && 
                           parseInt(message.sender_id) === parseInt(this.userId);

        if (!document.querySelector(`[data-message-id="${message.id}"]`)) {
            const messageHtml = `
                <div class="flex ${isOwnMessage ? 'justify-end' : 'justify-start'}" data-message-id="${message.id}">
                    <div class="chat-bubble ${isOwnMessage ? 'chat-bubble-sent bg-yellow-100' : 'chat-bubble-received bg-gray-100'} 
                                rounded-lg px-4 py-2 shadow-sm">
                        <div class="text-sm ${isOwnMessage ? 'text-yellow-800' : 'text-gray-600'}">${message.sender_name}</div>
                        <div class="break-words mt-1">${message.message}</div>
                        <div class="text-xs ${isOwnMessage ? 'text-yellow-600' : 'text-gray-400'} mt-1">
                            ${new Date(message.created_at).toLocaleTimeString()}
                        </div>
                    </div>
                </div>
            `;
            chatContainer.insertAdjacentHTML('beforeend', messageHtml);
            this.scrollToBottom();
        }
    },

    startRealtimePolling() {
        // Initial fetch
        this.fetchMessages();
        
        // Poll every 3 seconds
        setInterval(() => {
            if (this.userType === 'driver' && !this.currentParentId) return;
            this.fetchMessages();
        }, 3000);
    },

    async fetchMessages() {
        try {
            const params = new URLSearchParams({
                action: 'fetch',
                bus_id: this.busId,
                last_id: this.lastMessageId,
                user_type: this.userType,
                user_id: this.userId,
                parent_id: this.currentParentId || ''
            });

            const response = await fetch(`../includes/chat_handler.php?${params}`);
            const data = await response.json();

            if (data.messages?.length) {
                data.messages.forEach(msg => {
                    this.addMessageToChat(msg);
                    this.lastMessageId = Math.max(this.lastMessageId, msg.id);
                });
            }
        } catch (error) {
            console.error('Error fetching messages:', error);
        }
    },

    scrollToBottom() {
        const chatContainer = document.getElementById('chat-messages');
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
};
