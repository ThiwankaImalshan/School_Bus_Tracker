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
$stmt->execute([$driver['bus_id'], $current_time_sql, $current_route, $current_time_sql, $current_route]);
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
        if ($stmt->execute([$driver['bus_id'], $latitude, $longitude, $route_info['route_id'] ?? null, $speed])) {
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
                    <h4 class="text-lg font-medium text-gray-800" id="estimated-time">
                        <?php echo isset($route_info['estimated_duration']) ? $route_info['estimated_duration'] . ' min' : '--'; ?>
                    </h4>
                    <p class="text-xs text-gray-600">Est. Drive Time</p>
                </div>
                <div class="bg-purple-50 rounded-lg p-4 text-center">
                    <h4 class="text-lg font-medium text-gray-800">
                        <?php 
                            $school_count = count(array_unique(array_filter(array_column($route_points, 'location'))));
                            echo $school_count . ' ' . ($school_count == 1 ? 'Stop' : 'Stops');
                        ?>
                    </h4>
                    <p class="text-xs text-gray-600">Destinations</p>
                </div>
                <div class="bg-orange-50 rounded-lg p-4 text-center">
                    <h4 class="text-lg font-medium text-gray-800" id="completion-stats">
                        <?php 
                            $completed = $route_info['completed_points'] ?? 0;
                            $total = $route_info['total_points'] ?? 0;
                            echo $completed . ' / ' . $total;
                        ?>
                    </h4>
                    <p class="text-xs text-gray-600"><?php echo $current_route == 'morning' ? 'Pickups' : 'Drop-offs'; ?> Completed</p>
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
                <div class="space-y-3">
                    <?php if (empty($upcoming_stops)): ?>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-gray-500">No upcoming stops</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_stops as $stop): ?>
                            <?php 
                                $stop_type = 'pickup';
                                $bg_color = 'bg-orange-50';
                                $icon_bg = 'bg-orange-100';
                                $icon_text = 'text-orange-600';
                            ?>
                            <div class="<?php echo $bg_color; ?> rounded-lg p-3 flex items-center justify-between" id="stop-<?php echo $stop['stop_id']; ?>">
                                <div class="flex items-center">
                                    <div class="<?php echo $icon_bg; ?> p-2 rounded-lg mr-3 flex-shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 <?php echo $icon_text; ?>" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium truncate">
                                            <?php echo $current_route == 'morning' ? 'Pickup: ' : 'Drop-off: '; ?>
                                            <span class="<?php echo $icon_text; ?>"><?php echo htmlspecialchars($stop['short_name']); ?></span>
                                        </p>
                                        <p class="text-xs text-gray-500 truncate hidden sm:block">
                                            <?php echo htmlspecialchars($stop['address'] ?? 'No address available'); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="text-right flex-shrink-0">
                                        <p class="text-sm font-medium" id="eta-<?php echo $stop['stop_id']; ?>">--</p>
                                        <p class="text-xs text-gray-500" id="distance-<?php echo $stop['stop_id']; ?>">--</p>
                                    </div>
                                    <button 
                                        class="bg-green-500 hover:bg-green-600 text-white p-2 rounded-lg mark-completed-btn" 
                                        data-stop-id="<?php echo $stop['stop_id']; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Location Tracking Controls -->
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
        // Initialize variables
        let map, busMarker, routingControl;
        let lastPosition = null;
        let lastUpdateTime = null;
        let locationUpdateInterval;
        let pageRefreshInterval;
        let countdownInterval;

        // Function to get Sri Lanka time without seconds
        function getSriLankaTime() {
            return new Date().toLocaleString('en-US', {
                timeZone: 'Asia/Colombo',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        // Modified setupRefreshIntervals function
        function setupRefreshIntervals() {
            const interval = parseInt(document.getElementById('refresh-interval').value);
            
            // Clear all existing intervals
            clearInterval(locationUpdateInterval);
            clearInterval(pageRefreshInterval);
            clearInterval(countdownInterval);
            
            if (document.getElementById('auto-refresh').checked) {
                // Initial updates
                updateLocation();
                updateTimeDisplays();
                
                // Set up location updates
                locationUpdateInterval = setInterval(updateLocation, interval);
                
                // Set up time display updates every minute
                pageRefreshInterval = setInterval(updateTimeDisplays, 60000);
                
                // Setup countdown display
                setupCountdown(interval);
            }
        }

        // Separate function for countdown setup
        function setupCountdown(interval) {
            const existingTimer = document.getElementById('update-countdown');
            if (existingTimer) existingTimer.remove();

            const timerElement = document.createElement('span');
            timerElement.id = 'update-countdown';
            timerElement.className = 'text-xs text-gray-400 ml-2';
            document.getElementById('last-updated').after(timerElement);
            
            let countdown = interval / 1000;
            updateCountdownDisplay();
            
            countdownInterval = setInterval(() => {
                countdown--;
                if (countdown >= 0) {
                    updateCountdownDisplay();
                }
                if (countdown <= 0) {
                    countdown = interval / 1000;
                }
            }, 1000);

            function updateCountdownDisplay() {
                timerElement.textContent = `Next update in ${countdown}s`;
            }
        }

        // Modified updateTimeDisplays function
        function updateTimeDisplays() {
            const sriLankaTime = getSriLankaTime();
            
            // Update current time display
            document.getElementById('current-time').textContent = sriLankaTime;

            // Update route display
            const now = new Date();
            const sriLankaDate = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Colombo' }));
            const hours = sriLankaDate.getHours();
            const minutes = sriLankaDate.getMinutes();
            const timeInMinutes = (hours * 60) + minutes;

            let routeText;
            if (timeInMinutes >= 300 && timeInMinutes < 540) {
                routeText = `Morning Route (${sriLankaTime})`;
            } else if (timeInMinutes >= 720 && timeInMinutes < 1020) {
                routeText = `Evening Route (${sriLankaTime})`;
            } else {
                routeText = `No Active Route (${sriLankaTime})`;
            }
            document.getElementById('route-display').textContent = routeText;
        }

        // Function to handle refresh icon animation
        function toggleRefreshAnimation(show) {
            const refreshIcon = document.querySelector('#refresh-btn svg');
            if (show) {
                refreshIcon.classList.add('refresh-animation');
                // Remove animation after 1 second
                setTimeout(() => {
                    refreshIcon.classList.remove('refresh-animation');
                }, 1000);
            } else {
                refreshIcon.classList.remove('refresh-animation');
            }
        }

        // Function to stop all refresh intervals
        function stopRefreshIntervals() {
            clearInterval(locationUpdateInterval);
            clearInterval(pageRefreshInterval);
            clearInterval(countdownInterval);
        }

        // Update refresh interval change handler
        document.getElementById('refresh-interval').addEventListener('change', function() {
            if (document.getElementById('auto-refresh').checked) {
                setupRefreshIntervals(); // Restart intervals with new time
            }
        });

        // Update the auto-refresh toggle handler
        document.getElementById('auto-refresh').addEventListener('change', function() {
            if (this.checked) {
                setupRefreshIntervals();
            } else {
                stopRefreshIntervals();
            }
        });

        // Initialize map function
        function initializeMap() {
            const defaultLat = 6.9271;
            const defaultLng = 79.8612;

            map = L.map('map', {
                center: [defaultLat, defaultLng],
                zoom: 15,
                minZoom: 8,
                maxZoom: 18
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            // Initialize routing control
            routingControl = L.Routing.control({
                waypoints: [],
                routeWhileDragging: false,
                show: false,
                lineOptions: {
                    styles: [{
                        color: '#3B82F6',
                        opacity: 0.8,
                        weight: 4
                    }]
                }
            }).addTo(map);

            // Initialize bus marker if location exists
            <?php if ($location): ?>
            busMarker = L.marker([<?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?>], {
                icon: L.divIcon({
                    className: 'bus-icon',
                    html: '<span>🚌</span>',
                    iconSize: [30, 30]
                })
            }).addTo(map);
            <?php endif; ?>
        }

        // Calculate speed function
        function calculateSpeed(newPosition) {
            if (!lastPosition || !lastUpdateTime) {
                lastPosition = newPosition;
                lastUpdateTime = Date.now();
                return 0;
            }

            const distance = calculateDistance(
                lastPosition.coords.latitude,
                lastPosition.coords.longitude,
                newPosition.coords.latitude,
                newPosition.coords.longitude
            );

            const timeInSeconds = (Date.now() - lastUpdateTime) / 1000;
            const speedKmH = (distance / timeInSeconds) * 3600; // Convert to km/h

            lastPosition = newPosition;
            lastUpdateTime = Date.now();

            return Math.round(speedKmH);
        }

        // Calculate distance between coordinates
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in km
            const dLat = toRad(lat2 - lat1);
            const dLon = toRad(lon2 - lon1);
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                     Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * 
                     Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        function toRad(degrees) {
            return degrees * (Math.PI/180);
        }

        // Update location with speed calculation
        function updateLocation() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser');
                return;
            }

            toggleRefreshAnimation(true);

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const { latitude, longitude } = position.coords;
                    const speed = calculateSpeed(position);

                    // Persist speed value
                    localStorage.setItem('lastSpeed', speed);
                    
                    // Update speed display with animation
                    const speedDisplay = document.getElementById('speed-value');
                    const currentSpeed = parseInt(speedDisplay.textContent);
                    animateSpeedChange(currentSpeed, speed);

                    // Update bus marker
                    if (!busMarker) {
                        busMarker = L.marker([latitude, longitude], {
                            icon: L.divIcon({
                                className: 'bus-icon',
                                html: '<span>🚌</span>',
                                iconSize: [30, 30]
                            })
                        }).addTo(map);
                    } else {
                        busMarker.setLatLng([latitude, longitude]);
                    }

                    // Update route
                    if (window.routingControl) {
                        const waypoints = [
                            L.latLng(latitude, longitude),
                            // Add next stop as destination if available
                            <?php if (!empty($upcoming_stops)): 
                                $next_stop = reset($upcoming_stops);
                                $coords = explode(',', $next_stop['location']);
                                if (count($coords) === 2):
                            ?>
                            L.latLng(<?php echo $coords[0]; ?>, <?php echo $coords[1]; ?>)
                            <?php endif; endif; ?>
                        ].filter(Boolean);

                        window.routingControl.setWaypoints(waypoints);
                    }

                    // Center map on bus
                    map.panTo([latitude, longitude]);
                    
                    // Update server and last updated time
                    updateServer(latitude, longitude, speed);
                    toggleRefreshAnimation(false);
                },
                function(error) {
                    console.error('Error getting location:', error);
                    document.getElementById('last-updated').textContent = 'Failed to update location';
                    document.getElementById('last-updated').classList.add('text-red-500');
                    toggleRefreshAnimation(false);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 20000,
                    maximumAge: 0
                }
            );
        }

        // Add new function to animate speed changes
        function animateSpeedChange(start, end) {
            const speedDisplay = document.getElementById('speed-value');
            const duration = 1000; // 1 second animation
            const startTime = performance.now();
            
            function updateSpeedValue(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);

                const currentValue = Math.round(start + (end - start) * progress);
                speedDisplay.textContent = currentValue;

                if (progress < 1) {
                    requestAnimationFrame(updateSpeedValue);
                }
            }

            requestAnimationFrame(updateSpeedValue);
        }

        // Restore last known speed on page load
        const lastSpeed = localStorage.getItem('lastSpeed');
        if (lastSpeed) {
            document.getElementById('speed-value').textContent = lastSpeed;
        }

        // Update server with better promise handling
        async function updateServer(latitude, longitude, speed) {
            const formData = new FormData();
            formData.append('update_location', '1');
            formData.append('latitude', latitude);
            formData.append('longitude', longitude);
            formData.append('speed', speed);
            formData.append('device_time', new Date().toISOString());
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    updateLastUpdatedTimeDisplay();
                }
            } catch (error) {
                console.error('Error updating location:', error);
            }
        }

        // New function to update last updated time display
        function updateLastUpdatedTimeDisplay() {
            const timeString = getSriLankaTime();
            const lastUpdatedElement = document.getElementById('last-updated');
            lastUpdatedElement.textContent = 'Last updated: ' + timeString;
            lastUpdatedElement.classList.remove('text-gray-500');
            lastUpdatedElement.classList.add('text-green-600');
            
            // Store last update time
            localStorage.setItem('lastUpdateTime', new Date().toISOString());
            
            // Reset color after 2 seconds
            setTimeout(() => {
                lastUpdatedElement.classList.remove('text-green-600');
                lastUpdatedElement.classList.add('text-gray-500');
            }, 2000);
        }

        // Update refresh button click handler
        document.getElementById('refresh-btn').addEventListener('click', function() {
            updateLocation();
        });

        // Before page unload, clear intervals
        window.addEventListener('beforeunload', function() {
            stopRefreshIntervals();
        });

        // Initialize map
        initializeMap();

        // Start refresh intervals if auto-refresh is enabled
        if (document.getElementById('auto-refresh').checked) {
            setupRefreshIntervals();
        }
    });
    </script>
</body>
</html>