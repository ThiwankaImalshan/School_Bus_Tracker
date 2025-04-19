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
    <link rel="icon" type="image/png" href="../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../img/favicon/favicon.svg" />
    <!-- Add these lines before your existing head content -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
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

        /* Update modal styles to ensure proper layering */
        #updateModal,
        #changePasswordModal,
        #deleteAccountModal,
        #logoutModal {
            z-index: 99999 !important;  /* Highest z-index */
        }

        /* Modal backdrop styling */
        #updateModal::before,
        #changePasswordModal::before,
        #deleteAccountModal::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: -1;
        }

        /* Modal content animation and positioning */
        #updateModal > div,
        #changePasswordModal > div,
        #deleteAccountModal > div {
            position: relative;
            animation: modalFade 0.3s ease-out;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            background: white;
            transform: translateZ(1000px); /* Force 3D rendering */
        }

        /* Ensure modal containers are always on top */
        .fixed {
            isolation: isolate;
        }

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

        /* Pickup button styles */
        .pickup-btn {
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .pickup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .pickup-btn:active {
            transform: translateY(0);
        }

        .pickup-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 120%;
            height: 120%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 50%);
            transform: translate(-50%, -50%) scale(0);
            transition: transform 0.3s ease-out;
        }

        .pickup-btn:hover::after {
            transform: translate(-50%, -50%) scale(2);
        }

        /* Add this to your existing styles */
        .leaflet-container {
            z-index: 1 !important;
        }

        #locationModal {
            z-index: 9999 !important;
        }

        .modal-backdrop {
            z-index: 9998 !important;
        }

        /* Ensure other map controls stay above the map but below modal */
        .leaflet-control-container {
            z-index: 2 !important;
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
                <!-- Bus Tracking Button -->
                <a href="bus_location_tracker.php?child_id=<?php echo $selectedChildId; ?>" 
                    class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2c3.866 0 7 3.134 7 7 0 5.25-7 11-7 11s-7-5.75-7-11c0-3.866 3.134-7 7-7zM12 9a2 2 0 110 4 2 2 0 010-4z" />
                     </svg>
                     Track
                </a>
                <!-- <button onclick="showSection('history')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    History
                </button> -->
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
                <button onclick="showSection('pickup')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Pickup
                </button>
                <button onclick="showSection('settings')" class="nav-item w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c-.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
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
            <a href="bus_location_tracker.php?child_id=<?php echo $selectedChildId; ?>" 
                class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2c3.866 0 7 3.134 7 7 0 5.25-7 11-7 11s-7-5.75-7-11c0-3.866 3.134-7 7-7zM12 9a2 2 0 110 4 2 2 0 010-4z" />
                 </svg>
                 <span>Track</span>
            </a>
            <!-- <button onclick="showSection('history')" class="mobile-nav-item flex flex-1 flex-col items-center justify-center py-3 text-xs font-medium text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>History</span>
            </button> -->
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c-.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
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
                                     src="../img/profile-icon.jpg" 
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
                                    <?php 
                                    // Get current month and year
                                    $currentMonth = date('F Y');
                                    $currentMonthNumber = date('m');

                                    // Check payment status for the selected child for current month
                                    $paymentSql = "SELECT * FROM payment 
                                                WHERE child_id = ? 
                                                AND DATE_FORMAT(month_covered, '%m') = ? 
                                                AND status = 'completed'
                                                ORDER BY payment_date DESC LIMIT 1";
                                    $paymentStmt = $pdo->prepare($paymentSql);
                                    $paymentStmt->execute([$selectedChildId, $currentMonthNumber]);
                                    $paymentData = $paymentStmt->fetch(PDO::FETCH_ASSOC);

                                    $isPaid = !empty($paymentData);
                                    ?>
                                    <div class="<?php echo ($isPaid ? 'bg-green-50' : 'bg-red-50'); ?> rounded-xl p-4 mb-6">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-800"><?php echo $currentMonth; ?> Fees</h4>
                                                <p class="mt-1 text-xs text-gray-600">Due: 5th of every month</p>
                                            </div>
                                            <?php if ($isPaid): ?>
                                            <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                                PAID
                                            </div>
                                            <?php else: ?>
                                            <div class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">
                                                UNPAID
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($isPaid): ?>
                                        <!-- <div class="mt-3 text-xs text-gray-600">
                                            <p>Payment Date: <?php echo date('M d, Y', strtotime($paymentData['payment_date'])); ?></p>
                                            <p>Amount: Rs.<?php echo number_format($paymentData['amount'], 2); ?></p>
                                            <p>Method: <?php echo htmlspecialchars($paymentData['payment_method']); ?></p>
                                        </div> -->
                                        <?php endif; ?>
                                    </div>


                                    <!-- Attendance Marking -->
                                    <div class="flex flex-col">
                                        <?php
                                        // Get the current date in YYYY-MM-DD format
                                        $today = date('Y-m-d');
                                        
                                        // Check if there's an attendance record for the selected child for today
                                        $attendanceSql = "SELECT * FROM attendance WHERE child_id = ? AND attendance_date = ?";
                                        $attendanceStmt = $pdo->prepare($attendanceSql);
                                        $attendanceStmt->execute([$selectedChildId, $today]);
                                        $attendanceData = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        // If no record exists for today, create one
                                        if (!$attendanceData) {
                                            // Get bus_seat_id from child_reservation
                                            $reservationSql = "SELECT seat_id FROM child_reservation WHERE child_id = ? AND is_active = 1 ORDER BY reservation_date DESC LIMIT 1";
                                            $reservationStmt = $pdo->prepare($reservationSql);
                                            $reservationStmt->execute([$selectedChildId]);
                                            $reservationData = $reservationStmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            $busSeatId = $reservationData ? $reservationData['seat_id'] : null;
                                            
                                            // Insert new attendance record with status 'pending'
                                            $insertSql = "INSERT INTO attendance (child_id, bus_seat_id, attendance_date, status) 
                                                        VALUES (?, ?, ?, 'pending')
                                                        ON DUPLICATE KEY UPDATE status = 'pending'";
                                            $insertStmt = $pdo->prepare($insertSql);
                                            $insertStmt->execute([$selectedChildId, $busSeatId, $today]);
                                            
                                            // Get the newly created record
                                            $attendanceStmt->execute([$selectedChildId, $today]);
                                            $attendanceData = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
                                        }
                                        
                                        // Determine the status and appropriate colors
                                        $status = $attendanceData ? $attendanceData['status'] : 'pending';
                                        
                                        // Set colors based on status
                                        $bgColor = 'bg-yellow-50';
                                        $textColor = 'text-yellow-800';
                                        $statusBg = 'bg-yellow-100';
                                        $statusLabel = "Pending Pickup";
                                        
                                        if ($status == 'present') {
                                            $bgColor = 'bg-green-50';
                                            $textColor = 'text-green-800';
                                            $statusBg = 'bg-green-100';
                                            $statusLabel = "Pickuped";
                                        } else if ($status == 'absent') {
                                            $bgColor = 'bg-red-50';
                                            $textColor = 'text-red-800';
                                            $statusBg = 'bg-red-100';
                                            $statusLabel = "Today's Absent";
                                        }
                                        ?>
                                        
                                        <div id="attendanceStatus" class="<?php echo $bgColor; ?> rounded-xl p-4 mb-4">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-800">Today's Attendance</h4>
                                                    <p id="currentDate" class="text-xs text-gray-500"><?php echo date('F d, Y', strtotime($today)); ?></p>
                                                </div>
                                                <div id="attendanceLabel" class="<?php echo $statusBg; ?> <?php echo $textColor; ?> px-3 py-1 rounded-full text-sm font-medium">
                                                    <?php echo $statusLabel; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex space-x-3 justify-end mb-4">
                                            <?php if ($status != 'present' && $status != 'absent'): ?>
                                            <button onclick="updateAttendance('present', <?php echo $selectedChildId; ?>)" class="py-2 px-4 bg-green-500 text-white rounded-xl text-sm font-medium shadow-lg transform transition-all duration-200 hover:shadow-green-200 hover:-translate-y-1 active:translate-y-0 active:shadow-inner border border-green-600">
                                                Present
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($status != 'absent'): ?>
                                            <button onclick="updateAttendance('absent', <?php echo $selectedChildId; ?>)" class="py-2 px-4 bg-red-500 text-white rounded-xl text-sm font-medium shadow-lg transform transition-all duration-200 hover:shadow-red-200 hover:-translate-y-1 active:translate-y-0 active:shadow-inner border border-red-600">
                                                Absent
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($status != 'absent'): ?>
                                        <!-- Evening Route Div - Only show if not absent -->
                                        <div id="eveningRouteDiv">
                                            <div id="eveningRouteStatus" class="bg-blue-50 rounded-xl p-4 mb-4">
                                                <div class="flex justify-between items-center">
                                                    <div>
                                                        <h4 class="text-sm font-medium text-gray-800">Evening Return</h4>
                                                        <p class="text-xs text-gray-500">Update if child is not returning by bus</p>
                                                    </div>
                                                    <?php
                                                    $notReturning = (!empty($attendanceData['notes']) && strpos($attendanceData['notes'], 'Not returning') !== false);
                                                    $eveningStatusBg = $notReturning ? 'bg-orange-100' : 'bg-blue-100';
                                                    $eveningTextColor = $notReturning ? 'text-orange-800' : 'text-blue-800';
                                                    $eveningLabel = $notReturning ? 'Not Returning' : 'Returning by Bus';
                                                    ?>
                                                    <div id="eveningRouteLabel" class="<?php echo $eveningStatusBg; ?> <?php echo $eveningTextColor; ?> px-3 py-1 rounded-full text-sm font-medium">
                                                        <?php echo $eveningLabel; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="flex space-x-3 justify-end">
                                                <button onclick="updateEveningRoute(<?php echo $selectedChildId; ?>, <?php echo $notReturning ? 'false' : 'true'; ?>)" class="py-2 px-4 bg-blue-500 text-white rounded-xl text-sm font-medium shadow-lg transform transition-all duration-200 hover:shadow-orange-200 hover:-translate-y-1 active:translate-y-0 active:shadow-inner border border-orange-600">
                                                    <?php echo $notReturning ? 'Mark as Returning' : 'Not Returning Today'; ?>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <script>
                                    // Function to update attendance status
                                    function updateAttendance(status, childId) {
                                        // Create AJAX request
                                        fetch('update_attendance.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded',
                                            },
                                            body: 'child_id=' + childId + '&status=' + status
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                // Update UI based on status
                                                const statusDiv = document.getElementById('attendanceStatus');
                                                const label = document.getElementById('attendanceLabel');
                                                const eveningRouteDiv = document.getElementById('eveningRouteDiv');
                                                
                                                if (status === 'present') {
                                                    // Update to present
                                                    statusDiv.className = 'bg-green-50 rounded-xl p-4 mb-4';
                                                    label.className = 'bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium';
                                                    label.textContent = 'Today\'s Present';
                                                    
                                                    // Show evening route div if it was hidden
                                                    if (eveningRouteDiv) {
                                                        eveningRouteDiv.style.display = 'block';
                                                    } else {
                                                        // Reload the page to regenerate the evening route div
                                                        location.reload();
                                                    }
                                                    
                                                    // Hide present button, show absent button
                                                    updateAttendanceButtons('present');
                                                    location.reload();
                                                    
                                                } else if (status === 'absent') {
                                                    // Update to absent
                                                    statusDiv.className = 'bg-red-50 rounded-xl p-4 mb-4';
                                                    label.className = 'bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium';
                                                    label.textContent = 'Today\'s Absent';
                                                    
                                                    // Hide evening route div
                                                    if (eveningRouteDiv) {
                                                        eveningRouteDiv.style.display = 'none';
                                                    }
                                                    
                                                    // Hide absent button, show present button
                                                    updateAttendanceButtons('absent');
                                                }
                                            } else {
                                                alert('Failed to update attendance: ' + data.message);
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            alert('An error occurred while updating attendance. Please try again.');
                                        });
                                    }

                                    // Function to update attendance buttons based on current status
                                    function updateAttendanceButtons(currentStatus) {
                                        const buttonsDiv = document.querySelector('.flex.space-x-3.justify-end.mb-4');
                                        if (buttonsDiv) {
                                            if (currentStatus === 'present') {
                                                // Remove all buttons when status is present
                                                buttonsDiv.innerHTML = '';
                                            } else if (currentStatus === 'absent') {
                                                // Start with 20 seconds
                                                let timeLeft = 20;
                                                
                                                // Initial button render
                                                buttonsDiv.innerHTML = `
                                                    <button onclick="updateAttendance('present', <?php echo $selectedChildId; ?>)" class="py-2 px-4 bg-green-500 text-white rounded-xl text-sm font-medium shadow-lg transform transition-all duration-200 hover:shadow-green-200 hover:-translate-y-1 active:translate-y-0 active:shadow-inner border border-green-600">
                                                        Undo (${timeLeft}s)
                                                    </button>
                                                `;
                                                
                                                // Update timer every second
                                                const timer = setInterval(() => {
                                                    timeLeft--;
                                                    if (timeLeft > 0) {
                                                        // Update button text with remaining time
                                                        buttonsDiv.innerHTML = `
                                                            <button onclick="updateAttendance('present', <?php echo $selectedChildId; ?>)" class="py-2 px-4 bg-green-500 text-white rounded-xl text-sm font-medium shadow-lg transform transition-all duration-200 hover:shadow-green-200 hover:-translate-y-1 active:translate-y-0 active:shadow-inner border border-green-600">
                                                                Undo (${timeLeft}s)
                                                            </button>
                                                        `;
                                                    } else {
                                                        // Clear interval and remove button when time runs out
                                                        clearInterval(timer);
                                                        buttonsDiv.innerHTML = '';
                                                    }
                                                }, 1000); // Update every second
                                            }
                                        }
                                    }

                                    // Function to update evening route status
                                    function updateEveningRoute(childId, setNotReturning) {
                                        // Create AJAX request
                                        fetch('update_evening_route.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded',
                                            },
                                            body: 'child_id=' + childId + '&not_returning=' + (setNotReturning ? '1' : '0')
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                // Update UI based on status
                                                const statusDiv = document.getElementById('eveningRouteStatus');
                                                const label = document.getElementById('eveningRouteLabel');
                                                const button = statusDiv.nextElementSibling.querySelector('button');
                                                
                                                if (setNotReturning) {
                                                    label.className = 'bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-medium';
                                                    label.textContent = 'Not Returning';
                                                    button.textContent = 'Mark as Returning';
                                                } else {
                                                    label.className = 'bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium';
                                                    label.textContent = 'Returning by Bus';
                                                    button.textContent = 'Not Returning Today';
                                                }
                                            } else {
                                                alert('Failed to update evening route status: ' + data.message);
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            alert('An error occurred while updating evening route status. Please try again.');
                                        });
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
                // $academic_year = $academic_year_start . "-" . $academic_year_end;
                $academic_year = $academic_year_end;

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
                // $conn->close();
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
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
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
                                    <!-- <div class="mt-8">
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
                                    </div> -->
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



























































                





                <section id="children-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-72 md:mr-8 mx-4 md:mx-0">
                    <?php
                    // Ensure session is started and parent is logged in
                    // session_start();
                    // if (!isset($_SESSION['parent_id'])) {
                    //     header('Location: login.php');
                    //     exit;
                    // }

                    // require_once 'config/database.php';

                    // Get parent ID from session
                    $parent_id = $_SESSION['parent_id'];

                    // Fetch child information for the logged-in parent
                    $sql = "SELECT c.*, s.name as school_name, b.bus_number 
                            FROM child c
                            LEFT JOIN school s ON c.school_id = s.school_id
                            LEFT JOIN bus b ON c.bus_id = b.bus_id
                            WHERE c.parent_id = ?";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $parent_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    // Check if query was successful
                    if (!$result) {
                        die("Query failed: " . $conn->error);
                    }
                    ?>

                    <div class="container mx-auto">
                        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
                            <h2 class="text-2xl font-bold text-gray-800">My Children</h2>
                            <a href="add_child.php" 
                                class="mt-4 md:mt-0 px-4 py-2 bg-yellow-500 text-white rounded-lg 
                                         hover:bg-yellow-600 transform hover:-translate-y-0.5 hover:shadow-lg 
                                         active:translate-y-0 active:shadow-md
                                         transition-all duration-200 ease-in-out
                                         cursor-pointer inline-flex items-center"
                                onclick="window.location.href='add_child.php'">
                                 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                      <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                 </svg>
                                 Add Child
                            </a>
                        </div>

                        <?php if ($result->num_rows > 0): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php while ($child = $result->fetch_assoc()): ?>
                                    <div class="bg-white rounded-xl shadow-lg overflow-hidden transform transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-gray-100">
                                        <div class="p-4">
                                            <div class="flex items-center space-x-4">
                                                <?php if (!empty($child['photo_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($child['photo_url']); ?>" alt="Child Photo" class="w-16 h-16 rounded-full object-cover ring-2 ring-yellow-500 ring-offset-2">
                                                <?php else: ?>
                                                    <img src="assets\img\student1.jpg" alt="Default Child Photo" class="w-16 h-16 rounded-full object-cover ring-2 ring-yellow-500 ring-offset-2">
                                                <?php endif; ?>
                                                
                                                <div>
                                                    <h4 class="text-lg font-semibold text-gray-800 leading-tight">
                                                        <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                                    </h4>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">
                                                        Grade <?php echo htmlspecialchars($child['grade'] ?? 'N/A'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4 flex flex-col space-y-2 text-sm"> <!-- Changed to flex-col and space-y-2 -->
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                    </svg>
                                                    <span class="text-gray-600 truncate"><?php echo htmlspecialchars($child['school_name'] ?? 'N/A'); ?></span>
                                                </div>
                                                
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v6a2 2 0 002 2h2" />
                                                    </svg>
                                                    <span class="text-gray-600">Bus <?php echo htmlspecialchars($child['bus_number'] ?? 'N/A'); ?></span>
                                                </div>
                                                
                                                <?php if (!empty($child['emergency_contact'])): ?>
                                                    <div class="flex items-center space-x-2">
                                                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                        </svg>
                                                        <span class="text-gray-600"><?php echo htmlspecialchars($child['emergency_contact']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($child['medical_notes'])): ?>
                                                    <div class="flex items-center space-x-2">
                                                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m-6-8h6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z" />
                                                        </svg>
                                                        <span class="text-gray-600 truncate"><?php echo htmlspecialchars($child['medical_notes']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex justify-end space-x-2">
                                            <button onclick="openEditModal(<?php echo $child['child_id']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 rounded-md hover:bg-blue-100 transition-colors duration-200">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit
                                            </button>
                                            <button onclick="openDeleteModal(<?php echo $child['child_id']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 rounded-md hover:bg-red-100 transition-colors duration-200">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Delete
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Tailwind Edit Modal for each child -->
                                    <div id="editModal<?php echo $child['child_id']; ?>" class="fixed inset-0 z-50 hidden overflow-y-auto">
                                        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                                            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                                                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                                            </div>
                                            
                                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                                            
                                            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full md:max-w-xl">
                                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                                    <div class="flex justify-between items-center pb-3 border-b mb-4">
                                                        <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Child Information</h3>
                                                        <button onclick="closeEditModal(<?php echo $child['child_id']; ?>)" class="text-gray-400 hover:text-gray-500">
                                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    
                                                    <form action="process_edit_child.php" method="post" enctype="multipart/form-data">
                                                        <input type="hidden" name="child_id" value="<?php echo $child['child_id']; ?>">
                                                        
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                                            <div>
                                                                <label for="firstName<?php echo $child['child_id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                                                <input type="text" id="firstName<?php echo $child['child_id']; ?>" name="first_name" value="<?php echo htmlspecialchars($child['first_name']); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                            </div>
                                                            <div>
                                                                <label for="lastName<?php echo $child['child_id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                                                <input type="text" id="lastName<?php echo $child['child_id']; ?>" name="last_name" value="<?php echo htmlspecialchars($child['last_name']); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                                            <div>
                                                                <label for="grade<?php echo $child['child_id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                                                                <input type="text" id="grade<?php echo $child['child_id']; ?>" name="grade" value="<?php echo htmlspecialchars($child['grade'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                            </div>
                                                            <div>
                                                                <label for="school<?php echo $child['child_id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">School</label>
                                                                <select id="school<?php echo $child['child_id']; ?>" name="school_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                                    <option value="">Select School</option>
                                                                    <?php
                                                                    // Reset the result pointer for schools query
                                                                    $stmt = $conn->prepare("SELECT school_id, name FROM school");
                                                                    $stmt->execute();
                                                                    $schools = $stmt->get_result();
                                                                    
                                                                    while ($school = $schools->fetch_assoc()) {
                                                                        $selected = ($school['school_id'] == $child['school_id']) ? 'selected' : '';
                                                                        echo "<option value='" . $school['school_id'] . "' $selected>" . htmlspecialchars($school['name']) . "</option>";
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                                            <div>
                                                                <label for="bus<?php echo $child['child_id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Bus</label>
                                                                <select id="bus<?php echo $child['child_id']; ?>" name="bus_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                                    <option value="">Select Bus</option>
                                                                    <?php
                                                                    // Reset the result pointer for bus query
                                                                    $stmt = $conn->prepare("SELECT bus_id, bus_number FROM bus WHERE is_active = 1");
                                                                    $stmt->execute();
                                                                    $buses = $stmt->get_result();
                                                                    
                                                                    while ($bus = $buses->fetch_assoc()) {
                                                                        $selected = ($bus['bus_id'] == $child['bus_id']) ? 'selected' : '';
                                                                        echo "<option value='" . $bus['bus_id'] . "' $selected>" . htmlspecialchars($bus['bus_number']) . "</option>";
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label for="emergencyContact<?php echo $child['child_id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact</label>
                                                                <input type="text" id="emergencyContact<?php echo $child['child_id']; ?>" name="emergency_contact" value="<?php echo htmlspecialchars($child['emergency_contact'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-4">
                                                            <label for="pickupLocation<?php echo $child['child_id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Pickup Location</label>
                                                            <div class="relative">
                                                                <input type="text" 
                                                                    id="pickupLocation<?php echo $child['child_id']; ?>" 
                                                                    name="pickup_location" 
                                                                    value="<?php echo htmlspecialchars($child['pickup_location'] ?? ''); ?>" 
                                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                                <button type="button" 
                                                                        onclick="getCurrentLocation(<?php echo $child['child_id']; ?>)"
                                                                        class="absolute right-2 top-1/2 transform -translate-y-1/2 p-2 pb-4 text-gray-500 hover:text-blue-500 flex items-center">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </button>
                                                            </div>

                                                            <!-- Input fields for coordinates -->
                                                            <div class="grid grid-cols-2 gap-4 mb-2">
                                                                <div>
                                                                    <!-- <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label> -->
                                                                    <input type="hidden" id="latitude<?php echo $child['child_id']; ?>" name="latitude" 
                                                                        value="<?php echo htmlspecialchars($child['latitude'] ?? ''); ?>"
                                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                                </div>
                                                                <div>
                                                                    <!-- <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label> -->
                                                                    <input type="hidden" id="longitude<?php echo $child['child_id']; ?>" name="longitude" 
                                                                        value="<?php echo htmlspecialchars($child['longitude'] ?? ''); ?>"
                                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                                </div>
                                                            </div>

                                                            <!-- Loading indicator -->
                                                            <div id="mapLoading<?php echo $child['child_id']; ?>" class="w-full h-64 flex items-center justify-center bg-gray-100 rounded-lg">
                                                                <div class="text-center">
                                                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
                                                                    <p class="text-sm text-gray-600">Loading map...</p>
                                                                </div>
                                                            </div>

                                                            <!-- Map container -->
                                                            <div id="map<?php echo $child['child_id']; ?>" class="mt-2 h-64 w-full rounded-lg border border-gray-300 hidden"></div>
                                                            <p class="mt-1 text-sm text-gray-500">Click on the map to select pickup location or use current location button</p>

                                                            <script>
                                                            document.addEventListener('DOMContentLoaded', function() {
                                                                const childId = <?php echo $child['child_id']; ?>;
                                                                const mapId = 'map' + childId;
                                                                const loadingId = 'mapLoading' + childId;
                                                                let marker;
                                                                let map;
                                                                let mapInitialized = false;

                                                                // Pre-load map tiles
                                                                const preloadTiles = () => {
                                                                    const link = document.createElement('link');
                                                                    link.rel = 'preload';
                                                                    link.href = 'https://tile.openstreetmap.org/13/4093/2723.png';
                                                                    link.as = 'image';
                                                                    document.head.appendChild(link);
                                                                };
                                                                preloadTiles();

                                                                // Initialize map with optimized loading
                                                                function initMap() {
                                                                    if (mapInitialized) return;
                                                                    
                                                                    // Create map with better performance options
                                                                    map = L.map(mapId, {
                                                                        zoomControl: true,
                                                                        attributionControl: false,
                                                                        fadeAnimation: false,
                                                                        zoomAnimation: true,
                                                                        markerZoomAnimation: true,
                                                                        preferCanvas: true
                                                                    });

                                                                    // Add custom loading control
                                                                    const loadingControl = L.control({position: 'bottomleft'});
                                                                    loadingControl.onAdd = function() {
                                                                        const div = L.DomUtil.create('div', 'map-loading-control');
                                                                        div.innerHTML = '<div class="text-xs bg-white px-2 py-1 rounded shadow">Loading tiles...</div>';
                                                                        div.style.display = 'none';
                                                                        return div;
                                                                    };
                                                                    const loadingIndicator = loadingControl.addTo(map);

                                                                    // Add OpenStreetMap tiles with better performance
                                                                    const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                                                        maxZoom: 19,
                                                                        attribution: '© OpenStreetMap',
                                                                        tileSize: 256,
                                                                        updateWhenIdle: true,
                                                                        updateWhenZooming: false,
                                                                        keepBuffer: 2
                                                                    });

                                                                    // Track loading events
                                                                    let tilesLoading = 0;
                                                                    tiles.on('loading', () => {
                                                                        tilesLoading++;
                                                                        loadingIndicator.getContainer().style.display = 'block';
                                                                    });

                                                                    tiles.on('load', () => {
                                                                        tilesLoading--;
                                                                        if (tilesLoading <= 0) {
                                                                            loadingIndicator.getContainer().style.display = 'none';
                                                                            document.getElementById(loadingId).classList.add('hidden');
                                                                            document.getElementById(mapId).classList.remove('hidden');
                                                                        }
                                                                    });

                                                                    tiles.addTo(map);

                                                                    // Set initial view based on saved coordinates or default
                                                                    const savedLat = document.getElementById('latitude' + childId).value;
                                                                    const savedLng = document.getElementById('longitude' + childId).value;
                                                                    
                                                                    if (savedLat && savedLng) {
                                                                        map.setView([savedLat, savedLng], 15);
                                                                        marker = L.marker([savedLat, savedLng], {draggable: true}).addTo(map);
                                                                        setupMarkerEvents(marker);
                                                                        
                                                                        // Update pickup location text with coordinates
                                                                        document.getElementById('pickupLocation' + childId).value = `${savedLat},${savedLng}`;
                                                                    } else {
                                                                        // Default to Sri Lanka center
                                                                        map.setView([7.8731, 80.7718], 8);
                                                                    }

                                                                    // Handle map clicks
                                                                    map.on('click', function(e) {
                                                                        handleLocationSelect(e.latlng.lat, e.latlng.lng);
                                                                    });

                                                                    // Add attribution control in a less obtrusive position
                                                                    L.control.attribution({
                                                                        position: 'bottomright',
                                                                        prefix: false
                                                                    }).addAttribution('© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>').addTo(map);

                                                                    mapInitialized = true;
                                                                    
                                                                    // Force a map resize after revealing to fix rendering issues
                                                                    setTimeout(() => {
                                                                        map.invalidateSize();
                                                                    }, 100);
                                                                }

                                                                // Lazy load the map when element comes into view
                                                                const observer = new IntersectionObserver((entries) => {
                                                                    entries.forEach(entry => {
                                                                        if (entry.isIntersecting) {
                                                                            initMap();
                                                                            observer.disconnect();
                                                                        }
                                                                    });
                                                                }, {threshold: 0.1});
                                                                
                                                                observer.observe(document.getElementById(loadingId));

                                                                // Function to handle location selection
                                                                function handleLocationSelect(lat, lng) {
                                                                    if (marker) {
                                                                        map.removeLayer(marker);
                                                                    }

                                                                    marker = L.marker([lat, lng], {draggable: true}).addTo(map);
                                                                    setupMarkerEvents(marker);
                                                                    updateCoordinates(lat, lng);

                                                                    // Update pickup location with raw coordinates immediately
                                                                    document.getElementById('pickupLocation' + childId).value = `${lat.toFixed(6)},${lng.toFixed(6)}`;

                                                                    // Attempt reverse geocoding in background
                                                                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                                                                        .then(response => response.json())
                                                                        .then(data => {
                                                                            if (data.display_name) {
                                                                                const pickupField = document.getElementById('pickupLocation' + childId);
                                                                                // Add the place name but keep coordinates in parentheses
                                                                                pickupField.value = `${data.display_name} (${lat.toFixed(6)},${lng.toFixed(6)})`;
                                                                            }
                                                                        })
                                                                        .catch(error => console.error('Error:', error));
                                                                }

                                                                // Setup marker drag events
                                                                function setupMarkerEvents(marker) {
                                                                    marker.on('dragend', function(event) {
                                                                        const position = marker.getLatLng();
                                                                        updateCoordinates(position.lat, position.lng);
                                                                        
                                                                        // Update display immediately with coordinates
                                                                        document.getElementById('pickupLocation' + childId).value = 
                                                                            `${position.lat.toFixed(6)},${position.lng.toFixed(6)}`;
                                                                    });
                                                                }

                                                                // Update coordinate inputs
                                                                function updateCoordinates(lat, lng) {
                                                                    document.getElementById('latitude' + childId).value = lat.toFixed(6);
                                                                    document.getElementById('longitude' + childId).value = lng.toFixed(6);
                                                                }

                                                                // Make getCurrentLocation function available globally
                                                                window.getCurrentLocation = function(childId) {
                                                                    // Initialize map if not already done
                                                                    if (!mapInitialized) initMap();
                                                                    
                                                                    const locButton = document.querySelector(`button[onclick="getCurrentLocation(${childId})"]`);
                                                                    if (locButton) {
                                                                        // Show loading state on button
                                                                        const originalContent = locButton.innerHTML;
                                                                        locButton.innerHTML = '<div class="animate-spin h-5 w-5 border-2 border-blue-500 border-t-transparent rounded-full"></div>';
                                                                        locButton.disabled = true;
                                                                    }

                                                                    if ("geolocation" in navigator) {
                                                                        navigator.geolocation.getCurrentPosition(function(position) {
                                                                            const lat = position.coords.latitude;
                                                                            const lng = position.coords.longitude;
                                                                            
                                                                            // Center and zoom the map
                                                                            map.setView([lat, lng], 15);
                                                                            handleLocationSelect(lat, lng);
                                                                            
                                                                            // Restore button state
                                                                            if (locButton) {
                                                                                locButton.innerHTML = originalContent;
                                                                                locButton.disabled = false;
                                                                            }
                                                                        }, function(error) {
                                                                            alert("Error getting location: " + error.message);
                                                                            
                                                                            // Restore button state
                                                                            if (locButton) {
                                                                                locButton.innerHTML = originalContent;
                                                                                locButton.disabled = false;
                                                                            }
                                                                        }, {
                                                                            enableHighAccuracy: true,
                                                                            timeout: 10000,
                                                                            maximumAge: 0
                                                                        });
                                                                    } else {
                                                                        alert("Geolocation is not supported by your browser");
                                                                        
                                                                        // Restore button state
                                                                        if (locButton) {
                                                                            locButton.innerHTML = originalContent;
                                                                            locButton.disabled = false;
                                                                        }
                                                                    }
                                                                };

                                                                // Add this to the existing JavaScript section where map handling is done

                                                                function setModalLocation(lat, lng) {
                                                                    if (modalMarker) {
                                                                        modalMap.removeLayer(modalMarker);
                                                                    }
                                                                    modalMarker = L.marker([lat, lng], {draggable: true}).addTo(modalMap);
                                                                    modalMap.setView([lat, lng], 15);
                                                                    
                                                                    // Update hidden fields with combined coordinates
                                                                    document.getElementById('latitude').value = lat;
                                                                    document.getElementById('longitude').value = lng;

                                                                    modalMarker.on('dragend', function(event) {
                                                                        const position = event.target.getLatLng();
                                                                        document.getElementById('latitude').value = position.lat;
                                                                        document.getElementById('longitude').value = position.lng;
                                                                    });
                                                                }

                                                                // Update the function to display coordinates from the combined format
                                                                function displayLocation(locationString) {
                                                                    const [lat, lng] = locationString.split(',');
                                                                    return [parseFloat(lat), parseFloat(lng)];
                                                                }
                                                            });
                                                            </script>
                                                        </div>
                                                        
                                                        <div class="mb-4">
                                                            <label for="medicalNotes<?php echo $child['child_id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Medical Notes</label>
                                                            <textarea id="medicalNotes<?php echo $child['child_id']; ?>" name="medical_notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($child['medical_notes'] ?? ''); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="mb-4">
                                                            <label for="photo<?php echo $child['child_id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
                                                            <div class="flex items-center justify-center w-full">
                                                                <label for="photo<?php echo $child['child_id']; ?>" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-all duration-300">
                                                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                                        <svg class="w-8 h-8 mb-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                                        </svg>
                                                                        <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                                                        <p class="text-xs text-gray-500">PNG, JPG or JPEG (MAX. 800x400px)</p>
                                                                    </div>
                                                                    <input type="file" id="photo<?php echo $child['child_id']; ?>" name="photo" class="hidden" accept="image/*" />
                                                                </label>
                                                            </div>
                                                            
                                                            <?php if (!empty($child['photo_url'])): ?>
                                                                <div class="mt-4 flex items-start space-x-3">
                                                                    <div class="relative group">
                                                                        <p class="text-sm text-gray-500 mb-2">Current photo:</p>
                                                                        <div class="relative rounded-lg overflow-hidden shadow-md group-hover:shadow-lg transition-all duration-300">
                                                                            <img src="<?php echo htmlspecialchars($child['photo_url']); ?>" alt="Current Photo" class="h-20 w-20 object-cover">
                                                                            <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                                </svg>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex items-center">
                                                                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                                                                            <input type="checkbox" id="removePhoto<?php echo $child['child_id']; ?>" name="remove_photo" class="form-checkbox h-4 w-4 text-orange-500 rounded border-gray-300 focus:ring-orange-500 transition duration-150">
                                                                            <span class="text-sm text-gray-700 hover:text-orange-500 transition-colors duration-200">Remove current photo</span>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="mt-6 flex justify-end space-x-3">
                                                            <button type="button" onclick="closeEditModal(<?php echo $child['child_id']; ?>)" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                                                Cancel
                                                            </button>
                                                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                                Save Changes
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tailwind Delete Modal for each child -->
                                    <div id="deleteModal<?php echo $child['child_id']; ?>" class="fixed inset-0 z-50 hidden overflow-y-auto">
                                        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                                            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                                                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                                            </div>
                                            
                                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                                            
                                            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                                    <div class="sm:flex sm:items-start">
                                                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                            </svg>
                                                        </div>
                                                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                                            <h3 class="text-lg leading-6 font-medium text-gray-900">Confirm Deletion</h3>
                                                            <div class="mt-2">
                                                                <p class="text-sm text-gray-500">
                                                                    Are you sure you want to delete <strong><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></strong>?
                                                                </p>
                                                                <p class="text-sm text-red-600 mt-2">This action cannot be undone.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                                    <form action="process_delete_child.php" method="post">
                                                        <input type="hidden" name="child_id" value="<?php echo $child['child_id']; ?>">
                                                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                                            Delete
                                                        </button>
                                                    </form>
                                                    <button type="button" onclick="closeDeleteModal(<?php echo $child['child_id']; ?>)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                                        Cancel
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center bg-gray-50 rounded-xl p-12 text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <h4 class="text-xl font-semibold text-gray-800 mb-2">No children added yet</h4>
                                <p class="text-gray-500">Click the "Add Child" button to register your child for school bus service</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- JavaScript for handling modals -->
                    <script>
                        // Functions to handle the edit modal
                        function openEditModal(childId) {
                            document.getElementById('editModal' + childId).classList.remove('hidden');
                            document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
                        }
                        
                        function closeEditModal(childId) {
                            document.getElementById('editModal' + childId).classList.add('hidden');
                            document.body.style.overflow = 'auto'; // Re-enable scrolling
                        }
                        
                        // Functions to handle the delete modal
                        function openDeleteModal(childId) {
                            document.getElementById('deleteModal' + childId).classList.remove('hidden');
                            document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
                        }
                        
                        function closeDeleteModal(childId) {
                            document.getElementById('deleteModal' + childId).classList.add('hidden');
                            document.body.style.overflow = 'auto'; // Re-enable scrolling
                        }
                        
                        // Close modals when clicking outside of them
                        window.addEventListener('click', function(event) {
                            document.querySelectorAll('[id^="editModal"], [id^="deleteModal"]').forEach(function(modal) {
                                if (event.target === modal) {
                                    modal.classList.add('hidden');
                                    document.body.style.overflow = 'auto';
                                }
                            });
                        });
                        
                        // Close modals with Escape key
                        document.addEventListener('keydown', function(event) {
                            if (event.key === 'Escape') {
                                document.querySelectorAll('[id^="editModal"], [id^="deleteModal"]').forEach(function(modal) {
                                    if (!modal.classList.contains('hidden')) {
                                        modal.classList.add('hidden');
                                        document.body.style.overflow = 'auto';
                                    }
                                });
                            }
                        });
                    </script>
                </section>













































                <!-- Pickup Locations Section -->
<section id="pickup-section" class="dashboard-section p-6 px-8 bg-white rounded-lg shadow-md mt-6 mb-6 md:ml-72 md:mr-8 mx-4 md:mx-0">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
            <h2 class="text-3xl font-bold heading-brown">Pickup Locations</h2>
        </div>
        <button onclick="openAddLocationModal()" 
                class="pickup-btn btn-gradient px-4 py-2 rounded-lg text-white flex items-center hover:bg-orange-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add New Location
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold heading-brown">Manage Pickup Locations</h3>
        </div>
        
        <div id="locations-container" class="divide-y divide-gray-100">
            <?php
            // Update the query to use the single location column
            $stmt = $pdo->prepare("
                SELECT pl.*, CASE WHEN c.pickup_location = pl.location THEN 1 ELSE 0 END as is_current_default 
                FROM pickup_locations pl 
                LEFT JOIN child c ON c.child_id = pl.child_id 
                WHERE pl.child_id = ?
                ORDER BY pl.is_default DESC, pl.created_at DESC
            ");
            $stmt->execute([$selectedChildId]);
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($locations as $location):
                // Split the location string into latitude and longitude
                list($latitude, $longitude) = explode(',', $location['location']);
            ?>
            <div class="p-6 location-item" data-location-id="<?php echo $location['location_id']; ?>">
                <div class="flex items-start justify-between">
                    <div class="flex items-start space-x-4">
                        <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($location['name']); ?></h4>
                            <p class="text-sm text-gray-500 mt-1">
                                <?php echo $location['location']; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <?php if (!$location['is_current_default']): ?>
                            <button onclick="setDefaultLocation(<?php echo $location['location_id']; ?>, <?php echo $selectedChildId; ?>)" 
                                    class="pickup-btn text-blue-600 hover:text-blue-800 text-sm px-3 py-1 rounded-md hover:bg-blue-50 transition-all duration-200 active:bg-blue-100">
                                Make Default
                            </button>
                        <?php else: ?>
                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">Default</span>
                        <?php endif; ?>
                        <button onclick="editLocation(<?php echo $location['location_id']; ?>)" 
                                class="pickup-btn text-orange-500 hover:text-orange-700 text-sm px-3 py-1 rounded-md hover:bg-orange-50 transition-all duration-200 active:bg-orange-100">
                            Edit
                        </button>
                        <button onclick="deleteLocation(<?php echo $location['location_id']; ?>)"
                                class="pickup-btn text-red-500 hover:text-red-700 text-sm px-3 py-1 rounded-md hover:bg-red-50 transition-all duration-200 active:bg-red-100">
                            Delete
                        </button>
                    </div>
                </div>
                
                <div class="mt-4 h-48 rounded-lg overflow-hidden relative">
                    <div id="map-<?php echo $location['location_id']; ?>" class="w-full h-full leaflet-map"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add/Edit Location Modal -->
    <div id="locationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white max-w-xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-900" id="modalTitle">Add New Location</h3>
                <button onclick="closeLocationModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="locationForm" class="space-y-4">
                <input type="hidden" id="location_id" name="location_id">
                <input type="hidden" id="child_id" name="child_id" value="<?php echo $selectedChildId; ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Location Name</label>
                    <input type="text" id="location_name" name="name" required
                           class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-300">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Location</label>
                    <div id="modal-map" class="w-full h-64 rounded-lg mb-2"></div>
                    <input type="hidden" id="latitude" name="latitude" required>
                    <input type="hidden" id="longitude" name="longitude" required>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeLocationModal()" 
                            class="pickup-btn px-4 py-2 text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-md transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="pickup-btn px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-all duration-200">
                        Save Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
// Initialize maps for each location
document.querySelectorAll('[id^="map-"]').forEach(mapElement => {
    const locationId = mapElement.id.split('-')[1];
    const location = <?php echo json_encode($locations); ?>.find(l => l.location_id == locationId);
    
    if (location) {
        // Split location string into coordinates
        const [lat, lng] = location.location.split(',');
        const map = L.map(mapElement.id).setView([parseFloat(lat), parseFloat(lng)], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        L.marker([parseFloat(lat), parseFloat(lng)]).addTo(map);
    }
});

// Location Modal Map
let modalMap = null;
let modalMarker = null;

function initModalMap(lat = 7.8731, lng = 80.7718) {
    if (!modalMap) {
        modalMap = L.map('modal-map').setView([lat, lng], 8);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(modalMap);

        modalMap.on('click', function(e) {
            setModalLocation(e.latlng.lat, e.latlng.lng);
        });
    }

    setModalLocation(lat, lng);
    setTimeout(() => modalMap.invalidateSize(), 100);
}

function setModalLocation(lat, lng) {
    if (modalMarker) {
        modalMap.removeLayer(modalMarker);
    }
    modalMarker = L.marker([lat, lng], {draggable: true}).addTo(modalMap);
    modalMap.setView([lat, lng], 15);
    
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;

    modalMarker.on('dragend', function(event) {
        const position = event.target.getLatLng();
        document.getElementById('latitude').value = position.lat;
        document.getElementById('longitude').value = position.lng;
    });
}

// CRUD Operations
async function setDefaultLocation(locationId, childId) {
    try {
        const response = await fetch('update_default_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ location_id: locationId, child_id: childId })
        });
        
        if (response.ok) {
            location.reload();
        } else {
            throw new Error('Failed to update default location');
        }
    } catch (error) {
        alert('Error updating default location: ' + error.message);
    }
}

function openAddLocationModal() {
    document.getElementById('modalTitle').textContent = 'Add New Location';
    document.getElementById('locationForm').reset();
    document.getElementById('location_id').value = '';
    document.getElementById('locationModal').classList.remove('hidden');
    initModalMap();
}

function closeLocationModal() {
    document.getElementById('locationModal').classList.add('hidden');
}

async function editLocation(locationId) {
    try {
        const response = await fetch(`get_location.php?id=${locationId}`);
        const location = await response.json();
        
        document.getElementById('modalTitle').textContent = 'Edit Location';
        document.getElementById('location_id').value = location.location_id;
        document.getElementById('location_name').value = location.name;
        document.getElementById('locationModal').classList.remove('hidden');
        
        initModalMap(parseFloat(location.latitude), parseFloat(location.longitude));
    } catch (error) {
        alert('Error loading location details: ' + error.message);
    }
}

async function deleteLocation(locationId) {
    if (confirm('Are you sure you want to delete this location?')) {
        try {
            const response = await fetch('delete_location.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ location_id: locationId })
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to delete location');
            }
            
            const result = await response.json();
            if (result.success) {
                window.location.reload();
            } else {
                throw new Error(result.message || 'Failed to delete location');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error deleting location: ' + error.message);
        }
    }
}

// Form submission
document.getElementById('locationForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    try {
        // Create form data
        const formData = new FormData();
        formData.append('name', document.getElementById('location_name').value);
        formData.append('latitude', document.getElementById('latitude').value);
        formData.append('longitude', document.getElementById('longitude').value);
        formData.append('child_id', document.getElementById('child_id').value);
        
        if (document.getElementById('location_id').value) {
            formData.append('location_id', document.getElementById('location_id').value);
        }

        const response = await fetch('save_location.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            closeLocationModal();
            location.reload();
        } else {
            throw new Error(data.message || 'Failed to save location');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error saving location: ' + error.message);
    }
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
                                <h3 class="text-lg font-semibold heading-brown">Parent Information</h3>
                                <!-- <button onclick="openUpdateModal()" class="btn-primary text-sm px-4 py-2 rounded-lg flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    Update Information
                                </button> -->
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
                <div id="updateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-[9999]">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold text-gray-900">Update Information</h3>
                            <button onclick="closeUpdateModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form id="updateForm" class="space-y-4">
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
                <div id="changePasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-[9999]">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold text-gray-900">Change Password</h3>
                            <button onclick="closeChangePasswordModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form id="changePasswordForm" class="space-y-4">
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
                <div id="deleteAccountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-[9999]">
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
                        <form id="deleteAccountForm" class="space-y-4">
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
                    // Modal functions
                    function openUpdateModal() {
                        document.getElementById('updateModal').classList.remove('hidden');
                    }

                    function closeUpdateModal() {
                        document.getElementById('updateModal').classList.add('hidden');
                    }

                    function openChangePasswordModal() {
                        document.getElementById('changePasswordModal').classList.remove('hidden');
                    }

                    function closeChangePasswordModal() {
                        document.getElementById('changePasswordModal').classList.add('hidden');
                    }

                    function openDeleteAccountModal() {
                        document.getElementById('deleteAccountModal').classList.remove('hidden');
                    }

                    function closeDeleteAccountModal() {
                        document.getElementById('deleteAccountModal').classList.add('hidden');
                    }

                    // Form submissions
                    document.getElementById('updateForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        
                        fetch('update_parent_info.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Information updated successfully');
                                location.reload();
                            } else {
                                alert(data.message || 'Error updating information');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while updating information');
                        });
                    });

                    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        
                        fetch('change_password.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Password changed successfully');
                                closeChangePasswordModal();
                                this.reset();
                            } else {
                                alert(data.message || 'Error changing password');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while changing password');
                        });
                    });

                    document.getElementById('deleteAccountForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        
                        fetch('delete_account.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Account deleted successfully');
                                window.location.href = 'logout.php';
                            } else {
                                alert(data.message || 'Error deleting account');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while deleting account');
                        });
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
                #logoutModal {
                    z-index: 9999 !important;
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

                #logoutModal > div {
                    animation: modalFade 0.3s ease-out;
                }

                /* Hover effects for buttons */
                #logoutModal button:hover,
                #logoutModal a:hover {
                    transform: translateY(-1px);
                    transition: all 0.2s;
                }

                /* Add shadow to modal */
                #logoutModal > div {
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                }
                </style>
    </body>
</html>