<?php
// Start session
session_start();

// Check if parent is logged in
if (!isset($_SESSION['parent_id']) || !$_SESSION['logged_in']) {
    // Redirect to login page if not logged in
    header('Location: login.html');
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get parent's children
$stmt = $pdo->prepare("SELECT c.child_id, c.first_name, c.last_name, c.grade, s.name as school_name, 
                              b.bus_number, c.pickup_location, c.photo_url 
                       FROM child c 
                       LEFT JOIN school s ON c.school_id = s.school_id 
                       LEFT JOIN bus b ON c.bus_id = b.bus_id 
                       WHERE c.parent_id = ?");
$stmt->execute([$_SESSION['parent_id']]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent notifications for the parent
$notifStmt = $pdo->prepare("SELECT notification_id, title, message, sent_at, notification_type, is_read 
                           FROM notification 
                           WHERE recipient_type = 'parent' AND recipient_id = ? 
                           ORDER BY sent_at DESC LIMIT 5");
$notifStmt->execute([$_SESSION['parent_id']]);
$notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - School Bus Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body class="bg-gradient-to-b from-yellow-50 to-orange-50 min-h-screen p-4">
    <div class="fixed -z-10 top-0 left-0 w-full h-full opacity-50">
        <div class="absolute top-10 left-10 w-32 h-32 rounded-full bg-blue-100 blur-3xl"></div>
        <div class="absolute bottom-10 right-10 w-40 h-40 rounded-full bg-orange-100 blur-3xl"></div>
        <div class="absolute top-1/3 right-1/4 w-24 h-24 rounded-full bg-green-100 blur-3xl"></div>
    </div>

    <div class="container mx-auto py-6">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                <h1 class="text-3xl font-bold text-orange-800">Parent Dashboard</h1>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="font-medium"><?php echo htmlspecialchars($_SESSION['parent_name']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($_SESSION['parent_email']); ?></p>
                </div>
                <a href="logout.php" class="bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white px-4 py-2 rounded-xl shadow transition duration-300 ease-in-out transform hover:-translate-y-1">Logout</a>
            </div>
        </header>

        <main>
            <!-- Children Section -->
            <section class="bg-white rounded-3xl shadow-xl p-6 md:p-8 border border-orange-100 mb-8 relative overflow-hidden">
                <div class="absolute -right-20 -top-20 w-40 h-40 bg-orange-50 rounded-full"></div>
                <div class="absolute -left-12 -bottom-12 w-24 h-24 bg-blue-50 rounded-full"></div>
                
                <div class="flex justify-between items-center mb-6 relative">
                    <h2 class="text-2xl font-semibold text-gray-800">Your Children</h2>
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-orange-500 to-yellow-500 flex items-center justify-center shadow-md transform rotate-6 animate-float">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                </div>

                <?php if (empty($children)): ?>
                <div class="bg-orange-50 p-6 rounded-xl border border-orange-100 text-center">
                    <p class="text-gray-600">No children registered yet. Please contact the school administration.</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($children as $child): ?>
                    <div class="bg-orange-50 p-6 rounded-xl border border-orange-100 hover:shadow-md transition duration-300">
                        <div class="flex items-center mb-4">
                            <?php if ($child['photo_url']): ?>
                            <img src="<?php echo htmlspecialchars($child['photo_url']); ?>" alt="Child photo" class="w-12 h-12 rounded-full object-cover mr-4">
                            <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-orange-200 flex items-center justify-center mr-4">
                                <span class="text-orange-700 font-bold text-lg"><?php echo substr($child['first_name'], 0, 1); ?></span>
                            </div>
                            <?php endif; ?>
                            <div>
                                <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h3>
                                <p class="text-sm text-gray-500">Grade: <?php echo htmlspecialchars($child['grade'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <p><span class="font-medium">School:</span> <?php echo htmlspecialchars($child['school_name'] ?? 'Not assigned'); ?></p>
                            <p><span class="font-medium">Bus Number:</span> <?php echo htmlspecialchars($child['bus_number'] ?? 'Not assigned'); ?></p>
                            <p><span class="font-medium">Pickup Location:</span> <?php echo htmlspecialchars($child['pickup_location'] ?? 'Not set'); ?></p>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="child_details.php?id=<?php echo $child['child_id']; ?>" class="text-orange-600 hover:text-orange-800 font-medium">View Details</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <!-- Notifications Section -->
            <section class="bg-white rounded-3xl shadow-xl p-6 md:p-8 border border-orange-100 relative overflow-hidden">
                <div class="absolute -right-12 -bottom-12 w-32 h-32 bg-yellow-50 rounded-full"></div>
                
                <div class="flex justify-between items-center mb-6 relative">
                    <h2 class="text-2xl font-semibold text-gray-800">Recent Notifications</h2>
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center shadow-md transform -rotate-6 animate-float">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                </div>

                <?php if (empty($notifications)): ?>
                <div class="bg-blue-50 p-6 rounded-xl border border-blue-100 text-center">
                    <p class="text-gray-600">No new notifications.</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($notifications as $notification): 
                        $bgColor = 'bg-blue-50';
                        $borderColor = 'border-blue-100';
                        switch ($notification['notification_type']) {
                            case 'alert':
                                $bgColor = 'bg-red-50';
                                $borderColor = 'border-red-100';
                                break;
                            case 'warning':
                                $bgColor = 'bg-yellow-50';
                                $borderColor = 'border-yellow-100';
                                break;
                            case 'success':
                                $bgColor = 'bg-green-50';
                                $borderColor = 'border-green-100';
                                break;
                        }
                    ?>
                    <div class="<?php echo $bgColor; ?> p-6 rounded-xl border <?php echo $borderColor; ?> <?php echo $notification['is_read'] ? 'opacity-70' : ''; ?> hover:shadow-md transition duration-300">
                        <div class="flex justify-between items-start">
                            <h3 class="font-semibold text-lg mb-2"><?php echo htmlspecialchars($notification['title']); ?></h3>
                            <span class="text-xs text-gray-500"><?php echo date('M d, H:i', strtotime($notification['sent_at'])); ?></span>
                        </div>
                        <p class="text-gray-700"><?php echo htmlspecialchars($notification['message']); ?></p>
                        <?php if (!$notification['is_read']): ?>
                        <div class="mt-3 text-right">
                            <button onclick="markAsRead(<?php echo $notification['notification_id']; ?>)" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Mark as read</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-6 text-center">
                    <a href="all_notifications.php" class="text-orange-600 hover:text-orange-800 font-medium">View All Notifications</a>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        function markAsRead(notificationId) {
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh the page to update the UI
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>