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

// Get current speed from latest bus tracking record
if ($childDetails['bus_id']) {
    $stmt = $pdo->prepare("
        SELECT speed 
        FROM bus_tracking 
        WHERE bus_id = ? 
        ORDER BY tracking_id DESC 
        LIMIT 1
    ");
    $stmt->execute([$childDetails['bus_id']]);
    $current_speed = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Set timezone for Sri Lanka
date_default_timezone_set('Asia/Colombo');

// Get route times from database
$route_times = [];
if ($childDetails['bus_id']) {
    $stmt = $pdo->prepare("
        SELECT route_type, start_time, end_time
        FROM route_times 
        WHERE bus_id = ?
    ");
    $stmt->execute([$childDetails['bus_id']]);
    $route_times = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Set default times in case no route times are found
$morning_start = (5 * 60); // Default 5:00 AM
$morning_end = (12 * 60); // Default 12:00 PM
$evening_start = (12 * 60); // Default 12:00 PM
$evening_end = (17 * 60); // Default 5:00 PM

// Update times if found in database
foreach ($route_times as $rt) {
    if ($rt['route_type'] === 'morning') {
        $morning_time = strtotime($rt['start_time']);
        $morning_start = (int)date('H', $morning_time) * 60 + (int)date('i', $morning_time);
        
        $morning_end_time = strtotime($rt['end_time']);
        $morning_end = (int)date('H', $morning_end_time) * 60 + (int)date('i', $morning_end_time);
    } else if ($rt['route_type'] === 'evening') {
        $evening_time = strtotime($rt['start_time']);
        $evening_start = (int)date('H', $evening_time) * 60 + (int)date('i', $evening_time);
        
        $evening_end_time = strtotime($rt['end_time']);
        $evening_end = (int)date('H', $evening_end_time) * 60 + (int)date('i', $evening_end_time);
    }
}

// Determine current route based on time
$current_time = isset($_POST['device_time']) ? strtotime($_POST['device_time']) : time();
$current_hour = (int)date('H', $current_time);
$current_minute = (int)date('i', $current_time);
$time_in_minutes = ($current_hour * 60) + $current_minute;

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
    // Get morning route data
    $stmt = $pdo->prepare("
        SELECT bt.latitude, bt.longitude, bt.timestamp, 'morning' as route_type
        FROM bus_tracking bt 
        WHERE bt.bus_id = ? 
        AND DATE(bt.timestamp) = ?
        AND (
            HOUR(bt.timestamp) * 60 + MINUTE(bt.timestamp) 
            BETWEEN ? AND ?
        )
        ORDER BY bt.timestamp ASC
    ");
    $stmt->execute([$childDetails['bus_id'], $selected_date, $morning_start, $morning_end]);
    $morning_route = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get evening route data
    $stmt = $pdo->prepare("
        SELECT bt.latitude, bt.longitude, bt.timestamp, 'evening' as route_type
        FROM bus_tracking bt 
        WHERE bt.bus_id = ? 
        AND DATE(bt.timestamp) = ?
        AND (
            HOUR(bt.timestamp) * 60 + MINUTE(bt.timestamp) 
            BETWEEN ? AND ?
        )
        ORDER BY bt.timestamp ASC
    ");
    $stmt->execute([$childDetails['bus_id'], $selected_date, $evening_start, $evening_end]);
    $evening_route = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get selected date's tracking data
if (!$is_current_day && $childDetails['bus_id']) {
    $stmt = $pdo->prepare("
        SELECT latitude, longitude, timestamp, speed
        FROM bus_tracking 
        WHERE bus_id = ? 
        AND DATE(timestamp) = ?
        ORDER BY timestamp ASC
    ");
    $stmt->execute([$childDetails['bus_id'], $selected_date]);
    $historical_route = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

// Get current day's route data
if ($is_current_day && $childDetails['bus_id']) {
    $stmt = $pdo->prepare("SELECT latitude, longitude, timestamp 
                          FROM bus_tracking 
                          WHERE bus_id = ? 
                          AND DATE(timestamp) = CURDATE()
                          ORDER BY timestamp ASC");
    $stmt->execute([$childDetails['bus_id']]);
    $today_route = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Update the route segment query to be time-sensitive
$tracking_points_query = "
    SELECT 
        t1.latitude as lat1, t1.longitude as lon1,
        t1.timestamp as time1,
        t2.latitude as lat2, t2.longitude as lon2,
        t2.timestamp as time2,
        t1.speed,
        ROUND(
            (6371 * acos(
                cos(radians(t1.latitude)) 
                * cos(radians(t2.latitude))
                * cos(radians(t2.longitude) - radians(t1.longitude))
                + sin(radians(t1.latitude))
                * sin(radians(t2.latitude))
            )), 2
        ) as segment_distance
    FROM bus_tracking t1
    JOIN bus_tracking t2 ON t2.tracking_id = t1.tracking_id + 1
    WHERE t1.bus_id = ? 
    AND DATE(t1.timestamp) = ?
    AND (
        HOUR(t1.timestamp) * 60 + MINUTE(t1.timestamp) 
        BETWEEN ? AND ?
    )
    ORDER BY t1.timestamp DESC
    LIMIT 10
";

// Initialize variables for current view
$current_view = isset($_GET['view']) ? $_GET['view'] : 'morning';
$time_range = $current_view === 'morning' ? 
    [$morning_start, $morning_end] : 
    [$evening_start, $evening_end];

$stmt = $pdo->prepare($tracking_points_query);
$stmt->execute([$childDetails['bus_id'], $selected_date, $time_range[0], $time_range[1]]);
$tracking_points = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Get stats from bus_tracking
$tracking_stats = array(
    'current_speed' => 0,
    'total_distance' => 0,
    'eta_next' => '--'
);

if ($childDetails['bus_id']) {
    // Get current speed and latest tracking info
    $stmt = $pdo->prepare("
        SELECT speed, latitude, longitude 
        FROM bus_tracking 
        WHERE bus_id = ? 
        AND DATE(timestamp) = CURDATE()
        ORDER BY timestamp DESC LIMIT 1
    ");
    $stmt->execute([$childDetails['bus_id']]);
    $current_tracking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_tracking) {
        $tracking_stats['current_speed'] = round($current_tracking['speed'] ?? 0);
        
        // Calculate total distance traveled today
        $stmt = $pdo->prepare("
            SELECT 
                SUM(
                    6371 * acos(
                        cos(radians(t1.latitude)) 
                        * cos(radians(t2.latitude))
                        * cos(radians(t2.longitude) - radians(t1.longitude))
                        + sin(radians(t1.latitude))
                        * sin(radians(t2.latitude))
                    )
                ) as total_distance
            FROM
                (SELECT latitude, longitude, timestamp FROM bus_tracking
                WHERE bus_id = ? AND DATE(timestamp) = CURDATE()
                ORDER BY timestamp) AS t1
            JOIN
                (SELECT latitude, longitude, timestamp FROM bus_tracking
                WHERE bus_id = ? AND DATE(timestamp) = CURDATE()
                ORDER BY timestamp) AS t2
            WHERE
                t2.timestamp > t1.timestamp
                AND t2.timestamp = (
                    SELECT MIN(timestamp) FROM bus_tracking
                    WHERE bus_id = ? AND DATE(timestamp) = CURDATE()
                    AND timestamp > t1.timestamp
                )
        ");
        $stmt->execute([$childDetails['bus_id'], $childDetails['bus_id'], $childDetails['bus_id']]);
        $distance_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $tracking_stats['total_distance'] = round($distance_result['total_distance'] ?? 0, 1);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus Tracker</title>
    <meta http-equiv="refresh" content="30">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <link rel="icon" type="image/png" href="../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../img/favicon/favicon.svg" />
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
            position: relative;
        }
        .driver-info-card {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 0.75rem;
            width: 16rem;
            display: flex;
            align-items: center;
            z-index: 1000;
            pointer-events: auto;
        }

        @media (max-width: 640px) {
            .driver-info-card {
                width: 14rem;
                padding: 0.5rem;
                top: 0.5rem;
                right: 0.5rem;
            }
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
                <h1 class="text-xl font-bold text-yellow-900">Safe To School</h1>
            </div>
            <div class="flex items-center space-x-6">
                <span class="text-yellow-900 font-medium"><?php echo htmlspecialchars($childDetails['first_name'] . ' ' . $childDetails['last_name'] ?? 'Child'); ?></span>
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
                        Bus <?php echo htmlspecialchars($childDetails['bus_number'] ?? 'N/A'); ?>  
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
                            <!-- <a href="?date=<?php echo date('Y-m-d'); ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                                Back to Today
                            </a> -->
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
                            <div class="driver-info-card sm:w-14rem sm:p-2 sm:top-2 sm:right-2">
                                <div class="w-12 h-12 sm:w-10 sm:h-10 rounded-full overflow-hidden mr-3 sm:mr-2 flex-shrink-0 border-2 border-orange-300 bg-orange-100 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 sm:h-5 sm:w-5 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-800 sm:text-sm"><?php echo htmlspecialchars($childDetails['driver_name'] ?? 'Driver'); ?></h4>
                                    <div class="flex items-center mt-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-3 sm:w-3 text-orange-500 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-sm sm:text-xs text-gray-600">Bus <?php echo htmlspecialchars($childDetails['bus_number'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 bg-gray-50 border-t border-gray-100">
                            <div class="flex flex-wrap gap-4">
                                <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                    <div class="text-xs text-gray-500">Speed</div>
                                    <div class="text-lg font-medium" id="display-speed">
                                        <?php echo isset($current_speed['speed']) ? round($current_speed['speed']) : '0'; ?> km/h
                                    </div>
                                </div>
                                <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                    <div class="text-xs text-gray-500">ETA to Next Stop</div>
                                    <div class="text-lg font-medium" id="eta-next-stop">
                                        <?php echo $tracking_stats['eta_next']; ?>
                                    </div>
                                </div>
                                <div class="bg-white rounded-lg p-3 shadow-sm flex-1 min-w-max">
                                    <div class="text-xs text-gray-500">Distance Traveled</div>
                                    <div class="text-lg font-medium" id="distance-traveled">
                                        <?php echo $tracking_stats['total_distance']; ?> km
                                    </div>
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

                        <!-- Mini Map for Route Display -->
                        <div class="p-4 border-b border-gray-100">
                            <div id="mini-map" class="w-full h-48 rounded-lg mb-4"></div>
                            
                            <!-- Route Details from bus_tracking -->
                            <?php
                            $stmt = $pdo->prepare($tracking_points_query);
                            $stmt->execute([$childDetails['bus_id'], $selected_date, $time_range[0], $time_range[1]]);
                            $tracking_points = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($tracking_points)): 
                                $total_distance = 0;
                                $total_time = 0;
                            ?>
                            <div class="space-y-3 mt-4">
                                <div class="text-sm font-medium text-gray-700">Route Segments</div>
                                <div class="max-h-48 overflow-y-auto space-y-2">
                                    <?php foreach ($tracking_points as $point): 
                                        $total_distance += $point['segment_distance'];
                                        $time_diff = strtotime($point['time2']) - strtotime($point['time1']);
                                        $total_time += $time_diff;
                                    ?>
                                        <div class="bg-gray-50 rounded-lg p-2 text-xs">
                                            <div class="flex justify-between text-gray-600">
                                                <span><?php echo date('h:i A', strtotime($point['time1'])); ?></span>
                                                <span class="font-medium"><?php echo number_format($point['segment_distance'] ?? 0, 2); ?> km</span>
                                            </div>
                                            <div class="mt-1 text-gray-500">
                                                Speed: <?php echo round($point['speed'] ?? 0); ?> km/h
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-4 bg-orange-50 rounded-lg p-3">
                                    <div>
                                        <div class="text-xs text-gray-500">Total Distance</div>
                                        <div class="text-sm font-medium text-gray-800">
                                            <?php echo number_format($total_distance, 2); ?> km
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Total Time</div>
                                        <div class="text-sm font-medium text-gray-800">
                                            <?php echo floor($total_time / 60); ?> min
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-gray-500">
                                    <p>No route tracking data available for today</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
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
            
            // Function to draw route path through roads
            function drawRoutePath(points, targetMap, options = {}) {
                const isMainMap = targetMap === map;
                const routeOptions = {
                    color: options.color || '#ef4444',
                    weight: isMainMap ? 3 : 2,
                    opacity: 0.8
                };

                const markerSize = isMainMap ? 16 : 12;  // Reduced marker sizes

                if (points && points.length > 1) {
                    const waypoints = points.map(point => 
                        L.latLng(point.latitude || point.lat1, point.longitude || point.lon1)
                    );

                    const routingControl = L.Routing.control({
                        waypoints: waypoints,
                        routeWhileDragging: false,
                        lineOptions: { styles: [routeOptions] },
                        show: false,
                        addWaypoints: false,
                        createMarker: function(i, wp, nWps) {
                            if (i === 0 || i === nWps - 1) {
                                // Start or End marker
                                const markerLabel = i === 0 ? 'S' : 'E';
                                const markerColor = i === 0 ? '#10b981' : routeOptions.color;
                                return L.marker(wp.latLng, {
                                    icon: L.divIcon({
                                        className: 'point-icon',
                                        html: `<div style="background-color: ${markerColor}; color: white; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: ${isMainMap ? '10px' : '8px'}">${markerLabel}</div>`,
                                        iconSize: [markerSize, markerSize],
                                        iconAnchor: [markerSize/2, markerSize/2]
                                    })
                                });
                            }
                            
                            // Intermediate points as dots
                            return L.circleMarker(wp.latLng, {
                                radius: isMainMap ? 4 : 3,
                                color: routeOptions.color,
                                fillColor: routeOptions.color,
                                fillOpacity: 1,
                                weight: 1
                            });
                        }
                    }).addTo(targetMap);

                    routingControl.hide();

                    // Add intermediate points only on main map
                    if (isMainMap) {
                        points.slice(1, -1).forEach(point => {
                            L.circleMarker(
                                [point.latitude || point.lat1, point.longitude || point.lon1], 
                                {
                                    radius: 3,
                                    color: routeOptions.color,
                                    fillColor: routeOptions.color,
                                    fillOpacity: 0.5,
                                    weight: 1
                                }
                            ).addTo(targetMap);
                        });
                    }

                    const bounds = L.latLngBounds(waypoints);
                    targetMap.fitBounds(bounds, { padding: [50, 50] });

                    return routingControl;
                }
                return null;
            }

            function showHistoricalRoute(points, routeType) {
                const routeColor = routeType === 'morning' ? '#3b82f6' : '#ef4444';
                const timeRange = routeType === 'morning' ? 
                    { start: <?php echo $morning_start; ?>, end: <?php echo $morning_end; ?> } :
                    { start: <?php echo $evening_start; ?>, end: <?php echo $evening_end; ?> };

                // Clear existing routes
                if (routeControl) map.removeControl(routeControl);
                if (miniRouteControl) miniMap.removeControl(miniRouteControl);

                // Filter points for time period
                const filteredPoints = points.filter(point => {
                    const pointTime = new Date(point.timestamp);
                    const minutes = pointTime.getHours() * 60 + pointTime.getMinutes();
                    return minutes >= timeRange.start && minutes < timeRange.end;
                });

                if (filteredPoints.length < 2) {
                    alert('No route data available for ' + routeType + ' period');
                    return;
                }

                // Update route header
                document.querySelector('.heading-brown').textContent = 
                    routeType === 'morning' ? 'Morning Route History' : 'Evening Route History';

                // Draw route on main map
                routeControl = drawRoutePath(filteredPoints, map, { color: routeColor });

                // Update mini map with selected route only
                miniRouteControl = drawRoutePath(filteredPoints, miniMap, { 
                    color: routeColor,
                    selectedOnly: true 
                });

                // Update route segments
                updateRouteSegments(routeType);
            }

            // Update route segments function
            function updateRouteSegments(routeType) {
                const timeRange = routeType === 'morning' ? 
                    { start: <?php echo $morning_start; ?>, end: <?php echo $morning_end; ?> } :
                    { start: <?php echo $evening_start; ?>, end: <?php echo $evening_end; ?> };

                fetch(`get_route_segments.php?date=<?php echo $selected_date; ?>&type=${routeType}&start=${timeRange.start}&end=${timeRange.end}`)
                    .then(response => response.json())
                    .then(data => {
                        const container = document.querySelector('.max-h-48.overflow-y-auto');
                        if (!container || !data.segments?.length) {
                            container.innerHTML = `<div class="text-center py-4 text-gray-500">No ${routeType} route data available</div>`;
                            return;
                        }

                        let totalDistance = 0;
                        let totalTime = 0;

                        const html = data.segments.map(segment => {
                            totalDistance += parseFloat(segment.segment_distance);
                            totalTime += (new Date(segment.time2) - new Date(segment.time1)) / 1000 / 60;
                            return `
                                <div class="bg-gray-50 rounded-lg p-2 text-xs">
                                    <div class="flex justify-between text-gray-600">
                                        <span>${new Date(segment.time1).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}</span>
                                        <span class="font-medium">${parseFloat(segment.segment_distance).toFixed(2)} km</span>
                                    </div>
                                    <div class="mt-1 text-gray-500">Speed: ${Math.round(segment.speed)} km/h</div>
                                </div>
                            `;
                        }).join('');

                        container.innerHTML = html;

                        // Update summary
                        document.querySelector('.mt-4.grid.grid-cols-2 .text-sm.font-medium').textContent = 
                            totalDistance.toFixed(2) + ' km';
                        document.querySelector('.mt-4.grid.grid-cols-2 div:last-child .text-sm.font-medium').textContent = 
                            Math.round(totalTime) + ' min';
                    })
                    .catch(error => console.error('Error:', error));
            }

            // Initialize mini map with its own route control
            var miniMap = L.map('mini-map').setView([7.8731, 80.7718], 8);
            var miniRouteControl = null;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(miniMap);

            <?php if (!empty($tracking_points)): ?>
                // Draw initial mini map route
                miniRouteControl = drawRoutePath(<?php echo json_encode($tracking_points); ?>, miniMap, {
                    color: '#ef4444',
                    weight: 2
                });
            <?php endif; ?>

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
                
                // Add current day route if available
                <?php if ($is_current_day && !empty($today_route)): ?>
                    var currentDayRoute = <?php echo json_encode($today_route); ?>;
                    drawRoutePath(currentDayRoute, map);
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
                    var morningData = <?php echo json_encode($morning_route); ?>;
                    document.getElementById('show-morning-route').addEventListener('click', function() {
                        showHistoricalRoute(morningData, 'morning');
                    });
                <?php endif; ?>
                
                <?php if (!empty($evening_route)): ?>
                    var eveningData = <?php echo json_encode($evening_route); ?>;
                    document.getElementById('show-evening-route').addEventListener('click', function() {
                        showHistoricalRoute(eveningData, 'evening');
                    });
                <?php endif; ?>

                // Show morning route by default if available
                <?php if (!empty($morning_route)): ?>
                    showHistoricalRoute(morningData, 'morning');
                <?php elseif (!empty($evening_route)): ?>
                    showHistoricalRoute(eveningData, 'evening');
                <?php endif; ?>
            <?php endif; ?>
            
            // Date selector change event
            document.getElementById('date-select').addEventListener('change', function() {
                if (this.value) {
                    window.location.href = '?child_id=<?php echo $child_id; ?>&date=' + this.value;
                }
            });
            
            <?php if (!$is_current_day && !empty($historical_route)): ?>
            var historicalData = <?php echo json_encode($historical_route); ?>;
            if (historicalData.length > 0) {
                // Get start and end points
                var endPoint = [
                    parseFloat(historicalData[0].latitude), 
                    parseFloat(historicalData[0].longitude)
                ];
                var startPoint = [
                    parseFloat(historicalData[historicalData.length - 1].latitude),
                    parseFloat(historicalData[historicalData.length - 1].longitude)
                ];

                // Create routing control for road path only (no markers)
                var routingControl = L.Routing.control({
                    waypoints: [
                        L.latLng(startPoint[0], startPoint[1]),
                        L.latLng(endPoint[0], endPoint[1])
                    ],
                    routeWhileDragging: false,
                    lineOptions: {
                        styles: [{color: '#FF5733', opacity: 0.8, weight: 5}]
                    },
                    createMarker: function() { return null; }, // Don't create any markers
                    addWaypoints: false,
                    draggableWaypoints: false,
                    fitSelectedRoutes: true,
                    showAlternatives: false
                }).addTo(map);

                // Hide routing instructions
                routingControl.hide();

                // Center map on route
                routingControl.on('routesfound', function(e) {
                    map.fitBounds(L.latLngBounds(startPoint, endPoint), {
                        padding: [50, 50]
                    });
                });
            }
            <?php endif; ?>

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
            
            // Function to update route segments
            function updateRouteSegments(routeType) {
                fetch('get_route_segments.php?date=' + '<?php echo $selected_date; ?>' + '&type=' + routeType)
                    .then(response => response.json())
                    .then(data => {
                        const segmentsContainer = document.querySelector('.max-h-48.overflow-y-auto');
                        if (segmentsContainer) {
                            segmentsContainer.innerHTML = data.segments.map(segment => `
                                <div class="bg-gray-50 rounded-lg p-2 text-xs">
                                    <div class="flex justify-between text-gray-600">
                                        <span>${formatTime(segment.time1)}</span>
                                        <span class="font-medium">${segment.segment_distance} km</span>
                                    </div>
                                    <div class="mt-1 text-gray-500">
                                        Speed: ${Math.round(segment.speed)} km/h
                                    </div>
                                </div>
                            `).join('');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        });
    </script>
</body>
</html>