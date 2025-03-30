<?php
// Include session check file
include 'session_check.php';

// Require admin login
require_admin_login();

// Get admin info from session
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
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
        /* Custom scrollbar styling */
        .overflow-y-auto::-webkit-scrollbar {
            width: 8px;
        }

        .overflow-y-auto::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #666;
        }

        /* For Firefox */
        .overflow-y-auto {
            scrollbar-width: thin;
            scrollbar-color: #888 #f1f1f1;
        }

        /* Ensure sticky header works properly */
        thead.sticky {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        /* Add shadow to sticky header */
        thead.sticky::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -1px;
            height: 1px;
            background: #e5e7eb;
        }

        /* Button hover effects */
        .button-hover-effect {
            position: relative;
            overflow: hidden;
        }

        .button-hover-effect::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to right,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transition: left 0.5s;
        }

        .button-hover-effect:hover::after {
            left: 100%;
        }

        /* Ripple effect on click */
        .button-ripple {
            position: relative;
            overflow: hidden;
        }

        .button-ripple::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .button-ripple:active::before {
            width: 200%;
            height: 200%;
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
                <button onclick="showSection('tracker')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <i class="fas fa-chart-bar h-5 w-5 mr-3"></i>
                    Bus
                </button>
                <button onclick="showSection('history')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <i class="fas fa-id-card h-5 w-5 mr-3"></i>
                    Driver
                </button>
                <button onclick="showSection('payments')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <i class="fas fa-wallet h-5 w-5 mr-3"></i>
                    Parent
                </button>
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
            <button onclick="showSection('tracker')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <i class="fas fa-bus h-6 w-6"></i>
                <span>Bus</span>
            </button>
            <button onclick="showSection('history')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <i class="fas fa-id-card h-6 w-6"></i>
                <span>Driver</span>
            </button>
            <button onclick="showSection('payments')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <i class="fas fa-user-friends h-6 w-6"></i>
                <span>Parent</span>
            </button>
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
                                <button onclick="toggleLogoutPopup()" id="profile-menu-button" class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 order-last">
                                    <img class="h-8 w-8 rounded-full object-cover border-2 border-orange-200" src="https://randomuser.me/api/portraits/women/44.jpg" alt="Profile">
                                </button>
                                <div id="logout-popup" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 ring-1 ring-black ring-opacity-5">
                                    <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                                </div>
                            </div>
                            <script>
                                function toggleLogoutPopup() {
                                    const popup = document.getElementById('logout-popup');
                                    popup.classList.toggle('hidden');
                                }

                                // Close popup when clicking outside
                                window.addEventListener('click', function(e) {
                                    const popup = document.getElementById('logout-popup');
                                    const profileBtn = document.getElementById('profile-menu-button');
                                    if (!popup.contains(e.target) && !profileBtn.contains(e.target)) {
                                        popup.classList.add('hidden');
                                    }
                                });
                            </script>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="content-area flex-1 bg-orange-50">
            <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

                <!-- Home Section -->
                <section id="home-section" class="dashboard-section">
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
                                    <?php 
                                    if($childCount > 999) {
                                        echo '<span class="text-lg">' . $childCount . '</span>';
                                    } else {
                                        echo $childCount;
                                    }
                                    ?>
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
                        <!-- All Drivers (Left Side) -->
                        <div>
                            <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mb-6">
                                <div class="p-6 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold heading-brown">Latest Drivers</h3>
                                </div>
                                <div class="p-6">
                                    <div class="space-y-6">
                                        <?php
                                        // Get latest 5 drivers with their bus and school details
                                        $query = "
                                            SELECT DISTINCT 
                                                d.full_name,
                                                d.phone,
                                                b.bus_number,
                                                b.covering_cities,
                                                GROUP_CONCAT(s.name SEPARATOR ', ') as school_names
                                            FROM driver d
                                            LEFT JOIN bus b ON d.bus_id = b.bus_id 
                                            LEFT JOIN bus_school bs ON b.bus_id = bs.bus_id
                                            LEFT JOIN school s ON bs.school_id = s.school_id
                                            GROUP BY d.driver_id
                                            ORDER BY d.joined_date DESC
                                            LIMIT 5
                                        ";

                                        $result = $conn->query($query);

                                        if ($result && $result->num_rows > 0) {
                                            while($row = $result->fetch_assoc()) {
                                        ?>
                                        <!-- Driver Entry -->
                                        <div class="border border-blue-100 rounded-xl p-4 bg-blue-50">
                                            <div class="flex items-start space-x-4">
                                                <img src="../img/busdriver.jpg" alt="<?php echo htmlspecialchars($row['full_name']); ?>" class="w-16 h-16 rounded-full object-cover border-2 border-blue-200"/>
                                                <div class="flex-1">
                                                    <h4 class="text-md font-medium text-blue-800"><?php echo htmlspecialchars($row['full_name']); ?></h4>
                                                    <p class="text-blue-600 text-sm">Bus - <b class="text-blue-600"><?php echo htmlspecialchars($row['bus_number']); ?></b></p>
                                                    <div class="mt-2 text-xs text-gray-500">
                                                        <p><b class="text-blue-500">Schools:</b>  <br><?php echo htmlspecialchars($row['school_names']); ?></p><br>
                                                        <p><b class="text-blue-500">Cities:</b>  <br><?php echo htmlspecialchars($row['covering_cities']); ?></p><br>
                                                        <p><b class="text-blue-500">Contact:</b>  <br><?php echo htmlspecialchars($row['phone']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                            }
                                        } else {
                                            echo "<p class='text-gray-500'>No drivers found</p>";
                                        }
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

                                    // Calculate active time ago
                                    function timeAgo($datetime) {
                                        $now = new DateTime();
                                        $past = new DateTime($datetime);
                                        $diff = $now->diff($past);
                                        
                                        if ($diff->y > 0) {
                                            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
                                        }
                                        if ($diff->m > 0) {
                                            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
                                        }
                                        if ($diff->d > 0) {
                                            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
                                        }
                                        if ($diff->h > 0) {
                                            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                        }
                                        if ($diff->i > 0) {
                                            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                        }
                                        return 'just now';
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
                                                        <p class="text-xs text-gray-600">Last active: <?php echo timeAgo($parent['last_login']); ?></p>
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

                

                <?php
// Database connection
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root';
$password = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch buses with associated schools
    $stmt = $pdo->prepare("
        SELECT 
            b.bus_id, 
            b.bus_number, 
            b.license_plate, 
            b.capacity, 
            b.is_active, 
            b.starting_location,
            GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS schools
        FROM 
            bus b
        LEFT JOIN 
            bus_school bs ON b.bus_id = bs.bus_id
        LEFT JOIN 
            school s ON bs.school_id = s.school_id
        GROUP BY 
            b.bus_id
    ");
    $stmt->execute();
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $buses = [];
    echo "<div class='bg-red-100 p-4 text-red-800'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<section id="tracker-section" class="dashboard-section mt-8">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
            <h2 class="text-3xl font-bold heading-brown">Bus Management</h2>
        </div>
        <div class="flex mt-4 md:mt-0">
            <a href="add_bus.php" class="w-full md:w-auto bg-yellow-500 hover:bg-yellow-600 transition duration-300 ease-in-out transform hover:-translate-y-1 text-white px-4 py-2 rounded-lg flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                <span class="text-sm md:text-base">Add New Bus</span>
            </a>
        </div>
    </div>


      <!-- Add Bus Modal -->
      <div id="addBusModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-2xl font-bold text-orange-800">Add New Bus</h2>
                <button onclick="closeModal('addBusModal')" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form id="addBusForm" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="busNumber" class="block text-sm font-medium text-gray-700 mb-2">Bus Number *</label>
                        <input type="text" id="busNumber" name="bus_number" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    
                    <div>
                        <label for="licensePlate" class="block text-sm font-medium text-gray-700 mb-2">License Plate *</label>
                        <input type="text" id="licensePlate" name="license_plate" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="startingLocation" class="block text-sm font-medium text-gray-700 mb-2">Starting Location</label>
                        <input type="text" id="startingLocation" name="starting_location" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 mb-2">City</label>
                        <input type="text" id="city" name="city" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>

                <div>
                    <label for="coveringCities" class="block text-sm font-medium text-gray-700 mb-2">Covering Cities</label>
                    <textarea id="coveringCities" name="covering_cities" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                </div>

                <div>
                    <label for="coveringRegions" class="block text-sm font-medium text-gray-700 mb-2">Covering Regions</label>
                    <textarea id="coveringRegions" name="covering_regions" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                </div>

                <div>
                    <label for="schools" class="block text-sm font-medium text-gray-700 mb-2">Covering Schools</label>
                    <select multiple id="schools" name="schools[]" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <!-- School options will be dynamically populated -->
                    </select>
                </div>

                <div>
                    <label for="capacity" class="block text-sm font-medium text-gray-700 mb-2">Bus Capacity *</label>
                    <input type="number" id="capacity" name="capacity" required min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeModal('addBusModal')" 
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                        Add Bus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Function to open modal
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                fetchSchools(); // Fetch schools when modal opens
            }
        }

        // Function to close modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        // Fetch schools dynamically
        function fetchSchools() {
            const schoolSelect = document.getElementById('schools');
            
            // Fetch schools from the database
            fetch('fetch_schools.php')
                .then(response => response.json())
                .then(schools => {
                    // Clear existing options
                    schoolSelect.innerHTML = '';
                    
                    // Populate schools
                    schools.forEach(school => {
                        const option = document.createElement('option');
                        option.value = school.school_id;
                        option.textContent = `${school.name} (${school.location})`;
                        schoolSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error fetching schools:', error);
                    schoolSelect.innerHTML = '<option>Error loading schools</option>';
                });
        }

       
    </script>

    <!-- Filter and Search -->
    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 p-6 mb-8">
        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <div class="flex-1">
                <label for="search-bus" class="block text-sm font-medium text-gray-700 mb-1">Search Buses</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" id="search-bus" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-orange-500 focus:border-orange-500" placeholder="Search by bus number, license plate...">
                </div>
            </div>
            <!-- <div class="w-full md:w-48">
                <label for="filter-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="filter-status" class="block w-full border border-gray-300 rounded-lg focus:ring-orange-500 focus:border-orange-500 py-2">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div> -->
        </div>
    </div>

    <!-- Bus List -->
    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-semibold heading-brown">All Buses</h3>
            <div class="text-sm text-gray-500">Total: <?php echo count($buses); ?> buses</div>
        </div>
        
        <!-- Desktop and Tablet View with Scrolling -->
        <div class="hidden md:block relative overflow-x-auto" style="max-height: 500px; overflow-y: auto;">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Bus Info</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Capacity</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Location</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Covering Schools</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Status</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($buses as $bus): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($bus['bus_number']); ?></div>
                                    <div class="text-sm text-gray-500">License: <?php echo htmlspecialchars($bus['license_plate']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($bus['capacity']); ?> seats</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($bus['starting_location'] ?? 'N/A'); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-500 max-w-xs truncate">
                                <?php echo htmlspecialchars($bus['schools'] ?? 'No schools assigned'); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $bus['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $bus['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end space-x-2">
                                <button onclick="editBus(<?php echo $bus['bus_id']; ?>)" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 flex items-center">
                                    <i class="fas fa-edit mr-1"></i>
                                    <span>Edit</span>
                                </button>
                                <button onclick="deleteBus(<?php echo $bus['bus_id']; ?>)" 
                                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 flex items-center">
                                    <i class="fas fa-trash-alt mr-1"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile View with Scrolling -->
        <div class="md:hidden relative overflow-y-auto" style="max-height: 500px;">
            <div class="space-y-4 divide-y divide-gray-200">
                <?php foreach ($buses as $bus): ?>
                <div class="p-4 border-b border-gray-200 mt-4">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($bus['bus_number']); ?></h4>
                            <p class="text-sm text-gray-500">License: <?php echo htmlspecialchars($bus['license_plate']); ?></p>
                        </div>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            <?php echo $bus['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $bus['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    
                    <div class="space-y-2 mb-3">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Capacity:</span>
                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($bus['capacity']); ?> seats</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Location:</span>
                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($bus['starting_location'] ?? 'N/A'); ?></span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Schools:</span>
                            <p class="text-sm text-gray-900 mt-1"><?php echo htmlspecialchars($bus['schools'] ?? 'No schools assigned'); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-4">
                        <button onclick="editBus(<?php echo $bus['bus_id']; ?>)" class="text-blue-600">
                            <span class="text-sm">Edit</span>
                        </button>
                        <button onclick="deleteBus(<?php echo $bus['bus_id']; ?>)" class="text-red-600">
                            <span class="text-sm">Delete</span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
        </div>
    </div>
    <div class="pb-16"></div> <!-- Added bottom space -->
</section>

<script>
    // Search functionality
    document.getElementById('search-bus').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const busNumber = row.querySelector('div.text-sm.font-medium').textContent.toLowerCase();
            const licensePlate = row.querySelector('div.text-sm.text-gray-500').textContent.toLowerCase();
            
            if (busNumber.includes(searchTerm) || licensePlate.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Status filter functionality
    document.getElementById('filter-status').addEventListener('change', function() {
        const statusFilter = this.value;
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const statusSpan = row.querySelector('span');
            const status = statusSpan.textContent.toLowerCase();
            
            if (statusFilter === '' || status === statusFilter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Placeholder functions for bus actions
    function viewBusDetails(busId) {
        alert('View details for Bus ID: ' + busId);
        // Implement view details modal/page
    }

    function editBus(busId) {
        alert('Edit Bus ID: ' + busId);
        // Implement edit bus modal/page
    }

    function deleteBus(busId) {
        if (confirm('Are you sure you want to delete this bus?')) {
            // Implement bus deletion logic
            fetch('delete_bus.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ bus_id: busId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the row from the table
                    const row = document.querySelector(`tr:has(button[onclick="deleteBus(${busId})"])`);
                    if (row) row.remove();
                    alert('Bus deleted successfully');
                } else {
                    alert('Failed to delete bus: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the bus');
            });
        }
    }
</script>




                <!-- Driver Management Section -->
                <section id="history-section" class="dashboard-section mt-8">
                    <!-- Header with Add Button -->
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
                        <div class="flex items-center space-x-3">
                            <h2 class="text-3xl font-bold heading-brown">Driver Management</h2>
                        </div>
                        <button onclick="openAddDriverModal()" 
                                class="mt-4 md:mt-0 w-full md:w-auto bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg flex items-center justify-center md:justify-start gap-2 transform hover:scale-105 transition-all duration-300 shadow-md hover:shadow-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span class="font-medium">Add New Driver</span>
                        </button>
                    </div>

                    <!-- Search Bar -->
                    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 p-6 mb-8">
                        <div class="flex flex-col md:flex-row md:items-center gap-4">
                            <div class="flex-1">
                                <label for="driverSearch" class="block text-sm font-medium text-gray-700 mb-1">Search Drivers</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" 
                                           id="driverSearch" 
                                           placeholder="Search by driver name..." 
                                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-orange-500 focus:border-orange-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Driver List Section -->
                    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="text-lg font-semibold heading-brown">All Drivers</h3>
                            <div class="text-sm text-gray-500">Total: <?php echo mysqli_num_rows($result); ?> drivers</div>
                        </div>

                        <!-- Scrollable Container -->
                        <div class="relative overflow-x-auto" style="max-height: 460px;">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 sticky top-0 z-10">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Driver Info</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Assigned Bus</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Age & Experience</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Contact Details</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">License Info</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php
                                    // Fetch drivers with bus information
                                    $query = "SELECT d.*, b.bus_number 
                                             FROM driver d 
                                             LEFT JOIN bus b ON d.bus_id = b.bus_id 
                                             ORDER BY d.full_name";
                                    $result = mysqli_query($conn, $query);

                                    while ($driver = mysqli_fetch_assoc($result)) {
                                        echo "<tr class='hover:bg-gray-50'>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap'>{$driver['full_name']}</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap'>" . ($driver['bus_number'] ?? 'Not Assigned') . "</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap'>{$driver['age']} years, {$driver['experience_years']} years</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap'>
                                                <div>{$driver['email']}</div>
                                                <div class='text-gray-500'>{$driver['phone']}</div>
                                              </td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap'>
                                                <div>{$driver['license_number']}</div>
                                                <div class='text-gray-500'>Expires: " . date('M d, Y', strtotime($driver['license_expiry_date'])) . "</div>
                                              </td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-right text-sm font-medium'>
                                                <div class='flex justify-end space-x-2'>
                                                    <button onclick='editDriver({$driver['driver_id']})' class='bg-blue-500 text-white px-3 py-1.5 rounded-lg flex items-center gap-1 transform hover:scale-105 hover:bg-blue-600 transition-all duration-300 shadow-sm hover:shadow-md'>
                                                        <i class='fas fa-edit'></i>
                                                        <span>Edit</span>
                                                    </button>
                                                    <button onclick='deleteDriver({$driver['driver_id']})' class='bg-red-500 text-white px-3 py-1.5 rounded-lg flex items-center gap-1 transform hover:scale-105 hover:bg-red-600 transition-all duration-300 shadow-sm hover:shadow-md'>
                                                        <i class='fas fa-trash-alt'></i>
                                                        <span>Delete</span>
                                                    </button>
                                                </div>
                                              </td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Add/Edit Driver Modal -->
                <div id="driverModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
                    <div class="flex items-center justify-center min-h-screen px-4">
                        <div class="bg-white rounded-lg max-w-xl w-full mx-auto shadow-xl">
                            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-t-lg px-4 py-3 flex justify-between items-center">
                                <h3 class="text-xl font-bold text-white" id="modalTitle">Add New Driver</h3>
                                <button onclick="closeDriverModal()" class="text-white hover:text-gray-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>

                            <form id="driverForm" class="p-4 space-y-4">
                                <input type="hidden" id="driver_id" name="driver_id">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                        <input type="text" id="full_name" name="full_name" required class="form-input">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Bus</label>
                                        <select id="bus_id" name="bus_id" class="form-select">
                                            <option value="">Select Bus</option>
                                            <?php
                                            $busQuery = "SELECT bus_id, bus_number FROM bus WHERE is_active = 1";
                                            $busResult = mysqli_query($conn, $busQuery);
                                            while ($bus = mysqli_fetch_assoc($busResult)) {
                                                echo "<option value='{$bus['bus_id']}'>{$bus['bus_number']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                        <input type="email" id="email" name="email" required class="form-input">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                                        <input type="tel" id="phone" name="phone" required class="form-input">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">License Number *</label>
                                        <input type="text" id="license_number" name="license_number" required class="form-input">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">License Expiry Date *</label>
                                        <input type="date" id="license_expiry_date" name="license_expiry_date" required class="form-input">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Age *</label>
                                        <input type="number" id="age" name="age" required class="form-input">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Experience (Years) *</label>
                                        <input type="number" id="experience_years" name="experience_years" required class="form-input">
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-3 pt-4">
                                    <button type="button" onclick="closeDriverModal()" 
                                        class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">Cancel</button>
                                    <button type="submit" 
                                        class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">Save Driver</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <style>
                .form-input, .form-select {
                    @apply w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all;
                }
                </style>

                <script>
                function searchDrivers() {
                    const searchInput = document.getElementById('driverSearch');
                    const searchText = searchInput.value.toLowerCase();
                    
                    // For desktop/tablet view
                    const tableRows = document.querySelectorAll('table tbody tr');
                    tableRows.forEach(row => {
                        const driverName = row.querySelector('td:first-child').textContent.toLowerCase();
                        if (driverName.includes(searchText)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    // For mobile view
                    const mobileCards = document.querySelectorAll('.md\\:hidden .divide-y > div');
                    mobileCards.forEach(card => {
                        const driverName = card.querySelector('.font-medium').textContent.toLowerCase();
                        if (driverName.includes(searchText)) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                }

                // Add event listener for real-time search
                document.addEventListener('DOMContentLoaded', function() {
                    const searchInput = document.getElementById('driverSearch');
                    if (searchInput) {
                        searchInput.addEventListener('input', searchDrivers);
                    }
                });

                function openAddDriverModal() {
                    document.getElementById('driverModal').classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }

                function closeDriverModal() {
                    document.getElementById('driverModal').classList.add('hidden');
                }

                function editDriver(driverId) {
                    // Show loading state
                    const button = event.currentTarget;
                    const originalContent = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                    button.disabled = true;

                    // Fetch driver details
                    fetch(`get_driver.php?id=${driverId}`)
                        .then(response => response.json())
                        .then(data => {
                            // Reset button state
                            button.innerHTML = originalContent;
                            button.disabled = false;

                            // Populate and show modal
                            populateDriverForm(data);
                            document.getElementById('driverModal').classList.remove('hidden');
                            document.getElementById('modalTitle').textContent = 'Edit Driver';
                            document.body.style.overflow = 'hidden';
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            button.innerHTML = originalContent;
                            button.disabled = false;
                            alert('Failed to load driver details');
                        });
                }

                function deleteDriver(driverId) {
                    const button = event.currentTarget;
                    
                    if (confirm('Are you sure you want to delete this driver?')) {
                        // Show loading state
                        const originalContent = button.innerHTML;
                        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                        button.disabled = true;

                        fetch('delete_driver.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ driver_id: driverId })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove the row/card from the UI
                                const row = button.closest('tr') || button.closest('.mobile-card');
                                row.remove();
                                showNotification('Driver deleted successfully', 'success');
                            } else {
                                throw new Error(data.message || 'Failed to delete driver');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            button.innerHTML = originalContent;
                            button.disabled = false;
                            showNotification(error.message, 'error');
                        });
                    }
                }

                // Add notification function
                function showNotification(message, type = 'success') {
                    const notification = document.createElement('div');
                    notification.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg text-white ${
                        type === 'success' ? 'bg-green-500' : 'bg-red-500'
                    } shadow-lg transform transition-all duration-300 translate-y-0 opacity-100`;
                    notification.textContent = message;

                    document.body.appendChild(notification);

                    // Animate out and remove after 3 seconds
                    setTimeout(() => {
                        notification.classList.add('translate-y-2', 'opacity-0');
                        setTimeout(() => notification.remove(), 300);
                    }, 3000);
                }

                document.getElementById('driverForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    
                    fetch('save_driver.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeDriverModal();
                            location.reload();
                        } else {
                            alert(data.message || 'Failed to save driver');
                        }
                    });
                });
                </script>







                <!-- Parent Management Section -->
            <section id="payments-section" class="dashboard-section mt-8">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
                    <div class="flex items-center space-x-3">
                        <!-- <div class="h-10 w-1 bg-blue-500 rounded-full"></div> -->
                        <h2 class="text-3xl font-bold heading-brown">Parent Management</h2>
                    </div>
                    <button class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add New Parent
                    </button>
                </div>

                <!-- Search and Filter -->
                <div class="bg-white rounded-2xl shadow-enhanced border border-blue-100 p-6 mb-8">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <label for="search-parent" class="block text-sm font-medium text-gray-700 mb-1">Search Parents</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" id="search-parent" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Search by name, email, or child's name...">
                            </div>
                        </div>
                        <div class="w-full md:w-48">
                            <label for="filter-school" class="block text-sm font-medium text-gray-700 mb-1">School</label>
                            <select id="filter-school" class="block w-full border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 py-2">
                                <option value="">All Schools</option>
                                <option value="elementary">Elementary</option>
                                <option value="middle">Middle School</option>
                                <option value="high">High School</option>
                            </select>
                        </div>
                        <div class="w-full md:w-48">
                            <label for="sort-by" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                            <select id="sort-by" class="block w-full border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 py-2">
                                <option value="name">Name</option>
                                <option value="children">Number of Children</option>
                                <option value="bus">Bus Number</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Parent Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Parent Card 1 -->
                    <div class="bg-white rounded-2xl shadow-enhanced border border-blue-100 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="font-semibold heading-brown">Parent Profile</h3>
                            <div class="flex space-x-2">
                                <button class="text-indigo-600 hover:text-indigo-900">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button class="text-red-600 hover:text-red-900">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-col sm:flex-row gap-6">
                                <!-- Profile Picture Column -->
                                <div class="flex flex-col items-center">
                                    <div class="w-32 h-32 rounded-full overflow-hidden mb-3">
                                        <img src="/api/placeholder/200/200" alt="Jennifer Thompson" class="w-full h-full object-cover" />
                                    </div>
                                    <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                        2 Children
                                    </div>
                                </div>
                                
                                <!-- Details Column -->
                                <div class="flex-1 space-y-4">
                                    <div>
                                        <h4 class="text-xl font-medium text-gray-800">Jennifer Thompson</h4>
                                        <p class="text-gray-500 text-sm">Registered: August 2023</p>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <div class="flex">
                                            <span class="w-20 text-sm font-medium text-gray-500">Address:</span>
                                            <span class="flex-1 text-sm text-gray-800">1234 Maple Avenue, Springfield, IL 62704</span>
                                        </div>
                                        
                                        <div class="flex">
                                            <span class="w-20 text-sm font-medium text-gray-500">Email:</span>
                                            <span class="flex-1 text-sm text-gray-800">jennifer.thompson@example.com</span>
                                        </div>
                                        
                                        <div class="flex">
                                            <span class="w-20 text-sm font-medium text-gray-500">Phone:</span>
                                            <span class="flex-1 text-sm text-gray-800">(555) 234-5678</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Children Information -->
                                    <div class="pt-4 border-t border-gray-100">
                                        <h5 class="font-medium text-gray-800 mb-3">Children</h5>
                                        <div class="space-y-4">
                                            <!-- Child 1 -->
                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                <div class="flex items-center justify-between">
                                                    <h6 class="font-medium">Emma Thompson</h6>
                                                    <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs">Bus #38</span>
                                                </div>
                                                <div class="mt-2 grid grid-cols-2 gap-2">
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">Age:</span>
                                                        <span class="text-xs ml-1">9 years</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">Grade:</span>
                                                        <span class="text-xs ml-1">4th</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">School:</span>
                                                        <span class="text-xs ml-1">Lincoln Elementary</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">ID:</span>
                                                        <span class="text-xs ml-1">#ST54321</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Child 2 -->
                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                <div class="flex items-center justify-between">
                                                    <h6 class="font-medium">Noah Thompson</h6>
                                                    <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded-full text-xs">Bus #42</span>
                                                </div>
                                                <div class="mt-2 grid grid-cols-2 gap-2">
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">Age:</span>
                                                        <span class="text-xs ml-1">12 years</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">Grade:</span>
                                                        <span class="text-xs ml-1">7th</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">School:</span>
                                                        <span class="text-xs ml-1">Washington Middle School</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">ID:</span>
                                                        <span class="text-xs ml-1">#ST54322</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="pt-4">
                                        <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Full Profile</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Parent Card 2 -->
                    <div class="bg-white rounded-2xl shadow-enhanced border border-blue-100 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="font-semibold heading-brown">Parent Profile</h3>
                            <div class="flex space-x-2">
                                <button class="text-indigo-600 hover:text-indigo-900">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button class="text-red-600 hover:text-red-900">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-col sm:flex-row gap-6">
                                <!-- Profile Picture Column -->
                                <div class="flex flex-col items-center">
                                    <div class="w-32 h-32 rounded-full overflow-hidden mb-3">
                                        <img src="/api/placeholder/200/200" alt="Michael Rodriguez" class="w-full h-full object-cover" />
                                    </div>
                                    <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                        3 Children
                                    </div>
                                </div>
                                
                                <!-- Details Column -->
                                <div class="flex-1 space-y-4">
                                    <div>
                                        <h4 class="text-xl font-medium text-gray-800">Michael Rodriguez</h4>
                                        <p class="text-gray-500 text-sm">Registered: October 2022</p>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <div class="flex">
                                            <span class="w-20 text-sm font-medium text-gray-500">Address:</span>
                                            <span class="flex-1 text-sm text-gray-800">789 Oak Street, Springfield, IL 62701</span>
                                        </div>
                                        
                                        <div class="flex">
                                            <span class="w-20 text-sm font-medium text-gray-500">Email:</span>
                                            <span class="flex-1 text-sm text-gray-800">michael.rodriguez@example.com</span>
                                        </div>
                                        
                                        <div class="flex">
                                            <span class="w-20 text-sm font-medium text-gray-500">Phone:</span>
                                            <span class="flex-1 text-sm text-gray-800">(555) 876-5432</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Children Information -->
                                    <div class="pt-4 border-t border-gray-100">
                                        <h5 class="font-medium text-gray-800 mb-3">Children</h5>
                                        <div class="space-y-4">
                                            <!-- Child 1 -->
                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                <div class="flex items-center justify-between">
                                                    <h6 class="font-medium">Sophia Rodriguez</h6>
                                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">Bus #45</span>
                                                </div>
                                                <div class="mt-2 grid grid-cols-2 gap-2">
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">Age:</span>
                                                        <span class="text-xs ml-1">6 years</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">Grade:</span>
                                                        <span class="text-xs ml-1">1st</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">School:</span>
                                                        <span class="text-xs ml-1">Jefferson Elementary</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">ID:</span>
                                                        <span class="text-xs ml-1">#ST67890</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Child 2 -->
                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                <div class="flex items-center justify-between">
                                                    <h6 class="font-medium">Lucas Rodriguez</h6>
                                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">Bus #33</span>
                                                </div>
                                                <div class="mt-2 grid grid-cols-2 gap-2">
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">Age:</span>
                                                        <span class="text-xs ml-1">10 years</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">Grade:</span>
                                                        <span class="text-xs ml-1">5th</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">School:</span>
                                                        <span class="text-xs ml-1">Lincoln Elementary</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">ID:</span>
                                                        <span class="text-xs ml-1">#ST67891</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Child 3 -->
                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                <div class="flex items-center justify-between">
                                                    <h6 class="font-medium">Isabella Rodriguez</h6>
                                                    <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs">Bus #38</span>
                                                </div>
                                                <div class="mt-2 grid grid-cols-2 gap-2">
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">Age:</span>
                                                        <span class="text-xs ml-1">14 years</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">Grade:</span>
                                                        <span class="text-xs ml-1">9th</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">School:</span>
                                                        <span class="text-xs ml-1">Roosevelt High School</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-gray-500">ID:</span>
                                                        <span class="text-xs ml-1">#ST67892</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="pt-4">
                                        <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Full Profile</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                    </div>
                    </section>
                        






                <section id="settings-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-14 md:mr-8 mx-4 md:mx-0">

                <?php
                // Fetch current admin details
                $current_admin_id = $_SESSION['admin_id'];
                $stmt = $pdo->prepare("SELECT * FROM admin WHERE admin_id = ?");
                $stmt->execute([$current_admin_id]);
                $current_admin = $stmt->fetch(PDO::FETCH_ASSOC);

                // Fetch all admins
                $admins_query = $pdo->query("SELECT * FROM admin");
                $admins = $admins_query->fetchAll(PDO::FETCH_ASSOC);
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
                                        class="w-full p-2 border border-gray-300 rounded-lg cursor-not-allowed" 
                                        readonly>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                    <input type="text" name="role" 
                                           value="<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $current_admin['role']))); ?>"
                                           class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500 outline-none transition"
                                           <?php echo $current_admin['role'] === 'super_admin' ? 'disabled' : ''; ?>
                                           readonly>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition duration-300">
                                        Update Profile
                                    </button>
                                </div>
                            </div>
                            </div>

                            <!-- Admin Actions Section -->
                            <div class="p-6 grid md:grid-cols-2 gap-4">
                                <!-- Password Change Button -->
                                <button onclick="showChangePasswordModal()" class="w-full bg-yellow-500 text-white py-3 rounded-lg hover:bg-yellow-600 transition duration-300 ease-in-out transform hover:scale-105 flex items-center justify-center space-x-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    <span>Change Password</span>
                                </button>

                                <!-- Register New Admin Button -->
                                <button onclick="showRegisterAdminModal()" class="w-full bg-green-500 text-white py-3 rounded-lg hover:bg-green-600 transition duration-300 ease-in-out transform hover:scale-105 flex items-center justify-center space-x-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                    </svg>
                                    <span>Register New Admin</span>
                                </button>
                            </div>

                            <!-- Admins Table -->
                            <div class="p-6">
                                <h3 class="text-xl font-semibold mb-4">Administrators</h3>
                                <div class="relative overflow-x-auto" style="max-height: 500px; overflow-y: scroll;">
                                    <table class="w-full text-sm text-left text-gray-500">
                                        <thead class="text-xs text-gray-700 uppercase bg-gray-100 sticky top-0">
                                            <tr>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($admins as $admin): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">
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
                                                <td class="px-4 py-3 text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($admin['email']); ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-500">
                                                    <?php 
                                                        $role = str_replace('_', ' ', $admin['role']);
                                                        echo htmlspecialchars(ucwords($role)); 
                                                    ?>
                                                </td>
                                                <td class="px-4 py-3 text-right text-sm font-medium">
                                                    <?php if ($admin['role'] !== 'super_admin'): ?>
                                                        <button onclick="showEditAdminModal(<?php echo htmlspecialchars(json_encode($admin)); ?>)" 
                                                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transform hover:scale-105 transition duration-200 ease-in-out shadow-md hover:shadow-lg mr-2">
                                                            <i class="fas fa-edit mr-1"></i> Edit
                                                        </button>
                                                        <br><br>
                                                        <button onclick="confirmDeleteAdmin(<?php echo $admin['admin_id']; ?>)"
                                                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transform hover:scale-105 transition duration-200 ease-in-out shadow-md hover:shadow-lg">
                                                            <i class="fas fa-trash-alt mr-1"></i> Delete
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 cursor-not-allowed">Cannot Modify</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password Modal -->
                    <div id="changePasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
                        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                            <div class="mt-3 text-center">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Change Password</h3>
                                <div class="mt-2 px-7 py-3">
                                    <form id="changePasswordForm">
                                        <input type="password" placeholder="Current Password" name="current_password" class="mb-3 w-full px-3 py-2 border rounded-lg">
                                        <input type="password" placeholder="New Password" name="new_password" class="mb-3 w-full px-3 py-2 border rounded-lg">
                                        <input type="password" placeholder="Confirm New Password" name="confirm_password" class="mb-3 w-full px-3 py-2 border rounded-lg">
                                        <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600">Update Password</button>
                                        <button type="button" onclick="hideChangePasswordModal()" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Register Admin Modal -->
                    <div id="registerAdminModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
                        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                            <div class="mt-3 text-center">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Register New Admin</h3>
                                <div class="mt-2 px-7 py-3">
                                    <form id="registerAdminForm">
                                        <input type="text" placeholder="Full Name" name="full_name" class="mb-3 w-full px-3 py-2 border rounded-lg">
                                        <input type="email" placeholder="Email" name="email" class="mb-3 w-full px-3 py-2 border rounded-lg">
                                        <input type="password" placeholder="Password" name="password" class="mb-3 w-full px-3 py-2 border rounded-lg">
                                        <input type="password" placeholder="Confirm Password" name="confirm_password" class="mb-3 w-full px-3 py-2 border rounded-lg">
                                        <select name="role" class="mb-3 w-full px-3 py-2 border rounded-lg">
                                            <option value="admin">Admin</option>
                                            <option value="transportation_manager">Transportation Manager</option>
                                            <option value="school_admin">School Admin</option>
                                            <option value="support_staff">Support Staff</option>
                                        </select>
                                        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">Register</button>
                                        <button type="button" onclick="hideRegisterAdminModal()" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Admin Modal -->
                    <div id="editAdminModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
                        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                            <div class="mt-3 text-center">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Admin</h3>
                                <div class="mt-2 px-7 py-3">
                                    <form id="editAdminForm">
                                        <input type="hidden" name="admin_id" id="edit_admin_id">
                                        <input type="text" placeholder="Full Name" name="full_name" id="edit_full_name" class="mb-3 w-full px-3 py-2 border rounded-lg">
                                        <input type="email" placeholder="Email" name="email" id="edit_email" class="mb-3 w-full px-3 py-2 border rounded-lg" readonly>
                                        <select name="role" id="edit_role" class="mb-3 w-full px-3 py-2 border rounded-lg">
                                            <option value="admin">Admin</option>
                                            <option value="transportation_manager">Transportation Manager</option>
                                            <option value="school_admin">School Admin</option>
                                            <option value="support_staff">Support Staff</option>
                                        </select>
                                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">Update</button>
                                        <button type="button" onclick="hideEditAdminModal()" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Modal Functions
                        function showChangePasswordModal() {
                            document.getElementById('changePasswordModal').classList.remove('hidden');
                        }

                        function hideChangePasswordModal() {
                            document.getElementById('changePasswordModal').classList.add('hidden');
                        }

                        function showRegisterAdminModal() {
                            document.getElementById('registerAdminModal').classList.remove('hidden');
                        }

                        function hideRegisterAdminModal() {
                            document.getElementById('registerAdminModal').classList.add('hidden');
                        }

                        function showEditAdminModal(admin) {
                            document.getElementById('edit_admin_id').value = admin.admin_id;
                            document.getElementById('edit_full_name').value = admin.full_name;
                            document.getElementById('edit_email').value = admin.email;
                            document.getElementById('edit_role').value = admin.role;
                            document.getElementById('editAdminModal').classList.remove('hidden');
                        }

                        function hideEditAdminModal() {
                            document.getElementById('editAdminModal').classList.add('hidden');
                        }

                        function confirmDeleteAdmin(adminId) {
                            if (confirm('Are you sure you want to delete this admin?')) {
                                // Handle delete action here
                                console.log('Deleting admin with ID:', adminId);
                            }
                        }

                        // Form Submissions
                        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
                            e.preventDefault();
                            // Handle password change submission
                        });

                        document.getElementById('registerAdminForm').addEventListener('submit', function(e) {
                            e.preventDefault();
                            // Handle new admin registration
                        });

                        document.getElementById('editAdminForm').addEventListener('submit', function(e) {
                            e.preventDefault();
                            // Handle admin edit
                        });
                    </script>

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

<!-- Update the Edit Bus Modal with reduced size and yellow button -->
<div id="editBusModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <!-- Reduce max-width and add max-height -->
        <div class="relative bg-white rounded-lg max-w-xl w-full mx-auto shadow-xl" style="max-height: 80vh;">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-t-lg px-4 py-3 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Edit Bus Details</h3>
                <button onclick="closeEditModal()" class="text-white hover:text-gray-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Modal Body with scrollable content -->
            <div class="p-4 overflow-y-auto" style="max-height: calc(80vh - 60px);">
                <form id="editBusForm" class="space-y-4">
                    <input type="hidden" id="edit_bus_id">
                    
                    <!-- Bus Details Section -->
                    <div class="bg-gray-50 rounded-lg p-3 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="edit_bus_number" class="block text-sm font-medium text-gray-700 mb-1">Bus Number *</label>
                                <input type="text" id="edit_bus_number" name="bus_number" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all">
                            </div>
                            
                            <div>
                                <label for="edit_license_plate" class="block text-sm font-medium text-gray-700 mb-1">License Plate *</label>
                                <input type="text" id="edit_license_plate" name="license_plate" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="edit_capacity" class="block text-sm font-medium text-gray-700 mb-1">Capacity *</label>
                                <input type="number" id="edit_capacity" name="capacity" required min="1"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all">
                            </div>
                            
                            <div>
                                <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select id="edit_status" name="status" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="edit_starting_location" class="block text-sm font-medium text-gray-700 mb-1">Starting Location *</label>
                            <input type="text" id="edit_starting_location" name="starting_location" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all">
                        </div>

                        <div>
                            <label for="edit_covering_cities" class="block text-sm font-medium text-gray-700 mb-1">Covering Cities</label>
                            <textarea id="edit_covering_cities" name="covering_cities" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-3 pt-2">
                        <button type="button" onclick="closeEdit_Bus_Modal()" 
                            class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-300 text-sm">
                            Cancel
                        </button>
                        <button type="submit" 
                            class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition-all duration-300 text-sm flex items-center">
                            <span>Update</span>
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* Update animation for smaller modal */
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #editBusModal > div > div {
        animation: modalFadeIn 0.2s ease-out;
    }

    /* Custom scrollbar styling */
    .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #666;
    }
</style>

<script>
// Function to show edit modal
function editBus(busId) {
    const modal = document.getElementById('editBusModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling

    // Show loading state
    showLoadingState();

    // Fetch bus details
    fetch(`get_bus_details.php?id=${busId}`)
        .then(response => response.json())
        .then(data => {
            hideLoadingState();
            if (data.error) {
                throw new Error(data.error);
            }
            populateForm(data);
        })
        .catch(error => {
            hideLoadingState();
            showError('Failed to load bus details: ' + error.message);
        });
}

// Function to close edit modal
function closeEdit_Bus_Modal() {
    const modal = document.getElementById('editBusModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto'; // Restore scrolling
    resetForm();
}

// Function to populate form
function populateForm(data) {
    document.getElementById('edit_bus_id').value = data.bus_id;
    document.getElementById('edit_bus_number').value = data.bus_number;
    document.getElementById('edit_license_plate').value = data.license_plate;
    document.getElementById('edit_capacity').value = data.capacity;
    document.getElementById('edit_status').value = data.is_active;
    document.getElementById('edit_starting_location').value = data.starting_location;
    document.getElementById('edit_covering_cities').value = data.covering_cities;
}

// Function to reset form
function resetForm() {
    document.getElementById('editBusForm').reset();
}

// Function to show loading state
function showLoadingState() {
    // Add loading overlay if needed
}

// Function to hide loading state
function hideLoadingState() {
    // Remove loading overlay if needed
}

// Function to show error message
function showError(message) {
    alert(message); // You can replace this with a better error notification
}

// Handle form submission
document.getElementById('editBusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        bus_id: document.getElementById('edit_bus_id').value,
        bus_number: document.getElementById('edit_bus_number').value,
        license_plate: document.getElementById('edit_license_plate').value,
        capacity: document.getElementById('edit_capacity').value,
        is_active: document.getElementById('edit_status').value,
        starting_location: document.getElementById('edit_starting_location').value,
        covering_cities: document.getElementById('edit_covering_cities').value
    };

    // Show loading state
    showLoadingState();

    // Send update request
    fetch('update_bus.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingState();
        if (data.success) {
            closeEditModal();
            // Show success message and refresh the page
            alert('Bus details updated successfully');
            location.reload();
        } else {
            throw new Error(data.message || 'Failed to update bus details');
        }
    })
    .catch(error => {
        hideLoadingState();
        showError(error.message);
    });
});

// Close modal when clicking outside
document.getElementById('editBusModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<!-- Edit Driver Modal -->
<div id="editDriverModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-[28rem] max-w-[90%] shadow-lg rounded-md bg-white" style="margin-left: 20px; margin-right: 20px;">
        <div class="flex justify-between items-center mb-4 border-b pb-3">
            <h3 class="text-lg font-semibold text-gray-900">Edit Driver Details</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-500">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <form id="editDriverForm" class="space-y-3">
            <input type="hidden" id="editDriverId" name="driver_id">
            
            <!-- Personal Information -->
            <div>
                <label for="editFullName" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input type="text" id="editFullName" name="full_name" required 
                       class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-yellow-500 focus:border-yellow-500">
            </div>

            <!-- Bus and Age -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="editBusId" class="block text-sm font-medium text-gray-700 mb-1">Assigned Bus</label>
                    <select id="editBusId" name="bus_id" 
                            class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-yellow-500 focus:border-yellow-500">
                        <option value="">Select Bus</option>
                        <?php
                        $bus_query = "SELECT bus_id, bus_number FROM bus WHERE is_active = 1";
                        $bus_result = mysqli_query($conn, $bus_query);
                        while ($bus = mysqli_fetch_assoc($bus_result)) {
                            echo "<option value='" . $bus['bus_id'] . "'>" . $bus['bus_number'] . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label for="editAge" class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                    <input type="number" id="editAge" name="age" required min="18" max="65"
                           class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-yellow-500 focus:border-yellow-500">
                </div>
            </div>

            <!-- Contact Information -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="editEmail" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="editEmail" name="email" required
                           class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-yellow-500 focus:border-yellow-500">
                </div>
                <div>
                    <label for="editPhone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="tel" id="editPhone" name="phone" required
                           class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-yellow-500 focus:border-yellow-500">
                </div>
            </div>

            <!-- License Information -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="editLicenseNumber" class="block text-sm font-medium text-gray-700 mb-1">License Number</label>
                    <input type="text" id="editLicenseNumber" name="license_number" required
                           class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-yellow-500 focus:border-yellow-500">
                </div>
                <div>
                    <label for="editLicenseExpiry" class="block text-sm font-medium text-gray-700 mb-1">License Expiry</label>
                    <input type="date" id="editLicenseExpiry" name="license_expiry_date" required
                           class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-yellow-500 focus:border-yellow-500">
                </div>
            </div>

            <!-- Experience -->
            <div>
                <label for="editExperience" class="block text-sm font-medium text-gray-700 mb-1">Experience (Years)</label>
                <input type="number" id="editExperience" name="experience_years" required min="0"
                       class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-yellow-500 focus:border-yellow-500">
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-2 pt-3 border-t mt-4">
                <button type="button" onclick="closeEditModal()" 
                        class="px-3 py-1.5 bg-gray-500 text-white text-sm rounded-lg hover:bg-gray-600 transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-3 py-1.5 bg-yellow-500 text-white text-sm rounded-lg hover:bg-yellow-600 transition-colors">
                    Update
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add this JavaScript code -->
<script>
function editDriver(driverId) {
    // Show loading state on the button
    const button = event.currentTarget;
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    button.disabled = true;

    // Fetch driver details
    fetch(`get_driver_details.php?id=${driverId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate form fields
                document.getElementById('editDriverId').value = data.driver.driver_id;
                document.getElementById('editFullName').value = data.driver.full_name;
                document.getElementById('editBusId').value = data.driver.bus_id || '';
                document.getElementById('editAge').value = data.driver.age;
                document.getElementById('editEmail').value = data.driver.email;
                document.getElementById('editPhone').value = data.driver.phone;
                document.getElementById('editLicenseNumber').value = data.driver.license_number;
                document.getElementById('editLicenseExpiry').value = data.driver.license_expiry_date;
                document.getElementById('editExperience').value = data.driver.experience_years;

                // Show the modal
                document.getElementById('editDriverModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            } else {
                throw new Error(data.message || 'Failed to load driver details');
            }
        })
        .catch(error => {
            console.error('Error details:', error);
            alert('Failed to load driver details: ' + error.message);
        })
        .finally(() => {
            // Reset button state
            button.innerHTML = originalContent;
            button.disabled = false;
        });
}

function closeEditModal() {
    document.getElementById('editDriverModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    document.getElementById('editDriverForm').reset();
}

// Handle form submission
document.getElementById('editDriverForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitButton = this.querySelector('button[type="submit"]');
    const originalContent = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    submitButton.disabled = true;

    fetch('update_driver.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Driver updated successfully');
            closeEditModal();
            location.reload(); // Refresh to show updated data
        } else {
            throw new Error(data.message || 'Failed to update driver');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update driver: ' + error.message);
    })
    .finally(() => {
        submitButton.innerHTML = originalContent;
        submitButton.disabled = false;
    });
});
</script>
</body>
</html>