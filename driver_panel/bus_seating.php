<?php
// dashboard.php - Bus attendance tracking dashboard for drivers
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

// Determine current route based on time
$current_time = isset($_POST['device_time']) ? strtotime($_POST['device_time']) : time();
$current_hour = (int)date('H', $current_time);
$current_minute = (int)date('i', $current_time);
$time_in_minutes = ($current_hour * 60) + $current_minute;

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

// Get seats for the driver's bus
$bus_seats = [];
if (isset($driver['bus_id'])) {
    $stmt = $pdo->prepare("SELECT bs.*, 
                          cr.child_id,
                          c.first_name, c.last_name,
                          CONCAT(LEFT(c.first_name, 1), '. ', c.last_name) as short_name,
                          a.status, a.pickup_time, a.drop_time
                          FROM bus_seat bs
                          LEFT JOIN child_reservation cr ON bs.seat_id = cr.seat_id 
                              AND cr.reservation_date = ? AND cr.is_active = 1
                          LEFT JOIN child c ON cr.child_id = c.child_id
                          LEFT JOIN attendance a ON bs.seat_id = a.bus_seat_id
                              AND a.attendance_date = ?
                          WHERE bs.bus_id = ?
                          ORDER BY CAST(SUBSTRING(bs.seat_number, 5) AS UNSIGNED)");
    $stmt->execute([$today, $today, $driver['bus_id']]);
    $bus_seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update attendance summary query to handle both morning and evening routes
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN a.status = 'picked' AND ? = 'morning' THEN 1 
                 WHEN a.status = 'drop' AND ? = 'evening' THEN 1 
                 ELSE 0 END) as count_status,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status IN ('pending', 'picked', 'drop') THEN 1 ELSE 0 END) as total_assigned
        FROM bus_seat bs
        LEFT JOIN attendance a ON bs.seat_id = a.bus_seat_id AND a.attendance_date = ?
        WHERE bs.bus_id = ?");
    $stmt->execute([$current_route, $current_route, $today, $driver['bus_id']]);
    $attendance_summary = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus Seating Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="icon" type="image/png" href="../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../img/favicon/favicon.svg" />
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
        .bus-seat {
            width: 140px;  /* Increased width from 120px to 140px */
            height: 60px;  /* Keep original height */
            margin: 5px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
            font-size: 0.85rem;
            padding: 4px;
            overflow: hidden; /* Prevent text overflow */
        }
        .seat-empty {
            background-color: #e5e5e5;
            color: #666;
            border: 2px dashed #aaa;
        }
        .seat-reserved {
            background-color: #f0f0f0;
            color: #333;
            border: 2px solid #ddd;
        }
        .seat-present {
            background-color: #d1fae5;
            color: #047857;
            border: 2px solid #34d399;
        }
        .seat-absent {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 2px solid #f87171;
        }
        .seat-pending {
            background-color: #ffedd5;
            color: #c2410c;
            border: 2px solid #fb923c;
        }
        .refresh-animation {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .bus-layout {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            grid-auto-flow: dense;
            position: relative;
            background: #f8fafc;
            border-radius: 20px;
            border: 2px solid #e2e8f0;
            overflow-x: auto;
        }
        .driver-seat {
            grid-column: 3;
            grid-row: 1;
            background-color: #93c5fd;
            color: #1e40af;
            border: 2px solid #3b82f6;
        }
        .seat-window {
            grid-column: 1;
            border-left: 4px solid #4b5563;
        }
        .seat-middle {
            grid-column: 2;
            background-color: rgba(229, 231, 235, 0.5);
        }
        .seat-aisle {
            grid-column: 3;
            border-right: 4px solid #4b5563;
        }
        .bus-layout > div:nth-child(3n + 1) {
            margin-top: 10px;
        }
        .seat-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s, opacity 0.3s;
            z-index: 100;
        }
        .bus-seat:hover .seat-tooltip {
            visibility: visible;
            opacity: 1;
        }
        .name-display {
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
            width: 90%;
            text-align: center;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .bus-layout {
                padding: 10px;
                gap: 5px;
            }

            .bus-seat {
                width: 100px;
                height: 50px;
                font-size: 0.75rem;
                margin: 3px;
            }

            .name-display {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 480px) {
            .bus-layout {
                padding: 5px;
                gap: 3px;
            }

            .bus-seat {
                width: 80px;
                height: 45px;
                font-size: 0.7rem;
                margin: 2px;
            }

            .name-display {
                font-size: 0.65rem;
            }

            .seat-tooltip {
                font-size: 10px;
                padding: 3px 6px;
            }
        }

        /* Add horizontal scrolling container for very small screens */
        @media (max-width: 360px) {
            .bus-layout-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 10px;
            }

            .bus-layout {
                min-width: 320px;
            }
        }

        /* Add to existing styles */
        .card-front {
            transition: transform 0.3s ease;
        }
        
        .card-front:hover {
            transform: translateY(-5px);
        }
        
        @media (max-width: 640px) {
            .card-front {
                height: 380px;
            }
        }
        
        @media (max-width: 480px) {
            .card-front {
                height: 340px;
            }
        }

        /* Update student card styles */
        .student-card {
            width: 360px;
            height: 500px;
            margin: 0 auto;
        }
        
        .student-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            justify-content: center;
        }

        @media (max-width: 1400px) {
            .student-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1000px) {
            .student-grid {
                grid-template-columns: 1fr;
            }
        }

        .card-front {
            height: 100%;
            width: 100%;
            transition: transform 0.3s ease;
        }
        
        .student-image {
            margin-top: 7rem;
            margin-bottom: 1rem;
        }
        
        .student-image img {
            width: 10rem;
            height: 10rem;
        }
        
        @media (max-width: 1280px) {
            .student-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .student-card {
                width: 100%;
                max-width: 360px;
            }
        }

        /* Add responsive styles for screens between 800-1024px */
        @media (min-width: 800px) and (max-width: 1024px) {
            .student-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
                max-width: 900px;
                margin: 0 auto;
            }

            .student-card {
                width: 320px;
                height: 480px;
            }

            .student-image {
                margin-top: 5rem;
            }

            .student-image img {
                width: 8rem;
                height: 8rem;
            }

            .card-front h2 {
                font-size: 1.5rem;
            }

            .card-front p {
                font-size: 1rem;
            }
        }

        .route-section {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
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

        <div class="glass-container p-6 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Bus Attendance Dashboard</h2>
                    <p class="text-gray-600">
                        Bus <?php echo htmlspecialchars($driver['bus_number'] ?? 'N/A'); ?> | 
                        <span id="route-display" class="font-medium">
                            <?php echo htmlspecialchars($route_text); ?>
                        </span>
                    </p>
                </div>
                
                <div class="flex flex-row space-x-1 sm:space-x-3 mt-4 md:mt-0">
                    <a href="attendance-scanner.php" target="_blank" class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs sm:text-base px-2 sm:px-4 py-1 sm:py-2 rounded-lg flex items-center justify-center transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5 mr-1 sm:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                        <span class="text-xs sm:text-sm">QR</span>
                    </a>
                    <?php if ($current_route == 'morning'): ?>
                    <button id="mark-all-dropped" class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs sm:text-base px-2 sm:px-4 py-1 sm:py-2 rounded-lg flex items-center justify-center transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5 mr-1 sm:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="text-xs sm:text-sm">Mark All</span>
                    </button>
                    <?php endif; ?>
                    <button id="refresh-btn" class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs sm:text-base px-2 sm:px-4 py-1 sm:py-2 rounded-lg flex items-center justify-center transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5 mr-1 sm:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span class="text-xs sm:text-sm">Refresh</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">
                                <?php echo $current_route === 'morning' ? 'Picked Up' : 'Dropped Off'; ?>
                            </h3>
                            <p id="present-count" class="text-2xl font-bold text-gray-800">
                                <?php echo intval($attendance_summary['count_status'] ?? 0); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">Absent</h3>
                            <p id="absent-count" class="text-2xl font-bold text-gray-800"><?php echo intval($attendance_summary['absent_count'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">Total Assigned</h3>
                            <p id="total-count" class="text-2xl font-bold text-gray-800"><?php echo intval($attendance_summary['total_assigned'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Student Cards Section -->
    <main class="container mx-auto px-4 py-8">
        <div class="glass-container p-6 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Student Cards</h2>
                    <p class="text-gray-600">
                        Bus <?php echo htmlspecialchars($driver['bus_number'] ?? 'N/A'); ?> | 
                        <span id="route-display" class="font-medium">
                            <?php echo htmlspecialchars($route_text); ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <!-- Morning Route - Picked Up Students -->
                <?php if ($current_route === 'morning'): ?>
                <div class="route-section">
                    <div class="bg-white/80 backdrop-blur rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-6">
                            <span class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                Picked Up Students
                            </span>
                        </h3>
                        <div class="student-grid">
                            <?php
                            try {
                                // Get today's date
                                $today = date('Y-m-d');
                                
                                // Fetch children who are marked as picked up (status = 'picked')
                                $stmt = $pdo->prepare("
                                    SELECT c.*, s.name AS school_name, p.full_name AS parent_name, a.status 
                                    FROM attendance a
                                    JOIN child c ON a.child_id = c.child_id
                                    JOIN school s ON c.school_id = s.school_id
                                    JOIN parent p ON c.parent_id = p.parent_id
                                    WHERE a.attendance_date = ? 
                                    AND a.status = 'picked'
                                    AND c.bus_id = ?
                                ");
                                $stmt->execute([$today, $driver['bus_id']]);
                                $pickedStudents = $stmt->fetchAll();
                                
                                if (empty($pickedStudents)) {
                                    echo '<div class="col-span-full text-center text-gray-500 py-12">No students picked up yet.</div>';
                                } else {
                                    foreach ($pickedStudents as $student) {
                                        // Update photo URL handling
                                        $photoUrl = !empty($student['photo_url']) ? 
                                            "../img/child/" . $student['photo_url'] : 
                                            "../img/default-avatar.png";
                                        $cardGradient = $current_route === 'morning' 
                                            ? 'background: linear-gradient(135deg, rgba(255, 154, 0, 0.9) 0%, rgba(255, 106, 0, 0.9) 100%);' 
                                            : 'background: linear-gradient(135deg, rgba(99, 102, 241, 0.9) 0%, rgba(79, 70, 229, 0.9) 100%);';
                                        $borderColor = $current_route === 'morning' ? 'border-yellow-600' : 'border-yellow-600';
                                        ?>
                                        
                                        <div class="bg-white rounded-lg overflow-hidden shadow-lg mb-4 student-card">
                                            <div class="card-front text-white relative" 
                                                 style="background-image: url('../img/front1.jpg'); 
                                                        background-size: cover; 
                                                        background-position: center;">
                                                <!-- Gradient Overlay -->
                                                <!-- <div class="absolute inset-0" style="<?php echo $cardGradient; ?>"></div> -->
                                                
                                                <!-- Content -->
                                                <div class="relative z-10 p-4 h-full flex flex-col">
                                                    <!-- Student Image with error handling -->
                                                    <div class="flex justify-center student-image">
                                                        <div class="rounded-full border-4 <?php echo $borderColor; ?> bg-white p-1">
                                                            <img src="<?php echo htmlspecialchars($photoUrl); ?>" 
                                                                 alt="Student Photo" 
                                                                 class="rounded-full w-40 h-40 object-cover"
                                                                 onerror="this.src='../img/default-avatar.png'"/>
                                                        </div>
                                                    </div>

                                                    <!-- Student Info -->
                                                    <div class="text-center mb-2">
                                                        <h2 class="text-3xl font-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                                                        <p class="text-xl">Grade <?php echo htmlspecialchars($student['grade']); ?></p>
                                                    </div>

                                                    <!-- Additional Details -->
                                                    <div class="grid grid-cols-3 gap-1 text-sm pb-4">
                                                        <div class="font-bold">ID:</div>
                                                        <div class="col-span-2"><?php echo htmlspecialchars($student['child_id']); ?></div>
                                                        
                                                        <div class="font-bold">School:</div>
                                                        <div class="col-span-2"><?php echo htmlspecialchars($student['school_name']); ?></div>
                                                        
                                                        <div class="font-bold">Parent:</div>
                                                        <div class="col-span-2"><?php echo htmlspecialchars($student['parent_name']); ?></div>
                                                        
                                                        <div class="font-bold">Phone:</div>
                                                        <div class="col-span-2"><?php echo htmlspecialchars($student['emergency_contact']); ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                    }
                                }
                            } catch (PDOException $e) {
                                echo '<div class="col-span-full text-red-500">Error fetching students: ' . $e->getMessage() . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Evening Route - To Be Dropped Off -->
                <?php if ($current_route === 'evening'): ?>
                <div class="route-section">
                    <div class="bg-white/80 backdrop-blur rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-6">
                            <span class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                </svg>
                                Students To Be Dropped Off
                            </span>
                        </h3>
                        <div class="student-grid">
                            <?php
                            try {
                                // Get today's date
                                $today = date('Y-m-d');
                                
                                // Fetch children who are picked up but not yet dropped (status = 'picked')
                                $stmt = $pdo->prepare("
                                    SELECT c.*, s.name AS school_name, p.full_name AS parent_name, a.status 
                                    FROM attendance a
                                    JOIN child c ON a.child_id = c.child_id
                                    JOIN school s ON c.school_id = s.school_id
                                    JOIN parent p ON c.parent_id = p.parent_id
                                    WHERE a.attendance_date = ? 
                                    AND a.status = 'picked'
                                    AND c.bus_id = ?
                                ");
                                $stmt->execute([$today, $driver['bus_id']]);
                                $toDropStudents = $stmt->fetchAll();
                                
                                if (empty($toDropStudents)) {
                                    echo '<div class="col-span-full text-center text-gray-500 py-12">No students to be dropped off.</div>';
                                } else {
                                    foreach ($toDropStudents as $student) {
                                        // Update photo URL handling
                                        $photoUrl = !empty($student['photo_url']) ? 
                                            "../img/child/" . $student['photo_url'] : 
                                            "../img/default-avatar.png";
                                        ?>
                                        
                                        <div class="bg-white rounded-lg overflow-hidden shadow-lg mb-4 student-card" id="drop-card-<?php echo $student['child_id']; ?>">
                                            <div class="card-front text-white relative" 
                                                 style="background-image: url('../img/front1.jpg'); 
                                                        background-size: cover; 
                                                        background-position: center;">
                                                <!-- Gradient Overlay -->
                                                <!-- <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.9) 0%, rgba(5, 150, 105, 0.9) 100%);"></div> -->
                                                
                                                <!-- Content -->
                                                <div class="relative z-10 p-4 h-full flex flex-col">
                                                    <!-- Student Image with error handling -->
                                                    <div class="flex justify-center student-image">
                                                        <div class="rounded-full border-4 border-yellow-600 bg-white p-1">
                                                            <img src="<?php echo htmlspecialchars($photoUrl); ?>" 
                                                                 alt="Student Photo" 
                                                                 class="rounded-full w-40 h-40 object-cover"
                                                                 onerror="this.src='../img/default-avatar.png'"/>
                                                        </div>
                                                    </div>

                                                    <!-- Student Info -->
                                                    <div class="text-center mb-2">
                                                        <h2 class="text-3xl font-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                                                        <p class="text-xl">Grade <?php echo htmlspecialchars($student['grade']); ?></p>
                                                    </div>

                                                    <!-- Additional Details -->
                                                    <div class="grid grid-cols-3 gap-1 text-sm pb-4">
                                                        <div class="font-bold">ID:</div>
                                                        <div class="col-span-2"><?php echo htmlspecialchars($student['child_id']); ?></div>
                                                        
                                                        <div class="font-bold">School:</div>
                                                        <div class="col-span-2"><?php echo htmlspecialchars($student['school_name']); ?></div>
                                                        
                                                        <div class="font-bold">Parent:</div>
                                                        <div class="col-span-2"><?php echo htmlspecialchars($student['parent_name']); ?></div>
                                                        
                                                        <div class="font-bold">Phone:</div>
                                                        <div class="col-span-2"><?php echo htmlspecialchars($student['emergency_contact']); ?></div>
                                                    </div>
                                                    
                                                    <!-- Drop Off Button -->
                                                    <div class="mt-auto flex justify-center">
                                                        <button class="drop-student-btn bg-white text-green-700 font-semibold py-2 px-4 rounded-full shadow-md hover:bg-gray-100 transition" 
                                                                data-child-id="<?php echo $student['child_id']; ?>">
                                                            Mark as Dropped Off
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                    }
                                }
                            } catch (PDOException $e) {
                                echo '<div class="col-span-full text-red-500">Error fetching students: ' . $e->getMessage() . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get device time in Sri Lanka timezone
            function getLocalTime() {
                const now = new Date();
                // Convert to Sri Lanka time
                const sriLankaTime = now.toLocaleString('en-US', { 
                    timeZone: 'Asia/Colombo',
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                return sriLankaTime;
            }

            // Get device time and update server
            function updateServerTime() {
                const deviceTime = new Date().toLocaleString('en-US', {timeZone: 'Asia/Colombo'});
                fetch('bus_seating.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'device_time=' + encodeURIComponent(deviceTime)
                })
                .then(response => response.text())
                .then(() => {
                    // Refresh page to update route status
                    location.reload();
                })
                .catch(error => {
                    console.error('Error updating server time:', error);
                });
            }
            
            // Update time every minute
            setInterval(updateServerTime, 60000);

            const refreshBtn = document.getElementById('refresh-btn');
            const markAllDroppedBtn = document.getElementById('mark-all-dropped');
            const lastUpdated = document.getElementById('last-updated');
            
            function refreshData() {
                const refreshIcon = refreshBtn.querySelector('svg');
                refreshIcon.classList.add('refresh-animation');
                
                fetch('update_attendance.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('present-count').textContent = data.present_count;
                            document.getElementById('absent-count').textContent = data.absent_count;
                            document.getElementById('total-count').textContent = data.total_assigned;
                            
                            lastUpdated.textContent = new Date().toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });
                            
                            data.seats.forEach(seat => {
                                const seatElement = document.getElementById(`seat-${seat.seat_id}`);
                                if (seatElement) {
                                    seatElement.classList.remove('seat-empty', 'seat-reserved', 'seat-present', 'seat-absent', 'seat-pending');
                                    
                                    if (seat.is_reserved == 0) {
                                        seatElement.classList.add('seat-empty');
                                        seatElement.querySelector('.name-display').textContent = 'Empty';
                                    } else {
                                        if (seat.status === 'present') {
                                            seatElement.classList.add('seat-present');
                                        } else if (seat.status === 'absent') {
                                            seatElement.classList.add('seat-absent');
                                        } else {
                                            seatElement.classList.add('seat-pending');
                                        }
                                        
                                        if (seat.short_name) {
                                            seatElement.querySelector('.name-display').textContent = seat.short_name;
                                        }
                                    }
                                    
                                    const tooltip = seatElement.querySelector('.seat-tooltip');
                                    if (tooltip && seat.child_name) {
                                        let tooltipContent = `<strong>${seat.child_name}</strong>`;
                                        if (seat.status) {
                                            tooltipContent += `<br>Status: ${seat.status.charAt(0).toUpperCase() + seat.status.slice(1)}`;
                                        }
                                        if (seat.pickup_time) {
                                            tooltipContent += `<br>Pickup: ${formatTime(seat.pickup_time)}`;
                                        }
                                        if (seat.drop_time) {
                                            tooltipContent += `<br>Drop: ${formatTime(seat.drop_time)}`;
                                        }
                                        tooltip.innerHTML = tooltipContent;
                                    }
                                }
                            });
                        } else {
                            console.error('Error refreshing data:', data.message);
                        }
                        
                        refreshIcon.classList.remove('refresh-animation');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        refreshIcon.classList.remove('refresh-animation');
                    });
            }
            
            function formatTime(timeString) {
                if (!timeString) return '';
                const date = new Date(`2000-01-01T${timeString}`);
                return date.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            }
            
            window.toggleAttendanceStatus = function(seatId, childId, currentStatus) {
                const currentRoute = "<?php echo $current_route; ?>";
                if (currentRoute === 'none') {
                    alert('No active route at this time.');
                    return;
                }
                
                const newStatus = currentStatus === 'present' ? 'absent' : 'present';
                
                const formData = new FormData();
                formData.append('seat_id', seatId);
                formData.append('child_id', childId);
                formData.append('status', newStatus);
                
                fetch('submit_attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        refreshData();
                    } else {
                        console.error('Error updating status:', data.message);
                        alert('Error updating status: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating status. Please try again.');
                });
            };
            
            if (markAllDroppedBtn) {
                markAllDroppedBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to mark all students as dropped off?')) {
                        fetch('mark_all_dropped.php', {
                            method: 'POST'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('All students marked as dropped off.');
                                refreshData();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        });
                    }
                });
            }
            
            refreshBtn.addEventListener('click', refreshData);
            
            // Auto-refresh every 30 seconds
            setInterval(refreshData, 30000);

            // Function to refresh student cards
            function refreshStudentCards() {
                fetch('refresh_student_cards.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Refresh the cards if needed
                            if (data.html.picked) {
                                document.getElementById('picked-students').innerHTML = data.html.picked;
                            }
                            if (data.html.toDrop) {
                                document.getElementById('to-drop-students').innerHTML = data.html.toDrop;
                            }
                        }
                    })
                    .catch(error => console.error('Error refreshing student cards:', error));
            }
            
            // Add event listeners for drop off buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('.drop-student-btn')) {
                    const btn = e.target.closest('.drop-student-btn');
                    const childId = btn.getAttribute('data-child-id');
                    
                    if (confirm('Are you sure you want to mark this student as dropped off?')) {
                        // Update attendance status
                        fetch('update_drop_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'child_id=' + childId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove the card
                                const card = document.getElementById('drop-card-' + childId);
                                if (card) {
                                    card.classList.add('animate__animated', 'animate__fadeOut');
                                    setTimeout(() => {
                                        card.remove();
                                    }, 500);
                                }
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        });
                    }
                }
            });
            
            // Refresh student cards every minute
            setInterval(refreshStudentCards, 60000);
        });
    </script>
</body>
</html>