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

// Get route times from database with date validation
$route_times = [];
if ($childDetails['bus_id']) {
    $stmt = $pdo->prepare("
        SELECT route_type, start_time, end_time, DATE(updated_at) as last_updated
        FROM route_times 
        WHERE bus_id = ?
        AND DATE(created_at) = CURDATE()
        AND DATE(updated_at) = CURDATE()
    ");
    $stmt->execute([$childDetails['bus_id']]);
    $route_times = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no current date records found, get default times from route_settings
    if (empty($route_times)) {
        $stmt = $pdo->prepare("
            SELECT 
                morning_start, morning_end, 
                evening_start, evening_end
            FROM route_settings 
            WHERE bus_id = ?
        ");
        $stmt->execute([$childDetails['bus_id']]);
        $default_settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($default_settings) {
            $morning_start = $default_settings['morning_start'];
            $morning_end = $default_settings['morning_end'];
            $evening_start = $default_settings['evening_start'];
            $evening_end = $default_settings['evening_end'];
        } else {
            // Fallback to system defaults if no settings found
            $morning_start = (5 * 60); // 5:00 AM
            $morning_end = (12 * 60); // 12:00 PM
            $evening_start = (12 * 60); // 12:00 PM
            $evening_end = (17 * 60); // 5:00 PM
        }
    } else {
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
    // Get current speed and latest tracking info for the selected date
    $stmt = $pdo->prepare("
        SELECT speed, latitude, longitude 
        FROM bus_tracking 
        WHERE bus_id = ? 
        AND DATE(timestamp) = ?
        ORDER BY timestamp DESC LIMIT 1
    ");
    $stmt->execute([$childDetails['bus_id'], $selected_date]);
    $current_tracking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_tracking) {
        $tracking_stats['current_speed'] = round($current_tracking['speed'] ?? 0);
        
        // Calculate total distance traveled for selected date
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
                WHERE bus_id = ? AND DATE(timestamp) = ?
                ORDER BY timestamp) AS t1
            JOIN
                (SELECT latitude, longitude, timestamp FROM bus_tracking
                WHERE bus_id = ? AND DATE(timestamp) = ?
                ORDER BY timestamp) AS t2
            WHERE
                t2.timestamp > t1.timestamp
                AND t2.timestamp = (
                    SELECT MIN(timestamp) FROM bus_tracking
                    WHERE bus_id = ? AND DATE(timestamp) = ?
                    AND timestamp > t1.timestamp
                )
        ");
        $stmt->execute([
            $childDetails['bus_id'], 
            $selected_date,
            $childDetails['bus_id'], 
            $selected_date,
            $childDetails['bus_id'], 
            $selected_date
        ]);
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
    <!-- <meta http-equiv="refresh" content="30"> -->
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

        /* Hide routing machine control panel */
        .display-none {
            display: none !important;
        }
        .leaflet-routing-container {
            display: none !important;
        }
        .hidden {
            display: none !important;
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
                                    <button id="show-morning-route" 
                                            class="text-sm px-3 py-1 rounded-lg bg-blue-100 text-blue-700 border border-blue-200 
                                                   hover:bg-blue-200 hover:shadow-md active:bg-blue-300 active:transform active:scale-95
                                                   transition-all duration-150 ease-in-out focus:outline-none focus:ring-2 
                                                   focus:ring-blue-400 focus:ring-opacity-50">
                                        Morning Route
                                    </button>
                                    <button id="show-evening-route" 
                                            class="text-sm px-3 py-1 rounded-lg bg-orange-100 text-orange-700 border border-orange-200 
                                                   hover:bg-orange-200 hover:shadow-md active:bg-orange-300 active:transform active:scale-95 
                                                   transition-all duration-150 ease-in-out focus:outline-none focus:ring-2 
                                                   focus:ring-orange-400 focus:ring-opacity-50">
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
                                            <circle cx="12" cy="12" r="10" stroke-width="2"/>
                                            <circle cx="12" cy="12" r="4" fill="currentColor"/>
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
                            // Get route points ordered by timestamp
                            $stmt = $pdo->prepare("
                                SELECT 
                                    bt1.latitude, bt1.longitude, bt1.timestamp, bt1.speed,
                                    bt2.latitude as next_lat, bt2.longitude as next_lon,
                                    bt2.timestamp as next_time,
                                    ROUND(
                                        (6371 * acos(
                                            cos(radians(bt1.latitude)) 
                                            * cos(radians(bt2.latitude))
                                            * cos(radians(bt2.longitude) - radians(bt1.longitude))
                                            + sin(radians(bt1.latitude))
                                            * sin(radians(bt2.latitude))
                                        )), 2
                                    ) as segment_distance
                                FROM bus_tracking bt1
                                LEFT JOIN bus_tracking bt2 ON bt2.tracking_id = (
                                    SELECT MIN(tracking_id) 
                                    FROM bus_tracking 
                                    WHERE tracking_id > bt1.tracking_id 
                                    AND bus_id = bt1.bus_id 
                                    AND DATE(timestamp) = DATE(bt1.timestamp)
                                )
                                WHERE bt1.bus_id = ? 
                                AND DATE(bt1.timestamp) = ?
                                ORDER BY bt1.timestamp ASC
                            ");
                            
                            $stmt->execute([$childDetails['bus_id'], $selected_date]);
                            $route_points = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            $total_distance = 0;
                            $total_time = 0;

                            if (!empty($route_points)):
                                // Get first and last points for mini map
                                $start_point = reset($route_points);
                                $end_point = end($route_points);
                            ?>
                            <!-- Add start/end points to mini map -->
                            <script>
                                // Add start marker
                                L.marker([<?php echo $start_point['latitude']; ?>, <?php echo $start_point['longitude']; ?>], {
                                    icon: L.divIcon({
                                        className: 'point-icon',
                                        html: '<div style="background-color: #10b981; color: white; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden;">S</div>',
                                        iconSize: [20, 20],
                                        iconAnchor: [10, 10]
                                    })
                                }).addTo(miniMap);

                                // Add end marker with bus icon
                                L.marker([<?php echo $end_point['latitude']; ?>, <?php echo $end_point['longitude']; ?>], {
                                    icon: busIcon
                                }).addTo(miniMap);

                                // Fit bounds to show all points
                                miniMap.fitBounds([
                                    [<?php echo $start_point['latitude']; ?>, <?php echo $start_point['longitude']; ?>],
                                    [<?php echo $end_point['latitude']; ?>, <?php echo $end_point['longitude']; ?>]
                                ], { padding: [20, 20] });
                            </script>

                            <div class="space-y-3 mt-4">
                                <div class="text-sm font-medium text-gray-700">Route Segments</div>
                                <div class="max-h-48 overflow-y-auto space-y-2">
                                    <?php 
                                    foreach ($route_points as $point): 
                                        if ($point['next_lat'] !== null):
                                            $segment_time = strtotime($point['next_time']) - strtotime($point['timestamp']);
                                            $total_time += $segment_time;
                                            $total_distance += $point['segment_distance'];
                                    ?>
                                        <div class="bg-gray-50 rounded-lg p-2 text-xs">
                                            <div class="flex justify-between text-gray-600">
                                                <span><?php echo date('h:i A', strtotime($point['timestamp'])); ?></span>
                                                <span class="font-medium"><?php echo number_format($point['segment_distance'], 2); ?> km</span>
                                            </div>
                                            <div class="mt-1 text-gray-500">
                                                Speed: <?php echo round($point['speed'] ?? 0); ?> km/h
                                                <span class="ml-2">Duration: <?php echo round($segment_time / 60); ?> min</span>
                                            </div>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
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
                                    <p>No route tracking data available for selected date</p>
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
            // Initialize maps
            var map = L.map('map').setView([7.8731, 80.7718], 8);
            var miniMap = L.map('mini-map').setView([7.8731, 80.7718], 8);

            // Add tile layers without markers
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(miniMap);

            // Custom icons definition
            var startIcon = L.divIcon({
                className: 'point-icon',
                html: '<div style="background-color: #10b981; color: white; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden;">S</div>',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            var busIcon = L.divIcon({
                className: 'bus-icon',
                html: '<div style="background-color: #FF8C00; color: white; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden;">B</div>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });

            const ROUTE_COLORS = {
                morning: '#5b21b6', // Dark purple for morning route
                evening: '#312e81'  // Dark indigo for evening route
            };

            function drawRoutePath(points, targetMap, options = {}) {
                // Clear existing layers
                targetMap.eachLayer((layer) => {
                    if (!(layer instanceof L.TileLayer)) {
                        targetMap.removeLayer(layer);
                    }
                });
                
                if (points && points.length > 0) {
                    const latlngs = points.map(point => [
                        parseFloat(point.latitude || point.lat1),
                        parseFloat(point.longitude || point.lon1)
                    ]);

                    // Draw route line using routing machine
                    const routeColor = options.color || 
                        (new Date(points[0].timestamp).getHours() >= 5 && 
                         new Date(points[0].timestamp).getHours() < 12 ? 
                         ROUTE_COLORS.morning : ROUTE_COLORS.evening);

                    // Create waypoints for routing
                    const waypoints = latlngs.map(point => L.latLng(point[0], point[1]));

                    // Use Routing Machine to draw route through roads
                    L.Routing.control({
                        waypoints: waypoints,
                        router: L.Routing.osrmv1({
                            serviceUrl: 'https://router.project-osrm.org/route/v1',
                            profile: 'driving'
                        }),
                        lineOptions: {
                            styles: [{
                                color: routeColor,
                                opacity: 0.8,
                                weight: 3
                            }]
                        },
                        addWaypoints: false,
                        draggableWaypoints: false,
                        fitSelectedRoutes: true,
                        show: false,
                        createMarker: function() { return null; },
                        // Add these lines to hide the instructions panel
                        showAlternatives: false,
                        containerClassName: 'display-none',
                        formatter: undefined,
                        itineraryClassName: 'hidden'
                    }).addTo(targetMap);

                    // Add start marker
                    L.marker(latlngs[0], {
                        icon: startIcon
                    }).addTo(targetMap);

                    // Add end marker
                    L.marker(latlngs[latlngs.length - 1], {
                        icon: busIcon
                    }).addTo(targetMap);

                    // Fit map to show all points
                    targetMap.fitBounds(latlngs);
                }
            }

            // Date selector event handler
            document.getElementById('date-select').addEventListener('change', function() {
                const selectedDate = this.value;
                const today = new Date().toISOString().split('T')[0];
                
                // Add child_id to URL
                window.location.href = `bus_location_tracker.php?child_id=<?php echo $child_id; ?>&date=${selectedDate}`;
            });

            // Initialize routes based on selected date
            <?php if ($is_current_day && !empty($today_route)): ?>
                var todayData = <?php echo json_encode($today_route); ?>;
                drawRoutePath(todayData, map);
                drawRoutePath(todayData, miniMap);
            <?php else: ?>
                <?php if (!empty($morning_route)): ?>
                    var morningData = <?php echo json_encode($morning_route); ?>;
                    drawRoutePath(morningData, map, { color: ROUTE_COLORS.morning });
                    drawRoutePath(morningData, miniMap, { color: ROUTE_COLORS.morning });
                <?php endif; ?>

                <?php if (!empty($evening_route)): ?>
                    var eveningData = <?php echo json_encode($evening_route); ?>;
                    document.getElementById('show-morning-route')?.addEventListener('click', function() {
                        drawRoutePath(morningData, map, { color: ROUTE_COLORS.morning });
                        drawRoutePath(morningData, miniMap, { color: ROUTE_COLORS.morning });
                    });

                    document.getElementById('show-evening-route')?.addEventListener('click', function() {
                        drawRoutePath(eveningData, map, { color: ROUTE_COLORS.evening });
                        drawRoutePath(eveningData, miniMap, { color: ROUTE_COLORS.evening });
                    });
                <?php endif; ?>
            <?php endif; ?>

            // Remove auto-polling and refresh functionality for historical data
            <?php if ($is_current_day): ?>
                // ...existing polling code...
            <?php endif; ?>
        });
    </script>
</body>
</html>