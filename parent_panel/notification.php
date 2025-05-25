<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <style>
        .notification-toast {
            z-index: 1000;
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        #notificationContainer {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
        }
        .notification-item {
            margin-top: 10px;
            opacity: 0;
            transform: translateX(100%);
            animation: slideIn 0.5s forwards;
        }
    </style>
</head>
<body>
    <!-- Notification Container -->
    <div id="notificationContainer"></div>
    
    <!-- Notification Count Badge -->
    <div id="notification-count" class="hidden fixed top-4 right-4 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs">0</div>
    
    <!-- Add notification badge to your existing header -->
    <div id="notification-badge" class="hidden absolute top-0 right-0 transform -translate-y-1/2 translate-x-1/2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
        0
    </div>

    <!-- Your existing dashboard content -->
    <!-- ...existing code... -->

    <!-- <script src="notification.js"></script> -->
    <script src="notifications.js"></script>
    <script>
    let lastCheck = 0;
    
    function initNotifications() {
        if (!('Notification' in window)) {
            console.log('This browser does not support notifications');
            return;
        }

        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                startNotificationPolling();
            }
        });
    }

    function startNotificationPolling() {
        setInterval(checkAttendanceChanges, 5000);
    }

    function checkAttendanceChanges() {
        fetch('check_attendance_changes.php')
            .then(response => response.json())
            .then(data => {
                if (data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(showNotification);
                }
            })
            .catch(error => console.error('Error checking notifications:', error));
    }

    function showNotification(notification) {
        // Browser notification
        if (Notification.permission === 'granted') {
            new Notification(notification.title, {
                body: notification.message,
                icon: '../img/favicon.png',
                tag: 'attendance-update'
            });
        }

        // Toast notification
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.innerHTML = `
            <div class="bg-white rounded-lg shadow-lg p-4 mb-4 border-l-4 border-orange-500">
                <h4 class="text-sm font-medium">${notification.title}</h4>
                <p class="text-sm text-gray-600">${notification.message}</p>
            </div>
        `;
        
        document.getElementById('notificationContainer').appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    // Initialize notifications when page loads
    document.addEventListener('DOMContentLoaded', initNotifications);
    </script>
</body>
</html>