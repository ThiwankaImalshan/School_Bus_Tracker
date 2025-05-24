const Chat = {
    pollInterval: 3000, // Poll every 3 seconds
    lastMessageId: 0,
    busId: null,
    userType: null,
    userId: null,

    init(busId, userType, userId) {
        this.busId = busId;
        this.userType = userType;
        this.userId = userId;
        this.setupEventListeners();
        this.startPolling();
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

        try {
            const response = await fetch('chat_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'send',
                    message: message,
                    bus_id: this.busId,
                    sender_type: this.userType,
                    sender_id: this.userId
                })
            });

            if (response.ok) {
                input.value = '';
                await this.fetchMessages();
                this.scrollToBottom();
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    },

    async fetchMessages() {
        try {
            const response = await fetch(`chat_handler.php?action=fetch&bus_id=${this.busId}&last_id=${this.lastMessageId}`);
            const data = await response.json();

            if (data.messages?.length) {
                this.lastMessageId = data.messages[data.messages.length - 1].id;
                this.updateChat(data.messages);
            }
        } catch (error) {
            console.error('Error fetching messages:', error);
        }
    },

    updateChat(messages) {
        const chatContainer = document.getElementById('chat-messages');
        let html = '';

        messages.forEach(msg => {
            const isOwnMessage = msg.sender_type === this.userType && msg.sender_id === this.userId;
            html += `
                <div class="flex ${isOwnMessage ? 'justify-end' : 'justify-start'} mb-3">
                    <div class="max-w-[70%] ${isOwnMessage ? 'bg-yellow-500 text-white' : 'bg-gray-100'} rounded-lg px-4 py-2 shadow">
                        <div class="text-sm ${isOwnMessage ? 'text-yellow-50' : 'text-gray-500'}">${msg.sender_name}</div>
                        <div class="break-words">${msg.message}</div>
                        <div class="text-xs ${isOwnMessage ? 'text-yellow-100' : 'text-gray-400'} mt-1">
                            ${new Date(msg.created_at).toLocaleTimeString()}
                        </div>
                    </div>
                </div>
            `;
        });

        if (html) {
            chatContainer.insertAdjacentHTML('beforeend', html);
            this.scrollToBottom();
        }
    },

    scrollToBottom() {
        const chatContainer = document.getElementById('chat-messages');
        chatContainer.scrollTop = chatContainer.scrollHeight;
    },

    startPolling() {
        this.fetchMessages();
        setInterval(() => this.fetchMessages(), this.pollInterval);
    }
};
