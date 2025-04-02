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
try {
    $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Add this after your session check and database connection
try {
    // Fetch parent information
    $parentStmt = $pdo->prepare("
        SELECT full_name, email, phone, home_address 
        FROM parent 
        WHERE parent_id = :parent_id
    ");
    $parentStmt->execute(['parent_id' => $_SESSION['parent_id']]);
    $parentInfo = $parentStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error appropriately
    error_log("Error fetching parent info: " . $e->getMessage());
    $parentInfo = [
        'full_name' => '',
        'email' => '',
        'phone' => '',
        'home_address' => ''
    ];
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







// Get children for this parent
$parentId = $_SESSION['parent_id'];
$sql = "SELECT * FROM child WHERE parent_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$parentId]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get currently selected child (default to first child if none selected)
$selectedChildId = isset($_GET['child_id']) ? $_GET['child_id'] : (count($children) > 0 ? $children[0]['child_id'] : 0);

// Get selected child's details
$selectedChild = null;
foreach ($children as $child) {
    if ($child['child_id'] == $selectedChildId) {
        $selectedChild = $child;
        break;
    }
}

// In your PHP section where you fetch child details, modify the query:

$childStmt = $pdo->prepare("
    SELECT 
        c.*,
        s.name as school_name,
        s.address as school_address,
        b.bus_number,
        b.license_plate,
        b.starting_location,
        b.covering_cities
    FROM child c
    LEFT JOIN school s ON c.school_id = s.school_id
    LEFT JOIN bus b ON c.bus_id = b.bus_id
    WHERE c.child_id = :child_id AND c.parent_id = :parent_id
");

$childStmt->execute([
    'child_id' => $selectedChildId,
    'parent_id' => $_SESSION['parent_id']
]);
$childDetails = $childStmt->fetch(PDO::FETCH_ASSOC);
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
        /* Button hover animations */
        .button-hover-effect {
            position: relative;
            overflow: hidden;
        }

        .button-hover-effect::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 50%);
            transform: translate(-50%, -50%) scale(0);
            transition: transform 0.3s ease-out;
        }

        .button-hover-effect:hover::after {
            transform: translate(-50%, -50%) scale(2);
        }

        /* Disable styles for modal buttons */
        .modal-button-disabled {
            opacity: 0.7;
            cursor: not-allowed;
            pointer-events: none;
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
                <h1 class="ml-3 text-xl font-bold text-white">Parent Portal</h1>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2c3.866 0 7 3.134 7 7 0 5.25-7 11-7 11s-7-5.75-7-11c0-3.866 3.134-7 7-7zM12 9a2 2 0 110 4 2 2 0 010-4z" />
                    </svg>
                    Tracker
                </button>
                <button onclick="showSection('history')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    History
                </button>
                <button onclick="showSection('payments')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Payments
                </button>
                <button onclick="showSection('children')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Children
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2c3.866 0 7 3.134 7 7 0 5.25-7 11-7 11s-7-5.75-7-11c0-3.866 3.134-7 7-7zM12 9a2 2 0 110 4 2 2 0 010-4z" />
                </svg>
                <span>Tracker</span>
            </button>
            <button onclick="showSection('history')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>History</span>
            </button>
            <button onclick="showSection('payments')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>Payments</span>
            </button>
            <button onclick="showSection('children')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Children</span>
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
                            <h1 class="text-xl font-bold text-white heading-brown md:hidden">Parent Portal</h1>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="flex-shrink-0 relative flex items-center">
                            <span id="profile-name" class="hidden md:inline-block mr-3 font-medium text-grey order-first"><?php echo htmlspecialchars($_SESSION['parent_name']); ?></span>
                            <!-- <button id="profile-menu-button" class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 order-last">
                                <img class="h-8 w-8 rounded-full object-cover border-2 border-orange-200" src="https://randomuser.me/api/portraits/women/44.jpg" alt="Profile">
                            </button> -->
                            <button id="profileButton" 
                                    onclick="openLogoutModal()" 
                                    class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 order-last">
                                <img class="h-8 w-8 rounded-full object-cover border-2 border-orange-200" 
                                     src="https://randomuser.me/api/portraits/women/44.jpg" 
                                     alt="Profile">
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Add this right after the header section -->
        <div id="logoutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-[9999]">
            <div class="relative top-20 mx-auto p-5 border w-80 shadow-lg rounded-md bg-white">
                <div class="flex flex-col items-center">
                    <!-- Warning Icon -->
                    <div class="mb-4">
                        <svg class="h-12 w-12 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirm Logout</h3>
                    <p class="text-sm text-gray-500 text-center mb-6">Are you sure you want to logout from your account?</p>
                    
                    <div class="flex space-x-4">
                        <button onclick="closeLogoutModal()" 
                                class="px-4 py-2 bg-gray-500 text-white text-sm rounded-lg hover:bg-gray-600 transition-colors">
                            Cancel
                        </button>
                        <a href="logout.php" 
                           class="px-4 py-2 bg-yellow-500 text-white text-sm rounded-lg hover:bg-yellow-600 transition-colors">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

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
                        <p class="text-gray-600 mt-2 md:mt-0">Welcome back, <span class="font-medium"><?php echo htmlspecialchars($_SESSION['parent_name']); ?></span></p>
                    </div>

                    <!-- Child Profile Switcher -->
                    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 p-6 mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold heading-brown">Current Child Profile</h3>
                            
                            <!-- Simple Select Dropdown -->
                            <div class="relative">
                                <form action="" method="get" id="childSelectForm">
                                    <select name="child_id" id="childSelect" class="bg-orange-50 px-4 py-2 rounded-lg border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-300" onchange="this.form.submit()">
                                        <?php foreach ($children as $child): ?>
                                            <option value="<?php echo $child['child_id']; ?>" <?php echo ($child['child_id'] == $selectedChildId) ? 'selected' : ''; ?>>
                                                <?php echo $child['first_name'] . ' ' . $child['last_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Current Active Child Display -->
                        <?php if ($selectedChild): ?>
                        <div id="currentChildProfile" class="flex items-center space-x-4">
                            <img src="<?php echo !empty($selectedChild['photo_url']) ? $selectedChild['photo_url'] : 'assets\img\student1.jpg'; ?>" 
                                alt="<?php echo $selectedChild['first_name'] . ' ' . $selectedChild['last_name']; ?>" 
                                class="w-20 h-20 rounded-full object-cover border-4 border-orange-200"/>
                            <div>
                                <h4 class="text-xl font-medium text-gray-800"><?php echo $selectedChild['first_name'] . ' ' . $selectedChild['last_name']; ?></h4>
                                <!-- <p class="text-gray-500">Grade <?php echo $selectedChild['grade']; ?> • Bus #<?php echo $selectedChild['bus_id']; ?></p> -->
                                <p class="text-gray-500">Grade <?php echo $selectedChild['grade']; ?></p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center p-4 text-gray-500">No children found</div>
                        <?php endif; ?>
                    </div>

                    <!-- Stats Overview -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                     
                    </div>

                    <!-- Student Profile and Transportation Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Student Details (Left Side) -->
                        <div>
                            <!-- Student Profile Details -->
                            <?php if ($selectedChild): ?>
                            <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mb-6">
                                <div class="p-6 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold heading-brown">Student Profile</h3>
                                </div>
                                <div class="p-6">
                                    <div class="flex flex-col items-center mb-6">
                                        <!-- Student Image -->
                                        <div class="w-24 h-24 rounded-full overflow-hidden mb-3">
                                            <img src="<?php echo !empty($selectedChild['photo_url']) ? $selectedChild['photo_url'] : 'assets\img\student1.jpg'; ?>" 
                                                alt="Student Photo" class="w-full h-full object-cover" />
                                        </div>
                                        <!-- Student Name -->
                                        <h4 class="text-lg font-medium text-gray-800"><?php echo $selectedChild['first_name'] . ' ' . $selectedChild['last_name']; ?></h4>
                                    </div>

                                    <!-- Student Details -->
                                    <div class="space-y-4">
                                        <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">School:</span>
                                            <span class="flex-1 text-sm text-gray-800">
                                                <?php 
                                                // Get school name from school_id
                                                $schoolId = $selectedChild['school_id'];
                                                $schoolName = "Not assigned";
                                                
                                                if ($schoolId) {
                                                    $schoolSql = "SELECT name FROM school WHERE school_id = ?";
                                                    $schoolStmt = $pdo->prepare($schoolSql);
                                                    $schoolStmt->execute([$schoolId]);
                                                    if ($schoolRow = $schoolStmt->fetch(PDO::FETCH_ASSOC)) {
                                                        $schoolName = $schoolRow['name'];
                                                    }
                                                }
                                                echo $schoolName;
                                                ?>
                                            </span>
                                        </div>

                                        <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">Grade:</span>
                                            <span class="flex-1 text-sm text-gray-800"><?php echo $selectedChild['grade']; ?></span>
                                        </div>

                                        <!-- <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">Bus:</span>
                                            <span class="flex-1 text-sm text-gray-800">#<?php echo $selectedChild['bus_id']; ?></span>
                                        </div> -->

                                        <!-- <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">Pickup:</span>
                                            <span class="flex-1 text-sm text-gray-800"><?php echo $selectedChild['pickup_location']; ?></span>
                                        </div> -->

                                        <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">Medical Notes:</span>
                                            <span class="flex-1 text-sm text-gray-800"><?php echo $selectedChild['medical_notes']; ?></span>
                                        </div>

                                        <div class="flex">
                                            <span class="w-32 text-sm font-medium text-gray-500">Emergency:</span>
                                            <span class="flex-1 text-sm text-gray-800"><?php echo $selectedChild['emergency_contact']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                            <!-- Attendance and Fee Status -->
                            <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mb-6">
                                <div class="p-6 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold heading-brown">Attendance & Fee Status</h3>
                                </div>
                                <div class="p-6">
                                    <!-- Fee Status Box -->
                                    <div class="bg-green-50 rounded-xl p-4 mb-6">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-800">March 2025 Fees</h4>
                                                <p class="mt-1 text-xs text-gray-600">Due: 5th of every month</p>
                                            </div>
                                            <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                                PAID
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Attendance Marking -->
                                    <div class="flex flex-col">
                                        <div id="attendanceStatus" class="bg-green-50 rounded-xl p-4 mb-4">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-800">Today's Attendance</h4>
                                                    <p id="currentDate" class="text-xs text-gray-500"></p>
                                                </div>
                                                <div id="attendanceLabel" class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                                    Today's Present
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex space-x-3 justify-end">
                                            <button onclick="markAttendance('present')" class="py-2 px-4 bg-green-500 text-white rounded-xl text-sm font-medium shadow-lg transform transition-all duration-200 hover:shadow-green-200 hover:-translate-y-1 active:translate-y-0 active:shadow-inner border border-green-600">
                                                Present
                                            </button>
                                            <button onclick="markAttendance('absent')" class="py-2 px-4 bg-red-500 text-white rounded-xl text-sm font-medium shadow-lg transform transition-all duration-200 hover:shadow-red-200 hover:-translate-y-1 active:translate-y-0 active:shadow-inner border border-red-600">
                                                Absent
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <script>
                                        // Display current date
                                        function formatDate(date) {
                                            const options = { year: 'numeric', month: 'long', day: 'numeric' };
                                            return date.toLocaleDateString('en-US', options);
                                        }
                                        
                                        document.getElementById('currentDate').textContent = formatDate(new Date());
                                        
                                        function markAttendance(status) {
                                            const statusDiv = document.getElementById('attendanceStatus');
                                            const label = document.getElementById('attendanceLabel');
                                            
                                            if (status === 'present') {
                                                statusDiv.className = 'bg-green-50 rounded-xl p-4 mb-4';
                                                label.className = 'bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium';
                                                label.textContent = 'Today\'s Present';
                                            } else {
                                                statusDiv.className = 'bg-red-50 rounded-xl p-4 mb-4';
                                                label.className = 'bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium';
                                                label.textContent = 'Today\'s Absent';
                                            }
                                        }
                                    </script>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bus Information (Right Side) -->
                        <div>
                            <!-- Replace or update your existing transportation div -->
                            <div class="bg-white rounded-xl shadow-sm p-6 relative overflow-hidden">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-800">Transportation</h3>
                                    <?php if ($childDetails['bus_id']): ?>
                                        <span class="px-3 py-1 text-sm bg-green-100 text-green-800 rounded-full">Active</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-sm bg-yellow-100 text-yellow-800 rounded-full">Not Assigned</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($childDetails['bus_id']): ?>
                                    <div class="space-y-4">
                                        <!-- Bus Details -->
                                        <div class="flex items-start space-x-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v6a2 2 0 002 2h2" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="text-sm font-medium text-gray-900">Bus Information</h4>
                                                <div class="mt-2 grid grid-cols-2 gap-4 text-sm">
                                                    <div>
                                                        <p class="text-gray-500">Bus Number</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($childDetails['bus_number']); ?></p>
                                                    </div>
                                                    <div>
                                                        <p class="text-gray-500">License Plate</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($childDetails['license_plate']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Route Details -->
                                        <div class="flex items-start space-x-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="text-sm font-medium text-gray-900">Route Information</h4>
                                                <div class="mt-2 space-y-2 text-sm">
                                                    <div>
                                                        <p class="text-gray-500">Starting Location</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($childDetails['starting_location']); ?></p>
                                                    </div>
                                                    <!-- <div>
                                                        <p class="text-gray-500">Covering Areas</p>
                                                        <p class="font-medium text-gray-900">
                                                            <?php 
                                                            $cities = explode(',', $childDetails['covering_cities']);
                                                            echo htmlspecialchars(implode(', ', array_map('trim', $cities))); 
                                                            ?>
                                                        </p>
                                                    </div> -->
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Pickup Location -->
                                        <!-- <div class="flex items-start space-x-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="text-sm font-medium text-gray-900">Pickup Location</h4>
                                                <div class="mt-2 text-sm">
                                                    <p class="text-gray-500">Current Pickup Point</p>
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($childDetails['pickup_location']); ?></p>
                                                </div>
                                            </div>
                                        </div> -->
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-6">
                                        <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Bus Assigned</h3>
                                        <p class="text-gray-500 text-sm mb-4">This child has not been assigned to a bus route yet.</p>
                                        <a href="add_child.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                                            Request Transportation
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                        
                       
                        </div>
                    </div>
                </section>

                























                <!-- Tracker Section -->
                <section id="tracker-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-72 md:mr-8 mx-4 md:mx-0">

                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
                        <div class="flex items-center space-x-3">
                            <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                            <h2 class="text-3xl font-bold heading-brown">Bus Tracker</h2>
                        </div>
                        <div class="flex items-center mt-4 md:mt-0">
                            <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium flex items-center">
                                <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                Live Tracking
                            </div>
                            <span class="ml-2 text-sm text-gray-500">Last updated: Just now</span>
                        </div>
                    </div>

                    <!-- Main Content Area -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Map Section (2/3 width on large screens) -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden h-full">
                                <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                                    <h3 class="text-lg font-semibold heading-brown">Live Location</h3>
                                    <button class="btn-primary text-sm px-4 py-2 rounded-lg">Refresh</button>
                                </div>
                                <div class="relative" style="height: 400px;">
                                    <!-- Map Container -->
                                    <div class="absolute inset-0 bg-gray-200">
                                        <!-- Placeholder for map - in production, you'd use a real map service -->
                                        <img src="/api/placeholder/800/400" alt="Map" class="w-full h-full object-cover" />
                                        
                                        <!-- Driver Card (positioned on the map) -->
                                        <div class="absolute top-4 right-4 bg-white rounded-xl shadow-lg p-3 w-64 flex items-center">
                                            <div class="w-12 h-12 rounded-full overflow-hidden mr-3 flex-shrink-0 border-2 border-orange-300">
                                                <img src="/api/placeholder/100/100" alt="Driver" class="w-full h-full object-cover" />
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-medium text-gray-800">Mr. Robert Davis</h4>
                                                <div class="flex items-center mt-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-orange-500 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span class="text-sm text-gray-600">Bus #42</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Bus Icon (positioned on the map) -->
                                        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
                                            <div class="bg-orange-500 text-white rounded-full h-10 w-10 flex items-center justify-center shadow-lg">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8m-8 5h8m-4 5v-3m-4 3v-3" />
                                                    <path d="M4 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-4 bg-gray-50 border-t border-gray-100">
                                    <div class="flex flex-wrap gap-4">
                                        <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                            <div class="text-xs text-gray-500">Current Speed</div>
                                            <div class="text-lg font-medium">25 mph</div>
                                        </div>
                                        <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                            <div class="text-xs text-gray-500">ETA to Next Stop</div>
                                            <div class="text-lg font-medium">7 min</div>
                                        </div>
                                        <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                            <div class="text-xs text-gray-500">Distance to Home</div>
                                            <div class="text-lg font-medium">2.3 miles</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Details Section (1/3 width on large screens) -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden h-full">
                                <div class="p-4 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold heading-brown">Route Details</h3>
                                </div>
                                
                                <!-- Student Pickup & Drop-off Box -->
                                <div class="p-4 border-b border-gray-100 bg-orange-50">
                                    <div class="flex items-start">
                                        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <h4 class="font-medium text-gray-800">Alex Johnson</h4>
                                            <div class="grid grid-cols-2 gap-4 mt-2">
                                                <div>
                                                    <div class="text-xs text-gray-500">Pick-up</div>
                                                    <div class="text-sm font-medium">7:15 AM</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500">Drop-off</div>
                                                    <div class="text-sm font-medium">3:45 PM</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Route Timeline -->
                                <div class="p-4 overflow-y-auto" style="max-height: 350px;">
                                    <h4 class="text-sm font-medium text-gray-700 mb-4">Route Timeline</h4>
                                    
                                    <div class="relative">
                                        <!-- Timeline Line -->
                                        <div class="absolute top-0 bottom-0 left-4 w-0.5 bg-gray-200"></div>
                                        
                                        <!-- Timeline Stops -->
                                        <div class="space-y-6">
                                            <!-- Stop 1 -->
                                            <div class="relative flex items-start">
                                                <div class="absolute left-4 w-3 h-3 bg-green-500 rounded-full transform -translate-x-1.5 mt-1.5"></div>
                                                <div class="ml-8">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h5 class="text-sm font-medium text-gray-800">School Departure</h5>
                                                            <p class="text-xs text-gray-500">Westfield High School</p>
                                                        </div>
                                                        <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">Completed</span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 mt-1">3:15 PM</p>
                                                </div>
                                            </div>
                                            
                                            <!-- Stop 2 -->
                                            <div class="relative flex items-start">
                                                <div class="absolute left-4 w-3 h-3 bg-green-500 rounded-full transform -translate-x-1.5 mt-1.5"></div>
                                                <div class="ml-8">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h5 class="text-sm font-medium text-gray-800">Maple Avenue</h5>
                                                            <p class="text-xs text-gray-500">1st Stop</p>
                                                        </div>
                                                        <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">Completed</span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 mt-1">3:25 PM</p>
                                                </div>
                                            </div>
                                            
                                            <!-- Stop 3 -->
                                            <div class="relative flex items-start">
                                                <div class="absolute left-4 w-4 h-4 bg-orange-500 border-2 border-white rounded-full transform -translate-x-2 mt-1 shadow-md"></div>
                                                <div class="ml-8">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h5 class="text-sm font-medium text-gray-800">Oak Street</h5>
                                                            <p class="text-xs text-gray-500">2nd Stop - Current Location</p>
                                                        </div>
                                                        <span class="text-xs bg-orange-100 text-orange-800 px-2 py-0.5 rounded">In Progress</span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 mt-1">3:32 PM</p>
                                                </div>
                                            </div>
                                            
                                            <!-- Stop 4 (Alex's Stop) -->
                                            <div class="relative flex items-start">
                                                <div class="absolute left-4 w-3 h-3 bg-gray-300 rounded-full transform -translate-x-1.5 mt-1.5"></div>
                                                <div class="ml-8">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h5 class="text-sm font-medium text-gray-800">Education Lane</h5>
                                                            <p class="text-xs text-gray-500">3rd Stop - Alex's Stop</p>
                                                        </div>
                                                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Your Stop</span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 mt-1">3:45 PM (ETA)</p>
                                                </div>
                                            </div>
                                            
                                            <!-- Stop 5 -->
                                            <div class="relative flex items-start">
                                                <div class="absolute left-4 w-3 h-3 bg-gray-300 rounded-full transform -translate-x-1.5 mt-1.5"></div>
                                                <div class="ml-8">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h5 class="text-sm font-medium text-gray-800">Pine Road</h5>
                                                            <p class="text-xs text-gray-500">4th Stop</p>
                                                        </div>
                                                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">Upcoming</span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 mt-1">3:55 PM (ETA)</p>
                                                </div>
                                            </div>
                                            
                                            <!-- Stop 6 -->
                                            <div class="relative flex items-start">
                                                <div class="absolute left-4 w-3 h-3 bg-gray-300 rounded-full transform -translate-x-1.5 mt-1.5"></div>
                                                <div class="ml-8">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h5 class="text-sm font-medium text-gray-800">Cedar Avenue</h5>
                                                            <p class="text-xs text-gray-500">Final Stop</p>
                                                        </div>
                                                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">Upcoming</span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 mt-1">4:05 PM (ETA)</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Contact Driver Button -->
                                <div class="p-4 border-t border-gray-100">
                                    <button class="w-full btn-gradient py-3 rounded-xl flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        Contact Driver
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mobile & Tablet Push Notifications Section -->
                    <div class="mt-6 bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden md:flex md:items-center p-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold heading-brown">Receive Bus Notifications</h3>
                            <p class="text-sm text-gray-600 mt-1">Get notifications when the bus is approaching your stop</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <div class="flex items-center">
                                <span class="text-sm text-gray-600 mr-3">Enable notifications</span>
                                <label class="relative inline-block w-12 h-6">
                                    <input type="checkbox" class="opacity-0 w-0 h-0">
                                    <span class="absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:content-[''] before:absolute before:w-4 before:h-4 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300 checked:bg-orange-500 checked:before:translate-x-6"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>





























                <!-- Previous Routings Section -->
                <section id="history-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-72 md:mr-8 mx-4 md:mx-0">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
                        <div class="flex items-center space-x-3">
                            <!-- <div class="h-10 w-1 bg-blue-500 rounded-full"></div> -->
                            <h2 class="text-3xl font-bold heading-brown">Previous Routings</h2>
                        </div>
                        <div class="flex items-center mt-4 md:mt-0">
                            <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium flex items-center">
                                <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                Historical Data
                            </div>
                            <button class="ml-3 text-sm text-gray-500 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                                </svg>
                                Filter
                            </button>
                        </div>
                    </div>

                    <!-- Main Content Area -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Previous Routes List (2/3 width on large screens) -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-2xl shadow-enhanced border border-blue-100 overflow-hidden h-full">
                                <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                                    <h3 class="text-lg font-semibold heading-brown">Past 7 Days</h3>
                                    <div class="flex space-x-2">
                                        <button class="text-sm px-3 py-1 rounded-lg bg-gray-100 text-gray-700">Week</button>
                                        <button class="text-sm px-3 py-1 rounded-lg bg-gray-50 text-gray-500">Month</button>
                                    </div>
                                </div>
                                <div class="overflow-y-auto" style="max-height: 400px;">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Driver</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Arrival</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <!-- Day 1 -->
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">Mar 12, 2025</div>
                                                    <div class="text-xs text-gray-500">Wednesday</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 rounded-full overflow-hidden border border-gray-200">
                                                            <img src="/api/placeholder/100/100" alt="Driver" class="h-full w-full" />
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900">Robert Davis</div>
                                                            <div class="text-xs text-gray-500">Bus #42</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">3:43 PM</div>
                                                    <div class="text-xs text-gray-500">2 min early</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        On Time
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <button class="text-blue-600 hover:text-blue-800">View Route</button>
                                                </td>
                                            </tr>
                                            <!-- Day 2 -->
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">Mar 11, 2025</div>
                                                    <div class="text-xs text-gray-500">Tuesday</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 rounded-full overflow-hidden border border-gray-200">
                                                            <img src="/api/placeholder/100/100" alt="Driver" class="h-full w-full" />
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900">Robert Davis</div>
                                                            <div class="text-xs text-gray-500">Bus #42</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">3:48 PM</div>
                                                    <div class="text-xs text-gray-500">3 min late</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Slight Delay
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <button class="text-blue-600 hover:text-blue-800">View Route</button>
                                                </td>
                                            </tr>
                                            <!-- Day 3 -->
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">Mar 10, 2025</div>
                                                    <div class="text-xs text-gray-500">Monday</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 rounded-full overflow-hidden border border-gray-200">
                                                            <img src="/api/placeholder/100/100" alt="Driver" class="h-full w-full" />
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900">Sarah Johnson</div>
                                                            <div class="text-xs text-gray-500">Bus #42</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">3:52 PM</div>
                                                    <div class="text-xs text-gray-500">7 min late</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                        Delayed
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <button class="text-blue-600 hover:text-blue-800">View Route</button>
                                                </td>
                                            </tr>
                                            <!-- Days 4-7 -->
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">Mar 7, 2025</div>
                                                    <div class="text-xs text-gray-500">Friday</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 rounded-full overflow-hidden border border-gray-200">
                                                            <img src="/api/placeholder/100/100" alt="Driver" class="h-full w-full" />
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900">Robert Davis</div>
                                                            <div class="text-xs text-gray-500">Bus #42</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">3:44 PM</div>
                                                    <div class="text-xs text-gray-500">1 min early</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        On Time
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <button class="text-blue-600 hover:text-blue-800">View Route</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics & Analysis (1/3 width on large screens) -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-2xl shadow-enhanced border border-blue-100 overflow-hidden h-full">
                                <div class="p-4 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold heading-brown">Route Statistics</h3>
                                </div>
                                
                                <!-- Statistics Summary -->
                                <div class="p-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="bg-gray-50 rounded-lg p-3">
                                            <div class="text-xs text-gray-500">On-Time Rate</div>
                                            <div class="text-xl font-medium text-gray-800">85%</div>
                                            <div class="text-xs text-green-600 flex items-center mt-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                                </svg>
                                                5% from last week
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 rounded-lg p-3">
                                            <div class="text-xs text-gray-500">Avg. Arrival Time</div>
                                            <div class="text-xl font-medium text-gray-800">3:46 PM</div>
                                            <div class="text-xs text-gray-600 mt-1">1 min late avg.</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Route Time Trends Graph -->
                                    <div class="mt-4 p-3 bg-white border border-gray-100 rounded-lg">
                                        <h4 class="text-sm font-medium text-gray-700 mb-2">7-Day Arrival Trend</h4>
                                        <div class="h-40 w-full bg-gray-50 rounded">
                                            <!-- Placeholder for graph - would be a real chart in production -->
                                            <img src="/api/placeholder/300/160" alt="Arrival trend graph" class="w-full h-full object-cover" />
                                        </div>
                                    </div>
                                    
                                <!-- Performance Summary -->
                                    <div class="mt-4">
                                        <h4 class="text-sm font-medium text-gray-700 mb-2">Performance Summary</h4>
                                        <div class="space-y-3">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs text-gray-600">On Time (±2 min)</span>
                                                <span class="text-xs font-medium">4 days</span>
                                                <div class="w-32 bg-gray-200 rounded-full h-2">
                                                    <div class="bg-green-500 h-2 rounded-full" style="width: 57%"></div>
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs text-gray-600">Slight Delay (3-5 min)</span>
                                                <span class="text-xs font-medium">2 days</span>
                                                <div class="w-32 bg-gray-200 rounded-full h-2">
                                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 29%"></div>
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs text-gray-600">Delayed (>5 min)</span>
                                                <span class="text-xs font-medium">1 day</span>
                                                <div class="w-32 bg-gray-200 rounded-full h-2">
                                                    <div class="bg-red-500 h-2 rounded-full" style="width: 14%"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Driver Performance -->
                                        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full overflow-hidden mr-3 flex-shrink-0 border-2 border-blue-300">
                                                    <img src="/api/placeholder/100/100" alt="Driver" class="w-full h-full object-cover" />
                                                </div>
                                                <div>
                                                    <h5 class="text-sm font-medium text-gray-800">Robert Davis</h5>
                                                    <div class="flex items-center mt-1">
                                                        <div class="flex items-center text-yellow-500">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                            </svg>
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                            </svg>
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                            </svg>
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                            </svg>
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="gray" viewBox="0 0 24 24">
                                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                            </svg>
                                                            <span class="text-xs text-gray-600 ml-1">4.0/5.0</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Weather Impact Analysis -->
                                    <div class="mt-4 p-4 border-t border-gray-100">
                                        <h4 class="text-sm font-medium text-gray-700 mb-2">Weather Impact</h4>
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                                                </svg>
                                                <span class="text-xs text-gray-700">Rain on March 10</span>
                                            </div>
                                            <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded">+7 min delay</span>
                                        </div>
                                        <p class="text-xs text-gray-500">Weather conditions contributed to the delay on Monday due to reduced visibility and traffic congestion.</p>
                                    </div>

                                    <!-- Download Report Button -->
                                    <div class="p-4 border-t border-gray-100">
                                        <button class="w-full bg-blue-500 text-white py-2 rounded-lg flex items-center justify-center hover:bg-blue-600 transition duration-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            Download Weekly Report
                                        </button>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>







                <?php
                // Database connection
                $servername = "localhost";
                $username = "root"; // Replace with your database username
                $password = ""; // Replace with your database password
                $dbname = "school_bus_management";

                // Create connection
                $conn = new mysqli($servername, $username, $password, $dbname);

                // Check connection
                if ($conn->connect_error) {
                    die("Connection failed: " . $conn->connect_error);
                }

                // Get selected child_id (from session, URL parameter, or form submission)
                $child_id = isset($_GET['child_id']) ? intval($_GET['child_id']) : null;

                // If no child_id is provided, redirect or show error
                if (!$child_id) {
                    // Either redirect to a selection page or get the first child of the logged-in parent
                    // For this example, we'll assume we get the first child
                    $parent_id = isset($_SESSION['parent_id']) ? intval($_SESSION['parent_id']) : 1; // Default for example
                    
                    $sql = "SELECT child_id FROM child WHERE parent_id = ? LIMIT 1";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $parent_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $child_id = $row['child_id'];
                    } else {
                        echo "No children found for this parent.";
                        exit;
                    }
                    $stmt->close();
                }

                // Get child information
                $child_info = [];
                $sql = "SELECT child_id, first_name, last_name, joined_date FROM child WHERE child_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $child_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $child_info = $result->fetch_assoc();
                } else {
                    echo "Child not found.";
                    exit;
                }
                $stmt->close();

                // Get the monthly fee for this child (could be in a separate table, using a fixed amount for this example)
                $monthly_fee = 1000.00;

                // Get current date information
                $current_date = new DateTime();
                $current_month = $current_date->format('F');
                $current_year = $current_date->format('Y');
                $current_month_name = $current_date->format('F');
                $due_date = new DateTime();
                $due_date->setDate($current_year, $current_date->format('n'), 15);
                $due_date_formatted = $due_date->format('F d, Y');

                // Calculate academic year (assuming September to June)
                $academic_year_start = $current_date->format('n') >= 9 ? $current_year : $current_year - 1;
                $academic_year_end = $academic_year_start + 1;
                $academic_year = $academic_year_start . "-" . $academic_year_end;

                // Get joined date
                $joined_date = new DateTime($child_info['joined_date']);
                $joined_month_name = $joined_date->format('M');
                $joined_year = $joined_date->format('Y');

                // Get payments for this child
                $payments = [];
                $sql = "SELECT payment_id, amount, payment_date, payment_method, transaction_id, status, 
                            month_covered, MONTH(month_covered) as month_num, YEAR(month_covered) as year_num 
                        FROM payment 
                        WHERE child_id = ? 
                        ORDER BY month_covered DESC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $child_id);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $payments[] = $row;
                }
                $stmt->close();

                // Check if current month is paid
                $current_month_paid = false;
                $current_month_start = new DateTime($current_year . '-' . $current_date->format('m') . '-01');
                $current_month_end = clone $current_month_start;
                $current_month_end->modify('last day of this month');

                foreach ($payments as $payment) {
                    $payment_month = new DateTime($payment['month_covered']);
                    if ($payment_month->format('Y-m') === $current_month_start->format('Y-m') && $payment['status'] === 'completed') {
                        $current_month_paid = true;
                        break;
                    }
                }

                // Get the last payment date
                $last_payment_date = !empty($payments) ? new DateTime($payments[0]['payment_date']) : null;
                $last_payment_formatted = $last_payment_date ? $last_payment_date->format('M d, Y') : "No payments yet";

                // Calculate payment progress based on academic year (January to December or from registration to December)
                $start_month = 1; // January
                $end_month = 12; // December

                // If child joined in current year, use joined date as start
                if ($joined_date->format('Y') == $current_year) {
                    $start_month = (int)$joined_date->format('n');
                }

                // Calculate months in current year (or since joining if joined this year)
                $total_months_this_year = 13 - $start_month; // From start_month to December

                // Count paid months in current year
                $months_paid_this_year = 0;
                $paid_months_this_year = [];

                foreach ($payments as $payment) {
                    if ($payment['status'] === 'completed' && $payment['year_num'] == $current_year) {
                        $month_key = $payment['month_num'];
                        if (!in_array($month_key, $paid_months_this_year) && $month_key >= $start_month) {
                            $paid_months_this_year[] = $month_key;
                            $months_paid_this_year++;
                        }
                    }
                }

                // Calculate progress percentage for current year
                $progress_percentage = $total_months_this_year > 0 ? round(($months_paid_this_year / $total_months_this_year) * 100) : 0;
                $progress_text = $months_paid_this_year . '/' . $total_months_this_year . ' months paid';

                // Format start and end months for display
                $start_date = new DateTime($current_year . '-' . $start_month . '-01');
                $end_date = new DateTime($current_year . '-12-31');
                $start_month_name = $start_date->format('M');
                $end_month_name = $end_date->format('M');

                // Check if current month is paid
                $current_month_paid = false;
                $current_month_num = $current_date->format('m'); // Get just the month number (04 for April)

                foreach ($payments as $payment) {
                    $payment_month = new DateTime($payment['month_covered']);
                    $payment_month_num = $payment_month->format('m'); // Get just the payment month number
                    
                    // Compare just the month number and check if status is completed
                    if ($payment_month_num === $current_month_num && $payment['status'] === 'completed') {
                        $current_month_paid = true;
                        break;
                    }
                }

                // Close connection
                $conn->close();
                ?>

                <!-- Payment Section with PHP integration -->
                <section id="payments-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-72 md:mr-8 mx-4 md:mx-0">
                    <!-- Header with Child Info -->
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
                        <div class="flex items-center space-x-3">
                            <h2 class="text-3xl font-bold heading-brown">Payment History</h2>
                            <span id="child-name" class="text-lg text-gray-600">for <?php echo htmlspecialchars($child_info['first_name'] . ' ' . $child_info['last_name']); ?></span>
                        </div>
                        <div class="flex items-center mt-4 md:mt-0">
                            <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium flex items-center">
                                <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                <span id="payment-status"><?php echo $current_month_paid ? 'Current' : 'Payment Due'; ?></span>
                            </div>
                            <span id="last-payment-date" class="ml-2 text-sm text-gray-500">Last payment: <?php echo $last_payment_formatted; ?></span>
                        </div>
                    </div>

                    <!-- Main Content Area -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Current Payment Status (2/3 width on large screens) -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden h-full">
                                <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                                    <h3 class="text-lg font-semibold heading-brown">Current Payment Status</h3>
                                    <span class="text-sm text-gray-500" id="academic-year">For Year <?php echo $academic_year; ?></span>
                                </div>
                                
                                <!-- Current Month Payment Status -->
                                <div class="p-6">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="w-16 h-16 <?php echo $current_month_paid ? 'bg-green-100' : 'bg-red-100'; ?> rounded-full flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 <?php echo $current_month_paid ? 'text-green-600' : 'text-red-600'; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <div class="ml-4">
                                                <h4 class="text-xl font-semibold text-gray-800" id="current-month-payment"><?php echo $current_month_name; ?> Payment</h4>
                                                <p class="text-sm <?php echo $current_month_paid ? 'text-green-600' : 'text-red-600'; ?> mt-1" id="payment-due-date">
                                                    <?php echo $current_month_paid ? 'Paid' : 'Due by: ' . $due_date_formatted; ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4 md:mt-0 flex flex-col items-end">
                                            <div class="flex items-center">
                                                <?php if (!$current_month_paid): ?>
                                                <span class="mr-2 text-lg font-bold" id="monthly-fee">Rs.<?php echo number_format($monthly_fee, 2); ?></span>
                                                <span id="current-payment-status" class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">Unpaid</span>
                                                <?php else: ?>
                                                <span id="current-payment-status" class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">Paid</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!$current_month_paid): ?>
                                            <a href="payment_gateway/payment_gateway.html?child_id=<?php echo $child_id; ?>&month=<?php echo $current_month_name; ?>" 
                                            class="btn-gradient text-sm px-6 py-2 rounded-lg mt-2 text-white cursor-pointer hover:opacity-90 transition duration-300 ease-in-out inline-block" style="background-image: linear-gradient(to right, #f97316, #facc15); transform: translateZ(0);">
                                                Pay Now
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Progress (UPDATED) -->
                                    <div class="mt-8">
                                        <div class="flex justify-between items-center mb-2">
                                            <h4 class="text-sm font-medium text-gray-700">Payment Progress <?php echo $current_year; ?></h4>
                                            <span class="text-sm text-gray-500" id="payment-progress"><?php echo $progress_text; ?></span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div id="progress-bar" class="bg-yellow-500 h-2.5 rounded-full" style="width: <?php echo $progress_percentage; ?>%"></div>
                                        </div>
                                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                                            <span id="start-date"><?php echo $start_month_name . ' ' . $current_year; ?></span>
                                            <span id="end-date"><?php echo $end_month_name . ' ' . $current_year; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Methods (UPDATED) -->
                                <div class="p-4 bg-gray-50 border-t border-gray-100">
                                    <div class="flex flex-wrap gap-4">
                                        <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                            <div class="text-xs text-gray-500">Accepted Payment Methods</div>
                                            <div class="text-sm font-medium flex items-center mt-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                </svg>
                                                <span class="mr-3">VISA</span>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                </svg>
                                                <span class="mr-3">Mastercard</span>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-800 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                </svg>
                                                <span>Amex</span>
                                            </div>
                                        </div>
                                        <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                            <div class="text-xs text-gray-500">Monthly Fee</div>
                                            <div class="text-lg font-medium">Rs. <?php echo number_format($monthly_fee, 2); ?></div>
                                        </div>
                                        <!-- <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                            <div class="text-xs text-gray-500">Yearly Plan Savings</div>
                                            <div class="text-sm font-medium text-green-600">Save Rs.<?php echo number_format($monthly_fee, 2); ?> with yearly plan</div>
                                        </div> -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Options Section (1/3 width on large screens) -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-2xl shadow-enhanced border border-blue-100 overflow-hidden h-full">
                                <div class="p-4 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold heading-brown">Payment Options</h3>
                                </div>
                                
                                <!-- Payment Plans -->
                                <div class="p-4 border-b border-gray-100">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center">
                                            <!-- <input id="monthly-plan" name="payment-plan" type="radio" checked class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300"> -->
                                            <label for="monthly-plan" class="ml-2 text-sm font-medium text-gray-700">Monthly Plan</label>
                                        </div>
                                        <span class="text-sm font-medium">Rs. <?php echo number_format($monthly_fee, 2); ?>/month</span>
                                    </div>
                                    <!-- <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <input id="yearly-plan" name="payment-plan" type="radio" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300">
                                            <label for="yearly-plan" class="ml-2 text-sm font-medium text-gray-700">Yearly Plan</label>
                                        </div>
                                        <span class="text-sm font-medium">Rs.<?php echo number_format($monthly_fee * 9, 2); ?>/year <span class="text-green-600 text-xs">(Save $<?php echo number_format($monthly_fee, 2); ?>)</span></span>
                                    </div> -->
                                </div>
                                
                                <!-- Payment History -->
                                <div class="p-4 overflow-y-auto" style="max-height: 350px;">
                                    <h4 class="text-sm font-medium text-gray-700 mb-4">Payment History</h4>
                                    
                                    <div class="relative">
                                        <!-- Timeline Line -->
                                        <div class="absolute top-0 bottom-0 left-4 w-0.5 bg-gray-200"></div>
                                        
                                        <!-- Timeline Payments -->
                                        <div class="space-y-5">
                                            <?php if (empty($payments)): ?>
                                                <p class="ml-8 text-sm text-gray-600">No payment history available.</p>
                                            <?php else: ?>
                                                <?php foreach ($payments as $payment): 
                                                    $payment_date = new DateTime($payment['payment_date']);
                                                    $month_covered = new DateTime($payment['month_covered']);
                                                    $month_name = $month_covered->format('F');
                                                    $transaction_id = $payment['transaction_id'];
                                                ?>
                                                <div class="relative flex items-start">
                                                    <div class="absolute left-4 w-3 h-3 <?php echo $payment['status'] === 'completed' ? 'bg-green-500' : 'bg-yellow-500'; ?> rounded-full transform -translate-x-1.5 mt-1.5"></div>
                                                    <div class="ml-8">
                                                        <div class="flex justify-between items-start">
                                                            <div>
                                                                <h5 class="text-sm font-medium text-gray-800"><?php echo $month_name; ?> Payment</h5>
                                                                <p class="text-xs text-gray-500">Transaction ID: <?php echo htmlspecialchars($transaction_id); ?></p>
                                                            </div>
                                                            <span class="text-xs <?php echo $payment['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?> px-2 py-0.5 rounded">
                                                                <?php echo ucfirst($payment['status']); ?>
                                                            </span>
                                                        </div>
                                                        <div class="flex justify-between mt-1">
                                                            <p class="text-xs text-gray-600"><?php echo $payment_date->format('M d, Y'); ?></p>
                                                            <p class="text-xs font-medium">Rs.<?php echo number_format($payment['amount'], 2); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Download Receipts Button -->
                                <!-- <div class="p-4 border-t border-gray-100">
                                    <button class="w-full btn-secondary py-3 rounded-xl flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Download All Receipts
                                    </button>
                                </div> -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Auto-Pay Section -->
                    <!-- <div class="mt-6 bg-white rounded-2xl shadow-enhanced border border-blue-100 overflow-hidden md:flex md:items-center p-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold heading-brown">Enable Auto-Pay</h3>
                            <p class="text-sm text-gray-600 mt-1">Never miss a payment with automatic monthly billing</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <div class="flex items-center">
                                <span class="text-sm text-gray-600 mr-3">Auto-pay is disabled</span>
                                <label class="relative inline-block w-12 h-6">
                                    <input type="checkbox" class="opacity-0 w-0 h-0">
                                    <span class="absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:content-[''] before:absolute before:w-4 before:h-4 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300 checked:bg-blue-500 checked:before:translate-x-6"></span>
                                </label>
                            </div>
                        </div>
                    </div> -->
                </section>







                



                <!-- Children Details Section -->
                <section id="children-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-72 md:mr-8 mx-4 md:mx-0">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
                        <div class="flex items-center space-x-3">
                            <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                            <h2 class="text-3xl font-bold heading-brown">Children Details</h2>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mb-6">
                        <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="text-lg font-semibold heading-brown">Your Children</h3>
                            <a href="add_child.php" class="bg-yellow-500 text-white text-sm flex items-center px-4 py-2 rounded-lg hover:bg-yellow-600 transition duration-300 transform hover:scale-105 shadow-sm cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add Child
                            </a>
                        </div>
                        
                        <?php
                        // Get parent's children with more detailed information
                        $childrenStmt = $pdo->prepare("
                            SELECT c.child_id, c.first_name, c.last_name, c.grade, c.medical_notes, 
                                   c.photo_url, c.pickup_location, c.emergency_contact,
                                   s.name as school_name, s.school_id,
                                   b.bus_number, b.bus_id
                            FROM child c 
                            LEFT JOIN school s ON c.school_id = s.school_id 
                            LEFT JOIN bus b ON c.bus_id = b.bus_id 
                            WHERE c.parent_id = :parent_id
                        ");
                        $childrenStmt->execute(['parent_id' => $_SESSION['parent_id']]);
                        $children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Fetch all schools for dropdown
                        $schoolStmt = $pdo->prepare("SELECT school_id, name FROM school ORDER BY name");
                        $schoolStmt->execute();
                        $schools = $schoolStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Fetch all buses for dropdown
                        $busStmt = $pdo->prepare("SELECT bus_id, bus_number FROM bus WHERE is_active = 1 ORDER BY bus_number");
                        $busStmt->execute();
                        $buses = $busStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($children) > 0) {
                            foreach ($children as $index => $child) {
                                $borderClass = $index < count($children) - 1 ? 'border-b border-gray-100' : '';
                        ?>
                        <!-- Child Card -->
                        <div class="p-6 <?php echo $borderClass; ?>">
                            <div class="flex flex-col md:flex-row">
                                <!-- <div class="w-24 h-24 rounded-full overflow-hidden mb-4 md:mb-0 mx-auto md:mx-0 bg-gray-200 flex items-center justify-center">
                                    <?php if (!empty($child['photo_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($child['photo_url']); ?>" alt="<?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>" class="w-full h-full object-cover" />
                                    <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <?php endif; ?>
                                </div> -->
                                <div class="md:ml-6 flex-1">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Child's Name</label>
                                            <p class="p-2 border border-gray-300 rounded-lg bg-gray-50"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                                            <p class="p-2 border border-gray-300 rounded-lg bg-gray-50"><?php echo htmlspecialchars($child['grade']); ?></p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">School</label>
                                            <p class="p-2 border border-gray-300 rounded-lg bg-gray-50"><?php echo htmlspecialchars($child['school_name'] ?? 'Not assigned'); ?></p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Bus Route</label>
                                            <p class="p-2 border border-gray-300 rounded-lg bg-gray-50"><?php echo htmlspecialchars($child['bus_number'] ?? 'Not assigned'); ?></p>
                                        </div>
                                        <?php if (!empty($child['medical_notes'])): ?>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Special Notes (allergies, medical conditions)</label>
                                            <p class="p-2 border border-gray-300 rounded-lg bg-gray-50 min-h-[60px]"><?php echo htmlspecialchars($child['medical_notes']); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end mt-4 space-x-3">
                            <button type="button" onclick="openDeleteChildModal(<?php echo $child['child_id']; ?>, '<?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>')" 
                                        class="text-red-500 text-sm px-4 py-2 border border-red-200 rounded-lg hover:bg-red-50 transition duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-red-300 cursor-pointer">
                                    <span class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Delete Child
                                    </span>
                                </button>
                                <button type="button" onclick="openEditChildModal(<?php echo $child['child_id']; ?>)" 
                                        class="text-orange-500 text-sm px-4 py-2 border border-orange-200 rounded-lg hover:bg-orange-50 transition duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-orange-300 cursor-pointer">
                                    <span class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                        Edit Details
                                    </span>
                                </button>
                            </div>
                        </div>
                        <?php
                            }
                        } else {
                        ?>
                        <div class="p-6 text-center">
                            <p class="text-gray-500">You haven't added any children yet.</p>
                            <a href="add_child.php" class="inline-block mt-4 px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">Add Your First Child</a>
                                </div>
                        <?php } ?>
                    </div>
                </section>

                <!-- Edit Child Modal -->
                <div id="editChildModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-[99999]">
                    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-lg bg-white">
                        <div class="flex justify-between items-center pb-3 border-b">
                            <h3 class="text-xl font-semibold text-gray-700">Edit Child Information</h3>
                            <button onclick="closeEditChildModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <form id="editChildForm" method="post" action="update_child.php">
                            <input type="hidden" id="edit_child_id" name="child_id" value="">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                    <input type="text" id="edit_first_name" name="first_name" class="w-full p-2 border border-gray-300 rounded-lg">
                                        </div>
                                        <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                    <input type="text" id="edit_last_name" name="last_name" class="w-full p-2 border border-gray-300 rounded-lg">
                                        </div>
                                        <div>
                                    <label for="grade" class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                                    <input type="text" id="edit_grade" name="grade" class="w-full p-2 border border-gray-300 rounded-lg">
                                        </div>
                                        <div>
                                    <label for="school_id" class="block text-sm font-medium text-gray-700 mb-1">School</label>
                                    <select id="edit_school_id" name="school_id" class="w-full p-2 border border-gray-300 rounded-lg">
                                        <option value="">Select School</option>
                                        <?php foreach ($schools as $school): ?>
                                        <option value="<?php echo $school['school_id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                        </div>
                                        <div>
                                    <label for="bus_id" class="block text-sm font-medium text-gray-700 mb-1">Bus</label>
                                    <select id="edit_bus_id" name="bus_id" class="w-full p-2 border border-gray-300 rounded-lg">
                                        <option value="">Select Bus</option>
                                        <?php foreach ($buses as $bus): ?>
                                        <option value="<?php echo $bus['bus_id']; ?>"><?php echo htmlspecialchars($bus['bus_number']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                        </div>
                                        <div>
                                    <label for="emergency_contact" class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact</label>
                                    <input type="text" id="edit_emergency_contact" name="emergency_contact" class="w-full p-2 border border-gray-300 rounded-lg">
                                        </div>
                                <div class="md:col-span-2">
                                    <label for="medical_notes" class="block text-sm font-medium text-gray-700 mb-1">Medical Notes</label>
                                    <textarea id="edit_medical_notes" name="medical_notes" class="w-full p-2 border border-gray-300 rounded-lg" rows="3"></textarea>
                                        </div>
                                        </div>
                            <div class="flex justify-end mt-6 space-x-3">
                                <button type="button" onclick="closeEditChildModal()" class="px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Delete Child Modal -->
                <div id="deleteChildModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-[99999]">
                    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-96 shadow-lg rounded-lg bg-white">
                        <div class="flex justify-between items-center pb-3 border-b">
                            <h3 class="text-xl font-semibold text-gray-700">Confirm Deletion</h3>
                            <button onclick="closeDeleteChildModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="py-4">
                            <p class="text-gray-700" id="deleteChildText">Are you sure you want to delete this child?</p>
                            </div>
                        <form id="deleteChildForm" method="post" action="delete_child.php">
                            <input type="hidden" id="delete_child_id" name="child_id" value="">
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeDeleteChildModal()" class="px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    // ... existing code ...
                    
                    // Child modals functions
                    function openEditChildModal(childId) {
                        // Fetch child details via AJAX
                        fetch(`get_child_details.php?child_id=${childId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const child = data.child;
                                    // Populate the form
                                    document.getElementById('edit_child_id').value = child.child_id;
                                    document.getElementById('edit_first_name').value = child.first_name;
                                    document.getElementById('edit_last_name').value = child.last_name;
                                    document.getElementById('edit_grade').value = child.grade;
                                    document.getElementById('edit_emergency_contact').value = child.emergency_contact || '';
                                    document.getElementById('edit_medical_notes').value = child.medical_notes || '';
                                    
                                    // Set select options
                                    if (child.school_id) {
                                        document.getElementById('edit_school_id').value = child.school_id;
                                    }
                                    if (child.bus_id) {
                                        document.getElementById('edit_bus_id').value = child.bus_id;
                                    }
                                    
                                    // Show the modal
                                    document.getElementById('editChildModal').classList.remove('hidden');
                            } else {
                                    alert('Error fetching child details');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                                alert('Error fetching child details');
                            });
                    }
                    
                    function closeEditChildModal() {
                        document.getElementById('editChildModal').classList.add('hidden');
                    }
                    
                    function openDeleteChildModal(childId, childName) {
                        document.getElementById('delete_child_id').value = childId;
                        document.getElementById('deleteChildText').textContent = `Are you sure you want to delete ${childName}?`;
                        document.getElementById('deleteChildModal').classList.remove('hidden');
                    }
                    
                    function closeDeleteChildModal() {
                        document.getElementById('deleteChildModal').classList.add('hidden');
                    }
                    
                    // Close modals when clicking outside
                    document.getElementById('editChildModal').addEventListener('click', function(e) {
                        if (e.target === this) {
                            closeEditChildModal();
                        }
                    });
                    
                    document.getElementById('deleteChildModal').addEventListener('click', function(e) {
                        if (e.target === this) {
                            closeDeleteChildModal();
                        }
                    });
                    
                    // Close modals on escape key press
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            closeEditChildModal();
                            closeDeleteChildModal();
                        }
                    });
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

                <!-- Add this JavaScript for handling the modal -->
                <script>
                function openLogoutModal() {
                    const modal = document.getElementById('logoutModal');
                    if (modal) {
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                }

                function closeLogoutModal() {
                    const modal = document.getElementById('logoutModal');
                    if (modal) {
                        modal.classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }
                }

                // Close modal when clicking outside
                document.addEventListener('DOMContentLoaded', function() {
                    const modal = document.getElementById('logoutModal');
                    if (modal) {
                        modal.addEventListener('click', function(e) {
                            if (e.target === this) {
                                closeLogoutModal();
                            }
                        });
                    }

                    // Close modal on escape key press
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            closeLogoutModal();
                        }
                    });
                });
                </script>



                <style>
                /* Ensure modal is always on top */
                #logoutModal,
                #updateModal,
                #changePasswordModal,
                #deleteAccountModal,
                #editChildModal,
                #deleteChildModal {
                    z-index: 99999 !important;
                }

                /* Add animation for modal */
                @keyframes modalFade {
                    from {
                        opacity: 0;
                        transform: translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                #logoutModal > div,
                #updateModal > div,
                #changePasswordModal > div,
                #deleteAccountModal > div,
                #editChildModal > div,
                #deleteChildModal > div {
                    animation: modalFade 0.3s ease-out;
                }

                /* Hover effects for buttons */
                #logoutModal button:hover,
                #logoutModal a:hover,
                #updateModal button:hover,
                #changePasswordModal button:hover,
                #deleteAccountModal button:hover,
                #editChildModal button:hover,
                #deleteChildModal button:hover {
                    transform: translateY(-1px);
                    transition: all 0.2s;
                }

                /* Add shadow to modal */
                #logoutModal > div,
                #updateModal > div,
                #changePasswordModal > div,
                #deleteAccountModal > div,
                #editChildModal > div,
                #deleteChildModal > div {
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                }
                </style>

                <script>
                function openLogoutModal() {
                    const modal = document.getElementById('logoutModal');
                    if (modal) {
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                }

                function closeLogoutModal() {
                    const modal = document.getElementById('logoutModal');
                    if (modal) {
                        modal.classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }
                }

                // Modal functions for Account Settings
                function openUpdateModal() {
                    document.getElementById('updateModal').classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }

                function closeUpdateModal() {
                    document.getElementById('updateModal').classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }

                function openChangePasswordModal() {
                    document.getElementById('changePasswordModal').classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }

                function closeChangePasswordModal() {
                    document.getElementById('changePasswordModal').classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }

                function openDeleteAccountModal() {
                    document.getElementById('deleteAccountModal').classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }

                function closeDeleteAccountModal() {
                    document.getElementById('deleteAccountModal').classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }

                // Child modals functions
                function openEditChildModal(childId) {
                    // Fetch child details via AJAX
                    fetch(`get_child_details.php?child_id=${childId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const child = data.child;
                                // Populate the form
                                document.getElementById('edit_child_id').value = child.child_id;
                                document.getElementById('edit_first_name').value = child.first_name;
                                document.getElementById('edit_last_name').value = child.last_name;
                                document.getElementById('edit_grade').value = child.grade;
                                document.getElementById('edit_emergency_contact').value = child.emergency_contact || '';
                                document.getElementById('edit_medical_notes').value = child.medical_notes || '';
                                
                                // Set select options
                                if (child.school_id) {
                                    document.getElementById('edit_school_id').value = child.school_id;
                                }
                                if (child.bus_id) {
                                    document.getElementById('edit_bus_id').value = child.bus_id;
                                }
                                
                                // Show the modal
                                document.getElementById('editChildModal').classList.remove('hidden');
                                document.body.style.overflow = 'hidden';
                            } else {
                                alert('Error fetching child details');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error fetching child details');
                        });
                }
                
                function closeEditChildModal() {
                    document.getElementById('editChildModal').classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
                
                function openDeleteChildModal(childId, childName) {
                    document.getElementById('delete_child_id').value = childId;
                    document.getElementById('deleteChildText').textContent = `Are you sure you want to delete ${childName}?`;
                    document.getElementById('deleteChildModal').classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }
                
                function closeDeleteChildModal() {
                    document.getElementById('deleteChildModal').classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }

                // Close modals when clicking outside
                document.addEventListener('DOMContentLoaded', function() {
                    // Setup for existing modals
                    const modals = [
                        { id: 'logoutModal', closeFn: closeLogoutModal },
                        { id: 'updateModal', closeFn: closeUpdateModal },
                        { id: 'changePasswordModal', closeFn: closeChangePasswordModal },
                        { id: 'deleteAccountModal', closeFn: closeDeleteAccountModal },
                        { id: 'editChildModal', closeFn: closeEditChildModal },
                        { id: 'deleteChildModal', closeFn: closeDeleteChildModal }
                    ];
                    
                    modals.forEach(modal => {
                        const el = document.getElementById(modal.id);
                        if (el) {
                            el.addEventListener('click', function(e) {
                                if (e.target === this) {
                                    modal.closeFn();
                                }
                            });
                        }
                    });

                    // Close all modals on escape key press
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            modals.forEach(modal => {
                                const el = document.getElementById(modal.id);
                                if (el && !el.classList.contains('hidden')) {
                                    modal.closeFn();
                                }
                            });
                        }
                    });
                });
                </script>

                <!-- Account Settings Section -->
                <section id="settings-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-72 md:mr-8 mx-4 md:mx-0">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
                        <div class="flex items-center space-x-3">
                            <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                            <h2 class="text-3xl font-bold heading-brown">Account Settings</h2>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mb-6">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-semibold heading-brown">Personal Information</h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Full Name</label>
                                    <p class="mt-1 text-gray-900">
                                        <?php echo isset($parentInfo['full_name']) ? htmlspecialchars($parentInfo['full_name']) : 'Not available'; ?>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Email</label>
                                    <p class="mt-1 text-gray-900">
                                        <?php echo isset($parentInfo['email']) ? htmlspecialchars($parentInfo['email']) : 'Not available'; ?>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Phone</label>
                                    <p class="mt-1 text-gray-900">
                                        <?php echo !empty($parentInfo['phone']) ? htmlspecialchars($parentInfo['phone']) : 'Not provided'; ?>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Home Address</label>
                                    <p class="mt-1 text-gray-900">
                                        <?php echo isset($parentInfo['home_address']) ? htmlspecialchars($parentInfo['home_address']) : 'Not available'; ?>
                                    </p>
                                </div>
                            </div>

                            <div class="mt-8 flex flex-col md:flex-row gap-4">
                                <!-- Update Information Button -->
                                <button onclick="openUpdateModal()" 
                                        class="inline-flex items-center justify-center px-6 py-2 bg-yellow-500 text-white rounded-lg 
                                            hover:bg-yellow-600 transform hover:-translate-y-0.5 transition-all duration-200 
                                            focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    Update Information
                                </button>

                                <!-- Change Password Button -->
                                <button onclick="openChangePasswordModal()" 
                                        class="inline-flex items-center justify-center px-6 py-2 bg-blue-500 text-white rounded-lg 
                                            hover:bg-blue-600 transform hover:-translate-y-0.5 transition-all duration-200 
                                            focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Change Password
                                </button>

                                <!-- Delete Account Button -->
                                <button onclick="openDeleteAccountModal()" 
                                        class="inline-flex items-center justify-center px-6 py-2 border-2 bg-red-500 text-white rounded-lg 
                                            hover:bg-red-600 transform hover:-translate-y-0.5 transition-all duration-200 
                                            focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Update Information Modal -->
                <div id="updateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-[99999]">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold text-gray-900">Update Information</h3>
                            <button onclick="closeUpdateModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form id="updateForm" class="space-y-4" method="post" action="update_parent_info.php">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($parentInfo['full_name']); ?>" class="mt-1 w-full rounded-lg border border-gray-300 p-2 focus:ring-2 focus:ring-orange-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($parentInfo['phone']); ?>" class="mt-1 w-full rounded-lg border border-gray-300 p-2 focus:ring-2 focus:ring-orange-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Home Address</label>
                                <textarea name="home_address" class="mt-1 w-full rounded-lg border border-gray-300 p-2 focus:ring-2 focus:ring-orange-300"><?php echo htmlspecialchars($parentInfo['home_address']); ?></textarea>
                            </div>
                            <div class="flex justify-end space-x-3 mt-6">
                                <button type="button" onclick="closeUpdateModal()" class="px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password Modal -->
                <div id="changePasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-[99999]">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold text-gray-900">Change Password</h3>
                            <button onclick="closeChangePasswordModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form id="changePasswordForm" class="space-y-4" method="post" action="change_password.php">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Current Password</label>
                                <input type="password" name="current_password" required class="mt-1 w-full rounded-lg border border-gray-300 p-2 focus:ring-2 focus:ring-orange-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">New Password</label>
                                <input type="password" name="new_password" required class="mt-1 w-full rounded-lg border border-gray-300 p-2 focus:ring-2 focus:ring-orange-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                <input type="password" name="confirm_password" required class="mt-1 w-full rounded-lg border border-gray-300 p-2 focus:ring-2 focus:ring-orange-300">
                            </div>
                            <div class="flex justify-end space-x-3 mt-6">
                                <button type="button" onclick="closeChangePasswordModal()" class="px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Delete Account Modal -->
                <div id="deleteAccountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-[99999]">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold text-red-600">Delete Account</h3>
                            <button onclick="closeDeleteAccountModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="mb-4">
                            <p class="text-gray-600">Are you sure you want to delete your account? This action cannot be undone.</p>
                        </div>
                        <form id="deleteAccountForm" class="space-y-4" method="post" action="delete_account.php">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Enter your password to confirm</label>
                                <input type="password" name="confirm_password" required class="mt-1 w-full rounded-lg border border-gray-300 p-2 focus:ring-2 focus:ring-red-300">
                            </div>
                            <div class="flex justify-end space-x-3 mt-6">
                                <button type="button" onclick="closeDeleteAccountModal()" class="px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Delete Account</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    // Modal functions for both Children and Settings sections
                    function openEditChildModal(childId) {
                        // Fetch child details via AJAX
                        fetch(`get_child_details.php?child_id=${childId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const child = data.child;
                                    // Populate the form
                                    document.getElementById('edit_child_id').value = child.child_id;
                                    document.getElementById('edit_first_name').value = child.first_name;
                                    document.getElementById('edit_last_name').value = child.last_name;
                                    document.getElementById('edit_grade').value = child.grade;
                                    document.getElementById('edit_emergency_contact').value = child.emergency_contact || '';
                                    document.getElementById('edit_medical_notes').value = child.medical_notes || '';
                                    
                                    // Set select options
                                    if (child.school_id) {
                                        document.getElementById('edit_school_id').value = child.school_id;
                                    }
                                    if (child.bus_id) {
                                        document.getElementById('edit_bus_id').value = child.bus_id;
                                    }
                                    
                                    // Show the modal
                                    document.getElementById('editChildModal').classList.remove('hidden');
                                    document.body.style.overflow = 'hidden';
                                } else {
                                    alert('Error fetching child details');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error fetching child details');
                            });
                    }
                    
                    function closeEditChildModal() {
                        document.getElementById('editChildModal').classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }
                    
                    function openDeleteChildModal(childId, childName) {
                        document.getElementById('delete_child_id').value = childId;
                        document.getElementById('deleteChildText').textContent = `Are you sure you want to delete ${childName}?`;
                        document.getElementById('deleteChildModal').classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                    
                    function closeDeleteChildModal() {
                        document.getElementById('deleteChildModal').classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }

                    // Account settings modal functions
                    function openUpdateModal() {
                        document.getElementById('updateModal').classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }

                    function closeUpdateModal() {
                        document.getElementById('updateModal').classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }

                    function openChangePasswordModal() {
                        document.getElementById('changePasswordModal').classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }

                    function closeChangePasswordModal() {
                        document.getElementById('changePasswordModal').classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }

                    function openDeleteAccountModal() {
                        document.getElementById('deleteAccountModal').classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }

                    function closeDeleteAccountModal() {
                        document.getElementById('deleteAccountModal').classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }

                    // Document ready handler for all modals
                    document.addEventListener('DOMContentLoaded', function() {
                        // Setup for all modals
                        const modals = [
                            { id: 'logoutModal', closeFn: closeLogoutModal },
                            { id: 'updateModal', closeFn: closeUpdateModal },
                            { id: 'changePasswordModal', closeFn: closeChangePasswordModal },
                            { id: 'deleteAccountModal', closeFn: closeDeleteAccountModal },
                            { id: 'editChildModal', closeFn: closeEditChildModal },
                            { id: 'deleteChildModal', closeFn: closeDeleteChildModal }
                        ];
                        
                        modals.forEach(modal => {
                            const el = document.getElementById(modal.id);
                            if (el) {
                                el.addEventListener('click', function(e) {
                                    if (e.target === this) {
                                        modal.closeFn();
                                    }
                                });
                            }
                        });

                        // Close all modals on escape key press
                        document.addEventListener('keydown', function(e) {
                            if (e.key === 'Escape') {
                                modals.forEach(modal => {
                                    const el = document.getElementById(modal.id);
                                    if (el && !el.classList.contains('hidden')) {
                                        modal.closeFn();
                                    }
                                });
                            }
                        });
                    });
                </script>
    </body>
</html>