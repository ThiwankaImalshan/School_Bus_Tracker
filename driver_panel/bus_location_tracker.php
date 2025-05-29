<?php
// bus_location_tracker.php - Live bus location tracking for drivers and administrators
session_start();
require_once 'db_connection.php';

// Set timezone for Sri Lanka
date_default_timezone_set('Asia/Colombo');

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header('Location: log_in.php');
    exit;
}

$driver_id = $_SESSION['driver_id'];

// Get driver information and assigned bus
$stmt = $pdo->prepare("SELECT d.*, b.bus_number, b.capacity 
                      FROM driver d 
                      LEFT JOIN bus b ON d.bus_id = b.bus_id 
                      WHERE d.driver_id = ?");
$stmt->execute([$driver_id]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

// If driver has no assigned bus, show error
if (!$driver['bus_id']) {
    $error = "You don't have an assigned bus. Please contact the administrator.";
}

// Get current bus location from database
$stmt = $pdo->prepare("SELECT * FROM bus_tracking WHERE bus_id = ? ORDER BY timestamp DESC LIMIT 1");
$stmt->execute([$driver['bus_id']]);
$location = $stmt->fetch(PDO::FETCH_ASSOC);

// Get route time settings from database for current date
$stmt = $pdo->prepare("
    SELECT 
        m.start_time as morning_start,
        m.end_time as morning_end,
        e.start_time as evening_start,
        e.end_time as evening_end
    FROM route_times m
    LEFT JOIN route_times e ON e.bus_id = m.bus_id 
        AND e.route_type = 'evening'
        AND DATE(e.created_at) = CURDATE()
    WHERE m.bus_id = ?
        AND m.route_type = 'morning'
        AND DATE(m.created_at) = CURDATE()
    LIMIT 1
");

$stmt->execute([$driver['bus_id']]);
$route_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Set default times if no custom times are set
if (!$route_settings || !$route_settings['morning_start']) {
    $route_settings = [
        'morning_start' => '05:00:00',
        'morning_end' => '12:00:00',
        'evening_start' => '12:00:00',
        'evening_end' => '17:00:00'
    ];
}

// Convert times to minutes for comparison
function timeToMinutes($time) {
    list($hours, $minutes) = explode(':', $time);
    return ($hours * 60) + $minutes;
}

$morning_start = timeToMinutes($route_settings['morning_start']);
$morning_end = timeToMinutes($route_settings['morning_end']);
$evening_start = timeToMinutes($route_settings['evening_start']);
$evening_end = timeToMinutes($route_settings['evening_end']);

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

// Get today's date
$today = date('Y-m-d');

// Get schools with arrival status - Update query to use driver's bus_id
$stmt = $pdo->prepare("SELECT s.school_id, s.name, s.location_map, s.arrival_time, s.departure_time,
                      CASE WHEN rs.status = 'arrived' THEN 1 ELSE 0 END as is_arrived
                      FROM school s 
                      INNER JOIN bus_school bs ON s.school_id = bs.school_id AND bs.bus_id = ?
                      LEFT JOIN route_school rs ON s.school_id = rs.school_id 
                          AND rs.date = CURDATE() AND rs.bus_id = ?
                      ORDER BY s.arrival_time");
$stmt->execute([$driver['bus_id'], $driver['bus_id']]);
$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
$stmt->execute([$driver['bus_id'], $current_time_sql, $current_route, $current_time_sql, $current_route]);
$route_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student pickup locations
$stmt = $pdo->prepare("SELECT 
    c.child_id, 
    c.first_name, 
    c.last_name,
    CASE 
        WHEN (a.notes IS NOT NULL AND pl.location IS NOT NULL AND a.notes = pl.name) 
        THEN pl.location 
        ELSE c.pickup_location 
    END as pickup_location
    FROM child c 
    LEFT JOIN attendance a ON c.child_id = a.child_id AND DATE(a.attendance_date) = CURDATE()
    LEFT JOIN pickup_locations pl ON c.child_id = pl.child_id AND a.notes = pl.name
    WHERE c.bus_id = ?");
$stmt->execute([$driver['bus_id']]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get route stops for the bus route
$stmt = $pdo->prepare("SELECT rs.*, 
                     CASE WHEN rs.estimated_time IS NOT NULL THEN 1 ELSE 0 END as is_completed
                     FROM route_stop rs
                     WHERE rs.route_id = ?
                     ORDER BY rs.sequence_number");
$stmt->execute([$route_info['route_id'] ?? 0]);
$route_points = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize arrays before the route checks
$destinations = [];
$waypoints = [];

// Add attendance status check for morning route
if ($current_route == "morning") {
    // Get today's pickup status and attendance status
    $stmt = $pdo->prepare("SELECT child_id, status FROM attendance 
                          WHERE attendance_date = CURDATE()");
    $stmt->execute();
    $pickup_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // For morning route - schools are destinations
    foreach ($schools as $school) {
        if (!empty($school['location_map'])) {
            $coordinates = explode(',', $school['location_map']);
            $destinations[] = array(
                'name' => $school['name'],
                'location' => $coordinates,
                'arrival_time' => $school['arrival_time'],
                'type' => 'school',
                'distance' => 0,
                'is_arrived' => $school['is_arrived'],
                'school_id' => $school['school_id']  // Add this line
            );
        }
    }

    // Student pickup locations are waypoints - only add non-picked and non-absent students
    foreach ($students as $student) {
        if (!empty($student['pickup_location'])) {
            // Skip if student is marked as absent
            if (isset($pickup_status[$student['child_id']]) && 
                $pickup_status[$student['child_id']] === 'absent') {
                continue;
            }
            // Only include if student hasn't been picked up
            if (!isset($pickup_status[$student['child_id']]) || 
                $pickup_status[$student['child_id']] !== 'picked') {
                $coordinates = explode(',', $student['pickup_location']);
                $waypoints[] = array(
                    'name' => $student['first_name'] . ' ' . $student['last_name'],
                    'location' => $coordinates,
                    'type' => 'pickup',
                    'child_id' => $student['child_id'],
                    'is_picked' => false,
                    'distance' => 0
                );
            }
        }
    }

    // Sort waypoints by status and distance
    if (!empty($waypoints)) {
        usort($waypoints, function($a, $b) {
            if ($a['is_picked'] === $b['is_picked']) {
                return 0;
            }
            return $a['is_picked'] ? 1 : -1;
        });
    }
}

// Add attendance status check for evening route
if ($current_route == "evening") {
    // Get today's dropoff status and attendance status
    $stmt = $pdo->prepare("SELECT 
        c.child_id, 
        c.first_name, 
        c.last_name, 
        CASE 
            WHEN (a.notes IS NOT NULL AND pl.location IS NOT NULL AND a.notes = pl.name) 
            THEN pl.location 
            ELSE c.pickup_location 
        END as dropoff_location,
        a.status,
        a.notes
        FROM child c 
        LEFT JOIN attendance a ON c.child_id = a.child_id AND DATE(a.attendance_date) = CURDATE()
        LEFT JOIN pickup_locations pl ON c.child_id = pl.child_id AND a.notes = pl.name
        WHERE c.bus_id = ?");
    $stmt->execute([$driver['bus_id']]);
    $evening_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter and process students for evening route
    $destinations = [];
    foreach ($evening_students as $student) {
        if ($student['status'] !== 'absent' && 
            $student['notes'] !== 'Not returning' && 
            !empty($student['dropoff_location'])) {
                
            $destinations[] = [
                'name' => $student['first_name'] . ' ' . $student['last_name'],
                'location' => explode(',', $student['dropoff_location']),
                'type' => 'dropoff',
                'child_id' => $student['child_id'],
                'is_dropped' => ($student['status'] === 'drop'),
                'distance' => 0
            ];
        }
    }
}

// Function to update location in database
if (isset($_POST['update_location'])) {
    $latitude = filter_input(INPUT_POST, 'latitude', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $longitude = filter_input(INPUT_POST, 'longitude', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $speed = filter_input(INPUT_POST, 'speed', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    // Only update location if there's an active route
    if ($latitude && $longitude && ($current_route == 'morning' || $current_route == 'evening')) {
        $stmt = $pdo->prepare("INSERT INTO bus_tracking (bus_id, latitude, longitude, timestamp, route_id, status, speed) 
                              VALUES (?, ?, ?, NOW(), ?, 'ongoing', ?)");
        
        // After inserting location, check for school geofences
        if ($stmt->execute([$driver['bus_id'], $latitude, $longitude, $route_info['route_id'] ?? null, $speed])) {
            // Check school geofences
            foreach ($schools as $school) {
                if (!empty($school['location_map'])) {
                    $school_coords = explode(',', $school['location_map']);
                    $distance = calculateDistance(
                        $latitude, 
                        $longitude, 
                        floatval($school_coords[0]), 
                        floatval($school_coords[1])
                    );
                    
                    // If within 500m of school
                    if ($distance <= 0.5) { // 0.5 km = 500m
                        if ($current_route == 'morning') {
                            // Check if already recorded for today
                            $check_stmt = $pdo->prepare("SELECT * FROM bus_school 
                                                      WHERE school_id = ? AND bus_id = ? 
                                                      AND date = CURDATE()");
                            $check_stmt->execute([$school['school_id'], $driver['bus_id']]);
                            
                            if (!$check_stmt->fetch()) {
                                // Record arrival
                                $insert_stmt = $pdo->prepare("INSERT INTO bus_school 
                                                           (school_id, bus_id, date, arrival_time, status) 
                                                           VALUES (?, ?, CURDATE(), NOW(), 'arrived')");
                                $insert_stmt->execute([$school['school_id'], $driver['bus_id']]);
                            }
                        } elseif ($current_route == 'evening') {
                            // Update departure for existing record
                            $update_stmt = $pdo->prepare("UPDATE bus_school 
                                                       SET departure_time = NOW(), status = 'departed' 
                                                       WHERE school_id = ? AND bus_id = ? 
                                                       AND date = CURDATE() 
                                                       AND status = 'arrived'");
                            $update_stmt->execute([$school['school_id'], $driver['bus_id']]);
                        }
                    }
                }
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => ($current_route == 'none') ? 'No active route' : 'Invalid coordinates'
    ]);
    exit;
}

// Add helper function for distance calculation
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $radius = 6371; // Earth's radius in kilometers
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $radius * $c; // Returns distance in kilometers
}

// Convert destinations and waypoints to JSON for JavaScript
$destinationsJson = json_encode($destinations);
$waypointsJson = json_encode($waypoints);

// Check last attendance update time
$stmt = $pdo->prepare("SELECT MAX(last_updated) as last_attendance_update FROM attendance WHERE DATE(attendance_date) = CURDATE()");
$stmt->execute();
$lastAttendanceUpdate = $stmt->fetch(PDO::FETCH_ASSOC)['last_attendance_update'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus Location Tracker</title>
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
            background: #ea580c;
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 1.5s infinite;
        }
        
        .bus-icon-inner {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        /* Route path animation */
        .route-path {
            stroke-dasharray: 8000;
            stroke-dashoffset: 8000;
            animation: drawPath 40s ease forwards;
            transition: opacity 0.5s ease;
        }

        @keyframes drawPath {
            to {
                stroke-dashoffset: 0;
            }
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
                <span class="text-yellow-900 font-medium"><?php echo htmlspecialchars($driver['full_name'] ?? 'Driver'); ?></span>
                <a href="dashboard.php" class="bg-yellow-900 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition duration-300 shadow-md hover:shadow-lg">Dashboard</a>
            </div>
        </div>
    </nav>
    <main class="container mx-auto px-4 py-8">
        <?php if (isset($error)): ?>
            <div class="glass-container text-red-600 p-4 mb-6" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        <section id="tracker-section" class="glass-container p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold heading-brown">Live Route Tracking</h2>
                    <p class="text-gray-600">
                        Bus <?php echo htmlspecialchars($driver['bus_number'] ?? 'N/A'); ?> | 
                        <span id="route-display" class="font-medium sm:text-xs xs:text-[10px]">
                            <?php echo htmlspecialchars($route_text); ?>
                        </span>
                    </p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <span id="last-updated" class="text-xs text-gray-500">Last updated: <?php echo isset($location['timestamp']) ? date('h:i A', strtotime($location['timestamp'])) : 'Never'; ?></span>
                    <button id="refresh-btn" class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <!-- Route Information Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <h4 class="text-lg font-medium text-gray-800" id="current-time"><?php echo date('h:i A'); ?></h4>
                    <p class="text-xs text-gray-600">Current Time</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <h4 class="text-lg font-medium text-gray-800" id="estimated-time">--</h4>
                    <p class="text-xs text-gray-600">Est. Drive Time</p>
                </div>
                <div class="bg-purple-50 rounded-lg p-4 text-center">
                    <h4 class="text-lg font-medium text-gray-800" id="destinations-count">
                        <?php 
                            $stops_count = ($current_route == 'morning') ? 
                                count(array_filter($waypoints, function($w) { return !$w['is_picked']; })) + 
                                count(array_filter($destinations, function($d) { return !$d['is_arrived']; })) : 
                                count(array_filter($destinations, function($d) { return !$d['is_dropped']; }));
                            echo $stops_count . ' Stops';
                        ?>
                    </h4>
                    <p class="text-xs text-gray-600">Destinations</p>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4 text-center">
                    <h4 class="text-lg font-medium text-gray-800" id="completion-stats">
                        <?php 
                            if ($current_route == 'morning') {
                                $completed = count(array_filter($waypoints, fn($w) => $w['is_picked']));
                                $total = count($waypoints);
                                echo "$total";
                            } else {
                                $completed = count(array_filter($destinations, fn($d) => $d['is_dropped']));
                                $total = count($destinations);
                                echo "$completed";
                            }
                        ?>
                    </h4>
                    <p class="text-xs text-gray-600"><?php echo $current_route == 'morning' ? 'Pickups' : 'Drop-offs'; ?></p>
                </div>
            </div>
            <!-- Map Container -->
            <div class="bg-white rounded-2xl shadow-enhanced border border-yellow-100 overflow-hidden mb-6 relative">
                <div id="map"></div>
                <!-- Add live location status -->
                <div class="absolute top-4 left-4 bg-white/90 backdrop-filter backdrop-blur-sm rounded-lg shadow-lg p-3 z-[9999] border border-gray-200" style="visibility: hidden">
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <div class="flex-shrink-0 w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                            <span class="text-xs font-medium text-gray-700">Live Location</span>
                        </div>
                        <div class="text-[10px] text-gray-500" id="live-coordinates">Waiting for location...</div>
                        <div class="text-[10px] text-yellow-600" id="last-update-time"></div>
                    </div>
                </div>

                <!-- Existing speed display -->
                <div id="speed-display" class="fixed lg:bottom-4 lg:right-4 bottom-32 right-4 bg-white/90 backdrop-filter backdrop-blur-sm rounded-lg shadow-lg p-2 z-[9999] border border-gray-200 hover:bg-white transition-colors duration-200">
                    <div class="flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4.535l-3.928 2.62a1 1 0 101.11 1.66l4.5-3a1 1 0 00.318-1.334V6z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex items-baseline gap-1">
                            <span id="speed-value" class="text-lg font-bold text-yellow-500">0</span>
                            <span class="text-xs text-yellow-500">km/h</span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Next Stops Preview -->
            <div class="mt-4 sm:mt-6 md:mt-8 lg:mt-20 mb-4 sm:mb-6">
                <h4 class="text-md font-semibold text-gray-800 mb-2 sm:mb-3">Next Stops</h4>
                <div class="space-y-2 sm:space-y-3" id="stops-container">
                    <?php if ($current_route === 'morning'): ?>
                        <?php
                        $upcoming_stops = array_merge(
                            array_map(function($wp) {
                                return [
                                    'id' => 'pickup_' . $wp['child_id'],
                                    'name' => $wp['name'],
                                    'location' => $wp['location'],
                                    'type' => 'pickup',
                                    'is_completed' => $wp['is_picked']
                                ];
                            }, array_filter($waypoints, function($w) { return !$w['is_picked']; })),
                            array_map(function($dest) {
                                return [
                                    'id' => isset($dest['school_id']) ? 'school_' . $dest['school_id'] : 'school_0',
                                    'name' => $dest['name'],
                                    'location' => $dest['location'],
                                    'type' => 'school',
                                    'is_completed' => $dest['is_arrived'],
                                    'arrival_time' => $dest['arrival_time']
                                ];
                            }, array_filter($destinations, function($d) { return $d['type'] === 'school' && !$d['is_arrived']; }))
                        );
                        ?>
                    <?php else: ?>
                        <?php
                        $upcoming_stops = array_map(function($dest) {
                            return [
                                'id' => 'dropoff_' . $dest['child_id'],
                                'name' => $dest['name'],
                                'location' => $dest['location'],
                                'type' => 'dropoff',
                                'is_completed' => $dest['is_dropped']
                            ];
                        }, array_filter($destinations, function($d) { return !$d['is_dropped']; }));
                        ?>
                    <?php endif; ?>

                    <?php if (empty($upcoming_stops)): ?>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-gray-500">No upcoming stops</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_stops as $index => $stop): ?>
                            <div class="bg-yellow-50 rounded-lg p-3 flex justify-between items-center" 
                                 id="stop-<?php echo htmlspecialchars($stop['id']); ?>"
                                 data-location="<?php echo htmlspecialchars(implode(',', $stop['location'])); ?>"
                                 data-type="<?php echo htmlspecialchars($stop['type']); ?>"
                                 data-index="<?php echo $index + 1; ?>">
                                <div class="flex items-center">
                                    <div class="bg-yellow-100 p-2 rounded-lg mr-3 flex-shrink-0">
                                        <span class="font-bold text-yellow-600 text-sm sm:text-base"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs sm:text-sm font-medium">
                                            <?php echo $stop['type'] === 'school' ? 'School: ' : 
                                                  ($current_route === 'morning' ? 'Pickup: ' : 'Drop-off: '); ?>
                                            <br class="block sm:hidden" />
                                            <span class="text-yellow-600 block sm:inline text-xs sm:text-sm">
                                                <?php echo htmlspecialchars($stop['name']); ?>
                                            </span>
                                        </p>
                                        <?php if (isset($stop['arrival_time'])): ?>
                                            <p class="text-xs text-gray-500">
                                                Target arrival: <?php echo htmlspecialchars($stop['arrival_time']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center justify-end space-x-4">
                                    <div class="text-right" id="route-details-<?php echo htmlspecialchars($stop['id']); ?>">
                                        <p class="text-sm font-medium">--</p>
                                        <p class="text-xs text-gray-500">--</p>
                                        <p class="text-xs text-yellow-600">--</p>
                                    </div>
                                    <button class="bg-green-500 hover:bg-green-600 text-white p-1.5 sm:p-2 rounded-lg mark-completed-btn" 
                                            data-stop-id="<?php echo htmlspecialchars($stop['id']); ?>"
                                            <?php echo $stop['is_completed'] ? 'disabled' : ''; ?>>
                                        <?php if ($stop['is_completed']): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full sm:w-auto">
                    <div class="flex items-center">
                        <div class="relative inline-block w-10 mr-2 align-middle select-none">
                            <input type="checkbox" name="toggle" id="auto-refresh" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked />
                            <label for="auto-refresh" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                        </div>
                        <label for="auto-refresh" class="text-xs text-gray-700">Auto-refresh</label>
                    </div>
                    <select id="refresh-interval" class="text-xs border border-gray-300 rounded-lg px-2 py-1 w-full sm:w-auto">
                        <option value="15000">Update every 15s</option>
                        <option value="30000" selected>Update every 30s</option>
                        <option value="60000">Update every 1m</option>
                        <option value="300000">Update every 5m</option>
                    </select>
                </div>
                <!-- <button id="navigate-btn" class="bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 text-sm rounded-lg font-medium transition-colors w-full sm:w-auto">
                    Navigate in Google Maps
                </button> -->
            </div>
        </section>
    </main>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    const map = L.map('map', {
        center: [6.9271, 79.8612],
        zoom: 12,
        minZoom: 10,
        maxZoom: 18
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Initialize variables
    const destinations = Array.isArray(<?php echo $destinationsJson; ?>) ? <?php echo $destinationsJson; ?> : [];
    const waypoints = Array.isArray(<?php echo $waypointsJson; ?>) ? <?php echo $waypointsJson; ?> : [];
    let busMarker = null;
    let routingControl = null;
    let routePath = null;
    let currentRoute = '<?php echo $current_route; ?>';
    let isTracking = true;
    let lastPos = null; // Add this line for rotation tracking

    // Create bus icon with top view image
    const busIcon = L.divIcon({
        className: 'bus-marker',
        html: `
            <img src="../img/bus-top.png" 
                 style="width: 25px; height: auto; transform-origin: center; transition: transform 0.3s ease;"
                 class="bus-image"
            />
        `,
        iconSize: [10, 10],
        iconAnchor: [5, 5]
    });

    // Add markers for stops with improved styling
    function addStopMarkers() {
        let studentMarkers = new Map(); // Store markers for later removal

        // Add school markers
        if (currentRoute === 'morning') {
            destinations.forEach(dest => {
                if (dest.type === 'school') {
                    // Add school marker
                    L.marker(dest.location, {
                        icon: L.divIcon({
                            className: 'flex items-center justify-center bg-blue-600 rounded-full border-2 border-white',
                            iconSize: [35, 35],
                            iconAnchor: [17, 17],
                            html: `<div class="w-full h-full flex items-center justify-center">
                                <span class="text-white font-bold text-base leading-none">S</span>
                            </div>`
                        })
                    }).addTo(map).bindPopup(`
                        <div class="p-2">
                            <h3 class="font-bold">${dest.name}</h3>
                            <p class="text-sm">Target arrival: ${dest.arrival_time || 'N/A'}</p>
                            <p class="text-sm ${dest.is_arrived ? 'text-green-600' : 'text-red-600'}">
                                ${dest.is_arrived ? 'Arrived' : 'Not arrived'}
                            </p>
                        </div>
                    `);

                    // Add school geofence circle
                    L.circle(dest.location, {
                        color: '#2563EB',
                        fillColor: '#93C5FD',
                        fillOpacity: 0.2,
                        radius: 500,
                        weight: 2
                    }).addTo(map);
                }
            });
        }

        // Handle student markers based on route type
        if (currentRoute === 'morning') {
            // Morning route pickup markers
            waypoints.forEach((point, index) => {
                L.marker(point.location, {
                    icon: L.divIcon({
                        className: 'flex items-center justify-center bg-red-500 rounded-full border-2 border-white',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15],
                        html: `<div class="w-full h-full flex items-center justify-center">
                            <span class="text-white font-bold text-sm leading-none">${index + 1}</span>
                        </div>`
                    })
                }).addTo(map).bindPopup(`
                    <div class="p-2">
                        <h3 class="font-bold">${point.name}</h3>
                        <p class="text-sm">Pickup Point #${index + 1}</p>
                        <p class="text-xs text-gray-600">Student Pickup Location</p>
                    </div>
                `);

                // Add small radius around pickup points
                L.circle(point.location, {
                    color: '#EF4444',
                    fillColor: '#FEE2E2',
                    fillOpacity: 0.2,
                    radius: 100,
                    weight: 1
                }).addTo(map);
            });
        } else if (currentRoute === 'evening') {
            // Evening route dropoff markers
            destinations.forEach((student, index) => {
                if (!student.is_dropped) {
                    const marker = L.marker(student.location, {
                        icon: L.divIcon({
                            className: 'flex items-center justify-center bg-red-500 rounded-full border-2 border-white',
                            iconSize: [30, 30],
                            iconAnchor: [15, 15],
                            html: `<div class="w-full h-full flex items-center justify-center">
                                <span class="text-white font-bold text-sm leading-none">${index + 1}</span>
                            </div>`
                        })
                    }).addTo(map);

                    // Store marker reference
                    studentMarkers.set(`student_${student.child_id}`, marker);

                    marker.bindPopup(`
                        <div class="p-2">
                            <h3 class="font-bold">${student.name}</h3>
                            <p class="text-sm">Drop-off Point #${index + 1}</p>
                            <p class="text-xs text-gray-600">Student Drop-off Location</p>
                        </div>
                    `);

                    // Add circle around dropoff point
                    L.circle(student.location, {
                        color: '#EF4444',
                        fillColor: '#FEE2E2',
                        fillOpacity: 0.2,
                        radius: 100,
                        weight: 1
                    }).addTo(map);
                }
            });
        }

        // Handle marker removal when student is dropped
        document.querySelectorAll('.mark-completed-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const stopId = this.dataset.stopId;
                if (currentRoute === 'evening' && stopId.startsWith('dropoff_')) {
                    const marker = studentMarkers.get(`student_${stopId.split('_')[1]}`);
                    if (marker) {
                        map.removeLayer(marker);
                        studentMarkers.delete(`student_${stopId.split('_')[1]}`);
                    }
                }
            });
        });
    }

    // Real-time location polling
    async function updateBusLocation() {
        if (!isTracking) return;
        
        try {
            const position = await getCurrentPosition();
            const currentPos = [position.coords.latitude, position.coords.longitude];
            
            // Update live location display
            document.getElementById('live-coordinates').textContent = 
                `${currentPos[0].toFixed(6)}°N, ${currentPos[1].toFixed(6)}°E`;
            document.getElementById('last-update-time').textContent = 
                `Updated: ${new Date().toLocaleTimeString()}`;

            // Update bus marker position
            if (!busMarker) {
                busMarker = L.marker(currentPos, { icon: busIcon })
                    .bindPopup('Current Location')
                    .addTo(map);
                map.setView(currentPos, 15);
            } else {
                busMarker.setLatLng(currentPos);
                // Smoothly pan map to new position
                map.panTo(currentPos, {
                    animate: true,
                    duration: 1.5,
                    easeLinearity: 0.25
                });
            }
            
            // Calculate and update rotation
            if (lastPos) {
                const dx = currentPos[1] - lastPos[1];
                const dy = currentPos[0] - lastPos[0];
                if (dx !== 0 || dy !== 0) {
                    const angle = Math.atan2(dx, dy) * (180 / Math.PI);
                    const busImage = document.querySelector('.bus-marker .bus-image');
                    if (busImage) {
                        busImage.style.transform = `rotate(${angle}deg)`;
                    }
                }
            }
            
            lastPos = currentPos;

            // Update speed display
            const speed = position.coords.speed || 0;
            document.getElementById('speed-value').textContent = Math.round(speed * 3.6);

            // Save location to server
            await saveLocation(currentPos[0], currentPos[1], speed * 3.6);

            // Update route and estimates
            await calculateRoute(currentPos);

        } catch (error) {
            console.error('Location update error:', error);
        }
    }

    // Update route calculation function
    async function calculateRoute(currentPos) {
        try {
            // Fade out existing path smoothly
            if (routePath) {
                routePath.setStyle({ opacity: 0 });
                await new Promise(resolve => setTimeout(resolve, 500));
                map.removeLayer(routePath);
            }

            const stops = currentRoute === 'morning' ? 
                waypoints.filter(w => !w.is_picked) : 
                destinations.filter(d => !d.is_dropped);

            if (currentRoute === 'morning') {
                const schools = destinations.filter(d => 
                    d.type === 'school' && !d.is_arrived
                );
                stops.push(...schools);
            }

            if (stops.length === 0) return;

            let totalDistance = 0;
            let totalTime = 0;
            let allPoints = [currentPos];
            let currentPoint = currentPos;

            // Calculate sequential routes
            for (const stop of stops) {
                try {
                    const response = await fetch(
                        `https://router.project-osrm.org/route/v1/driving/` +
                        `${currentPoint[1]},${currentPoint[0]};${stop.location[1]},${stop.location[0]}` +
                        `?overview=full&geometries=geojson`
                    );
                    
                    if (!response.ok) continue;
                    
                    const data = await response.json();
                    if (data.code === 'Ok') {
                        const route = data.routes[0];
                        const points = route.geometry.coordinates.map(coord => [coord[1], coord[0]]);
                        allPoints = [...allPoints, ...points.slice(1)];

                        // Update stop details
                        const stopId = stop.type === 'school' ? 
                            `school_${stop.school_id}` : 
                            (currentRoute === 'morning' ? `pickup_${stop.child_id}` : `dropoff_${stop.child_id}`);

                        const duration = Math.round(route.duration / 60);
                        const distance = (route.distance / 1000).toFixed(1);
                        const eta = new Date(Date.now() + (totalTime + route.duration) * 1000);

                        const detailsElement = document.getElementById(`route-details-${stopId}`);
                        if (detailsElement) {
                            detailsElement.innerHTML = `
                                <p class="text-sm font-medium">${duration} min</p>
                                <p class="text-xs text-gray-500">${distance} km</p>
                                <p class="text-xs text-yellow-600">${eta.toLocaleTimeString()}</p>
                            `;
                        }

                        totalDistance += route.distance;
                        totalTime += route.duration;
                        currentPoint = stop.location;
                    }
                } catch (error) {
                    console.error('Route segment calculation error:', error);
                }
            }

            // Create new animated route path
            routePath = L.polyline(allPoints, {
                className: 'route-path',
                color: '#4C1D95',
                weight: 4,
                opacity: 0,
                lineCap: 'round',
                lineJoin: 'round'
            }).addTo(map);

            // Ensure opacity animation works
            setTimeout(() => {
                routePath.setStyle({ opacity: 0.8 });
            }, 100);

            // Update total time estimate
            document.getElementById('estimated-time').textContent = 
                `${Math.round(totalTime / 60)} min`;

            // Smooth map bounds adjustment
            const bounds = L.latLngBounds(allPoints);
            map.flyToBounds(bounds, {
                padding: [50, 50],
                duration: 0.5
            });

        } catch (error) {
            console.error('Route calculation error:', error);
        }
    }

    // Attendance change checker
    function checkAttendanceChanges() {
        fetch('check_attendance_changes.php')
            .then(response => response.json())
            .then(async data => {
                if (data.hasChanges) {
                    // Save map state
                    const mapState = {
                        center: map.getCenter(),
                        zoom: map.getZoom(),
                        bounds: map.getBounds(),
                        scroll: {
                            x: window.scrollX,
                            y: window.scrollY
                        }
                    };
                    sessionStorage.setItem('mapState', JSON.stringify(mapState));

                    // Create and show transition overlay
                    const overlay = document.createElement('div');
                    overlay.className = 'page-transition';
                    overlay.innerHTML = `
                        <div class="loader-content">
                            <div class="relative flex items-center gap-4">
                                <div class="flex items-center justify-center">
                                    <div class="w-10 h-10">
                                        <svg class="animate-spin" viewBox="0 0 50 50">
                                            <circle 
                                                cx="25" cy="25" r="20" 
                                                fill="none" 
                                                stroke="currentColor"
                                                stroke-width="4"
                                                stroke-linecap="round"
                                                class="text-yellow-100"
                                            />
                                            <circle 
                                                cx="25" cy="25" r="20" 
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="4"
                                                stroke-linecap="round"
                                                stroke-dasharray="80"
                                                stroke-dashoffset="60"
                                                class="text-yellow-500"
                                            />
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex flex-col">
                                    <p class="text-yellow-800 font-medium text-sm tracking-wide">
                                        Updating route information
                                    </p>
                                    <div class="flex items-center gap-1 mt-1.5">
                                        <span class="inline-block w-16 h-0.5 rounded-full bg-gradient-to-r from-yellow-500 to-yellow-500 animate-shimmer"></span>
                                        <span class="inline-block w-12 h-0.5 rounded-full bg-gradient-to-r from-yellow-400 to-yellow-400 animate-shimmer [animation-delay:150ms]"></span>
                                        <span class="inline-block w-8 h-0.5 rounded-full bg-gradient-to-r from-yellow-300 to-yellow-300 animate-shimmer [animation-delay:300ms]"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(overlay);

                    // Fade in overlay
                    requestAnimationFrame(() => {
                        overlay.style.opacity = '1';
                        overlay.querySelector('.loader-content').style.transform = 'translateY(0)';
                    });

                    // Fade out map content
                    const mapContainer = document.getElementById('map');
                    mapContainer.style.transition = 'opacity 0.3s ease';
                    mapContainer.style.opacity = '0.5';

                    // Reload after animations
                    await new Promise(resolve => setTimeout(resolve, 1700));
                    window.location.reload();
                }
            })
            .catch(error => console.error('Error checking attendance:', error));
    }

    // Add styles for loader
    const loaderStyles = document.createElement('style');
    loaderStyles.textContent = `
        .page-transition {
            position: fixed;
            inset: 0;
            background: rgba(251, 191, 36, 0.05);
            backdrop-filter: blur(4px);
            z-index: 9999;
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .loader-content {
            background: rgba(255, 255, 255, 0.95);
            padding: 1.5rem 2rem;
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(234, 88, 12, 0.1);
            transform: translateY(20px);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
    `;
    document.head.appendChild(loaderStyles);

    // Add this to window load event
    window.addEventListener('load', () => {
        const savedState = sessionStorage.getItem('mapState');
        if (savedState) {
            const state = JSON.parse(savedState);
            
            // Restore scroll position
            window.scrollTo({
                left: state.scroll.x,
                top: state.scroll.y,
                behavior: 'smooth'
            });

            // Restore map state with animation
            if (map && state.center) {
                map.setView(
                    [state.center.lat, state.center.lng],
                    state.zoom,
                    {
                        animate: true,
                        duration: 1,
                        easeLinearity: 0.25
                    }
                );
            }

            sessionStorage.removeItem('mapState');
        }
    });

    // Helper functions
    async function getCurrentPosition() {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: 5000
            });
        });
    }

    async function saveLocation(lat, lng, speed) {
        return fetch('bus_location_tracker.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                update_location: true,
                latitude: lat,
                longitude: lng,
                speed: speed,
                device_time: new Date().toISOString()
            })
        });
    }

    // Initialize markers and start tracking
    addStopMarkers();
    updateBusLocation(); // Initial update

    // Start real-time updates
    setInterval(updateBusLocation, 20000); // Update every 5 seconds instead of 20
    setInterval(checkAttendanceChanges, 50000); // Check attendance changes every 30 seconds

    // Handle refresh button clicks
    document.getElementById('refresh-btn').addEventListener('click', updateBusLocation);
});
    </script>
</body>
</html>