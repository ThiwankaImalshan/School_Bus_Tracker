<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../log_in.html');
    exit;
}

// Get active tab from URL parameter, default to 'bus'
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'bus';

// Define valid tabs
$valid_tabs = ['bus', 'driver', 'parent', 'child', 'school'];

// Define role permissions
$role_permissions = [
    'super_admin' => ['bus', 'driver', 'parent', 'child', 'school'],
    'admin' => ['bus', 'driver', 'parent', 'child', 'school'],
    'transportation_manager' => ['bus', 'driver', 'school'],
    'support_staff' => ['parent', 'child']
];

// Get user's role and allowed tabs
$user_role = $_SESSION['admin_role'] ?? 'admin';
$allowed_tabs = $role_permissions[$user_role] ?? [];

// Redirect if user tries to access unauthorized tab
if (!in_array($active_tab, $allowed_tabs)) {
    $active_tab = $allowed_tabs[0] ?? 'bus';
    header("Location: ?tab=$active_tab");
    exit;
}

// Include the corresponding page content based on active tab
$content_file = $active_tab . '_management.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Bus Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../../img/favicon/favicon.svg" />
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
        .nav-pill {
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .nav-pill:hover {
            background: rgba(251, 191, 36, 0.1);
        }
        .tab-active {
            border-bottom: 3px solid #ea580c;
            color: #ea580c;
        }
        .tab-active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #f97316, #92400e);
            border-radius: 3px 3px 0 0;
        }
        .content-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        .content-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
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
                <span class="text-yellow-900 font-medium">Welcome, <?php echo isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin'; ?></span>
                <a href="../dashboard.php" class="bg-yellow-900 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition duration-300 shadow-md hover:shadow-lg">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        <div class="glass-container p-6 mb-8">
            <!-- Navigation Tabs -->
            <div class="flex overflow-x-auto mb-8 bg-white shadow-md rounded-2xl p-2">
                <?php if (in_array('bus', $allowed_tabs)): ?>
                    <a href="?tab=bus" class="flex-shrink-0 px-8 py-4 text-center nav-pill <?php echo $active_tab === 'bus' ? 'tab-active' : 'text-gray-600 hover:text-amber-700'; ?>">
                        <i class="fas fa-bus mr-2"></i> Buses
                    </a>
                <?php endif; ?>
                
                <?php if (in_array('driver', $allowed_tabs)): ?>
                    <a href="?tab=driver" class="flex-shrink-0 px-8 py-4 text-center nav-pill <?php echo $active_tab === 'driver' ? 'tab-active' : 'text-gray-600 hover:text-amber-700'; ?>">
                        <i class="fas fa-id-card mr-2"></i> Drivers
                    </a>
                <?php endif; ?>
                
                <?php if (in_array('parent', $allowed_tabs)): ?>
                    <a href="?tab=parent" class="flex-shrink-0 px-8 py-4 text-center nav-pill <?php echo $active_tab === 'parent' ? 'tab-active' : 'text-gray-600 hover:text-amber-700'; ?>">
                        <i class="fas fa-user-friends mr-2"></i> Parents
                    </a>
                <?php endif; ?>
                
                <?php if (in_array('child', $allowed_tabs)): ?>
                    <a href="?tab=child" class="flex-shrink-0 px-8 py-4 text-center nav-pill <?php echo $active_tab === 'child' ? 'tab-active' : 'text-gray-600 hover:text-amber-700'; ?>">
                        <i class="fas fa-child mr-2"></i> Children
                    </a>
                <?php endif; ?>
                
                <?php if (in_array('school', $allowed_tabs)): ?>
                    <a href="?tab=school" class="flex-shrink-0 px-8 py-4 text-center nav-pill <?php echo $active_tab === 'school' ? 'tab-active' : 'text-gray-600 hover:text-amber-700'; ?>">
                        <i class="fas fa-school mr-2"></i> Schools
                    </a>
                <?php endif; ?>
            </div>

            <!-- Page Content -->
            <div class="content-card p-6">
                <?php 
                if (file_exists($content_file)) {
                    include($content_file);
                } else {
                    echo '<div class="text-center text-red-500 p-10">Content file not found.</div>';
                }
                ?>
            </div>
        </div>
    </main>

    <footer class="warm-gradient text-white mt-16 py-6 shadow-inner">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; <?php echo date('Y'); ?> School Bus Management System</p>
        </div>
    </footer>

    <script>
        // Function to show delete confirmation modal
        function confirmDelete(entityId, entityType) {
            // Create modal backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'fixed inset-0 bg-black bg-opacity-50 z-40';
            
            // Create modal content
            const modal = document.createElement('div');
            modal.className = 'fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg p-6 shadow-xl z-50 w-96';
            modal.innerHTML = `
            <h3 class="text-lg font-semibold mb-4">Confirm Delete</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete this ${entityType}?</p>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300" onclick="closeModal()">Cancel</button>
                <button class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600" onclick="proceedDelete('${entityId}', '${entityType}')">Delete</button>
            </div>
            `;

            // Add elements to body
            document.body.appendChild(backdrop);
            document.body.appendChild(modal);
        }

        function closeModal() {
            document.querySelector('div[class*="fixed inset-0"]').remove();
            document.querySelector('div[class*="fixed top-1/2"]').remove();
        }

        function proceedDelete(entityId, entityType) {
            window.location.href = `${entityType}_delete.php?id=${entityId}`;
        }

        // Show success message with fade out effect
        document.addEventListener('DOMContentLoaded', function() {
            const successMsg = document.getElementById('success-message');
            if (successMsg) {
                setTimeout(function() {
                    successMsg.style.opacity = '0';
                    setTimeout(function() {
                        successMsg.style.display = 'none';
                    }, 1000);
                }, 3000);
            }
        });
    </script>
</body>
</html>