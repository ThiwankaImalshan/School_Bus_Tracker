<?php
// dashboard.php
session_start();

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header("Location: log_in.php");
    exit();
}

require_once 'db_connection.php';

// Fetch driver details
try {
    $stmt = $pdo->prepare("SELECT full_name, email FROM driver WHERE driver_id = :driver_id");
    $stmt->bindParam(':driver_id', $_SESSION['driver_id']);
    $stmt->execute();
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Handle error
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
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
                <h1 class="ml-3 text-xl font-bold text-white">Driver Portal</h1>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto pt-5 pb-4">
            <nav class="px-4 space-y-1">
                <button onclick="showSection('home')" class="nav-item active w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Home
                </button>
                <button onclick="showSection('tracker')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v4m0 0l-2-2m2 2l2-2m-2 10v4m0 0l2-2m-2 2l-2-2M4 12h4m0 0l-2-2m2 2l-2 2m10 0h4m0 0l-2-2m2 2l-2 2" />
                    </svg>
                    Updator
                </button>
                <!-- <button onclick="showSection('history')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    History
                </button> -->
                <button onclick="window.location.href='bus_seating.php'" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12V5a3 3 0 013-3h8a3 3 0 013 3v7m-2 0V5a1 1 0 00-1-1H8a1 1 0 00-1 1v7m-2 2h14a2 2 0 012 2v3a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2H8v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-3a2 2 0 012-2z" />
                    </svg>
                    Seating
                </button>
                <button onclick="showSection('settings')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                </button>
            </nav>
        </div>
    </div>

    <!-- Mobile Navigation - Bottom Bar -->
    <div class="fixed md:hidden bottom-0 left-0 right-0 bg-white shadow-lg z-20 mobile-nav">
        <div class="flex justify-around items-center">
            <button onclick="showSection('home')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Home</span>
            </button>
            <button onclick="showSection('tracker')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v4m0 0l-2-2m2 2l2-2m-2 10v4m0 0l2-2m-2 2l-2-2M4 12h4m0 0l-2-2m2 2l-2 2m10 0h4m0 0l-2-2m2 2l-2 2" />
                </svg>
                <span>Updator</span>
            </button>
            <!-- <button onclick="showSection('history')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>History</span>
            </button> -->
            <button onclick="window.location.href='bus_seating.php'" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12V5a3 3 0 013-3h8a3 3 0 013 3v7m-2 0V5a1 1 0 00-1-1H8a1 1 0 00-1 1v7m-2 2h14a2 2 0 012 2v3a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2H8v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-3a2 2 0 012-2z" />
                </svg>
                <span>Seating</span>
            </button>
            <button onclick="showSection('settings')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
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
                            <h1 class="text-xl font-bold heading-brown md:hidden">Driver Portal</h1>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="flex-shrink-0 relative flex items-center">
                            <span id="profile-name" class="hidden md:inline-block mr-3 font-medium text-grey order-first"><?php echo htmlspecialchars($driver['full_name']); ?></span>
                            <div class="relative">
                                <button onclick="toggleLogoutPopup()" id="profile-btn" class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 order-last">
                                    <img class="h-8 w-8 rounded-full object-cover border-2 border-orange-200" src="https://randomuser.me/api/portraits/women/44.jpg" alt="Profile">
                                </button>
                                <div id="logout-popup" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 ring-1 ring-black ring-opacity-5">
                                    <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <script>
            function toggleLogoutPopup() {
                const popup = document.getElementById('logout-popup');
                popup.classList.toggle('hidden');
            }

            // Close popup when clicking outside
            window.addEventListener('click', function(e) {
                const popup = document.getElementById('logout-popup');
                const profileBtn = document.getElementById('profile-btn');
                if (!popup.contains(e.target) && !profileBtn.contains(e.target)) {
                    popup.classList.add('hidden');
                }
            });
        </script>

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
                        <p class="text-gray-600 mt-2 md:mt-0">Welcome back, <span class="font-medium"><?php echo htmlspecialchars($driver['full_name']); ?></span></p>
                    </div>

                    <?php
                    // Database connection
                    $servername = "localhost";
                    $username = "root";
                    $password = "";
                    $dbname = "school_bus_management";

                    // Create connection
                    $conn = new mysqli($servername, $username, $password, $dbname);

                    // Check connection
                    if ($conn->connect_error) {
                        die("Connection failed: " . $conn->connect_error);
                    }

                    // Get the current session driver's ID (assuming it's passed via session)
                    // session_start();
                    $driver_id = $_SESSION['driver_id'] ?? null;

                    // Initialize variables
                    $driver_name = "Unknown Driver";
                    $bus_number = "N/A";

                    if ($driver_id) {
                        // Fetch driver and bus details
                        $query = "SELECT d.full_name, b.bus_number, d.license_number
                                FROM driver d
                                LEFT JOIN bus b ON d.bus_id = b.bus_id
                                WHERE d.driver_id = ?";
                        
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("i", $driver_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $driver = $result->fetch_assoc();
                            $driver_name = htmlspecialchars($driver['full_name']);
                            $bus_number = htmlspecialchars($driver['bus_number'] ?? 'N/A');
                            $driver_license = htmlspecialchars($driver['license_number']);
                        }
                    }
                    ?>

                    <!-- Driver Profile -->
                    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 p-6 mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold heading-brown">Driver Profile</h3>
                        </div>
                        <!-- Driver Display -->
                        <div class="flex items-center space-x-4">
                            <img src="../img/busdriver1.jpg" alt="<?php echo $driver_name; ?>" class="w-20 h-20 rounded-full object-cover border-4 border-orange-200"/>
                            <div>
                                <h4 class="text-xl font-medium text-gray-800"><?php echo $driver_name; ?></h4>
                                <p class="text-gray-500"><?php echo $driver_license; ?></p>
                            </div>
                        </div>
                    </div>

                    <?php
                    // Close connection
                    // if (isset($stmt)) $stmt->close();
                    // $conn->close();
                    ?>

                    <!-- Stats Overview -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    
                    </div>

                    <!-- Bus Information and Schools Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Bus Students (Left Side) -->
                        <div>
                            <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mb-6">
                                <div class="p-6 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold heading-brown">Bus Students</h3>
                                </div>
                                <div class="p-6">
                                    <!-- Bus Details -->
                                    <div class="flex flex-col items-center mb-6">
                                        <!-- Bus Image -->
                                        <div class="w-100 h-40 overflow-hidden mb-3">
                                            <img src="../img/sclbus.jpg" alt="Bus No" class="w-full h-full object-cover" />
                                        </div>
                                        <!-- Bus Number -->
                                        <h4 class="text-lg font-medium text-gray-800">Bus - <b><?php echo $bus_number; ?></b></h4>
                                    </div>

                                    <!-- Student Count -->
                                    <div class="bg-blue-50 rounded-xl p-6 mb-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="text-lg font-medium text-gray-800">Total Students</h4>
                                                <p class="text-gray-600">Morning Route</p>
                                            </div>
                                            <div class="bg-blue-500 text-white text-2xl font-bold h-16 w-16 rounded-full flex items-center justify-center">
                                                42
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bus Details -->
                                    <div class="space-y-4">
                                        <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">Route:</span>
                                            <span class="flex-1 text-sm text-gray-800">North District - Route C</span>
                                        </div>
                                        
                                        <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">Capacity:</span>
                                            <span class="flex-1 text-sm text-gray-800">55 Students</span>
                                        </div>
                                        
                                        <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">First Pick-up:</span>
                                            <span class="flex-1 text-sm text-gray-800">7:00 AM</span>
                                        </div>
                                        
                                        <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">Last Drop-off:</span>
                                            <span class="flex-1 text-sm text-gray-800">4:15 PM</span>
                                        </div>
                                        
                                        <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">Contact:</span>
                                            <span class="flex-1 text-sm text-gray-800">(555) 987-6543</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        // Database connection
                        // $servername = "localhost";
                        // $username = "root";
                        // $password = "";
                        // $dbname = "school_bus_management";

                        // Create connection
                        // $conn = new mysqli($servername, $username, $password, $dbname);

                        // Check connection
                        // if ($conn->connect_error) {
                        //     die("Connection failed: " . $conn->connect_error);
                        // }

                        // Get the current session driver's ID (assuming it's passed via session)
                        // session_start();
                        $driver_id = $_SESSION['driver_id'] ?? null;

                        if ($driver_id) {
                            // Fetch bus details for the driver
                            $bus_query = "SELECT b.bus_id, b.bus_number 
                                        FROM bus b
                                        JOIN driver d ON d.bus_id = b.bus_id
                                        WHERE d.driver_id = ?";
                            $bus_stmt = $conn->prepare($bus_query);
                            $bus_stmt->bind_param("i", $driver_id);
                            $bus_stmt->execute();
                            $bus_result = $bus_stmt->get_result();
                            
                            if ($bus_result->num_rows > 0) {
                                $bus = $bus_result->fetch_assoc();
                                $bus_id = $bus['bus_id'];

                                // Fetch schools served by this bus
                                $schools_query = "SELECT s.school_id, s.name, s.arrival_time, s.departure_time,
                                                s.location, s.contact_number
                                                FROM school s
                                                JOIN bus_school bs ON bs.school_id = s.school_id
                                                WHERE bs.bus_id = ?";
                                $schools_stmt = $conn->prepare($schools_query);
                                $schools_stmt->bind_param("i", $bus_id);
                                $schools_stmt->execute();
                                $schools_result = $schools_stmt->get_result();
                            }
                        }
                        ?>

                        <!-- Schools Served Section -->
                        <div class="w-full max-w-md mx-auto sm:max-w-xl md:max-w-2xl lg:max-w-4xl">
                            <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden h-full">
                                <div class="p-6 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold heading-brown">Schools Served</h3>
                                </div>
                                <div class="p-6">
                                    <!-- Number of Schools -->
                                    <div class="bg-green-50 rounded-xl p-6 mb-6">
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-lg font-medium text-gray-800">Total Schools</h4>
                                            <div class="bg-green-500 text-white text-2xl font-bold h-16 w-16 rounded-full flex items-center justify-center">
                                                <?php echo $schools_result ? $schools_result->num_rows : 0; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- School Details -->
                                    <div class="space-y-6">
                                        <?php 
                                        if ($schools_result && $schools_result->num_rows > 0) {
                                            $color_classes = [
                                                'bg-blue-100 text-blue-500', 
                                                'bg-purple-100 text-purple-500', 
                                                'bg-yellow-100 text-yellow-500'
                                            ];
                                            $idx = 0;
                                            while ($school = $schools_result->fetch_assoc()) { 
                                                $color_class = $color_classes[$idx % count($color_classes)];
                                        ?>
                                            <!-- School Card -->
                                            <div class="border border-gray-100 rounded-xl p-4">
                                                <div class="flex items-center mb-2">
                                                    <div class="w-10 h-10 <?php echo $color_class; ?> rounded-full flex items-center justify-center mr-3">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                        </svg>
                                                    </div>
                                                    <h5 class="text-md font-medium text-gray-800"><?php echo htmlspecialchars($school['name']); ?></h5>
                                                </div>
                                                <div class="pl-12 space-y-1">
                                                    <div class="flex">
                                                        <span class="w-24 text-xs font-medium text-gray-500">Students:</span>
                                                        <!-- <span class="flex-1 text-xs text-gray-800"><?php echo $school['student_count']; ?></span> -->
                                                    </div>
                                                    <div class="flex">
                                                        <span class="w-24 text-xs font-medium text-gray-500">Arrival:</span>
                                                        <span class="flex-1 text-xs text-gray-800"><?php echo date('h:i A', strtotime($school['arrival_time'])); ?></span>
                                                    </div>
                                                    <div class="flex">
                                                        <span class="w-24 text-xs font-medium text-gray-500">Departure:</span>
                                                        <span class="flex-1 text-xs text-gray-800"><?php echo date('h:i A', strtotime($school['departure_time'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php 
                                            $idx++; 
                                            } 
                                        } else { 
                                        ?>
                                            <div class="text-center text-gray-500 p-4">
                                                No schools found for this bus route.
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Close connections
                        if (isset($schools_stmt)) $schools_stmt->close();
                        if (isset($bus_stmt)) $bus_stmt->close();
                        // $conn->close();
                        ?>
                            </div>
                        </div>
                    </div>
                </section>

              
                



                <!-- Tracker Section -->
<section id="tracker-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-72 md:mr-8 mx-4 md:mx-0">
    <!-- Live Bus Location Tracking Section -->
    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mt-4 md:mt-8">
        <div class="p-4 md:p-6 border-b border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
            <div>
                <h3 class="text-base md:text-lg font-semibold heading-brown">Live Route Tracking</h3>
                <p class="text-xs md:text-sm text-gray-500 mt-1">Auto-refreshes every 30 seconds</p>
            </div>
            <div class="flex items-center space-x-3 w-full md:w-auto justify-between md:justify-end">
                <span id="last-updated" class="text-xs text-gray-500">Last updated: 7:35 AM</span>
                <button onclick="refreshLocation()" class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="p-4 md:p-6">
            <!-- Route Information Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-4 mb-4 md:mb-6">
                <div class="bg-blue-50 rounded-lg p-3 md:p-4 text-center">
                    <h4 class="text-base md:text-lg font-medium text-gray-800">07:35 AM</h4>
                    <p class="text-xs text-gray-600">Current Time</p>
                </div>
                <div class="bg-green-50 rounded-lg p-3 md:p-4 text-center">
                    <h4 class="text-base md:text-lg font-medium text-gray-800">35 min</h4>
                    <p class="text-xs text-gray-600">Drive Time</p>
                </div>
                <div class="bg-purple-50 rounded-lg p-3 md:p-4 text-center">
                    <h4 class="text-base md:text-lg font-medium text-gray-800">3 Schools</h4>
                    <p class="text-xs text-gray-600">Destinations</p>
                </div>
                <div class="bg-orange-50 rounded-lg p-3 md:p-4 text-center">
                    <h4 class="text-base md:text-lg font-medium text-gray-800">12 / 21</h4>
                    <p class="text-xs text-gray-600">Pickups Completed</p>
                </div>
            </div>
            
            <!-- Map Container -->
            <div class="relative bg-gray-100 rounded-xl overflow-hidden" style="height: 250px; min-height: 250px; max-height: 400px;">
                <!-- Map Placeholder (would be replaced with actual map API) -->
                <div class="absolute inset-0" id="map-container">
                    <!-- Map image placeholder (would be replaced with actual map API) -->
                    <img src="/api/placeholder/800/400" alt="Route Map" class="w-full h-full object-cover" />
                    
                    <!-- Bus Icon (current location) -->
                    <div class="absolute" style="top: 45%; left: 60%;">
                        <div class="animate-pulse bg-yellow-500 p-1 rounded-full h-6 w-6 md:h-8 md:w-8 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-6 md:w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="bg-black bg-opacity-70 text-white px-1 md:px-2 py-1 rounded text-xs mt-1 text-center">
                            Bus #42
                        </div>
                    </div>
                    
                    <!-- Starting Point -->
                    <div class="absolute" style="top: 80%; left: 20%;">
                        <div class="bg-green-500 p-1 rounded-full h-4 w-4 md:h-6 md:w-6 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 md:h-4 md:w-4 text-white" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="bg-black bg-opacity-70 text-white px-1 md:px-2 py-1 rounded text-xs mt-1 hidden md:block">
                            Start: Depot
                        </div>
                    </div>
                    
                    <!-- Student Pickup Points (Example points) -->
                    <!-- Picked up students -->
                    <div class="absolute" style="top: 70%; left: 30%;">
                        <div class="bg-blue-500 p-1 rounded-full h-4 w-4 md:h-5 md:w-5 flex items-center justify-center">
                            <span class="text-white text-xs">✓</span>
                        </div>
                        <div class="bg-blue-500 text-white px-1 rounded text-xs">S101</div>
                    </div>
                    
                    <div class="absolute" style="top: 60%; left: 40%;">
                        <div class="bg-blue-500 p-1 rounded-full h-4 w-4 md:h-5 md:w-5 flex items-center justify-center">
                            <span class="text-white text-xs">✓</span>
                        </div>
                        <div class="bg-blue-500 text-white px-1 rounded text-xs">S104</div>
                    </div>
                    
                    <!-- Student to be picked up -->
                    <div class="absolute" style="top: 40%; left: 70%;">
                        <div class="bg-orange-500 p-1 rounded-full h-4 w-4 md:h-5 md:w-5 flex items-center justify-center">
                            <span class="text-white text-xs">!</span>
                        </div>
                        <div class="bg-orange-500 text-white px-1 rounded text-xs">S117</div>
                    </div>
                    
                    <div class="absolute" style="top: 30%; left: 75%;">
                        <div class="bg-orange-500 p-1 rounded-full h-4 w-4 md:h-5 md:w-5 flex items-center justify-center">
                            <span class="text-white text-xs">!</span>
                        </div>
                        <div class="bg-orange-500 text-white px-1 rounded text-xs">S118</div>
                    </div>
                    
                    <!-- School Destinations -->
                    <div class="absolute" style="top: 20%; left: 60%;">
                        <div class="bg-purple-500 p-1 rounded-full h-4 w-4 md:h-6 md:w-6 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 md:h-4 md:w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="bg-black bg-opacity-70 text-white px-2 py-1 rounded text-xs mt-1 hidden md:block">
                            Westfield HS
                        </div>
                    </div>
                    
                    <div class="absolute" style="top: 15%; left: 40%;">
                        <div class="bg-purple-500 p-1 rounded-full h-4 w-4 md:h-6 md:w-6 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 md:h-4 md:w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="bg-black bg-opacity-70 text-white px-2 py-1 rounded text-xs mt-1 hidden md:block">
                            Springfield MS
                        </div>
                    </div>
                    
                    <div class="absolute" style="top: 25%; left: 20%;">
                        <div class="bg-purple-500 p-1 rounded-full h-4 w-4 md:h-6 md:w-6 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 md:h-4 md:w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="bg-black bg-opacity-70 text-white px-2 py-1 rounded text-xs mt-1 hidden md:block">
                            Oakridge Elem
                        </div>
                    </div>
                    
                    <!-- Route Path Drawing (simplified) -->
                    <svg class="absolute inset-0" width="100%" height="100%">
                        <!-- Route lines - completed segments -->
                        <path d="M 160,320 L 240,240 L 320,192" stroke="#3B82F6" stroke-width="3" fill="none" stroke-dasharray="none" />
                        <!-- Route lines - upcoming segments -->
                        <path d="M 320,192 L 480,180 L 600,120 L 480,60 L 320,60 L 160,100" stroke="#3B82F6" stroke-width="3" fill="none" stroke-dasharray="5,5" />
                    </svg>
                </div>
                
                <!-- Map Controls Overlay -->
                <div class="absolute bottom-4 right-4 flex flex-col space-y-2">
                    <button class="bg-white p-1 md:p-2 rounded-full shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <button class="bg-white p-1 md:p-2 rounded-full shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <!-- Map Legend Overlay -->
                <div class="absolute top-4 left-4 bg-white bg-opacity-90 p-2 md:p-3 rounded-lg shadow-md max-w-[90px] md:max-w-none">
                    <h4 class="text-xs font-bold mb-1 md:mb-2">Legend</h4>
                    <div class="space-y-1 md:space-y-2">
                        <div class="flex items-center">
                            <div class="w-2 h-2 md:w-3 md:h-3 bg-green-500 rounded-full mr-1 md:mr-2"></div>
                            <span class="text-xs">Start</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-2 h-2 md:w-3 md:h-3 bg-yellow-500 rounded-full mr-1 md:mr-2"></div>
                            <span class="text-xs">Bus</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-2 h-2 md:w-3 md:h-3 bg-blue-500 rounded-full mr-1 md:mr-2"></div>
                            <span class="text-xs">Picked Up</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-2 h-2 md:w-3 md:h-3 bg-orange-500 rounded-full mr-1 md:mr-2"></div>
                            <span class="text-xs">Waiting</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-2 h-2 md:w-3 md:h-3 bg-purple-500 rounded-full mr-1 md:mr-2"></div>
                            <span class="text-xs">School</span>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Next Stops Preview -->
                <div class="mt-4 md:mt-6">
                    <h4 class="text-sm md:text-md font-semibold text-gray-800 mb-2 md:mb-3">Next Stops</h4>
                    <div class="space-y-2 md:space-y-3">
                        <!-- Next Stop 1 -->
                        <div class="bg-orange-50 rounded-lg p-2 md:p-3 flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="bg-orange-100 p-1 md:p-2 rounded-lg mr-2 md:mr-3 flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5 text-orange-600" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs md:text-sm font-medium truncate">Pickup: <span class="text-orange-600">S117 - Jack N.</span></p>
                                    <p class="text-xs text-gray-500 truncate hidden sm:block">456 Maple Dr, Springfield</p>
                                </div>
                            </div>
                            <div class="text-right ml-2 flex-shrink-0">
                                <p class="text-xs md:text-sm font-medium">2 min</p>
                                <p class="text-xs text-gray-500">0.4 mi</p>
                            </div>
                        </div>
                        
                        <!-- Next Stop 2 -->
                        <div class="bg-orange-50 rounded-lg p-2 md:p-3 flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="bg-orange-100 p-1 md:p-2 rounded-lg mr-2 md:mr-3 flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5 text-orange-600" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs md:text-sm font-medium truncate">Pickup: <span class="text-orange-600">S118 - Emily V.</span></p>
                                    <p class="text-xs text-gray-500 truncate hidden sm:block">573 Elm St, Springfield</p>
                                </div>
                            </div>
                            <div class="text-right ml-2 flex-shrink-0">
                                <p class="text-xs md:text-sm font-medium">5 min</p>
                                <p class="text-xs text-gray-500">0.8 mi</p>
                            </div>
                        </div>
                        
                        <!-- School Stop -->
                        <div class="bg-purple-50 rounded-lg p-2 md:p-3 flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="bg-purple-100 p-1 md:p-2 rounded-lg mr-2 md:mr-3 flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs md:text-sm font-medium truncate">Drop-off: <span class="text-purple-600">Westfield High School</span></p>
                                    <p class="text-xs text-gray-500 truncate hidden sm:block">1200 Education Blvd, Springfield</p>
                                </div>
                            </div>
                            <div class="text-right ml-2 flex-shrink-0">
                                <p class="text-xs md:text-sm font-medium">12 min</p>
                                <p class="text-xs text-gray-500">3.2 mi</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location Tracking Controls -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mt-4 md:mt-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full sm:w-auto">
                        <div class="flex items-center">
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" name="toggle" id="auto-refresh" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked />
                                <label for="auto-refresh" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                            <label for="auto-refresh" class="text-xs text-gray-700">Auto-refresh</label>
                        </div>
                        <select class="text-xs border border-gray-300 rounded-lg px-2 py-1 w-full sm:w-auto">
                            <option>Update every 30s</option>
                            <option>Update every 1m</option>
                            <option>Update every 5m</option>
                        </select>
                    </div>
                    <button class="bg-orange-500 hover:bg-orange-600 text-white py-1 md:py-2 px-3 md:px-4 text-xs md:text-sm rounded-lg font-medium transition-colors w-full sm:w-auto">
                        Navigate in Maps
                    </button>
                </div>

                <!-- JavaScript for location refresh functionality -->
                <script>
                // Function to refresh location
                function refreshLocation() {
                    // In a real implementation, this would get geolocation data
                    // and update the map view accordingly
                    
                    // Update last refreshed time
                    const now = new Date();
                    const hours = now.getHours().toString().padStart(2, '0');
                    const mins = now.getMinutes().toString().padStart(2, '0');
                    const timeString = `${hours}:${mins} ${hours >= 12 ? 'PM' : 'AM'}`;
                    
                    document.getElementById('last-updated').textContent = `Last updated: ${timeString}`;
                    
                    // Show updating effect (pulse)
                    const busIcon = document.querySelector('[style*="top: 45%; left: 60%"]');
                    busIcon.classList.add('scale-110');
                    setTimeout(() => {
                        busIcon.classList.remove('scale-110');
                    }, 300);
                    
                    console.log("Location refreshed at " + timeString);
                }

                // Set up auto refresh if enabled
                let refreshInterval;

                function setupAutoRefresh() {
                    const autoRefreshToggle = document.getElementById('auto-refresh');
                    
                    if (autoRefreshToggle.checked) {
                        refreshInterval = setInterval(refreshLocation, 30000); // 30 seconds
                        console.log("Auto-refresh enabled");
                    } else {
                        clearInterval(refreshInterval);
                        console.log("Auto-refresh disabled");
                    }
                }

                // Initialize auto-refresh
                document.addEventListener('DOMContentLoaded', function() {
                    setupAutoRefresh();
                    
                    // Add event listener for toggle changes
                    document.getElementById('auto-refresh').addEventListener('change', setupAutoRefresh);
                    
                    // For mobile: adjust map height based on screen size
                    function adjustMapHeight() {
                        const mapContainer = document.querySelector('.relative.bg-gray-100.rounded-xl');
                        if (window.innerWidth < 640) { // Mobile
                            mapContainer.style.height = '250px';
                        } else if (window.innerWidth < 1024) { // Tablet
                            mapContainer.style.height = '320px';
                        } else { // Desktop
                            mapContainer.style.height = '400px';
                        }
                    }
                    
                    // Run on load and resize
                    adjustMapHeight();
                    window.addEventListener('resize', adjustMapHeight);
                });
                </script>

                <style>
                /* Custom toggle switch styling */
                .toggle-checkbox:checked {
                    right: 0;
                    border-color: #FF8C00;
                }
                .toggle-checkbox:checked + .toggle-label {
                    background-color: #FF8C00;
                }

                /* Additional responsive styles */
                @media (max-width: 640px) {
                    .shadow-enhanced {
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                    }
                }
                </style>
                </div>
                </div>
                </section>




                <!-- Previous Routings Section -->
                <section id="history" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-14 md:mr-8 mx-4 md:mx-0">

                </section>







                






                <!-- Account Settings Section -->
                <?php
                // Database connection
                $conn = new mysqli("localhost", "root", "", "school_bus_management");
                if ($conn->connect_error) {
                    die("Connection failed: " . $conn->connect_error);
                }

                // Get driver data
                $driver_id = $_SESSION['driver_id']; // Assuming driver_id is stored in session
                $sql = "SELECT * FROM driver WHERE driver_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $driver_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $driver = $result->fetch_assoc();

                // Handle form submission for driver info update
                if(isset($_POST['save_changes'])) {
                    $sql = "UPDATE driver SET 
                            full_name = ?,
                            email = ?,
                            phone = ?,
                            license_number = ?,
                            license_expiry_date = ?,
                            experience_years = ?,
                            age = ?
                            WHERE driver_id = ?";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssssiis", 
                        $_POST['full_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['license_number'],
                        $_POST['license_expiry_date'],
                        $_POST['experience_years'],
                        $_POST['age'],
                        $driver_id
                    );
                    
                    if($stmt->execute()) {
                        echo "<script>alert('Information updated successfully!');</script>";
                        // Refresh the page to show updated info
                        echo "<script>window.location.reload();</script>";
                    } else {
                        echo "<script>alert('Error updating information');</script>";
                    }
                }
                ?>

                <section id="settings-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-72 md:mr-8 mx-4 md:mx-0">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
                        <div class="flex items-center space-x-3">
                            <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                            <h2 class="text-3xl font-bold heading-brown">Account Settings</h2>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mb-6">
                        <div class="p-4 border-b border-gray-100">
                            <h3 class="text-lg font-semibold heading-brown">Driver Information</h3>
                        </div>
                        
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                    <p class="p-2 bg-gray-50 rounded-lg"><?php echo htmlspecialchars($driver['full_name']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <p class="p-2 bg-gray-50 rounded-lg"><?php echo htmlspecialchars($driver['email']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                    <p class="p-2 bg-gray-50 rounded-lg"><?php echo htmlspecialchars($driver['phone']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">License Number</label>
                                    <p class="p-2 bg-gray-50 rounded-lg"><?php echo htmlspecialchars($driver['license_number']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">License Expiry Date</label>
                                    <p class="p-2 bg-gray-50 rounded-lg"><?php echo $driver['license_expiry_date']; ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Years of Experience</label>
                                    <p class="p-2 bg-gray-50 rounded-lg"><?php echo $driver['experience_years']; ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                                    <p class="p-2 bg-gray-50 rounded-lg"><?php echo $driver['age']; ?></p>
                                </div>
                            </div>
                            <div class="flex justify-end mt-4">
                                <button onclick="showEditModal()" class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-6 rounded-lg transform transition-all duration-300 ease-in-out hover:scale-105 hover:shadow-lg active:scale-95">
                                    Edit Information
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-100">
                                <h4 class="font-medium text-yellow-800 mb-2">Change Password</h4>
                                <p class="text-sm text-yellow-700 mb-4">Update your account password. Make sure to use a strong password.</p>
                                <button onclick="showPasswordModal()" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-6 rounded-lg transform transition-all duration-300 ease-in-out hover:scale-105 hover:shadow-lg active:scale-95">
                                    Change Password
                                </button>
                            </div>
                            <br>
                            <div class="bg-red-50 p-4 rounded-lg border border-red-100">
                                <h4 class="font-medium text-red-800 mb-2">Delete Account</h4>
                                <p class="text-sm text-red-700 mb-4">Once you delete your account, there is no going back. Please be certain.</p>
                                <button onclick="showDeleteModal()" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-6 rounded-lg transform transition-all duration-300 ease-in-out hover:scale-105 hover:shadow-lg active:scale-95">
                                    Delete My Account
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Edit Information Modal -->
                <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 px-4">
                    <div class="relative top-20 mx-auto p-5 border shadow-lg rounded-md bg-white w-full sm:w-[90%] md:w-[80%] lg:w-[600px]">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Driver Information</h3>
                            <form method="POST">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Full Name</label>
                                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($driver['full_name']); ?>" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Email</label>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($driver['email']); ?>" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Phone Number</label>
                                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($driver['phone']); ?>" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">License Number</label>
                                        <input type="text" name="license_number" value="<?php echo htmlspecialchars($driver['license_number']); ?>" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">License Expiry Date</label>
                                        <input type="date" name="license_expiry_date" value="<?php echo $driver['license_expiry_date']; ?>" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Years of Experience</label>
                                        <input type="number" name="experience_years" value="<?php echo $driver['experience_years']; ?>" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Age</label>
                                        <input type="number" name="age" value="<?php echo $driver['age']; ?>" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500">
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end space-x-3">
                                    <button type="button" onclick="hideEditModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                                    <button type="submit" name="save_changes" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Password Change Modal -->
                <div id="passwordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Change Password</h3>
                            <form id="passwordForm" method="POST" action="update_password.php">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Current Password</label>
                                        <input type="password" name="current_password" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">New Password</label>
                                        <input type="password" name="new_password" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Confirm New Password</label>
                                        <input type="password" name="confirm_password" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-500">
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end space-x-3">
                                    <button type="button" onclick="hidePasswordModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">Update Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Delete Account Modal -->
                <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-red-900 mb-4">Delete Account</h3>
                            <p class="text-sm text-gray-600 mb-4">Are you absolutely sure you want to delete your account? This action cannot be undone.</p>
                            <form method="POST" action="delete_account.php">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Enter Password to Confirm</label>
                                        <input type="password" name="confirm_password" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-red-300 focus:border-red-500">
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end space-x-3">
                                    <button type="button" onclick="hideDeleteModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Delete Account</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <script>
                function showEditModal() {
                    document.getElementById('editModal').classList.remove('hidden');
                }

                function hideEditModal() {
                    document.getElementById('editModal').classList.add('hidden');
                }

                function showPasswordModal() {
                    document.getElementById('passwordModal').classList.remove('hidden');
                }

                function hidePasswordModal() {
                    document.getElementById('passwordModal').classList.add('hidden');
                }

                function showDeleteModal() {
                    document.getElementById('deleteModal').classList.remove('hidden');
                }

                function hideDeleteModal() {
                    document.getElementById('deleteModal').classList.add('hidden');
                }
                </script>



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
        
        // Update active navigation item styling for desktop
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.classList.remove('active');
        });
        
        // Find the nav item that corresponds to the selected section and make it active
        const navItemsArray = Array.from(navItems);
        const activeNavItem = navItemsArray.find(item => item.textContent.trim().toLowerCase().includes(sectionId.toLowerCase()));
        if (activeNavItem) {
            activeNavItem.classList.add('active');
        }
        
        // Update mobile navigation styling
        const mobileNavItems = document.querySelectorAll('.mobile-nav-item');
        mobileNavItems.forEach(item => {
            item.classList.remove('text-orange-500');
            item.classList.add('text-gray-700');
        });
        
        // Find the mobile nav item that corresponds to the selected section and make it active
        const mobileNavItemsArray = Array.from(mobileNavItems);
        const activeMobileNavItem = mobileNavItemsArray.find(item => 
            item.textContent.trim().toLowerCase().includes(sectionId.toLowerCase()));
        if (activeMobileNavItem) {
            activeMobileNavItem.classList.remove('text-gray-700');
            activeMobileNavItem.classList.add('text-orange-500');
        }
    }
    
    // Initialize the dashboard to show the home section by default
    document.addEventListener('DOMContentLoaded', function() {
        // Create placeholder sections for other pages if they don't exist
        const sectionIds = ['tracker', 'payments','history', 'settings'];
        const mainContent = document.querySelector('main');
        
        sectionIds.forEach(id => {
            if (!document.getElementById(id + '-section')) {
                const section = document.createElement('section');
                section.id = id + '-section';
                section.className = 'dashboard-section';
                section.style.display = 'none';
                
                const header = document.createElement('div');
                header.className = 'flex flex-col md:flex-row items-start md:items-center justify-between mb-8';
                header.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                        <h2 class="text-3xl font-bold text-orange-800">${id.charAt(0).toUpperCase() + id.slice(1)}</h2>
                    </div>
                `;
                
                const content = document.createElement('div');
                content.className = 'bg-white rounded-2xl p-8 shadow-sm border border-orange-100';
                content.innerHTML = `<p>Content for ${id} section will be displayed here.</p>`;
                
                section.appendChild(header);
                section.appendChild(content);
                mainContent.appendChild(section);
            }
        });
        
        // Profile dropdown functionality
        const profileButton = document.getElementById('profile-menu-button');
        if (profileButton) {
            let isMenuOpen = false;
            let menuElement = null;
            
            profileButton.addEventListener('click', function() {
                if (isMenuOpen && menuElement) {
                    menuElement.remove();
                    isMenuOpen = false;
                    return;
                }
                
                menuElement = document.createElement('div');
                menuElement.className = 'origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50';
                menuElement.innerHTML = `
                    <div class="py-1" role="menu" aria-orientation="vertical">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Your Profile</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Account Settings</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Sign out</a>
                    </div>
                `;
                
                document.querySelector('.flex-shrink-0.relative').appendChild(menuElement);
                isMenuOpen = true;
                
                // Close menu when clicking outside
                document.addEventListener('click', function closeMenu(e) {
                    if (!profileButton.contains(e.target) && !menuElement.contains(e.target)) {
                        menuElement.remove();
                        isMenuOpen = false;
                        document.removeEventListener('click', closeMenu);
                    }
                });
            });

            const historyButton = document.querySelector('button[onclick="showSection(\'history\')"]');
            if (historyButton) {
                historyButton.addEventListener('click', function() {
                    const sections = document.querySelectorAll('.dashboard-section');
                    sections.forEach(section => {
                        section.style.display = 'none';
                    });
                    
                    const historySection = document.getElementById('history');
                    if (historySection) {
                        historySection.style.display = 'block';
                    }
                    
                    // Update active states
                    const navItems = document.querySelectorAll('.nav-item');
                    navItems.forEach(item => item.classList.remove('active'));
                    historyButton.classList.add('active');
                });
            }
        }
        
        // Show home section by default
        showSection('home');
    });

    function switchProfile(childId) {
        // Example profiles data - in production this would come from your backend
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
        
        profileDisplay.innerHTML = `
            <img src="${profile.image}" alt="${profile.name}" class="w-20 h-20 rounded-full object-cover border-4 border-orange-200"/>
            <div>
                <h4 class="text-xl font-medium text-gray-800">${profile.name}</h4>
                <p class="text-gray-500">Grade ${profile.grade} • Bus #${profile.busNo}</p>
            </div>
        `;
    }
</script>
</body>
</html>