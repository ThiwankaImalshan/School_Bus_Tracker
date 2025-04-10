<?php
// enhanced_bus_tracker.php - Advanced bus location tracking with history features
session_start();
require_once 'db_connection.php';

// Check if parent is logged in
if (!isset($_SESSION['parent_id'])) {
    header('Location: login.php');
    exit;
}

// Check for child_id parameter
if (!isset($_GET['child_id'])) {
    header('Location: dashboard.php');
    exit;
}

$parent_id = $_SESSION['parent_id'];
$child_id = (int)$_GET['child_id'];

// Verify child belongs to parent
$stmt = $pdo->prepare("SELECT c.*, b.bus_number, b.license_plate, b.capacity, b.starting_location, 
                             d.full_name as driver_name, d.phone as driver_phone
                      FROM child c 
                      LEFT JOIN bus b ON c.bus_id = b.bus_id
                      LEFT JOIN driver d ON d.bus_id = b.bus_id
                      WHERE c.child_id = ? AND c.parent_id = ?");
$stmt->execute([$child_id, $parent_id]);
$childDetails = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$childDetails) {
    header('Location: dashboard.php');
    exit;
}

// Get bus tracking info if bus is assigned
$location = null;
if ($childDetails['bus_id']) {
    $stmt = $pdo->prepare("SELECT * FROM bus_tracking 
                          WHERE bus_id = ? 
                          ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$childDetails['bus_id']]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Set timezone for Sri Lanka
date_default_timezone_set('Asia/Colombo');

// Determine current route based on time
$current_time = isset($_POST['device_time']) ? strtotime($_POST['device_time']) : time();
$current_hour = (int)date('H', $current_time);
$current_minute = (int)date('i', $current_time);
$time_in_minutes = ($current_hour * 60) + $current_minute;

$morning_start = (5 * 60); // 5:00 AM
$morning_end = (9 * 60); // 9:00 AM
$evening_start = (12 * 60); // 12:00 PM
$evening_end = (17 * 60); // 5:00 PM

if ($time_in_minutes >= $morning_start && $time_in_minutes < $morning_end) {
    $current_route = "morning";
    $route_text = "Morning Route (" . date('h:i A', $current_time) . ")";
} elseif ($time_in_minutes >= $evening_start && $time_in_minutes < $evening_end) {
    $current_route = "evening";
    $route_text = "Evening Route (" . date('h:i A', $current_time) . ")";
} else {
    $current_route = "none"; 
    $route_text = "No Active Route (" . date('h:i A', $current_time) . ")";
}

// Get selected date (default to today)
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$is_current_day = ($selected_date == date('Y-m-d'));

// Get route history for the selected date if viewing past data
$route_history = null;
if (!$is_current_day) {
    $stmt = $pdo->prepare("SELECT bt.*, 
                          r.route_name,
                          CASE 
                            WHEN HOUR(bt.timestamp) BETWEEN 5 AND 11 THEN 'morning' 
                            WHEN HOUR(bt.timestamp) BETWEEN 12 AND 17 THEN 'evening'
                            ELSE 'other' 
                          END as route_type
                          FROM bus_tracking bt
                          LEFT JOIN route r ON bt.route_id = r.route_id
                          WHERE bt.bus_id = ? AND DATE(bt.timestamp) = ?
                          ORDER BY bt.timestamp ASC");
    $stmt->execute([$childDetails['bus_id'], $selected_date]);
    $route_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group tracking points by route type
    $morning_route = array_filter($route_history, function($point) {
        return $point['route_type'] == 'morning';
    });
    
    $evening_route = array_filter($route_history, function($point) {
        return $point['route_type'] == 'evening';
    });
}

// Get list of dates with route data for the dropdown
$stmt = $pdo->prepare("SELECT DISTINCT DATE(timestamp) as route_date 
                      FROM bus_tracking 
                      WHERE bus_id = ? 
                      ORDER BY route_date DESC 
                      LIMIT 30");
$stmt->execute([$childDetails['bus_id']]);
$available_dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's date
$today = date('Y-m-d');

// Get route information for the bus
$stmt = $pdo->prepare("SELECT r.*, 
                      COUNT(DISTINCT rs.stop_id) as total_points,
                      SUM(CASE WHEN rs.estimated_time IS NOT NULL THEN 1 ELSE 0 END) as completed_points
                      FROM route r
                      LEFT JOIN route_stop rs ON r.route_id = rs.route_id
                      WHERE r.bus_id = ? AND (
                          (? BETWEEN r.morning_pickup_start AND r.morning_dropoff_end AND ? = 'morning')
                          OR 
                          (? BETWEEN r.evening_pickup_start AND r.evening_dropoff_end AND ? = 'evening')
                      )
                      GROUP BY r.route_id");
$current_time_sql = date('H:i:s', $current_time);
$stmt->execute([$childDetails['bus_id'], $current_time_sql, $current_route, $current_time_sql, $current_route]);
$route_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get route stops for the bus route
$stmt = $pdo->prepare("SELECT rs.*, 
                     CASE WHEN rs.estimated_time IS NOT NULL THEN 1 ELSE 0 END as is_completed
                     FROM route_stop rs
                     WHERE rs.route_id = ?
                     ORDER BY rs.sequence_number");
$stmt->execute([$route_info['route_id'] ?? 0]);
$route_points = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to update location in database
if (isset($_POST['update_location'])) {
    $latitude = filter_input(INPUT_POST, 'latitude', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $longitude = filter_input(INPUT_POST, 'longitude', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $speed = filter_input(INPUT_POST, 'speed', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    if ($latitude && $longitude) {
        $stmt = $pdo->prepare("INSERT INTO bus_tracking (bus_id, latitude, longitude, timestamp, route_id, status, speed) 
                              VALUES (?, ?, ?, NOW(), ?, 'ongoing', ?)");
        if ($stmt->execute([$childDetails['bus_id'], $latitude, $longitude, $route_info['route_id'] ?? null, $speed])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
    exit;
}

// Function to mark a point as completed via AJAX
if (isset($_POST['mark_completed'])) {
    $stop_id = filter_input(INPUT_POST, 'stop_id', FILTER_SANITIZE_NUMBER_INT);
    
    if ($stop_id) {
        $stmt = $pdo->prepare("UPDATE route_stop SET estimated_time = NOW() WHERE stop_id = ?");
        if ($stmt->execute([$stop_id])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid stop ID']);
    exit;
}

// Get nearest upcoming stops (limited to 3)
$upcoming_stops = array_filter($route_points, function($point) {
    return empty($point['estimated_time']);
});
$upcoming_stops = array_slice($upcoming_stops, 0, 3);

// Update the HTML section to use location instead of school/child info
foreach ($upcoming_stops as &$stop) {
    $stop['short_name'] = "Stop #" . $stop['sequence_number'];
    $stop['address'] = $stop['location'];
}
unset($stop);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Bus Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
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
        .shadow-enhanced {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        #map {
            height: 400px;
            width: 100%;
            border-radius: 0.75rem;
        }
        .heading-brown {
            color: #92400e;
        }
        .toggle-checkbox:checked {
            right: 0;
            border-color: #FF8C00;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #FF8C00;
        }
        .toggle-checkbox {
            absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer
        }
        .toggle-label {
            block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer
        }
        .bus-icon {
            border-radius: 50%;
            text-align: center;
            background-color: #FF8C00;
            color: white;
            width: 30px;
            height: 30px;
            line-height: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            font-weight: bold;
        }
        .point-icon {
            border-radius: 50%;
            text-align: center;
            width: 24px;
            height: 24px;
            line-height: 24px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
            font-weight: bold;
        }
        .refresh-animation {
            animation: spin 1s linear infinite;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
            color: white;
            transition: all 0.3s ease;
        }
        .btn-gradient:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Timeline styling */
        .timeline-line {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 16px;
            width: 2px;
            background-color: #e5e7eb;
        }
        .timeline-dot {
            position: absolute;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            transform: translateX(-5px);
            left: 16px;
            margin-top: 6px;
        }
        .timeline-dot-completed {
            background-color: #10b981;
        }
        .timeline-dot-current {
            background-color: #f59e0b;
            width: 16px;
            height: 16px;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #f59e0b;
            transform: translateX(-7px);
            margin-top: 4px;
        }
        .timeline-dot-upcoming {
            background-color: #d1d5db;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            #map {
                height: 300px;
            }
        }
        @media (max-width: 640px) {
            #map {
                height: 250px;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <nav class="bg-white/90 backdrop-blur-sm text-gray-800 shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-bold text-yellow-900">School Bus Tracker</h1>
            </div>
            <div class="flex items-center space-x-6">
                <span class="text-yellow-900 font-medium"><?php echo htmlspecialchars($childDetails['full_name'] ?? 'Parent'); ?></span>
                <a href="dashboard.php" class="bg-yellow-900 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition duration-300 shadow-md hover:shadow-lg">Dashboard</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        <?php if (!$childDetails['bus_id']): ?>
            <div class="glass-container text-red-600 p-4 mb-6" role="alert">
                <p>Your child is not assigned to a bus. Please contact the administrator.</p>
            </div>
        <?php endif; ?>

        <section id="tracker-section" class="glass-container p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <div class="flex items-center space-x-3">
                        <h2 class="text-2xl font-bold heading-brown">Bus Tracker</h2>
                    </div>
                    <p class="text-gray-600 mt-1">
                        Bus <?php echo htmlspecialchars($childDetails['bus_number'] ?? 'N/A'); ?> | 
                        <span id="route-display" class="font-medium">
                            <?php echo htmlspecialchars($route_text); ?>
                        </span>
                    </p>
                </div>
                
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium flex items-center">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                        <?php echo $is_current_day ? 'Live Tracking' : 'Historical View'; ?>
                    </div>
                    <div id="last-updated-container" class="flex items-center">
                        <span id="last-updated" class="text-xs text-gray-500">Last updated: <?php echo isset($location['timestamp']) ? date('h:i A', strtotime($location['timestamp'])) : 'Never'; ?></span>
                        <?php if ($is_current_day): ?>
                            <button id="refresh-btn" class="text-white p-2 rounded-lg transition-colors ml-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="black">
                                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Date Selection for Route History -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6 shadow-sm">
                <form action="" method="get" class="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-4">
                    <div class="w-full sm:w-auto">
                        <label for="date-select" class="block text-sm font-medium text-gray-700 mb-1">Select Date</label>
                        <select id="date-select" name="date" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-200 focus:ring-opacity-50">
                            <option value="<?php echo date('Y-m-d'); ?>" <?php echo $selected_date == date('Y-m-d') ? 'selected' : ''; ?>>Today</option>
                            <?php foreach ($available_dates as $date): ?>
                                <?php if ($date['route_date'] != date('Y-m-d')): ?>
                                    <option value="<?php echo $date['route_date']; ?>" <?php echo $selected_date == $date['route_date'] ? 'selected' : ''; ?>>
                                        <?php echo date('M d, Y', strtotime($date['route_date'])); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full sm:w-auto flex space-x-2">
                        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-colors">
                            View Route
                        </button>
                        <?php if ($selected_date != date('Y-m-d')): ?>
                            <a href="?date=<?php echo date('Y-m-d'); ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                                Back to Today
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Map Section (2/3 width on large screens) -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden h-full">
                        <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="text-lg font-semibold heading-brown">
                                <?php echo $is_current_day ? 'Live Location' : 'Route History'; ?>
                            </h3>
                            <?php if ($is_current_day): ?>
                                <button id="map-refresh-btn" class="btn-primary text-sm px-4 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 text-white transition-colors">
                                    Refresh
                                </button>
                            <?php else: ?>
                                <div class="flex space-x-3">
                                    <button id="show-morning-route" class="text-sm px-3 py-1 rounded-lg bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-200 transition-colors">
                                        Morning Route
                                    </button>
                                    <button id="show-evening-route" class="text-sm px-3 py-1 rounded-lg bg-orange-100 text-orange-700 border border-orange-200 hover:bg-orange-200 transition-colors">
                                        Evening Route
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="relative">
                            <div id="map"></div>
                            
                            <?php if ($is_current_day): ?>
                                <div id="speed-display" class="fixed lg:bottom-4 lg:right-4 bottom-32 right-4 bg-white/90 backdrop-filter backdrop-blur-sm rounded-lg shadow-lg p-2 z-[9999] border border-gray-200 hover:bg-white transition-colors duration-200">
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4.535l-3.928 2.62a1 1 0 101.11 1.66l4.5-3a1 1 0 00.318-1.334V6z" clip-rule="evenodd"/>
                                        </svg>
                                        <div class="flex items-baseline gap-1">
                                            <span id="speed-value" class="text-lg font-bold text-orange-500">0</span>
                                            <span class="text-xs text-orange-500">km/h</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Driver Information Card -->
                            <div class="absolute top-4 right-4 bg-white rounded-xl shadow-lg p-3 w-64 flex items-center">
                                <div class="w-12 h-12 rounded-full overflow-hidden mr-3 flex-shrink-0 border-2 border-orange-300 bg-orange-100 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($childDetails['driver_name'] ?? 'Driver'); ?></h4>
                                    <div class="flex items-center mt-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-orange-500 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-sm text-gray-600">Bus #<?php echo htmlspecialchars($childDetails['bus_number'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 bg-gray-50 border-t border-gray-100">
                            <div class="flex flex-wrap gap-4">
                                <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                    <div class="text-xs text-gray-500">Current Speed</div>
                                    <div class="text-lg font-medium" id="display-speed">
                                        <?php echo isset($location['speed']) ? round($location['speed']) : '0'; ?> km/h
                                    </div>
                                </div>
                                <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                    <div class="text-xs text-gray-500">ETA to Next Stop</div>
                                    <div class="text-lg font-medium" id="eta-next-stop">--</div>
                                </div>
                                <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                    <div class="text-xs text-gray-500">Distance Traveled</div>
                                    <div class="text-lg font-medium" id="distance-traveled">--</div>
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
                        
                        <?php if ($is_current_day && $current_route != 'none'): ?>
                            <!-- Current Route Status -->
                            <div class="p-4 border-b border-gray-100 bg-orange-50">
                                <div class="flex items-start">
                                    <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                        </svg>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <h4 class="font-medium text-gray-800">Current Route Progress</h4>
                                        <div class="grid grid-cols-2 gap-4 mt-2">
                                            <div>
                                                <div class="text-xs text-gray-500">Completed</div>
                                                <div class="text-sm font-medium">
                                                    <?php 
                                                        $completed = $route_info['completed_points'] ?? 0;
                                                        $total = $route_info['total_points'] ?? 0;
                                                        echo $completed . ' / ' . $total . ' stops';
                                                    ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Estimated Time</div>
                                                <div class="text-sm font-medium">
                                                    <?php echo isset($route_info['estimated_duration']) ? $route_info['estimated_duration'] . ' min' : '--'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Progress Bar -->
                                        <div class="mt-3 w-full bg-gray-200 rounded-full h-2.5">
                                            <?php $progress = $total > 0 ? ($completed / $total) * 100 : 0; ?>
                                            <div class="bg-orange-500 h-2.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Route Timeline -->
                        <div class="p-4 overflow-y-auto" style="max-height: 400px;">
                            <h4 class="text-sm font-medium text-gray-700 mb-4">
                                <?php echo $is_current_day ? 'Route Timeline' : 'Historical Route Stops'; ?>
                            </h4>
                            
                            <div class="relative">
                            <div class="timeline-line"></div>
                                
                                <!-- Route Stops Timeline -->
                                <?php if (!empty($route_points)): ?>
                                    <div class="space-y-5">
                                        <?php foreach ($route_points as $index => $point): ?>
                                            <?php 
                                                $current = !$point['is_completed'] && ($index === 0 || $route_points[$index-1]['is_completed']);
                                                $status_class = $point['is_completed'] ? 'timeline-dot-completed' : ($current ? 'timeline-dot-current' : 'timeline-dot-upcoming');
                                                $item_class = $point['is_completed'] ? 'bg-green-50' : ($current ? 'bg-orange-50' : 'bg-white');
                                            ?>
                                            <div class="relative pl-8">
                                                <span class="timeline-dot <?php echo $status_class; ?>"></span>
                                                <div class="rounded-lg p-3 <?php echo $item_class; ?> border <?php echo $point['is_completed'] ? 'border-green-100' : ($current ? 'border-orange-100' : 'border-gray-100'); ?>">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($point['location']); ?></p>
                                                            <p class="text-xs text-gray-500 mt-1">Stop #<?php echo $point['sequence_number']; ?></p>
                                                        </div>
                                                        <div class="text-right">
                                                            <?php if ($point['is_completed']): ?>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                    Completed
                                                                </span>
                                                                <p class="text-xs text-gray-500 mt-1">
                                                                    <?php echo date('h:i A', strtotime($point['estimated_time'])); ?>
                                                                </p>
                                                            <?php else: ?>
                                                                <?php if ($current && $is_current_day): ?>
                                                                    <button 
                                                                        class="mark-complete-btn inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 hover:bg-orange-200 transition-colors" 
                                                                        data-stop-id="<?php echo $point['stop_id']; ?>">
                                                                        Mark Complete
                                                                    </button>
                                                                    <p class="text-xs text-gray-500 mt-1" id="eta-<?php echo $point['stop_id']; ?>">
                                                                        ETA: Calculating...
                                                                    </p>
                                                                <?php else: ?>
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                                        Pending
                                                                    </span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                        </svg>
                                        <p>No route stops available for this time period</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Action Buttons (for current day only) -->
                        <?php if ($is_current_day && $childDetails['bus_id']): ?>
                            <div class="p-4 border-t border-gray-100 bg-gray-50">
                                <button id="start-tracking-btn" class="w-full btn-gradient px-4 py-3 rounded-lg font-medium flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z" clip-rule="evenodd" />
                                    </svg>
                                    Start Tracking
                                </button>
                                <button id="stop-tracking-btn" class="w-full mt-2 bg-white border border-gray-200 hover:bg-gray-50 px-4 py-3 rounded-lg font-medium flex items-center justify-center text-gray-700 hidden">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd" />
                                    </svg>
                                    Stop Tracking
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Upcoming Stops Section (Only for current day) -->
        <?php if ($is_current_day && !empty($upcoming_stops)): ?>
            <section class="glass-container p-6 mb-6">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="h-8 w-1 bg-orange-500 rounded-full"></div>
                    <h2 class="text-2xl font-bold heading-brown">Upcoming Stops</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($upcoming_stops as $stop): ?>
                        <div class="bg-white rounded-xl shadow-enhanced p-4 border border-gray-100 flex items-start">
                            <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center mr-3 flex-shrink-0">
                                <span class="font-semibold text-orange-500"><?php echo $stop['sequence_number']; ?></span>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($stop['location']); ?></h4>
                                <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($stop['address']); ?></p>
                                <div class="mt-2 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-orange-500 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-xs text-gray-500" id="eta-upcoming-<?php echo $stop['stop_id']; ?>">ETA: Calculating...</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer class="bg-white/80 backdrop-blur-sm text-white py-4 border-t border-gray-200">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; <?php echo date('Y'); ?> School Bus Tracking System - Sri Lanka</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            var map = L.map('map').setView([7.8731, 80.7718], 8); // Default center of Sri Lanka
            
            // Add OpenStreetMap tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Bus icon
            var busIcon = L.divIcon({
                className: 'bus-icon',
                html: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mx-auto mt-1.5" viewBox="0 0 20 20" fill="currentColor"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" /><path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H11a1 1 0 001-1v-1h3.05a2.5 2.5 0 014.9 0H19a1 1 0 001-1v-7a1 1 0 00-.293-.707l-2-2A1 1 0 0017 3H3z" /></svg>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            // Waypoint icon (for route stops)
            function createPointIcon(number, completed) {
                return L.divIcon({
                    className: 'point-icon',
                    html: '<div style="background-color: ' + (completed ? '#10b981' : '#d1d5db') + '; color: white; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 12px;">' + number + '</div>',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                });
            }
            
            // Variables for tracking
            var busMarker = null;
            var routeControl = null;
            var watchId = null;
            var isTracking = false;
            var currentPosition = null;
            var routePoints = [];
            var routePolyline = null;
            var routeMarkers = [];
            
            <?php if ($is_current_day): ?>
                // Current location from database (if available)
                <?php if (isset($location) && $location): ?>
                    currentPosition = [<?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?>];
                    showBusOnMap(currentPosition);
                <?php endif; ?>
                
                // Route points
                <?php if (!empty($route_points)): ?>
                    var routeStops = [
                        <?php foreach ($route_points as $point): ?>
                            {
                                id: <?php echo $point['stop_id']; ?>,
                                location: [<?php echo $point['latitude']; ?>, <?php echo $point['longitude']; ?>],
                                name: "<?php echo htmlspecialchars(addslashes($point['location'])); ?>",
                                sequence: <?php echo $point['sequence_number']; ?>,
                                completed: <?php echo $point['is_completed'] ? 'true' : 'false'; ?>
                            },
                        <?php endforeach; ?>
                    ];
                    showRouteStops(routeStops);
                    
                    if (currentPosition) {
                        calculateETAs(currentPosition, routeStops.filter(stop => !stop.completed));
                    }
                <?php endif; ?>
                
                // Map refresh button
                document.getElementById('map-refresh-btn').addEventListener('click', function() {
                    if (isTracking) {
                        getCurrentLocation();
                    } else {
                        alert('Tracking is not active. Please start tracking first.');
                    }
                });
                
                // Start tracking button
                document.getElementById('start-tracking-btn').addEventListener('click', function() {
                    if (!isTracking) {
                        startTracking();
                        this.classList.add('hidden');
                        document.getElementById('stop-tracking-btn').classList.remove('hidden');
                    }
                });
                
                // Stop tracking button
                document.getElementById('stop-tracking-btn').addEventListener('click', function() {
                    if (isTracking) {
                        stopTracking();
                        this.classList.add('hidden');
                        document.getElementById('start-tracking-btn').classList.remove('hidden');
                    }
                });
                
                // Mark complete buttons
                document.querySelectorAll('.mark-complete-btn').forEach(function(button) {
                    button.addEventListener('click', function() {
                        var stopId = this.getAttribute('data-stop-id');
                        markStopComplete(stopId);
                    });
                });
            <?php else: ?>
                // Historical route data
                <?php if (!empty($morning_route)): ?>
                    var morningRoutePoints = [
                        <?php foreach ($morning_route as $point): ?>
                            {
                                lat: <?php echo $point['latitude']; ?>,
                                lng: <?php echo $point['longitude']; ?>,
                                time: "<?php echo date('h:i A', strtotime($point['timestamp'])); ?>"
                            },
                        <?php endforeach; ?>
                    ];
                <?php else: ?>
                    var morningRoutePoints = [];
                <?php endif; ?>
                
                <?php if (!empty($evening_route)): ?>
                    var eveningRoutePoints = [
                        <?php foreach ($evening_route as $point): ?>
                            {
                                lat: <?php echo $point['latitude']; ?>,
                                lng: <?php echo $point['longitude']; ?>,
                                time: "<?php echo date('h:i A', strtotime($point['timestamp'])); ?>"
                            },
                        <?php endforeach; ?>
                    ];
                <?php else: ?>
                    var eveningRoutePoints = [];
                <?php endif; ?>
                
                // Show historical route by default (if available)
                if (morningRoutePoints.length > 0) {
                    showHistoricalRoute(morningRoutePoints, 'morning');
                } else if (eveningRoutePoints.length > 0) {
                    showHistoricalRoute(eveningRoutePoints, 'evening');
                }
                
                // Morning route button
                document.getElementById('show-morning-route').addEventListener('click', function() {
                    if (morningRoutePoints.length > 0) {
                        showHistoricalRoute(morningRoutePoints, 'morning');
                    } else {
                        alert('No morning route data available for this date.');
                    }
                });
                
                // Evening route button
                document.getElementById('show-evening-route').addEventListener('click', function() {
                    if (eveningRoutePoints.length > 0) {
                        showHistoricalRoute(eveningRoutePoints, 'evening');
                    } else {
                        alert('No evening route data available for this date.');
                    }
                });
            <?php endif; ?>
            
            // Date selector change event
            document.getElementById('date-select').addEventListener('change', function() {
                if (this.value) {
                    window.location.href = '?date=' + this.value;
                }
            });
            
            // Function to update the bus marker on the map
            function showBusOnMap(position) {
                if (busMarker) {
                    busMarker.setLatLng(position);
                } else {
                    busMarker = L.marker(position, {icon: busIcon}).addTo(map);
                }
                map.setView(position, 15);
            }
            
            // Function to show route stops on the map
            function showRouteStops(stops) {
                // Clear existing markers
                routeMarkers.forEach(function(marker) {
                    map.removeLayer(marker);
                });
                routeMarkers = [];
                
                // Add markers for each stop
                stops.forEach(function(stop) {
                    var marker = L.marker(stop.location, {
                        icon: createPointIcon(stop.sequence, stop.completed)
                    }).addTo(map);
                    
                    marker.bindPopup("<b>Stop #" + stop.sequence + "</b><br>" + stop.name);
                    routeMarkers.push(marker);
                });
                
                // Adjust map to show all stops if no current position
                if (!currentPosition && stops.length > 0) {
                    var bounds = L.latLngBounds(stops.map(function(stop) {
                        return stop.location;
                    }));
                    map.fitBounds(bounds, { padding: [50, 50] });
                }
            }
            
            // Function to show historical route
            function showHistoricalRoute(points, routeType) {
                // Clear existing route
                if (routePolyline) {
                    map.removeLayer(routePolyline);
                }
                
                if (busMarker) {
                    map.removeLayer(busMarker);
                    busMarker = null;
                }
                
                // Create route line
                var color = routeType === 'morning' ? '#3b82f6' : '#f59e0b';
                var latlngs = points.map(function(point) {
                    return [point.lat, point.lng];
                });
                
                routePolyline = L.polyline(latlngs, {
                    color: color,
                    weight: 5,
                    opacity: 0.8
                }).addTo(map);
                
                // Add start and end markers
                if (points.length > 0) {
                    var startIcon = L.divIcon({
                        className: 'point-icon',
                        html: '<div style="background-color: #10b981; color: white; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.707-10.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L9.414 11H13a1 1 0 100-2H9.414l1.293-1.293z" clip-rule="evenodd" /></svg></div>',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                    
                    var endIcon = L.divIcon({
                        className: 'point-icon',
                        html: '<div style="background-color: #ef4444; color: white; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z" clip-rule="evenodd" /></svg></div>',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                    
                    var start = points[0];
                    var end = points[points.length - 1];
                    
                    L.marker([start.lat, start.lng], {icon: startIcon})
                        .bindPopup("<b>Start</b><br>" + start.time)
                        .addTo(map);
                        
                    L.marker([end.lat, end.lng], {icon: endIcon})
                        .bindPopup("<b>End</b><br>" + end.time)
                        .addTo(map);
                    
                    // Set map bounds to show the whole route
                    map.fitBounds(routePolyline.getBounds(), { padding: [50, 50] });
                }
            }
            
            // Function to start tracking
            function startTracking() {
                if (navigator.geolocation) {
                    routePoints = [];
                    isTracking = true;
                    
                    if (routePolyline) {
                        map.removeLayer(routePolyline);
                        routePolyline = null;
                    }
                    
                    // Get current location immediately
                    getCurrentLocation();
                    
                    // Set up regular updates
                    watchId = navigator.geolocation.watchPosition(handleLocation, handleLocationError, {
                        enableHighAccuracy: true,
                        maximumAge: 0,
                        timeout: 5000
                    });
                    
                    // Update UI
                    document.getElementById('refresh-btn').classList.add('refresh-animation');
                } else {
                    alert("Geolocation is not supported by this browser.");
                }
            }
            
            // Function to stop tracking
            function stopTracking() {
                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }
                isTracking = false;
                document.getElementById('refresh-btn').classList.remove('refresh-animation');
            }
            
            // Function to get current location
            function getCurrentLocation() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(handleLocation, handleLocationError, {
                        enableHighAccuracy: true,
                        maximumAge: 0,
                        timeout: 5000
                    });
                } else {
                    alert("Geolocation is not supported by this browser.");
                }
            }
            
            // Function to handle location update
            function handleLocation(position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                var speed = position.coords.speed ? position.coords.speed * 3.6 : 0; // Convert m/s to km/h
                
                currentPosition = [lat, lng];
                showBusOnMap(currentPosition);
                updateRouteDisplay(lat, lng, speed);
                
                // Calculate ETAs to upcoming stops
                <?php if (!empty($route_points)): ?>
                    var upcomingStops = routeStops.filter(stop => !stop.completed);
                    calculateETAs(currentPosition, upcomingStops);
                <?php endif; ?>
                
                // Add point to route history
                routePoints.push({lat: lat, lng: lng});
                
                // Update route polyline
                if (routePoints.length > 1) {
                    if (routePolyline) {
                        map.removeLayer(routePolyline);
                    }
                    
                    routePolyline = L.polyline(routePoints, {
                        color: '#f59e0b',
                        weight: 4,
                        opacity: 0.7
                    }).addTo(map);
                }
                
                // Update last location time
                document.getElementById('last-updated').textContent = 'Last updated: ' + new Date().toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true});
            }
            
            // Function to handle location errors
            function handleLocationError(error) {
                let errorMessage;
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage = "User denied the request for Geolocation.";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage = "Location information is unavailable.";
                        break;
                    case error.TIMEOUT:
                        errorMessage = "The request to get user location timed out.";
                        break;
                    case error.UNKNOWN_ERROR:
                        errorMessage = "An unknown error occurred.";
                        break;
                }
                alert("Error getting location: " + errorMessage);
            }
            
            // Function to update route display
            function updateRouteDisplay(lat, lng, speed) {
                // Save to database via AJAX
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (!response.success) {
                            console.error('Error updating location: ', response.message);
                        }
                    }
                };
                
                // Update speed display
                var roundedSpeed = Math.round(speed);
                document.getElementById('speed-value').textContent = roundedSpeed;
                document.getElementById('display-speed').textContent = roundedSpeed + ' km/h';
                
                // Send device time to adjust for timezone
                var deviceTime = new Date().toISOString();
                xhr.send('update_location=1&latitude=' + lat + '&longitude=' + lng + '&speed=' + speed + '&device_time=' + deviceTime);
            }
            
            // Function to calculate ETAs
            function calculateETAs(currentPos, stops) {
                if (stops.length === 0) return;
                
                // Calculate straight-line distance and rough ETA
                stops.forEach(function(stop) {
                    var distance = calculateDistance(currentPos[0], currentPos[1], stop.location[0], stop.location[1]);
                    var eta = estimateETA(distance);
                    
                    // Update ETA in the UI
                    var etaElement = document.getElementById('eta-' + stop.id);
                    if (etaElement) {
                        etaElement.textContent = 'ETA: ' + eta;
                    }
                    
                    // Update upcoming stops ETA as well
                    var upcomingEtaElement = document.getElementById('eta-upcoming-' + stop.id);
                    if (upcomingEtaElement) {
                        upcomingEtaElement.textContent = 'ETA: ' + eta;
                    }
                });
                
                // Update next stop ETA in stats section
                if (stops.length > 0) {
                    var nextStop = stops[0];
                    var distance = calculateDistance(currentPos[0], currentPos[1], nextStop.location[0], nextStop.location[1]);
                    var eta = estimateETA(distance);
                    document.getElementById('eta-next-stop').textContent = eta;
                    
                    // Calculate total distance traveled
                    if (routePoints.length > 1) {
                        var totalDistance = 0;
                        for (var i = 1; i < routePoints.length; i++) {
                            totalDistance += calculateDistance(
                                routePoints[i-1].lat, routePoints[i-1].lng,
                                routePoints[i].lat, routePoints[i].lng
                            );
                        }
                        document.getElementById('distance-traveled').textContent = (totalDistance).toFixed(1) + ' km';
                    }
                }
            }
            
            // Function to calculate distance between coordinates
            function calculateDistance(lat1, lon1, lat2, lon2) {
                var R = 6371; // Radius of the earth in km
                var dLat = deg2rad(lat2 - lat1);
                var dLon = deg2rad(lon2 - lon1);
                var a = 
                    Math.sin(dLat/2) * Math.sin(dLat/2) +
                    Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
                    Math.sin(dLon/2) * Math.sin(dLon/2)
                    ; 
                var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
                var d = R * c; // Distance in km
                return d;
            }
            
            function deg2rad(deg) {
                return deg * (Math.PI/180);
            }
            
            // Function to estimate ETA based on distance
            function estimateETA(distance) {
                // Assuming average speed of 30 km/h in traffic
                var timeInHours = distance / 30;
                var timeInMinutes = Math.round(timeInHours * 60);
                
                if (timeInMinutes < 1) {
                    return 'Less than 1 min';
                } else if (timeInMinutes >= 60) {
                    var hours = Math.floor(timeInMinutes / 60);
                    var mins = timeInMinutes % 60;
                    return hours + ' hr ' + (mins > 0 ? mins + ' min' : '');
                } else {
                    return timeInMinutes + ' min';
                }
            }
            
            // Function to mark a stop as completed
            function markStopComplete(stopId) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
xhr.onload = function() {
    if (xhr.status === 200) {
        var response = JSON.parse(xhr.responseText);
        if (response.success) {
            // Update UI to show the stop as completed
            var buttonElement = document.querySelector('[data-stop-id="' + stopId + '"]');
            if (buttonElement) {
                var parentElement = buttonElement.parentElement;
                parentElement.innerHTML = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>' +
                                        '<p class="text-xs text-gray-500 mt-1">' + 
                                        new Date().toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true}) + 
                                        '</p>';
            }
            
            // Update the map marker icon to completed
            routeStops.forEach(function(stop) {
                if (stop.id == stopId) {
                    stop.completed = true;
                    
                    // Find and update the marker
                    routeMarkers.forEach(function(marker, index) {
                        if (marker._latlng.lat === stop.location[0] && marker._latlng.lng === stop.location[1]) {
                            map.removeLayer(marker);
                            routeMarkers[index] = L.marker(stop.location, {
                                icon: createPointIcon(stop.sequence, true)
                            }).addTo(map);
                            routeMarkers[index].bindPopup("<b>Stop #" + stop.sequence + "</b><br>" + stop.name);
                        }
                    });
                    
                    // Update timeline dot
                    var timelineDots = document.querySelectorAll('.timeline-dot');
                    if (timelineDots[stop.sequence - 1]) {
                        timelineDots[stop.sequence - 1].classList.remove('timeline-dot-current', 'timeline-dot-upcoming');
                        timelineDots[stop.sequence - 1].classList.add('timeline-dot-completed');
                        
                        // Update the parent div background
                        var parentDiv = timelineDots[stop.sequence - 1].closest('.relative').querySelector('.rounded-lg');
                        if (parentDiv) {
                            parentDiv.classList.remove('bg-orange-50', 'bg-white');
                            parentDiv.classList.add('bg-green-50');
                            parentDiv.classList.remove('border-orange-100', 'border-gray-100');
                            parentDiv.classList.add('border-green-100');
                        }
                    }
                    
                    // Set the next stop to current if it exists
                    var nextStop = routeStops.find(s => s.sequence === stop.sequence + 1 && !s.completed);
                    if (nextStop) {
                        var nextTimelineDot = document.querySelectorAll('.timeline-dot')[nextStop.sequence - 1];
                        if (nextTimelineDot) {
                            nextTimelineDot.classList.remove('timeline-dot-upcoming');
                            nextTimelineDot.classList.add('timeline-dot-current');
                            
                            // Update the parent div background
                            var nextParentDiv = nextTimelineDot.closest('.relative').querySelector('.rounded-lg');
                            if (nextParentDiv) {
                                nextParentDiv.classList.remove('bg-white');
                                nextParentDiv.classList.add('bg-orange-50');
                                nextParentDiv.classList.remove('border-gray-100');
                                nextParentDiv.classList.add('border-orange-100');
                            }
                        }
                    }
                    
                    // Update progress display
                    var completedPoints = document.querySelectorAll('.timeline-dot-completed').length;
                    var totalPoints = document.querySelectorAll('.timeline-dot').length;
                    var progressBar = document.querySelector('.bg-orange-500');
                    if (progressBar) {
                        var progress = (completedPoints / totalPoints) * 100;
                        progressBar.style.width = progress + '%';
                    }
                }
            });
                
            // Recalculate ETAs with the updated stops
            var upcomingStops = routeStops.filter(stop => !stop.completed);
            if (currentPosition) {
                calculateETAs(currentPosition, upcomingStops);
            }
        } else {
            console.error('Error marking stop as completed: ', response.message);
            alert('Could not mark stop as completed. Please try again.');
        }
    }
};
xhr.send('mark_completed=1&stop_id=' + stopId);
}
        });
    </script>
</body>
</html>