let lastNotificationTime = new Date().getTime();
let notificationTimer = null;
const shownNotifications = new Set();

function checkNotifications() {
    if (notificationTimer) {
        clearTimeout(notificationTimer);
    }

    fetch('check_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                const uniqueNotifications = Array.from(
                    new Map(data.notifications.map(n => [n.notification_id, n])).values()
                ).filter(notification => !shownNotifications.has(notification.notification_id.toString()));

                if (uniqueNotifications.length > 0) {
                    uniqueNotifications.forEach(notification => {
                        // Show only one type of notification (either browser or toast)
                        if (Notification.permission === 'granted') {
                            new Notification(notification.title, {
                                body: notification.message,
                                icon: '../img/favicon/favicon-96x96.png',
                                tag: notification.notification_id // Prevent duplicate browser notifications
                            });
                        } else {
                            showToast(notification);
                        }
                        
                        // Track and mark as read
                        markNotificationAsRead(notification.notification_id);
                        shownNotifications.add(notification.notification_id.toString());
                    });
                    updateNotificationBadge(uniqueNotifications.length);
                }
            }
        })
        .catch(error => console.error('Error:', error))
        .finally(() => {
            notificationTimer = setTimeout(checkNotifications, 10000);
        });
}

// Rename showNotification to showToast for clarity
function showToast(notification) {
    // Create notification element
    const toast = document.createElement('div');
    toast.className = 'notification-toast';
    toast.innerHTML = `
        <div class="fixed bottom-5 right-5 bg-white rounded-lg shadow-lg p-4 mb-4 border-l-4 border-orange-500 max-w-md">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                    <p class="text-sm text-gray-500">${notification.message}</p>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function markNotificationAsRead(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ notification_id: notificationId })
    });
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    if (badge) {
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
    }
}

// Remove all existing intervals and event listeners
window.addEventListener('load', () => {
    // Clear any existing timers
    if (notificationTimer) {
        clearTimeout(notificationTimer);
    }
    
    // Request notification permission and start checking
    if ('Notification' in window) {
        Notification.requestPermission().then(() => {
            checkNotifications();
        });
    } else {
        checkNotifications();
    }
});

// Clear shown notifications every hour but keep the timer running
setInterval(() => {
    shownNotifications.clear();
}, 3600000);
