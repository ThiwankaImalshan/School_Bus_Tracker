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
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch child details
    $child_id = $_GET['child_id'] ?? null;
    if (!$child_id) {
        header('Location: dashboard.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM child WHERE child_id = :child_id AND parent_id = :parent_id");
    $stmt->execute(['child_id' => $child_id, 'parent_id' => $_SESSION['parent_id']]);
    $child = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$child) {
        header('Location: dashboard.php');
        exit;
    }

    // Fetch school details
    $schoolStmt = $pdo->prepare("SELECT * FROM school WHERE school_id = :school_id");
    $schoolStmt->execute(['school_id' => $child['school_id']]);
    $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

    // Fetch bus details
    $busStmt = $pdo->prepare("SELECT * FROM bus WHERE bus_id = :bus_id");
    $busStmt->execute(['bus_id' => $child['bus_id']]);
    $bus = $busStmt->fetch(PDO::FETCH_ASSOC);

    // Fetch pickup location
    $pickupStmt = $pdo->prepare("SELECT * FROM pickup_locations WHERE child_id = :child_id AND is_default = 1");
    $pickupStmt->execute(['child_id' => $child_id]);
    $pickup_location = $pickupStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" type="image/png" href="../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../img/favicon/favicon.svg" />
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-b from-yellow-50 to-orange-50 min-h-screen p-4">
    <div class="fixed -z-10 top-0 left-0 w-full h-full opacity-50">
        <div class="absolute top-10 left-10 w-32 h-32 rounded-full bg-blue-100 blur-3xl"></div>
        <div class="absolute bottom-10 right-10 w-40 h-40 rounded-full bg-orange-100 blur-3xl"></div>
        <div class="absolute top-1/3 right-1/4 w-24 h-24 rounded-full bg-green-100 blur-3xl"></div>
    </div>

    <main class="container mx-auto py-6 md:py-10 relative">
        <div class="max-w-4xl mx-auto">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
                <div class="flex items-center space-x-3">
                    <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                    <h2 class="text-3xl font-bold text-orange-800">Child Profile</h2>
                </div>
                <div class="flex items-center">
                    <a href="dashboard.php" class="flex items-center btn-gradient px-4 py-2 rounded-full shadow-md font-bold mr-3 group">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-xl p-6 md:p-8 border border-orange-100 relative overflow-hidden">
                <div class="absolute -right-20 -top-20 w-40 h-40 bg-orange-50 rounded-full"></div>
                <div class="absolute -left-12 -bottom-12 w-24 h-24 bg-blue-50 rounded-full"></div>
                
                <div class="flex justify-between items-center mb-8 relative">
                    <div>
                        <h3 class="text-2xl font-semibold text-gray-800">Child Information</h3>
                        <p class="text-gray-500 text-sm mt-1">Details about your child</p>
                    </div>
                </div>
                
                <div class="space-y-6 relative">
                    <div class="flex items-center space-x-4">
                        <?php
                        $photo_url = $child['photo_url'] ? '../img/child/' . $child['photo_url'] : '../img/default-avatar.png';
                        ?>
                        <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="Child Photo" class="w-24 h-24 rounded-full border-2 border-orange-200">
                        <div>
                            <h4 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h4>
                            <p class="text-sm text-gray-500">Grade: <?php echo htmlspecialchars($child['grade']); ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800">School</h4>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($school['name'] ?? 'N/A'); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($school['address'] ?? ''); ?></p>
                    </div>
                    
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800">Bus</h4>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($bus['bus_number'] ?? 'N/A'); ?></p>
                        <p class="text-sm text-gray-500">Capacity: <?php echo htmlspecialchars($bus['capacity'] ?? ''); ?></p>
                    </div>
                    
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800">Pickup Location</h4>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($pickup_location['name'] ?? 'N/A'); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($pickup_location['location'] ?? ''); ?></p>
                    </div>
                    
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800">Emergency Contact</h4>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($child['emergency_contact'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800">Medical Notes</h4>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($child['medical_notes'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-8 text-sm text-gray-500">
                <p>All information is securely stored and protected</p>
            </div>
        </div>
    </main>
</body>
</html>