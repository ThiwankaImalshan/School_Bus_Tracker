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

// Determine current route based on time
$current_time = isset($_POST['device_time']) ? strtotime($_POST['device_time']) : time();
$current_hour = (int)date('H', $current_time);
$current_minute = (int)date('i', $current_time);
$time_in_minutes = ($current_hour * 60) + $current_minute;

$morning_start = (5 * 60); // 5:00 AM
$morning_end = (10 * 60); // 9:00 AM
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
$stmt = $pdo->prepare("SELECT c.child_id, c.first_name, c.last_name, c.pickup_location 
                      FROM child c 
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
    $stmt = $pdo->prepare("SELECT child_id, status FROM attendance 
                          WHERE attendance_date = CURDATE()");
    $stmt->execute();
    $dropoff_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // For evening route - student dropoff locations are destinations (exclude absent students)
    foreach ($students as $student) {
        if (!empty($student['pickup_location'])) {
            // Skip if student is marked as absent
            if (isset($dropoff_status[$student['child_id']]) && 
                $dropoff_status[$student['child_id']] === 'absent') {
                continue;
            }
            $coordinates = explode(',', $student['pickup_location']);
            $status = $dropoff_status[$student['child_id']] ?? '';
            $destinations[] = array(
                'name' => $student['first_name'] . ' ' . $student['last_name'],
                'location' => $coordinates,
                'type' => 'dropoff',
                'child_id' => $student['child_id'],
                'is_dropped' => ($status === 'drop'),
                'distance' => 0
            );
        }
    }

    // Sort destinations - dropped students go to end of list
    if (!empty($destinations)) {
        usort($destinations, function($a, $b) {
            if ($a['is_dropped'] === $b['is_dropped']) {
                return 0;
            }
            return $a['is_dropped'] ? 1 : -1;
        });
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($current_route == 'morning' || $current_route == 'evening'): ?>
    <meta http-equiv="refresh" content="30">
    <?php endif; ?>
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
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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
                        <span id="route-display" class="font-medium">
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
                <div class="bg-orange-50 rounded-lg p-4 text-center">
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
            <div class="bg-white rounded-2xl shadow-enhanced border border-orange-100 overflow-hidden mb-6 relative">
                <div id="map"></div>
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
            </div>
            <!-- Next Stops Preview -->
            <div class="mt-6 mb-6 sm:mt-20">
                <h4 class="text-md font-semibold text-gray-800 mb-3">Next Stops</h4>
                <div class="space-y-3" id="stops-container">
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
                            }, array_filter($waypoints, fn($w) => !$w['is_picked'])),
                            array_map(function($dest) {
                                return [
                                    'id' => isset($dest['school_id']) ? 'school_' . $dest['school_id'] : 'school_0',
                                    'name' => $dest['name'],
                                    'location' => $dest['location'],
                                    'type' => 'school',
                                    'is_completed' => $dest['is_arrived'],
                                    'arrival_time' => $dest['arrival_time']
                                ];
                            }, array_filter($destinations, fn($d) => $d['type'] === 'school' && !$d['is_arrived']))
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
                        }, array_filter($destinations, fn($d) => !$d['is_dropped']));
                        ?>
                    <?php endif; ?>

                    <?php if (empty($upcoming_stops)): ?>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-gray-500">No upcoming stops</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_stops as $index => $stop): ?>
                            <div class="bg-orange-50 rounded-lg p-3 flex items-center justify-between" 
                                 id="stop-<?php echo htmlspecialchars($stop['id']); ?>"
                                 data-location="<?php echo htmlspecialchars(implode(',', $stop['location'])); ?>"
                                 data-type="<?php echo htmlspecialchars($stop['type']); ?>"
                                 data-index="<?php echo $index + 1; ?>">
                                <div class="flex items-center">
                                    <div class="bg-orange-100 p-2 rounded-lg mr-3 flex-shrink-0">
                                        <span class="font-bold text-orange-600"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium truncate">
                                            <?php echo $stop['type'] === 'school' ? 'School: ' : 
                                                  ($current_route === 'morning' ? 'Pickup: ' : 'Drop-off: '); ?>
                                            <span class="text-orange-600"><?php echo htmlspecialchars($stop['name']); ?></span>
                                        </p>
                                        <?php if (isset($stop['arrival_time'])): ?>
                                            <p class="text-xs text-gray-500">
                                                Target arrival: <?php echo htmlspecialchars($stop['arrival_time']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="text-right flex-shrink-0" id="route-details-<?php echo htmlspecialchars($stop['id']); ?>">
                                        <p class="text-sm font-medium">--</p>
                                        <p class="text-xs text-gray-500">--</p>
                                    </div>
                                    <button class="bg-green-500 hover:bg-green-600 text-white p-2 rounded-lg mark-completed-btn" 
                                            data-stop-id="<?php echo htmlspecialchars($stop['id']); ?>"
                                            <?php echo $stop['is_completed'] ? 'disabled' : ''; ?>>
                                        <?php if ($stop['is_completed']): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                <button id="navigate-btn" class="bg-orange-500 hover:bg-orange-600 text-white py-2 px-4 text-sm rounded-lg font-medium transition-colors w-full sm:w-auto">
                    Navigate in Google Maps
                </button>
            </div>
        </section>
    </main>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    const map = L.map('map').setView([6.9271, 79.8612], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    // Get route data from PHP
    const destinations = <?php echo $destinationsJson; ?>;
    const waypoints = <?php echo $waypointsJson; ?>;
    let busMarker = null;
    let routingControl = null;
    let completedPath = null;
    let pendingPath = null;
    const currentRoute = '<?php echo $current_route; ?>';
    // Create bus icon
    const busIcon = L.divIcon({
        className: 'bus-icon',
        html: '<span>🚌</span>'
    });
    // Add geofence circles for schools
    destinations.forEach(dest => {
        if (dest.type === 'school') {
            // Create 500m radius circle around school
            const circle = L.circle(dest.location, {
                color: dest.is_arrived ? '#10B981' : '#EF4444', // Green if arrived, red if not
                fillColor: dest.is_arrived ? '#D1FAE5' : '#FEE2E2',
                fillOpacity: 0.3,
                radius: 500 // 500 meters
            }).addTo(map);

            // Add school marker
            const marker = L.marker(dest.location, {
                icon: L.divIcon({
                    className: `bg-${dest.is_arrived ? 'green' : 'red'}-600 rounded-full border-2 border-white w-6 h-6`,
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                })
            }).addTo(map);

            // Enhanced popup content
            let popupContent = `
                <div class="p-2">
                    <h3 class="font-bold">${dest.name}</h3>
                    <p class="text-sm">Geofence radius: 500m</p>
                    ${dest.arrival_time ? `<p class="text-sm">Target arrival: ${dest.arrival_time}</p>` : ''}
                    <p class="text-sm ${dest.is_arrived ? 'text-green-600' : 'text-red-600'}">
                        Status: ${dest.is_arrived ? 'Arrived' : 'Not arrived'}
                    </p>
                </div>`;
            
            marker.bindPopup(popupContent);
            circle.bindPopup(popupContent);
        }
    });
    // Add markers for destinations
    destinations.forEach(dest => {
        const marker = L.marker(dest.location, {
            icon: L.divIcon({
                className: 'bg-red-600 rounded-full border-2 border-white w-6 h-6',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            })
        }).addTo(map);
        let popupContent = `<b>${dest.name}</b><br>`;
        if (dest.arrival_time) {
            popupContent += `Arrival: ${dest.arrival_time}`;
        } else if (dest.type === 'dropoff') {
            popupContent += 'Drop-off location';
        }
        marker.bindPopup(popupContent);
    });
    // Add markers for waypoints
    waypoints.forEach((point, index) => {
        const marker = L.marker(point.location, {
            icon: L.divIcon({
                className: 'bg-red-500 rounded-full border-2 border-white',
                iconSize: [16, 16],
                iconAnchor: [8, 8],
                html: `<div class="flex items-center justify-center h-full w-full text-white font-bold text-xs">${index + 1}</div>`
            })
        }).addTo(map);
        let popupContent = `<b>${point.name}</b><br>`;
        if (point.type === 'pickup') {
            popupContent += 'Pickup location';
        }
        if (point.departure_time) {
            popupContent += `Departure: ${point.departure_time}`;
        }
        marker.bindPopup(popupContent);
    });
    // Function to fetch route from OSRM
    async function fetchRoadPath(start, end) {
        const url = `https://router.project-osrm.org/route/v1/driving/${start[1]},${start[0]};${end[1]},${end[0]}?geometries=geojson&overview=full`;
        try {
            const response = await fetch(url);
            const data = await response.json();
            if (data.code === 'Ok') {
                return data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
            }
            return [start, end]; // Fallback to straight line
        } catch (error) {
            console.error('Error fetching road path:', error);
            return [start, end]; // Fallback to straight line
        }
    }
    // Function to calculate distance between two points
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Earth's radius in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c; // Distance in km
    }
    // Function to sort destinations by distance from current position
    function sortDestinationsByDistance(currentPosition, destinations) {
        return destinations.map(dest => ({
            ...dest,
            distance: calculateDistance(
                currentPosition[0], currentPosition[1],
                parseFloat(dest.location[0]), parseFloat(dest.location[1])
            )
        })).sort((a, b) => a.distance - b.distance);
    }
    // Function to update route display
    async function updateRoute(currentPosition) {
        if (completedPath) map.removeLayer(completedPath);
        if (pendingPath) map.removeLayer(pendingPath);

        // Common function to get road path between two points
        async function getRoadPath(start, end) {
            const url = `https://router.project-osrm.org/route/v1/driving/${start[1]},${start[0]};${end[1]},${end[0]}?overview=full&geometries=geojson`;
            try {
                const response = await fetch(url);
                const data = await response.json();
                if (data.code === 'Ok') {
                    return data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                }
                return [start, end]; // Fallback to straight line
            } catch (error) {
                console.error('Error fetching road path:', error);
                return [start, end]; // Fallback to straight line
            }
        }

        if (currentRoute === 'morning') {
            const unpickedStudents = waypoints.filter(wp => !wp.is_picked);
            let currentPoint = currentPosition;
            let allRoutePoints = [currentPosition];

            // Calculate road paths to each student sequentially
            for (const student of unpickedStudents) {
                const roadPath = await getRoadPath(currentPoint, student.location);
                allRoutePoints = [...allRoutePoints, ...roadPath.slice(1)];
                currentPoint = student.location;
            }

            // After all students, calculate paths to schools
            const unvisitedSchools = destinations
                .filter(d => d.type === 'school' && !d.is_arrived)
                .sort((a, b) => a.arrival_time.localeCompare(b.arrival_time));

            for (const school of unvisitedSchools) {
                const roadPath = await getRoadPath(currentPoint, school.location);
                allRoutePoints = [...allRoutePoints, ...roadPath.slice(1)];
                currentPoint = school.location;
            }

            // Draw the complete route with all road paths
            if (allRoutePoints.length > 1) {
                completedPath = L.polyline(allRoutePoints, {
                    color: '#4C1D95',
                    weight: 4,
                    opacity: 0.8
                }).addTo(map);

                // Update markers with sequential numbering
                let stopIndex = 1;
                [...unpickedStudents, ...unvisitedSchools].forEach(stop => {
                    const isSchool = stop.type === 'school';
                    L.marker(stop.location, {
                        icon: L.divIcon({
                            className: `bg-${isSchool ? 'blue' : 'red'}-600 rounded-full border-2 border-white`,
                            iconSize: [isSchool ? 24 : 16, isSchool ? 24 : 16],
                            iconAnchor: [isSchool ? 12 : 8, isSchool ? 12 : 8],
                            html: `<div class="flex items-center justify-center h-full w-full text-white font-bold text-xs">${stopIndex++}</div>`
                        })
                    }).addTo(map)
                    .bindPopup(`<b>${stop.name}</b><br>${isSchool ? 'School' : 'Student pickup'}`);
                });

                // Update map bounds to include all points
                const bounds = L.latLngBounds([
                    currentPosition,
                    ...allRoutePoints
                ]);
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        } else if (currentRoute === 'evening') {
            // Get undropped students
            const remainingDropoffs = destinations.filter(dest => !dest.is_dropped);
            
            if (remainingDropoffs.length > 0) {
                // Calculate complete evening route
                let currentPoint = currentPosition;
                let allRoutePoints = [currentPosition];

                for (const dropoff of remainingDropoffs) {
                    const roadPath = await getRoadPath(currentPoint, dropoff.location);
                    allRoutePoints = [...allRoutePoints, ...roadPath.slice(1)];
                    currentPoint = dropoff.location;
                }

                // Draw route
                completedPath = L.polyline(allRoutePoints, {
                    color: '#4C1D95',
                    weight: 4,
                    opacity: 0.8
                }).addTo(map);

                // Update markers
                let stopIndex = 1;
                remainingDropoffs.forEach(stop => {
                    L.marker(stop.location, {
                        icon: L.divIcon({
                            className: 'bg-orange-600 rounded-full border-2 border-white',
                            iconSize: [24, 24],
                            iconAnchor: [12, 12],
                            html: `<div class="flex items-center justify-center h-full w-full text-white font-bold text-xs">${stopIndex++}</div>`
                        })
                    }).addTo(map)
                    .bindPopup(`<b>${stop.name}</b><br>Drop-off location`);
                });

                // Update map bounds
                const bounds = L.latLngBounds([
                    currentPosition,
                    ...allRoutePoints
                ]);
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }
    }
    // Update the updateBusLocation function in JavaScript
    function updateBusLocation() {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const speed = position.coords.speed || 0;
                const currentPos = [lat, lng];

                // Update speed display
                document.getElementById('speed-value').textContent = 
                    Math.round((speed * 3.6) || 0); // Convert m/s to km/h

                // Update bus marker
                if (!busMarker) {
                    busMarker = L.marker(currentPos, {
                        icon: L.divIcon({
                            className: 'bus-marker',
                            html: `<div class="animate-pulse">
                                    <div class="relative">
                                        <div class="bg-orange-500 rounded-full p-2 shadow-lg">🚌</div>
                                        <div class="absolute inset-0 bg-orange-300 opacity-25 rounded-full animate-ping"></div>
                                    </div>
                                </div>`,
                            iconSize: [40, 40],
                            iconAnchor: [20, 20],
                        })
                    }).addTo(map);
                } else {
                    busMarker.setLatLng(currentPos);
                }
                    
                // Update route based on current position
                updateRoute(currentPos);

                // Send location update to server with route type
                updateServer(lat, lng, speed);

                // Check status updates based on route type
                if (currentRoute === 'morning') {
                    checkPickupUpdates();
                } else if (currentRoute === 'evening') {
                    checkDropoffUpdates();
                }

                // Update route details
                updateRouteDetails(currentPos);
            }, function(error) {
                console.error("Error getting location:", error);
            }, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            });
        }
    }
    // Update the updateServer function in JavaScript
    function updateServer(lat, lng, speed) {
        const data = {
            update_location: true,
            latitude: lat,
            longitude: lng,
            speed: speed,
            route_type: currentRoute,
            device_time: new Date().toISOString()
        };

        fetch('bus_location_tracker.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const now = new Date();
                document.getElementById('last-updated').textContent = 
                    'Last updated: ' + now.toLocaleTimeString();
                document.getElementById('current-time').textContent = 
                    now.toLocaleTimeString();

                // Also update route display if needed
                document.getElementById('route-display').textContent = 
                    currentRoute === 'morning' ? 'Morning Route' : 
                    currentRoute === 'evening' ? 'Evening Route' : 
                    'No Active Route';
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Add function to check pickup status updates
    function checkPickupUpdates() {
        fetch('check_pickups.php')
        .then(response => response.json())
        .then(data => {
            if (data.updates) {
                // Redraw route with updated waypoints
                updateRoute(busMarker.getLatLng());
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Add function to check dropoff status updates
    function checkDropoffUpdates() {
        fetch('check_dropoffs.php')
        .then(response => response.json())
        .then(data => {
            if (data.updates) {
                // Redraw route with updated destinations
                updateRoute(busMarker.getLatLng());
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Add function to mark student as dropped off
    async function markStudentDropped(childId) {
        try {
            const response = await fetch('update_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    child_id: childId,
                    status: 'drop', // Changed from 'dropped' to 'drop'
                    date: new Date().toISOString().split('T')[0]
                })
            });
            
            if (response.ok) {
                // Update local state
                const dest = destinations.find(d => d.child_id === childId);
                if (dest) {
                    dest.is_dropped = true;
                    // Refresh route
                    if (busMarker) {
                        updateRoute(busMarker.getLatLng());
                    }
                }
            }
        } catch (error) {
            console.error('Error updating dropoff status:', error);
        }
    }

    // Add function to mark school as arrived
    async function markSchoolArrival(schoolId) {
        try {
            const response = await fetch('update_school_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    school_id: schoolId,
                    status: 'arrived',
                    date: new Date().toISOString().split('T')[0],
                    arrival_time: new Date().toTimeString().split(' ')[0]
                }),
            });
            
            if (response.ok) {
                // Update local state
                const school = destinations.find(d => d.type === 'school' && d.school_id === schoolId);
                if (school) {
                    school.is_arrived = true;
                    // Refresh route
                    if (busMarker) {
                        updateRoute(busMarker.getLatLng());
                    }
                }
            }
        } catch (error) {
            console.error('Error updating school arrival status:', error);
        }
    }

    // Add function to update route details
    async function updateRouteDetails(currentPosition) {
        const stopElements = document.querySelectorAll('[id^="stop-"]');
        
        for (const stopElement of stopElements) {
            const location = stopElement.dataset.location.split(',').map(Number);
            const stopId = stopElement.id.replace('stop-', '');
            const detailsElement = document.getElementById(`route-details-${stopId}`);
            
            // Calculate road distance and time using OSRM
            const url = `https://router.project-osrm.org/route/v1/driving/${currentPosition[1]},${currentPosition[0]};${location[1]},${location[0]}`;
            
            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.code === 'Ok') {
                    const distance = (data.routes[0].distance / 1000).toFixed(1); // Convert to km
                    const duration = Math.round(data.routes[0].duration / 60); // Convert to minutes
                    
                    detailsElement.innerHTML = `
                        <p class="text-sm font-medium">${duration} min</p>
                        <p class="text-xs text-gray-500">${distance} km</p>
                    `;
                }
            } catch (error) {
                console.error('Error calculating route details:', error);
            }
        }
    }

    // Auto-refresh controls
    const autoRefreshToggle = document.getElementById('auto-refresh');
    const refreshIntervalSelect = document.getElementById('refresh-interval');
    let locationUpdateInterval;
    let isAutoRefreshEnabled = true;

    function startLocationUpdates(interval) {
        if (locationUpdateInterval) {
            clearInterval(locationUpdateInterval);
        }
        // Initial update
        updateBusLocation();
        
        // Set interval based on route type
        const routeInterval = currentRoute === 'morning' ? interval : 
                             currentRoute === 'evening' ? interval : 
                             60000; // Default 1 minute for no active route

        locationUpdateInterval = setInterval(updateBusLocation, routeInterval);
    }

    function stopLocationUpdates() {
        if (locationUpdateInterval) {
            clearInterval(locationUpdateInterval);
        }
    }

    // Handle auto-refresh toggle
    autoRefreshToggle.addEventListener('change', function() {
        isAutoRefreshEnabled = this.checked;
        if (isAutoRefreshEnabled) {
            startLocationUpdates(parseInt(refreshIntervalSelect.value));
        } else {
            stopLocationUpdates();
        }
    });

    // Handle refresh interval changes
    refreshIntervalSelect.addEventListener('change', function() {
        if (isAutoRefreshEnabled) {
            startLocationUpdates(parseInt(this.value));
        }
    });

    // Initialize location updates with default interval
    startLocationUpdates(parseInt(refreshIntervalSelect.value));

    // Manual refresh button
    document.getElementById('refresh-btn').addEventListener('click', function() {
        const refreshIcon = this.querySelector('svg');
        refreshIcon.classList.add('refresh-animation');
        updateBusLocation();
        setTimeout(() => {
            refreshIcon.classList.remove('refresh-animation');
        }, 1000);
    });
});
    </script>
</body>
</html>