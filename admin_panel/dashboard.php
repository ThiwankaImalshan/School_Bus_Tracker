<?php
// Include session check file
include 'session_check.php';

// Require admin login
require_admin_login();

// Get admin info from session
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$current_admin_role = $_SESSION['admin_role'] ?? 'admin';
$allowed_roles = ['admin', 'super_admin'];

// Define role-based menu access
$menu_permissions = [
    'payments' => ['admin', 'super_admin'],
    'newsletter' => ['admin', 'super_admin', 'support_staff']
];

// Function to check menu item visibility
function canAccessMenu($menuItem, $userRole, $permissions) {
    return isset($permissions[$menuItem]) && in_array($userRole, $permissions[$menuItem]);
}
?>


<?php
 // Database connection parameters
 $host = "localhost";
 $username = "root";
 $password = "";
 $database = "school_bus_management";

 // Create database connection
 $conn = new mysqli($host, $username, $password, $database);

 // Check connection
 if ($conn->connect_error) {
     die("Connection failed: " . $conn->connect_error);
 }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="icon" type="image/png" href="../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../img/favicon/favicon.svg" />
    <!-- Font Awesome 5 (CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- OR Font Awesome 6 (CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .input-focus-effect:focus {
            box-shadow: 0 0 0 3px rgba(255, 179, 71, 0.3);
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        /* Custom button styles for analogous color palette */
        .btn-primary {
            background-color: #ac5300; /* Main brown-orange */
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #8B4513; /* Darker brown */
            transform: translateY(-2px);
        }
        .btn-secondary {
            background-color: #CD853F; /* Peru - lighter brown */
            color: white;
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            background-color: #B8860B; /* Dark goldenrod */
            transform: translateY(-2px);
        }
        .btn-accent {
            background-color: #D2691E; /* Chocolate */
            color: white;
            transition: all 0.3s ease;
        }
        .btn-accent:hover {
            background-color: #A0522D; /* Sienna */
            transform: translateY(-2px);
        }
        /* Custom gradient styles for buttons */
        .btn-gradient {
            background: linear-gradient(135deg, #ac5300, #CD853F);
            color: white;
            transition: all 0.3s ease;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #8B4513, #B8860B);
            transform: translateY(-2px);
        }
        /* Enhanced shadow classes */
        .shadow-enhanced {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .shadow-lg-enhanced {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        /* Active navigation item */
        .nav-item.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            border-left: 3px solid #ffffff;
        }
        .nav-item {
            color: white !important;
        }
        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }
        /* Navigation item hover override */
        .nav-item:hover svg {
            color: white !important;
        }
        
        /* Mobile navigation active state */
        .mobile-nav-item.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: white !important;
        }
        .mobile-nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white !important;
        }
        /* Navigation background */
        .nav-bg-purple {
            background-color: #f78e2d; /* Deep Purple */
        }
        /* For mobile navigation */
        @media (max-width: 768px) {
            .mobile-nav {
                background: #f78e2d;
                box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.1);
            }
            .mobile-nav-item {
                color: white !important;
            }
        }
        /* Background gradient */
        .bg-theme-gradient {
            background: linear-gradient(to bottom right, #ac5300, #CD853F);
        }
        /* Update accent colors */
        .accent-primary {
            color: #ac5300;
        }
        .accent-secondary {
            color: #CD853F;
        }
        .border-theme {
            border-color: #ac5300;
        }
        .checked\:bg-theme:checked {
            background-color: #ac5300;
        }
        /* Add new heading style */
        .heading-brown {
            color: #ac5300;
        }
        /* Content area background */
        .content-area {
            background-color: rgba(255, 205, 141, 0.1); /* Lightest orange with 10% opacity */
        }
    </style>
</head>
<body class="bg-gradient-to-b from-orange-50 to-orange-100 min-h-screen">
    <div class="fixed -z-10 top-0 left-0 w-full h-full opacity-50">
        <!-- <div class="absolute top-10 left-10 w-32 h-32 rounded-full bg-blue-100 blur-3xl"></div>
        <div class="absolute bottom-10 right-10 w-40 h-40 rounded-full bg-orange-100 blur-3xl"></div>
        <div class="absolute top-1/3 right-1/4 w-24 h-24 rounded-full bg-green-100 blur-3xl"></div> -->
    </div>

    <!-- Desktop Navigation - Left sidebar -->
    <div class="hidden md:flex md:fixed md:inset-y-0 md:left-0 md:w-64 nav-bg-purple md:shadow-lg-enhanced md:flex-col md:z-20">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-yellow-500 flex items-center justify-center shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="ml-3 text-xl font-bold text-white">Admin Portal</h1>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto pt-5 pb-4">
            <nav class="px-4 space-y-1">
                <button onclick="showSection('home')" class="nav-item active w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <i class="fas fa-home h-5 w-5 mr-3"></i>
                    Home
                </button>
                <a href="management" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <i class="fas fa-tasks h-5 w-5 mr-3"></i> 
                    Management
                </a>
                <?php if (canAccessMenu('payments', $admin_role, $menu_permissions)): ?>
                <a href="payment_monitor.php" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <i class="fas fa-dollar-sign h-5 w-5 mr-3"></i>
                    Payments
                </a>
                <?php endif; ?>
                <?php if (canAccessMenu('newsletter', $admin_role, $menu_permissions)): ?>
                <a href="newsletter_sender.php" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <i class="fas fa-envelope h-5 w-5 mr-3"></i>
                    Newsletter
                </a>
                <?php endif; ?>
                <button onclick="showSection('settings')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <i class="fas fa-cog h-5 w-5 mr-3"></i>
                    Settings
                </button>
            </nav>
        </div>
    </div>

    <!-- Mobile Navigation - Bottom Bar -->
    <div class="fixed md:hidden bottom-0 left-0 right-0 bg-white shadow-lg z-20 mobile-nav">
        <div class="flex justify-around items-center">
            <button onclick="showSection('home')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <i class="fas fa-home h-6 w-6"></i>
                <span>Home</span>
            </button>
            <a href="management" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <i class="fas fa-tasks h-6 w-6"></i>
                <span>Management</span>
            </a>
            <?php if (canAccessMenu('payments', $admin_role, $menu_permissions)): ?>
            <a href="payment_monitor.php" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <i class="fas fa-dollar-sign h-6 w-6"></i>
                <span>Payments</span>
            </a>
            <?php endif; ?>
            <?php if (canAccessMenu('newsletter', $admin_role, $menu_permissions)): ?>
            <a href="newsletter_sender.php" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <i class="fas fa-envelope h-6 w-6"></i>
                <span>Newsletter</span>
            </a>
            <?php endif; ?>
            <button onclick="showSection('settings')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <i class="fas fa-cog h-6 w-6"></i>
                <span>Settings</span>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="md:pl-64">
        <!-- Top Header Bar -->
        <header class="shadow-sm sticky top-0 z-10" style="background-color: #ffffff;">
            <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <h1 class="text-xl font-bold text-[#ac5300] heading-brown md:hidden">Admin Portal</h1>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="flex-shrink-0 relative flex items-center">
                            <span id="profile-name" class="hidden md:inline-block mr-3 font-medium text-grey order-first"><?php echo htmlspecialchars($admin_name); ?></span>
                            <div class="relative">
                                <button id="profile-menu-button" class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 order-last">
                                    <img class="h-8 w-8 rounded-full object-cover border-2 border-orange-200" src="../img/profile-icon.jpg" alt="Profile">
                                </button>
                                <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50">
                                    <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-orange-50">
                                        <div class="flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                            </svg>
                                            Logout
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const profileButton = document.getElementById('profile-menu-button');
                        const dropdown = document.getElementById('profile-dropdown');

                        // Toggle dropdown when clicking the profile button
                        profileButton.addEventListener('click', function(e) {
                            e.stopPropagation();
                            dropdown.classList.toggle('hidden');
                        });

                        // Close dropdown when clicking outside
                        document.addEventListener('click', function(e) {
                            if (!profileButton.contains(e.target) && !dropdown.contains(e.target)) {
                                dropdown.classList.add('hidden');
                            }
                        });
                    });
                    </script>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="content-area flex-1 bg-orange-50">
            <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

                <!-- Home Section -->
                <section id="home-section" class="dashboard-section pb-20 md:pb-0">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
                        <div class="flex items-center space-x-3">
                            <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                            <h2 class="text-3xl font-bold heading-brown">Dashboard</h2>
                        </div>
                        <p class="text-gray-600 mt-2 md:mt-0">Welcome back, <span class="font-medium"><?php echo htmlspecialchars($admin_name); ?></span></p>
                    </div>

                    <?php
                   
                    // Get count of parents
                    $parentQuery = "SELECT COUNT(*) as total_parents FROM parent";
                    $parentResult = $conn->query($parentQuery);
                    $parentCount = $parentResult->fetch_assoc()['total_parents'];

                    // Get count of children
                    $childQuery = "SELECT COUNT(*) as total_children FROM child";
                    $childResult = $conn->query($childQuery);
                    $childCount = $childResult->fetch_assoc()['total_children'];

                    // Get count of drivers
                    $driverQuery = "SELECT COUNT(*) as total_drivers FROM driver";
                    $driverResult = $conn->query($driverQuery);
                    $driverCount = $driverResult->fetch_assoc()['total_drivers'];

                    // Get count of schools
                    $schoolQuery = "SELECT COUNT(*) as total_schools FROM school";
                    $schoolResult = $conn->query($schoolQuery);
                    $schoolCount = $schoolResult->fetch_assoc()['total_schools'];

                    // Close connection
                    // $conn->close();
                    ?>

                    <!-- Stats Overview -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <!-- Parents Card -->
                        <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 p-6">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-lg font-semibold heading-brown">Parents</h3>
                                <div class="bg-blue-500 text-white text-xl font-bold h-12 w-12 rounded-full flex items-center justify-center">
                                    <?php echo $parentCount; ?>
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm">Total registered parents</p>
                        </div>
                        
                        <!-- Children Card -->
                        <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 p-6">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-lg font-semibold heading-brown">Children</h3>
                                <div class="bg-green-500 text-white text-xl font-bold h-12 w-12 rounded-full flex items-center justify-center">
                                    <?php echo $childCount; ?>
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm">Total enrolled students</p>
                        </div>
                        
                        <!-- Drivers Card -->
                        <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 p-6">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-lg font-semibold heading-brown">Drivers</h3>
                                <div class="bg-purple-500 text-white text-xl font-bold h-12 w-12 rounded-full flex items-center justify-center">
                                    <?php echo $driverCount; ?>
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm">Active bus drivers</p>
                        </div>
                        
                        <!-- Schools Card -->
                        <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 p-6">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-lg font-semibold heading-brown">Schools</h3>
                                <div class="bg-yellow-500 text-white text-xl font-bold h-12 w-12 rounded-full flex items-center justify-center">
                                    <?php echo $schoolCount; ?>
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm">District schools served</p>
                        </div>
                    </div>

                    <!-- Bus Information and Schools Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    <?php
                   
                    // SQL query to join driver and bus tables, limiting to 5 results
                    $sql = "SELECT d.driver_id, d.full_name, d.license_number, d.joined_date, d.phone, 
                                b.bus_number
                            FROM driver d
                            LEFT JOIN bus b ON d.bus_id = b.bus_id
                            ORDER BY d.driver_id
                            LIMIT 5";  // Added LIMIT 5 to show only up to 5 drivers

                    $result = $conn->query($sql);
                    ?>

                    <!-- All Drivers (Left Side) -->
                    <div>
                        <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mb-6">
                            <div class="p-6 border-b border-gray-100">
                                <h3 class="text-lg font-semibold heading-brown">Drivers</h3>
                            </div>
                            <div class="p-6">
                                <div class="space-y-6">
                                    <?php
                                    if ($result->num_rows > 0) {
                                        // Output data of each row
                                        while($row = $result->fetch_assoc()) {
                                    ?>
                                    <!-- Driver Card -->
                                    <div class="border border-gray-100 rounded-xl p-4">
                                        <div class="flex items-start space-x-4">
                                            <img src="../img/busdriver1.jpg" alt="<?php echo htmlspecialchars($row["full_name"]); ?>" class="w-16 h-16 rounded-full object-cover border-2 border-orange-200"/>
                                            <div class="flex-1">
                                                <h4 class="text-md font-medium text-gray-800"><?php echo htmlspecialchars($row["full_name"]); ?></h4>
                                                <p class="text-gray-500 text-sm">Bus <?php echo htmlspecialchars($row["bus_number"] ?? 'Not Assigned'); ?></p>
                                                <div class="mt-2 text-xs text-gray-600">
                                                    <p>License Number: <?php echo htmlspecialchars($row["license_number"]); ?></p>
                                                    <p>Joined Date: <?php echo htmlspecialchars($row["joined_date"]); ?></p>
                                                    <p>Contact: <?php echo htmlspecialchars($row["phone"]); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                        }
                                    } else {
                                        echo "<p class='text-center py-4'>No drivers found</p>";
                                    }
                                    // $conn->close();
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                        
                        <!-- Parents Logged In (Right Side) -->
                        <div>
                            <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden h-full">
                                <div class="p-6 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold heading-brown">Parents Logged In</h3>
                                </div>
                                <div class="p-6">

                                <?php
                               
                                // Get count of parents logged in today
                                $query = "
                                    SELECT COUNT(*) as active_today 
                                    FROM parent 
                                    WHERE DATE(last_login) = CURDATE()
                                ";

                                $result = $conn->query($query);
                                $activeParents = 0;

                                if ($result && $result->num_rows > 0) {
                                    $row = $result->fetch_assoc();
                                    $activeParents = $row['active_today'];
                                }

                                // Close connection
                                // $conn->close();
                                ?>

                                <!-- Number of Parents -->
                                <div class="bg-green-50 rounded-xl p-6 mb-6">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-lg font-medium text-gray-800">Active Today</h4>
                                        <div class="bg-green-500 text-white text-2xl font-bold h-16 w-16 rounded-full flex items-center justify-center">
                                            <?php echo $activeParents; ?>
                                        </div>
                                    </div>
                                </div>

                                    <?php
                                    
                                    // Get 5 most recently active parents
                                    $query = "
                                        SELECT p.parent_id, p.full_name, p.last_login
                                        FROM parent p
                                        WHERE p.last_login IS NOT NULL
                                        ORDER BY p.last_login DESC
                                        LIMIT 5
                                    ";

                                    $result = $conn->query($query);

                                    // Prepare data array
                                    $parents = [];
                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $parents[] = [
                                                'id' => $row['parent_id'],
                                                'name' => $row['full_name'],
                                                'last_login' => $row['last_login']
                                            ];
                                        }
                                    }

                                    // Get children for each parent
                                    foreach ($parents as &$parent) {
                                        $childQuery = "
                                            SELECT c.first_name, c.last_name
                                            FROM child c
                                            WHERE c.parent_id = {$parent['id']}
                                        ";
                                        
                                        $childResult = $conn->query($childQuery);
                                        $children = [];
                                        
                                        if ($childResult && $childResult->num_rows > 0) {
                                            while ($childRow = $childResult->fetch_assoc()) {
                                                $children[] = $childRow['first_name'] . " " . substr($childRow['last_name'], 0, 1) . ".";
                                            }
                                        }
                                        
                                        $parent['children'] = $children;
                                    }

                                    // Close connection
                                    // $conn->close();

                                    // Calculate active time ago with current time
                                    function timeAgo($datetime) {
                                        $now = new DateTime('now', new DateTimeZone('Asia/Colombo')); // Set timezone to Sri Lanka
                                        $past = new DateTime($datetime, new DateTimeZone('Asia/Colombo'));
                                        $diff = $now->diff($past);
                                        
                                        if ($diff->d > 0) {
                                            if ($diff->d == 1) return "Yesterday";
                                            if ($diff->d <= 7) return $diff->d . " days ago";
                                            return date('M j', strtotime($datetime));
                                        }
                                        if ($diff->h > 0) {
                                            return $diff->h . " hour" . ($diff->h > 1 ? 's' : '') . " ago";
                                        }
                                        if ($diff->i > 0) {
                                            return $diff->i . " minute" . ($diff->i > 1 ? 's' : '') . " ago";
                                        }
                                        if ($diff->s > 30) {
                                            return $diff->s . " seconds ago";
                                        }
                                        return "Just now";
                                    }

                                    // Background colors for parent icons
                                    $bgColors = ['blue', 'purple', 'yellow', 'green', 'red'];
                                    ?>

                                   
                                        <!-- <meta http-equiv="refresh" content="3600"> Refresh every hour (3600 seconds) -->
                                        
                                        <!-- Parents Details -->
                                        <div class="space-y-6">
                                            <?php foreach ($parents as $index => $parent): ?>
                                                <?php $colorIndex = $index % count($bgColors); ?>
                                                <div class="border border-gray-100 rounded-xl p-4">
                                                    <div class="flex items-center mb-2">
                                                        <div class="w-10 h-10 bg-<?php echo $bgColors[$colorIndex]; ?>-100 rounded-full flex items-center justify-center mr-3">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-<?php echo $bgColors[$colorIndex]; ?>-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                            </svg>
                                                        </div>
                                                        <h5 class="text-md font-medium text-gray-800"><?php echo htmlspecialchars($parent['name']); ?></h5>
                                                    </div>
                                                    <div class="pl-12 space-y-1">
                                                        <p class="text-xs text-gray-600">
                                                            <span class="w-24 text-xs font-medium text-gray-500">Last login: &nbsp &nbsp &nbsp &nbsp &nbsp </span>
                                                            <span class="<?php echo (strtotime('now') - strtotime($parent['last_login']) < 300) ? 'text-green-500' : ''; ?>">
                                                                <?php echo timeAgo($parent['last_login']); ?>
                                                            </span>
                                                        </p>
                                                        <div class="flex">
                                                            <span class="w-24 text-xs font-medium text-gray-500">Children:</span>
                                                            <span class="flex-1 text-xs text-gray-800"><?php echo htmlspecialchars(implode(', ', $parent['children'])); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if (count($parents) === 0): ?>
                                                <div class="text-center p-4">
                                                    <p class="text-gray-500">No parent activity found.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>


                    </div>
                </section>

                















                <section id="settings-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-14 md:mr-8 mx-4 md:mx-0">

                <?php
                // Fetch current admin details
                $current_admin_id = $_SESSION['admin_id'];
                $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
                $stmt->bind_param("i", $current_admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $current_admin = $result->fetch_assoc();

                // Get current admin's role from session
                $current_admin_role = $_SESSION['admin_role'] ?? 'admin';

                // Modify the query based on admin role
                if ($current_admin_role === 'super_admin') {
                    $admins_query = $conn->query("SELECT * FROM admin ORDER BY role, full_name");
                    $admins = [];
                    while($row = $admins_query->fetch_assoc()) {
                        $admins[] = $row;
                    }
                } else {
                    // Regular admins can only see other regular admins and themselves
                    $admins_query = $conn->prepare("SELECT * FROM admin WHERE role != 'super_admin' OR admin_id = ? ORDER BY role, full_name");
                    $admins_query->bind_param("i", $_SESSION['admin_id']);
                    $admins_query->execute();
                    $result = $admins_query->get_result();
                    $admins = [];
                    while($row = $result->fetch_assoc()) {
                        $admins[] = $row;
                    }
                }
                ?>

                    <div class="container mx-auto px-4 py-8">
                        <div class="bg-white shadow-md rounded-lg overflow-hidden">
                            <!-- Admin Profile Section -->
                            <div class="p-6 bg-orange-50 border-b border-gray-200">
                                <h2 class="text-2xl font-bold text-gray-800">Admin Profile</h2>
                                <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                    <input type="text" name="full_name" 
                                        value="<?php echo htmlspecialchars($current_admin['full_name']); ?>" 
                                        class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500 outline-none transition"
                                        <?php echo $current_admin['role'] === 'super_admin' ? 'readonly' : ''; ?>>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" name="email" 
                                        value="<?php echo htmlspecialchars($current_admin['email']); ?>" 
                                        class="w-full p-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed" 
                                        readonly>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                    <select name="role" 
                                            class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500 outline-none transition"
                                            <?php echo $current_admin['role'] === 'super_admin' ? 'disabled' : ''; ?>>
                                        <option value="super_admin" <?php echo $current_admin['role'] === 'super_admin' ? 'selected' : 'disabled'; ?>>Super Admin</option>
                                        <option value="admin" <?php echo $current_admin['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="transportation_manager" <?php echo $current_admin['role'] === 'transportation_manager' ? 'selected' : ''; ?>>Transportation Manager</option>
                                        <option value="school_admin" <?php echo $current_admin['role'] === 'school_admin' ? 'selected' : ''; ?>>School Admin</option>
                                        <option value="support_staff" <?php echo $current_admin['role'] === 'support_staff' ? 'selected' : ''; ?>>Support Staff</option>
                                    </select>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition duration-300">
                                        Update Profile
                                    </button>
                                </div>
                            </div>
                            </div>

                            <?php if (in_array($current_admin_role, $allowed_roles)): ?>
                                <!-- Admin Actions Section -->
                                <div class="p-6 grid md:grid-cols-2 gap-4">
                                    <!-- Password Change Button -->
                                    <a href="forgot_password.html" class="block">
                                        <button class="w-full bg-yellow-500 text-white py-3 rounded-lg hover:bg-yellow-600 transition duration-300 ease-in-out transform hover:scale-105 flex items-center justify-center space-x-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            <span>Change Password</span>
                                        </button>
                                    </a>

                                    <!-- Register New Admin Button -->
                                    <a href="register_admin.php" class="block">
                                        <button class="w-full bg-green-500 text-white py-3 rounded-lg hover:bg-green-600 transition duration-300 ease-in-out transform hover:scale-105 flex items-center justify-center space-x-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                            </svg>
                                            <span>Register New Admin</span>
                                        </button>
                                    </a>
                                </div>

                                <!-- Admins Table -->
                                <div class="p-6">
                                    <h3 class="text-xl font-semibold mb-4">Administrators</h3>
                                    <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                                        <div class="min-w-full inline-block align-middle">
                                            <div class="overflow-hidden">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-100">
                                                        <tr>
                                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Name</th>
                                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Email</th>
                                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Role</th>
                                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-200 bg-white">
                                                        <?php foreach ($admins as $admin): 
                                                            // Skip displaying super_admin records for regular admins
                                                            if ($current_admin_role !== 'super_admin' && $admin['role'] === 'super_admin') continue;
                                                        ?>
                                                        <tr class="hover:bg-gray-50 transition-colors">
                                                            <td class="px-4 py-3 whitespace-nowrap">
                                                                <div class="flex items-center">
                                                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-orange-100 flex items-center justify-center">
                                                                        <span class="text-orange-500 font-medium">
                                                                            <?php echo strtoupper(substr($admin['full_name'], 0, 2)); ?>
                                                                        </span>
                                                                    </div>
                                                                    <div class="ml-4">
                                                                        <div class="text-sm font-medium text-gray-900">
                                                                            <?php echo htmlspecialchars($admin['full_name']); ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
                                                                <?php echo htmlspecialchars($admin['email']); ?>
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                                    <?php echo $admin['role'] === 'super_admin' ? 'bg-purple-100 text-purple-800' : 
                                                                        ($admin['role'] === 'admin' ? 'bg-blue-100 text-blue-800' : 
                                                                        'bg-green-100 text-green-800'); ?>">
                                                                    <?php echo htmlspecialchars($admin['role']); ?>
                                                                </span>
                                                            </td>
                                                            <td class="px-4 py-3 text-right text-sm font-medium whitespace-nowrap">
                                                                <div class="flex justify-end space-x-2">
                                                                    <?php if (
                                                                        // Allow editing if:
                                                                        // 1. Current user is super_admin
                                                                        // 2. Current user is editing their own profile
                                                                        // 3. Target user is not a super_admin
                                                                        $current_admin_role === 'super_admin' || 
                                                                        ($_SESSION['admin_id'] === $admin['admin_id']) ||
                                                                        ($admin['role'] !== 'super_admin')
                                                                    ): ?>
                                                                        <a href="edit_admin.php?id=<?php echo $admin['admin_id']; ?>" 
                                                                           class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-md
                                                                                  hover:bg-blue-200 transition-colors duration-200">
                                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                            </svg>
                                                                            <span class="hidden sm:inline">Edit</span>
                                                                        </a>
                                                                        <?php if ($current_admin_role === 'super_admin' && $admin['role'] !== 'super_admin'): ?>
                                                                            <button onclick="confirmDelete(<?php echo $admin['admin_id']; ?>)" 
                                                                                    class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-md
                                                                                           hover:bg-red-200 transition-colors duration-200">
                                                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                                </svg>
                                                                                <span class="hidden sm:inline">Delete</span>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-400 rounded-md cursor-not-allowed">
                                                                            Cannot Modify
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="p-6 text-center">
                                    <p class="text-gray-500">You don't have permission to view administrator management.</p>
                                </div>
                            <?php endif; ?>

                            <script>
                                function confirmDelete(adminId) {
                                    if (confirm('Are you sure you want to delete this administrator?')) {
                                        window.location.href = 'delete_admin.php?id=' + adminId;
                                    }
                                }
                            </script>


                    </div>

                    </section>



            </div>
        </main>
    </div>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
// Function to show selected section and hide others
function showSection(sectionId) {
    // Hide all dashboard sections
    const sections = document.querySelectorAll('.dashboard-section');
    sections.forEach(section => {
        section.style.display = 'none';
    });
    
    // Show the selected section
    const selectedSection = document.getElementById(sectionId + '-section');
    if (selectedSection) {
        selectedSection.style.display = 'block';
    }
    
    // Update active states for both desktop and mobile navigation
    updateNavigationState(sectionId);
}

// Function to update navigation states
function updateNavigationState(sectionId) {
    // Desktop navigation
    const desktopNavItems = document.querySelectorAll('.nav-item');
    desktopNavItems.forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('onclick').includes(sectionId)) {
            item.classList.add('active');
        }
    });

    // Mobile navigation
    const mobileNavItems = document.querySelectorAll('.mobile-nav-item');
    mobileNavItems.forEach(item => {
        item.classList.remove('active');
        item.classList.remove('text-orange-500');
        item.classList.add('text-gray-700');
        if (item.getAttribute('onclick').includes(sectionId)) {
            item.classList.add('active');
            item.classList.remove('text-gray-700');
            item.classList.add('text-orange-500');
        }
    });
}

// Profile dropdown functionality
function initializeProfileDropdown() {
    const profileButton = document.getElementById('profile-menu-button');
    if (!profileButton) return;

    let isMenuOpen = false;
    let menuElement = null;

    profileButton.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleProfileMenu();
    });

    function toggleProfileMenu() {
        if (isMenuOpen) {
            closeProfileMenu();
        } else {
            openProfileMenu();
        }
    }

    function openProfileMenu() {
        menuElement = createProfileMenu();
        document.querySelector('.flex-shrink-0.relative').appendChild(menuElement);
        isMenuOpen = true;
        document.addEventListener('click', handleOutsideClick);
    }

    function closeProfileMenu() {
        if (menuElement) {
            menuElement.remove();
            menuElement = null;
        }
        isMenuOpen = false;
        document.removeEventListener('click', handleOutsideClick);
    }

    function handleOutsideClick(e) {
        if (!profileButton.contains(e.target) && !menuElement?.contains(e.target)) {
            closeProfileMenu();
        }
    }
}

// Function to switch child profiles
function switchProfile(childId) {
    const profiles = {
        alex: {
            name: 'Alex Johnson',
            grade: '10',
            busNo: '42',
            image: '/api/placeholder/200/200'
        },
        emma: {
            name: 'Emma Johnson',
            grade: '8',
            busNo: '43',
            image: '/api/placeholder/200/200'
        },
        ryan: {
            name: 'Ryan Johnson',
            grade: '6',
            busNo: '44',
            image: '/api/placeholder/200/200'
        }
    };

    const profile = profiles[childId];
    const profileDisplay = document.getElementById('currentChildProfile');
    
    if (profileDisplay && profile) {
        profileDisplay.innerHTML = `
            <img src="${profile.image}" alt="${profile.name}" class="w-20 h-20 rounded-full object-cover border-4 border-orange-200"/>
            <div>
                <h4 class="text-xl font-medium text-gray-800">${profile.name}</h4>
                <p class="text-gray-500">Grade ${profile.grade} • Bus #${profile.busNo}</p>
            </div>
        `;
    }
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Create placeholder sections
    const sectionIds = ['home', 'tracker', 'history', 'payments', 'settings'];
    createPlaceholderSections(sectionIds);
    
    // Initialize profile dropdown
    initializeProfileDropdown();
    
    // Show home section by default
    showSection('home');
    
    // Initialize event listeners for filter and search functionality
    initializeFilters();
});

// Function to create placeholder sections
function createPlaceholderSections(sectionIds) {
    const mainContent = document.querySelector('main');
    if (!mainContent) return;

    sectionIds.forEach(id => {
        if (!document.getElementById(id + '-section')) {
            const section = createSection(id);
            mainContent.appendChild(section);
        }
    });
}

// Function to create a section element
function createSection(id) {
    const section = document.createElement('section');
    section.id = id + '-section';
    section.className = 'dashboard-section';
    section.style.display = 'none';
    
    const header = document.createElement('div');
    header.className = 'flex flex-col md:flex-row items-start md:items-center justify-between mb-8';
    header.innerHTML = `
        <div class="flex items-center space-x-3">
            <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
            <h2 class="text-3xl font-bold heading-brown">${id.charAt(0).toUpperCase() + id.slice(1)}</h2>
        </div>
    `;
    
    section.appendChild(header);
    return section;
}

// Function to initialize filters
function initializeFilters() {
    const searchInputs = document.querySelectorAll('input[type="text"][id*="search"]');
    const filterSelects = document.querySelectorAll('select[id*="filter"]');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', handleSearch);
    });
    
    filterSelects.forEach(select => {
        select.addEventListener('change', handleFilter);
    });
}

// Function to handle search
function handleSearch(event) {
    const searchTerm = event.target.value.toLowerCase();
    // Implement your search logic here
}

// Function to handle filter
function handleFilter(event) {
    const filterValue = event.target.value;
    // Implement your filter logic here
}

// Function to open modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
    }
}

// Function to close modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Function to add route stop
function addRouteStop() {
    const routeStops = document.getElementById('routeStops');
    if (routeStops) {
        const stopInput = document.createElement('div');
        stopInput.className = 'flex items-center space-x-2 mt-2';
        stopInput.innerHTML = `
            <input type="text" name="stops[]" placeholder="Add stop" class="flex-1 rounded-lg border-gray-300 focus:border-orange-500 focus:ring focus:ring-orange-200">
            <button type="button" onclick="removeRouteStop(this)" class="p-2 text-red-500 hover:text-red-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        `;
        routeStops.appendChild(stopInput);
    }
}

// Function to remove route stop
function removeRouteStop(button) {
    const stopInput = button.parentElement;
    if (stopInput) {
        stopInput.remove();
    }
}
</script>
</body>
</html>