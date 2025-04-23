<?php
// refresh_student_cards.php - Handle AJAX requests to refresh student cards
session_start();
require_once 'db_connection.php';

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$driver_id = $_SESSION['driver_id'];
$today = date('Y-m-d');

// Get driver information and assigned bus
$stmt = $pdo->prepare("SELECT d.*, b.bus_id FROM driver d LEFT JOIN bus b ON d.bus_id = b.bus_id WHERE d.driver_id = ?");
$stmt->execute([$driver_id]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$driver['bus_id']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No assigned bus']);
    exit;
}

// Determine current route based on time
$current_hour = (int)date('H');
$current_minute = (int)date('i');
$time_in_minutes = ($current_hour * 60) + $current_minute;

$morning_start = (5 * 60); // 5:00 AM
$morning_end = (12 * 60); // 12:00 PM
$evening_start = (12 * 60); // 12:00 PM
$evening_end = (17 * 60); // 5:00 PM

if ($time_in_minutes >= $morning_start && $time_in_minutes < $morning_end) {
    $current_route = "morning";
} elseif ($time_in_minutes >= $evening_start && $time_in_minutes < $evening_end) {
    $current_route = "evening";
} else {
    $current_route = "none";
}

// Initialize HTML containers
$pickedHTML = '';
$toDropHTML = '';

// If a route is active, get the appropriate student cards
if ($current_route !== 'none') {
    try {
        // Fetch children who are marked as picked up
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
        $pickedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pickedStudents)) {
            $pickedHTML = '<div class="w-full text-center text-gray-500 py-12">No students picked up yet.</div>';
        } else {
            ob_start();
            foreach ($pickedStudents as $student) {
                // Default image if none available
                $photoUrl = !empty($student['photo_url']) ? $student['photo_url'] : '../img/child.jpg';
                
                // Set card gradient based on route
                $cardGradient = $current_route === 'morning' 
                    ? 'background: linear-gradient(135deg, #ff9a00 0%, #ff6a00 100%);' 
                    : 'background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);';
                
                // Set border color based on route
                $borderColor = $current_route === 'morning' ? 'border-orange-500' : 'border-indigo-500';
                ?>
                
                <!-- Front side of card -->
                <div class="bg-white rounded-lg overflow-hidden shadow-lg mb-4">
                    <!-- Card Front with background image -->
                    <div class="card-front text-white p-4" style="height: 500px; width: 320px; <?php echo $cardGradient; ?>">
                        <!-- Student Image - Increased Size -->
                        <div class="flex justify-center mt-28 mb-4">
                            <div class="rounded-full border-4 <?php echo $borderColor; ?> bg-white p-1">
                                <img src="<?php echo $photoUrl; ?>" alt="Student Photo" class="rounded-full w-40 h-40 object-cover"/>
                            </div>
                        </div>

                        <!-- Student Info -->
                        <div class="text-center mb-2">
                            <h2 class="text-3xl font-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                            <p class="text-xl">Grade <?php echo htmlspecialchars($student['grade']); ?></p>
                        </div>

                        <!-- Additional Details - Left aligned with smaller font and bottom padding -->
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
            <?php
            }
            $pickedHTML = ob_get_clean();
        }
        
        // For evening route, also get the to-be-dropped-off students separately
        if ($current_route === 'evening') {
            // These will be the same students in evening route, but shown in a different section
            if (empty($pickedStudents)) {
                $toDropHTML = '<div class="w-full text-center text-gray-500 py-12">No students to be dropped off.</div>';
            } else {
                ob_start();
                foreach ($pickedStudents as $student) {
                    // Default image if none available
                    $photoUrl = !empty($student['photo_url']) ? $student['photo_url'] : '../img/child.jpg';
                    ?>
                    
                    <!-- Front side of card -->
                    <div class="bg-white rounded-lg overflow-hidden shadow-lg mb-4" id="drop-card-<?php echo $student['child_id']; ?>">
                        <!-- Card Front with background image -->
                        <div class="card-front text-white p-4" style="height: 500px; width: 320px; background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <!-- Student Image - Increased Size -->
                            <div class="flex justify-center mt-28 mb-4">
                                <div class="rounded-full border-4 border-green-500 bg-white p-1">
                                    <img src="<?php echo $photoUrl; ?>" alt="Student Photo" class="rounded-full w-40 h-40 object-cover"/>
                                </div>
                            </div>

                            <!-- Student Info -->
                            <div class="text-center mb-2">
                                <h2 class="text-3xl font-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                                <p class="text-xl">Grade <?php echo htmlspecialchars($student['grade']); ?></p>
                            </div>

                            <!-- Additional Details - Left aligned with smaller font and bottom padding -->
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
                            <div class="absolute bottom-4 left-0 right-0 flex justify-center">
                                <button class="drop-student-btn bg-white text-green-700 font-semibold py-2 px-4 rounded-full shadow-md hover:bg-gray-100 transition" 
                                        data-child-id="<?php echo $student['child_id']; ?>">
                                    Mark as Dropped Off
                                </button>
                            </div>
                        </div>
                    </div>
                <?php
                }
                $toDropHTML = ob_get_clean();
            }
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Return the HTML for both sections
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'html' => [
        'picked' => $pickedHTML,
        'toDrop' => $toDropHTML
    ],
    'route' => $current_route
]); 