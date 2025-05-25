let notificationCount = 0;
let lastCheckTime = new Date().getTime();

function initializeNotifications() {
    if (!('Notification' in window)) {
        console.log('Browser does not support notifications');
        return;
    }

    Notification.requestPermission();
    checkNewNotifications();
    setInterval(checkNewNotifications, 5000); // Check every 5 seconds
}

function checkNewNotifications() {
    fetch('check_attendance_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications.length > 0) {
                data.notifications.forEach(showNotification);
                updateNotificationCount(data.notifications.length);
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

function showNotification(notification) {
    // Create browser notification
    if (Notification.permission === 'granted') {
        new Notification('Attendance Update', {
            body: notification.message,
            icon: '../img/favicon/favicon-96x96.png'
        });
    }

    // Create toast notification
    const toast = document.createElement('div');
    toast.className = 'notification-item bg-white rounded-lg shadow-lg p-4 mb-4 border-l-4 border-blue-500';
    toast.innerHTML = `
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                <p class="text-sm text-gray-500">${notification.message}</p>
            </div>
        </div>
    `;

    document.getElementById('notificationContainer').appendChild(toast);

    // Remove toast after 5 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.5s forwards';
        setTimeout(() => toast.remove(), 500);
        markNotificationAsRead(notification.notification_id);
    }, 5000);
}

function updateNotificationCount(count) {
    notificationCount = count;
    const badge = document.getElementById('notification-count');
    badge.textContent = notificationCount;
    badge.classList.toggle('hidden', notificationCount === 0);
}

function markNotificationAsRead(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notification_id: notificationId })
    });
}
