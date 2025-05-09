class NotificationHandler {
    constructor() {
        this.notificationCache = new Set();
        this.init();
    }

    init() {
        if ("Notification" in window) {
            Notification.requestPermission();
        }
        this.connectToEventStream();
    }

    connectToEventStream() {
        const eventSource = new EventSource('notification_stream.php');
        
        eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleNotification(data);
        };
        
        eventSource.onerror = () => {
            // Reconnect after 5 seconds if connection fails
            setTimeout(() => this.connectToEventStream(), 5000);
        };
    }

    handleNotification(data) {
        if (data.type !== 'attendance') return;

        const notificationKey = `${data.child_id}_${data.status}_${new Date().toDateString()}`;
        
        // Check if we've already shown this notification today
        if (this.notificationCache.has(notificationKey)) return;
        
        // Add to cache
        this.notificationCache.add(notificationKey);
        
        // Show notification if permitted
        if (Notification.permission === 'granted') {
            new Notification('School Bus Update', {
                body: data.message,
                icon: '../img/favicon/favicon.png',
                tag: notificationKey
            });
        }

        // Update UI
        this.updateAttendanceUI(data);
        
        // Play notification sound
        this.playNotificationSound();
    }

    updateAttendanceUI(data) {
        const attendanceElements = document.querySelectorAll(`[data-child-id="${data.child_id}"]`);
        
        attendanceElements.forEach(element => {
            const statusLabel = element.querySelector('.status-label');
            if (statusLabel) {
                statusLabel.textContent = data.status === 'picked' ? 'Picked up' : 'Dropped off';
                statusLabel.className = `status-label ${data.status === 'picked' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'} px-3 py-1 rounded-full text-sm font-medium`;
            }
            
            const timeLabel = element.querySelector('.time-label');
            if (timeLabel) {
                timeLabel.textContent = data.timestamp;
            }
        });
    }

    playNotificationSound() {
        const audio = new Audio('../sound/notification.mp3');
        audio.play().catch(e => console.log('Error playing notification sound:', e));
    }
}

// Initialize notification handler when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationHandler = new NotificationHandler();
});