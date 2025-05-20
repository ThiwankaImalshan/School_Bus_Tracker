<?php
session_start();
require_once 'db_connection.php';
date_default_timezone_set('Asia/Colombo');

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header('Location: log_in.php');
    exit;
}

// Handle QR scan data submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['child_id'])) {
        $child_id = $data['child_id'];
        $current_time = date('H:i:s');
        $today = date('Y-m-d');
        $current_hour = (int)date('H');
        $response = ['success' => false, 'message' => ''];

        try {
            // Get driver's assigned bus_id first
            $stmt = $pdo->prepare("SELECT bus_id FROM driver WHERE driver_id = ?");
            $stmt->execute([$_SESSION['driver_id']]);
            $driver = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$driver || !$driver['bus_id']) {
                throw new PDOException('No bus assigned to driver');
            }

            // Get route time settings for current date
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
            $route_times = $stmt->fetch(PDO::FETCH_ASSOC);

            // Set default times if no custom times exist
            if (!$route_times) {
                $route_times = [
                    'morning_start' => '05:00:00',
                    'morning_end' => '12:00:00',
                    'evening_start' => '12:00:00', 
                    'evening_end' => '17:00:00'
                ];
            }

            // Check if attendance record exists for today
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE child_id = ? AND attendance_date = ?");
            $stmt->execute([$child_id, $today]);
            $attendance = $stmt->fetch();

            if (!$attendance) {
                // Create new attendance record
                $stmt = $pdo->prepare("INSERT INTO attendance (child_id, attendance_date) VALUES (?, ?)");
                $stmt->execute([$child_id, $today]);
            }

            // Compare current time with route times
            $current_minutes = (int)date('H') * 60 + (int)date('i');
            $morning_start = (int)substr($route_times['morning_start'], 0, 2) * 60 + (int)substr($route_times['morning_start'], 3, 2);
            $morning_end = (int)substr($route_times['morning_end'], 0, 2) * 60 + (int)substr($route_times['morning_end'], 3, 2);
            $evening_start = (int)substr($route_times['evening_start'], 0, 2) * 60 + (int)substr($route_times['evening_start'], 3, 2);
            $evening_end = (int)substr($route_times['evening_end'], 0, 2) * 60 + (int)substr($route_times['evening_end'], 3, 2);

            // Morning pickup
            if ($current_minutes >= $morning_start && $current_minutes < $morning_end) {
                $stmt = $pdo->prepare("UPDATE attendance 
                                     SET pickup_time = ?, 
                                         status = 'picked'
                                     WHERE child_id = ? 
                                     AND attendance_date = ?");
                $stmt->execute([$current_time, $child_id, $today]);
                $response = ['success' => true, 'message' => 'Pickup recorded'];
            }
            // Evening drop-off
            elseif ($current_minutes >= $evening_start && $current_minutes < $evening_end) {
                $stmt = $pdo->prepare("UPDATE attendance 
                                     SET drop_time = ?, 
                                         status = 'drop'
                                     WHERE child_id = ? 
                                     AND attendance_date = ?");
                $stmt->execute([$current_time, $child_id, $today]);
                $response = ['success' => true, 'message' => 'Drop-off recorded'];
            }
            else {
                $response = ['success' => false, 'message' => 'Outside of valid route time window'];
            }

            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        catch (PDOException $e) {
            $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student QR Scanner</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="icon" type="image/png" href="../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../img/favicon/favicon.svg" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.4/html5-qrcode.min.js"></script>
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
        .btn-primary {
            background-color: #FF9500;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #FF7A00;
            transform: translateY(-2px);
        }
        .scan-animation {
            animation: scan 2s ease-in-out infinite;
        }
        @keyframes scan {
            0% { transform: translateY(0); opacity: 0.8; }
            50% { transform: translateY(130px); opacity: 0.2; }
            100% { transform: translateY(0); opacity: 0.8; }
        }
        .success-animation {
            animation: success-pulse 1s ease-in-out;
        }
        @keyframes success-pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .scanner-container {
            position: relative;
            width: 100%;
            aspect-ratio: 1/1;
            overflow: hidden;
            border-radius: 1rem;
        }
        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 10;
            pointer-events: none;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .scanner-line {
            height: 2px;
            background: linear-gradient(90deg, rgba(255,149,0,0) 0%, rgba(255,149,0,1) 50%, rgba(255,149,0,0) 100%);
            width: 100%;
        }
        .scanner-box {
            border: 2px dashed #FF9500;
            width: 70%;
            height: 70%;
            position: relative;
        }
    </style>
</head>
<body class="bg-gradient-to-b from-yellow-50 to-yellow-50 min-h-screen p-4">
    <div class="fixed -z-10 top-0 left-0 w-full h-full opacity-50">
        <div class="absolute top-10 left-10 w-32 h-32 rounded-full bg-blue-100 blur-3xl"></div>
        <div class="absolute bottom-10 right-10 w-40 h-40 rounded-full bg-yellow-100 blur-3xl"></div>
        <div class="absolute top-1/3 right-1/4 w-24 h-24 rounded-full bg-green-100 blur-3xl"></div>
    </div>

    <main class="container mx-auto py-8 md:py-12 relative">
        <div class="max-w-md mx-auto">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-6 gap-4">
                <div class="flex items-center space-x-3">
                    <div class="h-10 w-1 bg-yellow-500 rounded-full"></div>
                    <h2 class="text-3xl font-bold text-yellow-800">QR Attendance</h2>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-xl p-6 md:p-8 border border-yellow-100 relative overflow-hidden mb-6">
                <div class="absolute -right-20 -top-20 w-40 h-40 bg-yellow-50 rounded-full"></div>
                <div class="absolute -left-12 -bottom-12 w-24 h-24 bg-blue-50 rounded-full"></div>
                
                <div class="flex justify-between items-center mb-6 relative">
                    <div>
                        <h3 class="text-2xl font-semibold text-gray-800">QR Scanner</h3>
                        <p class="text-gray-500 text-sm mt-1">Scan student attendance quickly</p>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-yellow-500 to-yellow-500 flex items-center justify-center shadow-lg transform rotate-6 animate-float">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </div>
                </div>
                
                <div class="scanner-container bg-gray-100 mb-6 shadow-inner">
                    <div id="reader" class="w-full h-full"></div>
                    <div class="scanner-overlay">
                        <div class="scanner-box">
                            <div class="scanner-line scan-animation absolute"></div>
                        </div>
                    </div>
                </div>
                
                <div class="flex space-x-2">
                    <button id="startButton" class="flex-1 py-3 px-4 rounded-xl font-medium shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 btn-primary">
                        <span class="flex items-center justify-center text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Start Scanning
                        </span>
                    </button>
                    <button id="stopButton" class="flex-1 py-3 px-4 rounded-xl font-medium shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 bg-gray-500 text-white hidden">
                        <span class="flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                            </svg>
                            Stop
                        </span>
                    </button>
                </div>
            </div>

            <!-- Student Info Card -->
            <div id="studentCard" class="bg-white rounded-3xl shadow-xl p-6 md:p-6 border border-yellow-100 relative overflow-hidden hidden">
                <div class="absolute -right-12 -bottom-12 w-24 h-24 bg-green-50 rounded-full"></div>
                
                <div class="flex items-start space-x-4 relative">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-green-500 to-green-400 flex items-center justify-center shadow-lg success-animation">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <h3 class="text-xl font-semibold text-gray-800" id="studentName">John Smith</h3>
                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Recorded</span>
                        </div>
                        <p class="text-gray-500 text-sm mt-1" id="studentId">Student ID: S12345</p>
                        <div class="flex justify-between mt-3">
                            <div>
                                <p class="text-xs text-gray-500">Date Scanned</p>
                                <p class="text-sm font-medium" id="scanDate">April 5, 2025</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Time Scanned</p>
                                <p class="text-sm font-medium" id="scanTime">10:23 AM</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startButton = document.getElementById('startButton');
            const stopButton = document.getElementById('stopButton');
            const studentCard = document.getElementById('studentCard');
            const studentName = document.getElementById('studentName');
            const studentId = document.getElementById('studentId');
            const scanDate = document.getElementById('scanDate');
            const scanTime = document.getElementById('scanTime');
            
            let html5QrCode;

            // Play beep sound function
            function playBeepSound() {
                const beep = new Audio('sound/beep.mp3');
                beep.play().catch(err => console.error('Error playing sound:', err));
            }

            // Track scanned codes to prevent duplicates
            let lastScannedCode = '';
            let lastScannedTime = 0;
            const SCAN_COOLDOWN = 100000; // 5 seconds cooldown between same code scans

            // Initialize QR scanner
            function initScanner() {
                html5QrCode = new Html5Qrcode("reader");
                
                const config = {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                };
                
                startButton.addEventListener("click", () => {
                    html5QrCode.start(
                        { facingMode: "environment" },
                        config,
                        onScanSuccess,
                        onScanFailure)
                    .then(() => {
                        startButton.classList.add('hidden');
                        stopButton.classList.remove('hidden');
                    })
                    .catch((err) => {
                        console.error('Scanner start error:', err);
                        alert('Could not start scanner: ' + err);
                    });
                });
                
                stopButton.addEventListener("click", () => {
                    html5QrCode.stop()
                    .then(() => {
                        startButton.classList.remove('hidden');
                        stopButton.classList.add('hidden');
                    })
                    .catch((err) => {
                        console.error('Scanner stop error:', err);
                    });
                });
            }
            
            // Handle successful scan
            function onScanSuccess(decodedText, decodedResult) {
                const currentTime = Date.now();
                
                if (decodedText === lastScannedCode && 
                    currentTime - lastScannedTime < SCAN_COOLDOWN) {
                    return;
                }
                
                lastScannedCode = decodedText;
                lastScannedTime = currentTime;
                
                html5QrCode.pause();
                
                try {
                    // Sample QR code format:
                    // {"id": "10","name": "Nuwan Chamara","school_id": "10","grade": "7"}
                    const data = JSON.parse(decodedText);
                    
                    // Validate required fields
                    if (!data.id || !data.name || !data.school_id || !data.grade) {
                        throw new Error('Missing required QR code data');
                    }
                    
                    playBeepSound();
                    
                    // Send scan data to server
                    fetch('attendance-scanner.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            child_id: data.id,
                            name: data.name,
                            school_id: data.school_id,
                            grade: data.grade
                        })
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            displayStudentInfo(data);
                            studentCard.classList.remove('hidden');
                        } else {
                            alert(result.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating attendance');
                    })
                    .finally(() => {
                        setTimeout(() => {
                            html5QrCode.resume();
                        }, 1500);
                    });
                } catch (error) {
                    console.error('QR data parsing error:', error);
                    alert('Invalid QR code format');
                    html5QrCode.resume();
                }
            }

            function onScanFailure(error) {
                // We don't need to show errors for normal scanning operation
                // console.warn(`Code scan error = ${error}`);
            }
            
            function displayStudentInfo(data) {
                const now = new Date();
                studentName.textContent = data.name || 'Unknown Student';
                studentId.textContent = `Student ID: ${data.id || 'Unknown'}`;
                scanDate.textContent = now.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                scanTime.textContent = now.toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit', 
                    hour12: true 
                });
            }
            
            // Initialize the scanner
            initScanner();
        });
    </script>
</body>
</html>